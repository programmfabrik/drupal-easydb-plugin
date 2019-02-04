<?php

namespace Drupal\easydb;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Overrides the CORS service (http_middleware.cors) with own class EasydbCors.
 */
class EasydbServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   *
   * Overrides the CORS service (http_middleware.cors) to change some
   * functionality.
   */
  public function alter(ContainerBuilder $container) {
    $container->getDefinition('http_middleware.cors')->setClass(EasydbCors::class);
  }

}
