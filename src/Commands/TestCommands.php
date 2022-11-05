<?php

namespace Drupal\xom_web_services\Commands;

use Drush\Commands\DrushCommands;

class TestCommands extends DrushCommands {

    /**
     * Test Discord integration.
     * 
     * @command sparkle:discord
     * @aliases sparkle-discord
     * 
     * @usage sparkle:discord
     */
    public function testDiscord() {
        \Drupal::service('xom_web_services.social_announcement')->postAppcastToDiscord();
    }

    /**
     * Test Discord integration.
     * 
     * @command sparkle:feed
     * @aliases sparkle-feed
     * 
     * @usage sparkle:feed
     */
    public function testFeedUpdate() {
        \Drupal::service('xom_web_services.social_announcement')->postAppcastToFeed();
    }

    /**
     * Poll SE's Appcast feed.
     * 
     * @command sparkle:secheck
     * @aliases sparkle-secheck
     * 
     * @usage sparkle:secheck
     */
    public function SECheck() {
        \Drupal::service('xom_web_services.se_update_scanner')->checkAndNotify();
    }
}