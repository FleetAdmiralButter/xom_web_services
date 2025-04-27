<?php

namespace Drupal\xom_web_services;

use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;
use Drupal\Core\Site\Settings;
use Drupal\xom_web_services\Socials\DiscordHelper;
use Drupal\xom_web_services\Socials\FeedHelper;
use Drupal\xom_web_services\Socials\AppcastHelper;
use Drupal\Core\Datetime\DrupalDateTime;
use Ramsey\Uuid\Uuid;

class ExternalService {

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

    public function invalidateCdnCache(string $dist, string $path) {
      $cf = new CloudFrontClient([
        'version'     => 'latest',
        'region'      => 'us-east-1', // CloudFront region is always us-east-1
        'credentials' => [
          'key'    => Settings::get('xom_web_services.aws_access_key_id'),
          'secret' => Settings::get('xom_web_services.aws_secret_key_id'),
        ],
      ]);

      $invalidationBatch = [
        'Paths'           => [
          'Quantity' => 1,
          'Items'    => [$path],
        ],
        'CallerReference' => Uuid::uuid4()->toString(),
      ];

      try {
        $result = $cf->createInvalidation([
          'DistributionId'   => $dist,
          'InvalidationBatch'=> $invalidationBatch,
        ]);

        $inv = $result->get('Invalidation');
        \Drupal::logger('xom_web_services')->notice("Invalidation submitted: {$inv['Id']} (status: {$inv['Status']})");
      } catch (AwsException $e) {
        throw new \Exception("Invalidation failed: {$e->getAwsErrorMessage()}");
      }
    }

}
