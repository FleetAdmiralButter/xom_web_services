<?php

namespace Drupal\xom_web_services\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\file\Entity\File;
use Drupal\Core\Archiver\Zip;

class SparkleForm extends FormBase {

    /**
     * Build the form layout and prepare the staging folder.
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $staging_directory = 'private://release_manager_staging';
        \Drupal::service('file_system')->prepareDirectory($staging_directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);

        $form['sparkle'] = [
            '#title' => $this->t('App Updates'),
            '#type' => 'fieldset',
        ];

        $form['sparkle']['appcast'] = [
            '#type' => 'managed_file',
            '#title' => $this->t('AppCast XML file'),
            '#description' => $this->t('Upload a new AppCast XML. The file will be renamed and existing file will be replaced automatically.'),
            '#upload_location' => 'private://release_manager_staging',
            '#upload_validators' => [
                'file_validate_extensions' => ['xml'],
            ],
        ];

        $form['sparkle']['update'] = [
            '#type' => 'managed_file',
            '#title' => $this->t('Update package'),
            '#description' => $this->t('A zip file containing the main update package (.tar.xz) as well as delta (.delta) files.'),
            '#upload_location' => 'private://release_manager_staging',
            '#upload_validators' => [
                'file_validate_extensions' => ['zip'],
            ],
        ];

        $form['sparkle']['social_post'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Post release to social channels? (Discord and front page)'),
            '#description' => $this->t('Untick if re-releasing the same version for some reason.'),
            '#default_value' => TRUE,
        ];


        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Confirm Release'),
          '#button_type' => 'primary',
        ];

        return $form;
    }

    public function getFormId() {
        return 'xom_web_services.release';
    }

    /**
     * Process the submitted files.
     * This function does a few things:
     * 1) Ingest the uploaded XML, ZIP, TXT files into a private staging folder.
     * 2) If the file is a ZIP, extract only relevant content (ignoring _MACOSX directories).
     * 3) Copy all files into the relevant public folder (either update_data or seventh_dawn).
     * 4) Clean up the staging folder.
     *
     * There is some duplicated code in here which should be cleaned up, but for now everything is stable.
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {

        $batch = [
            'title' => $this->t('Casting'),
            'operations' => [],
            'init_message' => t('Getting ready...'),
            'progress_message' => t('@estimate.'),
            'error_message' => t('XXX Interrupted'),
        ];

        $appcast = $form_state->getValue('appcast', 0);
        $package = $form_state->getValue('update', 0);

        if (isset($appcast[0]) && !empty($appcast[0])) {
            $appcast_file = File::load($appcast[0]);
            $source = 'private://release_manager_staging/' . $appcast_file->getFilename();
            $destination = 'public://update_data/xivonmac_appcast.xml';
            $batch['operations'][] = [['\Drupal\xom_web_services\Form\SparkleForm', 'copyFile'], [$source, $destination]];
            $batch['operations'][] = [['\Drupal\xom_web_services\Form\SparkleForm', 'clean'], [$appcast_file]];
        }

        if (isset($package[0]) && !empty($package[0])) {
            $package_file = File::load($package[0]);
            $abs = \Drupal::service('file_system')->realpath($package_file->getFileUri());
            $zip = new Zip($abs);
            $files_to_copy = [];

            // Only extract tar.xz and .delta
            foreach ($zip->listContents() as $archive_file) {
                if ((str_contains($archive_file, '.tar.xz') || str_contains($archive_file, '.delta')) && !(str_contains($archive_file, 'MACOSX'))) {
                    array_push($files_to_copy, $archive_file);
                }
            }
            $zip->extract('private://release_manager_staging/', $files_to_copy);

            foreach ($files_to_copy as $file_to_copy) {
                $source = 'private://release_manager_staging/' . $file_to_copy;
                $destination = 'public://update_data/' . $file_to_copy;
                $batch['operations'][] = [['\Drupal\xom_web_services\Form\SparkleForm', 'copyFile'], [$source, $destination]];
            }
            $batch['operations'][] = [['\Drupal\xom_web_services\Form\SparkleForm', 'clean'], [$package_file]];

        }

        if ($form_state->getValue('social_post') == TRUE) {
            $batch['operations'][] = [['\Drupal\xom_web_services\Form\SparkleForm', 'postAppcastToDiscord'], []];
        }

        $batch['operations'][] = [['\Drupal\xom_web_services\Form\SparkleForm', 'invalidateCache'], []];

        if (empty($batch['operations'])) {
            \Drupal::service('messenger')->addMessage('Nothing to do!');
        } else {
            batch_set($batch);
        }
    }

    /**
     * Prepare any relevant directories and copy files.
     */
    public static function copyFile($source, $destination) {
        $fs_driver = \Drupal::service('file_system');
        $directory = 'public://update_data';
        $patcher_directory = 'public://seventh_dawn';
        $fs_driver->prepareDirectory($directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
        $fs_driver->prepareDirectory($patcher_directory, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS);
        copy($source, $destination);
        \Drupal::service('messenger')->addMessage('Pushed ' . $destination . ' to softwareupdate.xivmac.com!');
    }

    /**
     * Delete unrequired file entities.
     */
    public static function clean(File $file) {
        $file->delete();
    }

    public static function postAppcastToDiscord() {
        \Drupal::service('xom_web_services.external_service')->postAppcastToDiscord();
        \Drupal::service('xom_web_services.external_service')->postAppcastToFeed();
    }

    public static function invalidateCache() {
      \Drupal::service('xom_web_services.external_service')->invalidateCdnCache(
        Settings::get('xom_web_services.software_update_dist_id'),
        '/sites/default/files/update_data/xivonmac_appcast.xml'
      );
    }

}
