<?php

namespace Drupal\easydb\Element;

use Drupal\file\Element\ManagedFile;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;

/**
 * Provides an AJAX/progress aware widget for uploading and saving a file.
 *
 * @FormElement("easydb_file")
 */
class EasydbFile extends ManagedFile {

  /**
   * Override this function.
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    return (array) $input + ['fids' => []];
  }

  /**
   * Render API callback: Expands the managed_file element type.
   *
   * Expands the file type to include Upload and Remove buttons, as well as
   * support for a default value.
   */
  public static function processManagedFile(&$element, FormStateInterface $form_state, &$complete_form) {

    $current_user = \Drupal::currentUser();
    // Access check.
    if (!$current_user->hasPermission('access easydb')) {
      $error_message = \Drupal::translation()->translate('You don\'t have the permission to use the easydb file picker.');
      drupal_set_message($error_message, 'error');
      \Drupal::logger('easydb')->error($error_message);
      $element['error']['#markup'] = ':-/';
      return $element;
    }

    // This is used sometimes so let's implode it just once.
    $parents_prefix = implode('_', $element['#parents']);

    $tempstore = \Drupal::service('user.private_tempstore')->get('easydb');
    $eb_uuid_list = $tempstore->get('eb_uuid_list') ?: [];
    // Get the instance uuid of the current entity browser.
    if ($eb_uuid = $form_state->get(['entity_browser', 'instance_uuid'])) {
      if (!in_array($eb_uuid, $eb_uuid_list)) {
        $tempstore->set('eb_uuid_list', array_merge($eb_uuid_list, [$eb_uuid]));
      }
      $mids = $tempstore->get('mids_' . $eb_uuid);
    }
    else {
      // Quit if we don't have $eb_uuid as we require it later for the route
      // for the callback URL.
      $error_message = \Drupal::translation()->translate('Couldn\'t retrieve the entity browser\'s instance uuid.');
      drupal_set_message($error_message, 'error');
      \Drupal::logger('easydb')->error($error_message);
      $element['error']['#markup'] = ':-/';
      return $element;
    }
    $form_state->setValue(['easydb_mids'], $mids);

    // Set some default element properties.
    $element['#progress_indicator'] = empty($element['#progress_indicator']) ? 'none' : $element['#progress_indicator'];
    $element['#tree'] = TRUE;
    // Load the media entities which will be rendered later.
    $element['#media_entities'] = !empty($mids) ? Media::loadMultiple($mids) : FALSE;

    // Generate a unique wrapper HTML ID.
    $ajax_wrapper_id = Html::getUniqueId('ajax-wrapper');

    $ajax_settings = [
      'callback' => [get_called_class(), 'uploadAjaxCallback'],
      'options' => [
        'query' => [
          'element_parents' => implode('/', $element['#array_parents']),
        ],
      ],
      'wrapper' => $ajax_wrapper_id,
      'effect' => 'fade',
      'progress' => [
        'type' => $element['#progress_indicator'],
        'message' => $element['#progress_message'],
      ],
    ];

    // Add a hidden button that reloads the file list. This will be triggered
    // when the easydb window sends "action: reload".
    $element['easydb_refresh'] = [
      '#name' => $parents_prefix . '_easydb_refresh',
      '#type' => 'submit',
      '#value' => t('Refresh'),
      '#attributes' => ['class' => ['js-hide']],
      '#validate' => [],
      '#submit' => [],
      '#ajax' => $ajax_settings,
      '#weight' => 2,
    ];

    $element['easydb_button'] = [
      '#name' => $parents_prefix . '_easydb_button',
      '#type' => 'submit',
      '#value' => t('Fetch from easydb'),
      '#validate' => [],
      // No regular submit-handler. This form only works via JavaScript.
      '#submit' => [],
      '#weight' => 1,
      '#attributes' => [
        'onclick' => 'easydbAdapter.openPicker(this.event); return false;',
      ],
    ];

    $callback_url = Url::fromRoute('easydb.import', ['eb_uuid' => $eb_uuid], ['absolute' => TRUE])->toString();
    $easydb_server = \Drupal::config('easydb.settings')->get('easydb_server_url');
    $short_config = [
      'callbackurl' => $callback_url,
      'extensions' => ['jpg', 'jpeg', 'tif', 'png', 'gif'],
    ];
    // List all existing easydb UIDs in an array of arrays ['uid' => $uid].
    $existing_files = [];
    foreach (\Drupal::entityTypeManager()->getStorage('media')->loadMultiple() as $media_entity) {
      $field_easydb_uid = $media_entity->get('field_easydb_uid')->getValue();
      if (!empty($field_easydb_uid[0]['value'])) {
        $existing_files[] = ['uid' => $field_easydb_uid[0]['value']];
      }
    }
    $full_config = $short_config + ['existing_files' => $existing_files];
    if ($user_id = $current_user->id()) {
      $window_preferences = \Drupal::service('user.data')->get('easydb', $user_id, 'window_preferences') ?: [];
    }
    else {
      $window_preferences = [];
    }
    // Use default values if $window_preferences isn't set yet.
    $window_preferences += ['width' => 650, 'height' => 600];
    $element['easydb_button']['#attached']['drupalSettings']['easydb'] = [
      'easydb_url' => $easydb_server . '?drupalfilepicker=' . rawurlencode(base64_encode(json_encode($short_config))),
      'easydb_server' => $easydb_server,
      'config' => rawurlencode(base64_encode(json_encode($full_config))),
      // Not smaller than 100 px.
      'window_width' => max($window_preferences['width'], 100),
      'window_height' => max($window_preferences['height'], 100),
      'refresh_button_name' => $element['easydb_refresh']['#name'],
    ];
    $element['easydb_button']['#attached']['library'][] = 'easydb/easydbAdapter';

    // Force the progress indicator for the remove button to be either 'none' or
    // 'throbber', even if the upload button is using something else.
    $ajax_settings['progress']['type'] = ($element['#progress_indicator'] == 'none') ? 'none' : 'throbber';
    $ajax_settings['progress']['message'] = NULL;
    $ajax_settings['effect'] = 'none';
    $element['remove_button'] = [
      '#name' => $parents_prefix . '_remove_button',
      '#type' => 'submit',
      '#value' => $element['#multiple'] ? t('Remove selected') : t('Remove'),
      '#validate' => [],
      '#submit' => ['file_managed_file_submit'],
      '#limit_validation_errors' => [$element['#parents']],
      '#ajax' => $ajax_settings,
      '#weight' => 1,
    ];

    // ManagedFile::validateManagedFile() expects $element['fids']['#value'].
    $element['fids'] = [
      '#type' => 'hidden',
      '#value' => [],
    ];

    if (!empty($mids) && $element['#media_entities']) {
      foreach ($element['#media_entities'] as $delta => $media_entity) {
        $media_entity_link = \Drupal::service('entity_type.manager')->getViewBuilder('media')->view($media_entity, 'thumbnail');

        if ($element['#multiple']) {
          $element['media_entity_' . $delta]['selected'] = [
            '#type' => 'checkbox',
            '#title' => \Drupal::service('renderer')->renderPlain($media_entity_link),
          ];
        }
        else {
          $element['media_entity_' . $delta]['filename'] = $media_entity_link + ['#weight' => -10];
        }
      }
    }

    // Prefix and suffix used for Ajax replacement.
    $element['#prefix'] = '<div id="' . $ajax_wrapper_id . '">';
    $element['#suffix'] = '</div>';

    return $element;
  }

}
