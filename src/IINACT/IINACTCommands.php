<?php

namespace Drupal\xom_web_services\IINACT;

use Drush\Commands\DrushCommands;

class IINACTCommands extends DrushCommands {
  /**
   * Get the latest plugin version
   *
   * @command sparkle:iinact-update
   *
   * @usage sparkle:iinact-update
   */
  public function pluginGetLatest() {
    \Drupal::logger('xom_web_services')->debug('IINACT: Caches scheduled for sync.');
    \Drupal::service('xom_web_services.iinact_update_manager')->pluginGetLatest();
  }
}