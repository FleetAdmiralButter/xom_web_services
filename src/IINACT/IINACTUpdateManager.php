<?php

namespace Drupal\xom_web_services\IINACT;

use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\Client;
use Drupal\Component\Utility\Xss;
use Ramsey\Uuid\Uuid;

class IINACTUpdateManager {

    private StateInterface $state;
    private $http_client;
    private $webhook_url;
    public function __construct(StateInterface $state, Client $http_client) {
        $this->state = $state;
        $this->http_client = $http_client;
        $this->webhook_url = Settings::get('xom_web_services.iinact_webhook_url');
    }

    public function pluginGetLatest() {
      try {
        \Drupal::logger('xom_web_services')->debug('IINACT: Cache sync requested.');
        $release_json = $this->http_client->get('https://api.github.com/repos/ravahn/FFXIV_ACT_Plugin/releases/latest')->getBody()->getContents();
        $response = json_decode($release_json, TRUE);
        $latest_plugin_ver = $response['tag_name'];
        $latest_plugin_ver = Xss::filter($latest_plugin_ver);
        $url = $response['assets'][0]['browser_download_url'];
        $this->updateCachedPluginVersion($latest_plugin_ver, $url);
      } catch (\Exception $e) {
        \Drupal::logger('xom_web_services')->error($e->getMessage());
      }
    }

    private function updateCachedPluginVersion($version, $url) {
      $old_version = $this->state->get('xom_web_services.iinact_plugin_latest', '0.0.0.0');
      if ($old_version != $version) {
        // Initialise and download the ZIP.
        $fs_driver = \Drupal::service('file_system');
        $staging_directory = 'private://iinact';
        $public_directory = 'public://update_data/IINACT';
        $fs_driver->prepareDirectory($staging_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        $downloadedZip = $fs_driver->realpath($staging_directory) . '/FFXIV_ACT_Plugin.zip';
        $this->http_client->get($url, [
          'sink' => $downloadedZip,
        ]);

        // Extract the DLL from the downloaded archive.
        $zip = new \ZipArchive();
        if ($zip->open($downloadedZip) !== TRUE) {
          unlink($downloadedZip);
          throw new \Exception('Could not open ZIP file.');
        }
        if ($zip->extractTo($staging_directory, 'FFXIV_ACT_Plugin.dll') !== TRUE) {
          unlink($downloadedZip);
          throw new \Exception('Could not extract DLL from ZIP file.');
        }
        unlink($downloadedZip);

        // Create a new ZIP file containing only the plugin DLL.
        $dllPath = $fs_driver->realpath($staging_directory) . '/FFXIV_ACT_Plugin.dll';
        $zip = new \ZipArchive();
        $finalZipName = sprintf('FFXIV_ACT_Plugin_%s.zip', $version);
        $finalZipPath = $fs_driver->realpath($staging_directory) . '/' . $finalZipName;
        $zip->open($finalZipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFile($dllPath, 'FFXIV_ACT_Plugin.dll' );;
        $zip->close();
        unlink($dllPath);

        // Copy the file to the SU service.
        $fs_driver->prepareDirectory($public_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
        copy($finalZipPath, 'public://update_data/IINACT/' . $finalZipName);
        unlink($finalZipPath);

        $finalZipUrl = 'https://softwareupdate.xivmac.com/sites/default/files/update_data/IINACT/' . $finalZipName;

        $this->state->set('xom_web_services.iinact_plugin_latest', $version);
        $this->state->set('xom_web_services.iinact_plugin_download', $finalZipUrl);

        // Clear all application and CDN caches.
        Cache::invalidateTags(['iinact_plugin_latest']);
        \Drupal::service('xom_web_services.external_service')->invalidateCdnCache(Settings::get('xom_web_services.iinact_updater_dist_id'), '/updater/*');

        $this->postDiscordMessage($version, $finalZipUrl);
        \Drupal::logger('xom_web_services')->notice('IINACT: Cache sync complete.');
      } else {
        \Drupal::logger('xom_web_services')->notice('IINACT: Cache sync skipped.');
      }
    }

    private function postDiscordMessage($version, $url) {
      $body = [
        'content' => $this->templateMessage($version, $url),
        'username' => 'www.iinact.com',
        'avatar_url' => "https://content.xivmac.com/sites/default/files/2022-04/discord_bot.png",
      ];
      $body = Json::encode($body);
      $request_params = [
        'headers' => [
          'Content-Type' => 'application/json'
        ],
        'body' => $body
      ];
      try {
        $this->http_client->post($this->webhook_url, $request_params);
      } catch (\Exception) {
        \Drupal::service('messenger')->addError('Discord webhook error.');
      }
    }

    private function templateMessage($version, $url) {
      return <<<EOT
      Found and repacked a new FFXIV_ACT_Plugin release.

      Version: $version
      Download URL: $url
      EOT;
    }
}
