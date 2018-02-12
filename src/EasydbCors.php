<?php

/**
 * @file
 * Contains \Drupal\easydb\EasydbCors.
 *
 * Adds the easydb server URL from the config to the CORS allowed origins.
 */

namespace Drupal\easydb;

use Asm89\Stack\Cors;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class EasydbCors extends Cors {

  /**
   * {@inheritdoc}
   */
  public function __construct(HttpKernelInterface $app, array $options = array()) {
    // Use the easydb_server_url value from the config, remove the trailing
    // slash and any possible path after the domain, and append this to the
    // allowed origins.
    $matches = [];
    if (preg_match('#https?://[^/]+#', \Drupal::config('easydb.settings')->get('easydb_server_url'), $matches)) {
      $options['allowedOrigins'][] = $matches[0];
    }
    else {
      \Drupal::logger('easydb')->error('Invalid server name (see easydb server URL in the module configuration).');
    }
    parent::__construct($app, $options);
  }

}
