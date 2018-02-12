<?php

/**
 * @file
 * Contains \Drupal\easydb\Plugin\EntityBrowser\Widget\Easydb.
 */

namespace Drupal\easydb\Plugin\EntityBrowser\Widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\entity_browser\Plugin\EntityBrowser\Widget\Upload;
use Drupal\media\Entity\Media;

/**
 * Uses upload to create media entity images.
 *
 * @EntityBrowserWidget(
 *   id = "easydb_copy",
 *   label = @Translation("Copy from easydb"),
 *   description = @Translation("Copies images from easydb and creates media entities for them.")
 * )
 */
class Easydb extends Upload {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'extensions' => 'jpg jpeg tif png gif',
      'media_type' => 'easydb_image',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);
    $form['upload']['#upload_validators']['file_validate_extensions'] = [$this->configuration['extensions']];
    $form['upload']['#type'] = 'easydb_file';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    if ($mids = $form_state->getValue(['easydb_mids'], FALSE)) {
      $media_entities = Media::loadMultiple($mids);
      return $media_entities;
    }
    else {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $media_entities = $this->prepareEntities($form, $form_state);
      // We don't need any array_walk() with setPermanent() and save() (as in
      // Drupal\entity_browser\Plugin\EntityBrowser\Widget\Upload::submit())
      // here because we already save the media entities permanently in
      // ImportFilesController.
      $this->selectEntities($media_entities, $form_state);
      $this->clearFormValues($element, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['extensions'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Allowed extensions'),
      '#default_value' => $this->configuration['extensions'],
      '#required' => TRUE,
    ];

    $bundle_options = [];
    $bundles = $this
      ->entityTypeManager
      ->getStorage('media_type')
      ->loadByProperties(['type' => 'image']);

    foreach ($bundles as $bundle) {
      $bundle_options[$bundle->id()] = $bundle->label();
    }

    if (empty($bundle_options)) {
      $url = Url::fromRoute('entity.media_type.collection')->toString();
      $form['media_type'] = [
        '#markup' => $this->t('You don\'t have media bundle of the Image type. You should <a href="!link">create one</a>', ['!link' => $url]),
      ];
    }
    else {
      $form['media_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Media bundle'),
        '#default_value' => $this->configuration['media_type'],
        '#options' => $bundle_options,
      ];
    }

    return $form;
  }

}
