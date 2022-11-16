<?php

namespace Drupal\xom_web_services\IINACT;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\Client;
use Drupal\Component\Utility\Xss;

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
        \Drupal::logger('xom_web_services')->debug('IINACT: Cache sync complete.');
      } catch (\Exception $e) {
        \Drupal::logger('xom_web_services')->error('IINACT Plugin refresh failed, serving stale content.');
        \Drupal::logger('xom_web_services')->error($e->getMessage());
      }
    }

    private function updateCachedPluginVersion($version, $url) {
      $old_version = $this->state->get('xom_web_services.iinact_plugin_latest', '0.0.0.0');
      if ($old_version != $version) {
        $this->state->set('xom_web_services.iinact_plugin_latest', $version);
        $this->state->set('xom_web_services.iinact_plugin_download', $url);
        Cache::invalidateTags(['iinact_plugin_latest']);
        $this->postDiscordMessage($version, $url);
      } else {
        \Drupal::logger('xom_web_services')->debug('IINACT: Cache sync skipped.');
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
      Found new FFXIV_ACT_Plugin release!
      
      Propagating to caches, allow +/- 5 minutes.
      
      Version: $version
      Download URL: $url
      EOT;
  }


}