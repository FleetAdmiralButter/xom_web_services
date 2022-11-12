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
    $version = $this->fetchFromCache('iinact_plugin_latest');
    $response = new CacheableResponse($version, 200);
    $response->getCacheableMetadata()->addCacheTags(['iinact_plugin_latest']);
    return $response;
  }

  public function serveDownload() {
    $url = $this->fetchFromCache('iinact_plugin_latest_url');
    return new TrustedRedirectResponse($url, 307);
  }

  private function fetchFromCache($cid) {
    $result = $this->cache->get($cid, TRUE)->data;

    /**
     * Ensure the caching backend gave us a valid result.
     */
    if (empty($result)) {
      \Drupal::service('xom_web_services.iinact_update_manager')->pluginGetLatest();
      $result = $this->cache->get($cid, TRUE)->data;
    }
    return $result;
  }

}