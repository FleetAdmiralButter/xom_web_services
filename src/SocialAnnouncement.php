<?php

namespace Drupal\xom_web_services;

use Drupal\xom_web_services\Socials\DiscordHelper;
use Drupal\xom_web_services\Socials\FeedHelper;
use Drupal\xom_web_services\Socials\AppcastHelper;
use Drupal\Core\Datetime\DrupalDateTime;

class SocialAnnouncement {

    private $discord_helper;
    private $feed_helper;
    private $appcast_helper;
    public function __construct(DiscordHelper $discord_helper, FeedHelper $feed_helper, AppcastHelper $appcast_helper) {
        $this->discord_helper = $discord_helper;
        $this->feed_helper = $feed_helper;
        $this->appcast_helper = $appcast_helper;
    }

    public function postAppcastToDiscord() {
        $appcast = $this->appcast_helper->parseAppcast();
        $description = $this->discord_helper->templateChangelogEntries($appcast['changelogEntries']);
        $message = $this->discord_helper->templateMessage($appcast['version'], $description);
        $this->discord_helper->postDiscordMessage($message);
    }

    public function postAppcastToFeed() {
        $date = new DrupalDateTime('now');
        $date = $date->format('F j, Y');
        $appcast = $this->appcast_helper->parseAppcast();
        $description = $this->feed_helper->templateChangelogEntries($appcast['changelogEntries']);
        $message = $this->feed_helper->templateMessage($appcast['version'], $description, $date);
        $this->feed_helper->updateFeed($message);
    }

}