<?php

namespace Drupal\xom_web_services\Socials;

use Drupal\block_content\Entity\BlockContent;
use Drupal\paragraphs\Entity\Paragraph;


class FeedHelper {

    public function __construct() {

    }

    public function updateFeed($message) {
      $bid = 15;
      $block = BlockContent::load($bid);
      $pid = $block->get('field_c_b_components')->getValue();
      $paragraph = Paragraph::load($pid[0]['target_id']);
      $feed = $paragraph->get('field_c_p_content')->getValue()[0]['value'];
      $body = $message . $feed;
      $paragraph->set('field_c_p_content', ['value' => $body, 'format'=> 'basic_html']);
      $paragraph->save();
    }

    public function templateChangelogEntries($changelogEntries) {
        $result = "";
        foreach ($changelogEntries as $changelogEntry) {
            $result .= '• ' . $changelogEntry . "<br>";
        }
        return $result;
    }

    public function templateMessage($version, $description, $date) {
        return <<<EOT
        <strong>$date:</strong><br><br>

        XIV on Mac $version Beta<br>

        $description

        --- <br>
        EOT;
    }
}