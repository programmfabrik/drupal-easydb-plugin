<?php

/**
 * @file
 * Contains \Drupal\easydb\Form\EasydbSettingsForm.
 *
 * Creates the module settings form. The available easydb languages (like
 * "en-US" or "de-DE") are set up here in @c $easydb_languages.
 */

namespace Drupal\easydb\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Settings form for easydb module.
 */
class EasydbSettingsForm extends ConfigFormBase {

  /**
   * List of language codes available in easydb.
   *
   * @var array
   */
  protected $easydb_languages = ['en-US', 'de-DE'];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'easydb_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['easydb.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('easydb.settings');
    $form['easydb_server_url'] = array(
      '#type' => 'url',
      '#title' => $this->t('easydb server URL'),
      '#default_value' => $config->get('easydb_server_url'),
      '#description' => $this->t('The URL of the easydb server, including "http://" or "https://".'),
    );
    $form['easydb_files_subdir'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('easydb files sub-directory'),
      '#default_value' => $config->get('easydb_files_subdir'),
      '#description' => $this->t('The sub-directory where the files from easydb will be stored, e.g. "easydb". I.e. the files will be stored in sites/default/files/<em>sub-directory</em> and thus will have a file URL like "http://example.org/sites/default/files/<em>sub-directory</em>/filename.jpg". Leave it empty to store the easydb files among all others in the files directory.'),
    );
    $options_array = [];
    foreach ($this->easydb_languages as $easydb_langcode) {
      $options_array[$easydb_langcode] = $this->t('Use easydb\'s @easydb_langcode translation.', ['@easydb_langcode' => $easydb_langcode]);
    }
    $form['langmap'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Language mapping'),
      '#markup' => $this->t('For each language configured on this Drupal site, you can choose which of the languages of easydb should be used. Alternatively, you can choose to not create a media entity translation in this language.'),
    );
    foreach (\Drupal::languageManager()->getLanguages() as $language_id => $language) {
      $form['langmap']['langmap_' . $language_id] = array(
        '#type' => 'select',
        '#title' => $language->getName(),
        '#options' => $options_array,
        '#empty_value' => 'none',
        '#empty_option' => $this->t('Don\'t create a translation in this language.'),
        '#default_value' => $config->get('language_mapping.' . $language_id),
      );
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $easydb_settings = $this->configFactory->getEditable('easydb.settings');
    // Turn the the entered easydb_server_url string to a valid URL.
    $easydb_settings->set('easydb_server_url', Url::fromUri($form_state->getValue('easydb_server_url'))->toString());
    $easydb_settings->set('easydb_files_subdir', $form_state->getValue('easydb_files_subdir'));
    foreach (array_keys(\Drupal::languageManager()->getLanguages()) as $language_id) {
      $easydb_settings->set('language_mapping.' . $language_id, $form_state->getValue(['langmap_' . $language_id]));
    }
    $easydb_settings->save();
    parent::submitForm($form, $form_state);
  }

}
