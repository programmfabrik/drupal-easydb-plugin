<?php

/**
 * @file
 * Contains \Drupal\easydb\EasydbServiceProvider.
 *
 * Overrides the default CORS service to be able to add the easydb server URL
 * to the allowed origins in \Drupal\easydb\EasydbCors.
 */

namespace Drupal\easydb;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

class EasydbServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   *
   * Overrides the CORS service (http_middleware.cors) to change some functionality.
   */
  public function alter(ContainerBuilder $container) {
    $container->getDefinition('http_middleware.cors')->setClass(EasydbCors::class);
  }

}
