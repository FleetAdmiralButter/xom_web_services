<?php

namespace Drupal\xom_web_services\IINACT;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Cache\CacheableResponse;

/**
 * Class IINACTUpdateEndpoint
 * Faster than advancedcombattracker.com :)
 */
class IINACTUpdateEndpoint extends ControllerBase {

  private $state;
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state')
    );
  }

  public function serve() {
    $version = $this->fetchFromState('xom_web_services.iinact_plugin_latest');
    $response = new CacheableResponse($version, 200);
    $response->getCacheableMetadata()->addCacheTags(['iinact_plugin_latest']);
    return $response;
  }

  public function serveDownload() {
    $url = $this->fetchFromState('xom_web_services.iinact_plugin_download');
    $response = new TrustedRedirectResponse($url, 307);
    $response->getCacheableMetadata()->addCacheTags(['iinact_plugin_latest']);
    return $response;
  }

  private function fetchFromState($sid) {
    return $this->state->get($sid);
  }

}