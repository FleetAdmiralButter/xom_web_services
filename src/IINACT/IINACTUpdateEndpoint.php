<?php

namespace Drupal\xom_web_services\IINACT;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Cache\CacheableResponse;

/**
 * Class IINACTUpdateEndpoint
 * Faster than advancedcombattracker.com :)
 */
class IINACTUpdateEndpoint extends ControllerBase {

  private $cache;
  public function __construct(CacheBackendInterface $cache) {
    $this->cache = $cache;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default')
    );
  }

  public function serve() {
    $hit = 'HIT';
    $version = $this->fetchFromCache('iinact_plugin_latest');
    if (!$version) {
      $hit = 'MISS';
      \Drupal::service('xom_web_services.iinact_update_manager')->pluginGetLatest();
      $version = $this->fetchFromCache('iinact_plugin_latest');
    }
    $response = new CacheableResponse($version, 200, ['X-IINACT-Origin-Cache-Status' => $hit]);
    $response->getCacheableMetadata()->addCacheTags(['iinact_plugin_latest']);
    return $response;
  }

  public function serveDownload() {
    $hit = 'HIT';
    $url = $this->fetchFromCache('iinact_plugin_latest_url');
    if (!$url) {
      $hit = 'MISS';
      \Drupal::service('xom_web_services.iinact_update_manager')->pluginGetLatest();
      $url = $this->fetchFromCache('iinact_plugin_latest_url');
    }
    return new TrustedRedirectResponse($url, 307, ['X-IINACT-Origin-Cache-Status' => $hit]);
  }

  private function fetchFromCache($cid) {
    return $this->cache->get($cid, TRUE)->data;
  }
}