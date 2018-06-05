<?php

/**
 * @file
 * Contains \Drupal\easydb\Controller\ImportFilesController.
 */

namespace Drupal\easydb\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\user\PrivateTempStoreFactory;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ImportFilesController extends ControllerBase {

  /**
   * The user temp store.
   *
   * @var \Drupal\user\PrivateTempStore
   */
  protected $tempstore;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $user_data;

  /**
   * Constructs the ImportFilesController.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, UserDataInterface $user_data) {
    $this->tempstore = $temp_store_factory->get('easydb');
    $this->user_data = $user_data;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.private_tempstore'),
      $container->get('user.data')
    );
  }

  /**
   * Handles the image delivery request from easydb.
   *
   * $eb_uuid is the uuid of the entity browser where the image "order" towards
   * easydb originates from. It serves (1.) as a kind of access token to allow
   * the delivery of images at all, and (2.) allows to assign an image import
   * request to the Drupal browser window it originates from. See
   * easydb.routing.yml and EasydbFile::processManagedFile().
   *
   * @param Request $request The request object.
   * @param string $eb_uuid The entity browser uuid which is used as a token.
   *
   * @return A JsonResponse containing 'took' (the time to process the request) and 'files' (an array of feedback data about the file ingestion).
   */
  public function handle_request(Request $request, $eb_uuid) {
    $user_id = \Drupal::currentUser()->id();
    // Check if user is authenticated (because we need the user's tempstore and
    // user.data) and if the eb_uuid (the token) is valid, i.e. $eb_uuid is in
    // the list; $eb_uuid can't be empty because of the requirements in route
    // easydb.import.
    if (!($user_id > 0) || !in_array($eb_uuid, $this->tempstore->get('eb_uuid_list'))) {
      \Drupal::logger('easydb')->error('ImportFilesController: user not authenticated or invalid eb_uuid (' . $eb_uuid . ').');
      return new Response('', 401);
    }

    $easydb_data = Json::decode($request->request->get('body'));
    $subdir = \Drupal::config('easydb.settings')->get('easydb_files_subdir');
    if (!empty($subdir)) {
      $uri_base = 'public://' . $subdir . DIRECTORY_SEPARATOR;
      if (!file_prepare_directory($uri_base, FILE_MODIFY_PERMISSIONS | FILE_CREATE_DIRECTORY)) {
        $description = $this->t('Failed to create %dir. Check the easydb module\'s settings ("easydb files sub-directory") and the permissions in your file system.', ['%dir' => $uri_base]);
        \Drupal::logger('easydb')->error($description);
        drupal_set_message($description, 'error');
      }
    }
    else {  // if $subdir is empty
      $uri_base = 'public://';
    }

    $response_files = [];
    $media_entities = [];
    $curl_error_occured = FALSE;
    foreach ($easydb_data['files'] as $file_metadata) {
      // If a curl error occured before, we assume that the connection/download
      // won't work for this image either.
      if ($curl_error_occured) {
        $response_this_file = [
          'uid' => $file_metadata['uid'],
        ];
        $this->set_error(
          $response_this_file,
          'error.drupal.curl',
          $this->t('A curl error occured when trying to fetch another image before.')
        );
      }
      else {  // if no curl error occured
        $response_this_file = $this->handle_file($file_metadata, $easydb_data['send_data'], $uri_base, $request->headers->get('Content-Type'));
        $response_files[] = $response_this_file;
        if (isset($response_this_file['error']) && $response_this_file['error']['code'] == 'error.drupal.curl') {
          $curl_error_occured = TRUE;
        }
        if (isset($response_this_file['resourceid'])) {
          $media_entities[] = $response_this_file['resourceid'];
        }
      }
    }

    // Save the window_preferences to the Drupal user.data system.
    $wp_width = $easydb_data['window_preferences']['width'];
    $wp_height = $easydb_data['window_preferences']['height'];
    // Check if width and height exist and are numbers
    if (isset($wp_width, $wp_height) && is_int($wp_width) && is_int($wp_height)) {
      $this->user_data->set('easydb', $user_id, 'window_preferences', ['width' => $wp_width, 'height' => $wp_height]);
    }

    // Load and add the already copied media entities in case the "copy from
    // easydb" button is pressed several times.
    if ($old_mids = $this->tempstore->get('mids_' . $eb_uuid)) {
      $media_entities = array_merge($old_mids, $media_entities);
    }
    // Save the mids of the newly created media entities to the user's private
    // tempstore.
    $this->tempstore->set('mids_' . $eb_uuid, $media_entities);
    $response = new JsonResponse([
      'took' => round((\Drupal::time()->getCurrentMicroTime() - \Drupal::time()->getRequestMicroTime()) * 1000),
      'files' => $response_files,
    ]);
    return $response;
  }

  /**
   * Handles the ingestion of a single file of the delivery request from easydb.
   *
   * Saves the file as a "managed file" and creates a media entity containing
   * this file and the fields from the metadata. On a multilingual setup,
   * multiple translations of the media entity are created. If a media entity
   * with the same easydb UID already exists, it's changed/updated (including
   * its translations) instead of creating a new one.
   *
   * @param array $file_metadata The metadata array from easydb.
   * @param bool $send_data Whether or not the file is sent within the POST request.
   * @param string $uri_base The directory where to save the file.
   * @param string $request_content_type The "Content-Type" header of the request if given.
   *
   * @return An array of feedback data for easydb about the file ingestion.
   */
  protected function handle_file(array &$file_metadata, $send_data, $uri_base, $request_content_type) {
    $response_this_file = [
      'uid' => $file_metadata['uid'],
    ];
    if (!empty($file_metadata['filename'])) {
      $uri =  $uri_base . file_munge_filename($file_metadata['filename'], 'jpg png gif');  // the intended file name
    }
    else {
      $uri = $uri_base . 'file';
    }
    if ($send_data) {
      // Proceed only if Content-Type header is multipart/form-data.
      if (strpos($request_content_type, 'multipart/form-data;') !== 0) {
        $this->set_error(
          $response_this_file,
          'error.drupal.not_multipart_form_data',
          $this->t('The Content-Type header of the POST request isn\'t "multipart/form-data" as expected.'),
          ['content_type' => $request_content_type]
        );
        return $response_this_file;
      }
      // Proceed only if the actual POST ($_FILES) file name equals the file
      // name in the easydb metadata.
      if (!empty($_FILES['files']['name'][0]) && $_FILES['files']['name'][0] != $file_metadata['filename']) {
        $this->set_error(
          $response_this_file,
          'error.drupal.filename_inconsistent',
          $this->t('Filename inconsistent: filename promised by JSON data differs from the one delivered by POST files.'),
          [
            'filename_json' => $file_metadata['filename'],
            'filename_post' => $_FILES['files']['name'][0],
          ]
        );
        return $response_this_file;
      }
      try {
        $file_data = file_get_contents($_FILES['files']['tmp_name'][0]);
      }
      catch (\Exception $e) {
        $this->set_error(
          $response_this_file,
          'error.drupal.file_get_contents',
          $this->t('Exception while using file_get_contents() with the download URL.'),
          ['message' => $e->getMessage()]
        );
      }
    }
    else {  // if not $send_data
      // Proceed only if there is a URL
      if (!array_key_exists('url', $file_metadata) || empty($file_metadata['url'])) {
        $this->set_error(
          $response_this_file,
          'error.drupal.no_url',
          $this->t('Download URL missing.')
        );
        return $response_this_file;
      }
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $file_metadata['url']);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
      $file_data = curl_exec($ch);
      // Proceed only if the server is reachable (connect within 5 seconds)
      if (curl_errno($ch)) {
        drupal_set_message($this->t('The easydb images couldn\'t be fetched. The easydb server might be unreachable by the Drupal server and activating the "Send file via browser" option for Drupal in your easydb server\'s configuration might help.'), 'error');
        $this->set_error(
          $response_this_file,
          'error.drupal.curl',
          $this->t('A curl error occured: The easydb images couldn\'t be fetched.'),
          [
            'curl error code' => curl_errno($ch),
            'curl error message' => curl_error($ch),
          ]
        );
        curl_close($ch);
        return $response_this_file;
      }
      curl_close($ch);
    }
    // Check for existing media entities with this easydb UID; there should be
    // only one or none, so all "foreach ($existing_media_entities ..." loops
    // should run only once; however, if anything went wrong and there is
    // more than one entity, we handle all of them, but in that case, the
    // "resourceid" field in the response to easydb will only contain the last
    // entity's id.
    $existing_media_entities = \Drupal::entityTypeManager()->getStorage('media')->loadByProperties(['field_easydb_uid' => $file_metadata['uid']]);
    // If there is already a media entity with this easydb UID, we delete the
    // image file already before calling file_save_data(..., ..., FILE_EXISTS_RENAME)
    // to prevent a file name extension like "_0" if it's not necessary.
    if ($existing_media_entities) {
      foreach ($existing_media_entities as $media_entity) {
        if ($file = File::load($media_entity->field_media_image->target_id)) $file->delete();
      }
    }
    $file = file_save_data($file_data, $uri, FILE_EXISTS_RENAME);
    // Proceed only if saving the file was successful.
    if ($file === FALSE) {
      $this->set_error(
        $response_this_file,
        'error.drupal.file_save',
        $this->t('Couldn\'t save file in Drupal\'s file system.')
      );
      return $response_this_file;
    }
    // Get the language_mapping from the easydb.settings config but the keys
    // (Drupal langcodes) should be restricted to to the enabled languages
    // (just in case a language once configured in the easydb settings was
    // disabled in Drupal later).
    $language_mapping = array_intersect_key(\Drupal::config('easydb.settings')->get('language_mapping'), \Drupal::languageManager()->getLanguages());
    // Remove languages with "don't create a translation..." setting.
    foreach ($language_mapping as $langcode => $easydb_langcode) {
      if ($easydb_langcode == 'none') unset($language_mapping[$langcode]);
    }
    // It's a fatal error if no Drupal language is mapped to any easydb
    // language because the following metadata import requires some basic
    // language decisions.
    if (empty($language_mapping)) {
      drupal_set_message($this->t('The easydb images couldn\'t be imported because no easydb language is mapped to this site\'s language(s). Please check the language mapping section on the <a href=":settings_url">easydb settings page</a>.', [':settings_url' => Url::fromRoute('easydb.settings')->toString()]), 'error');
      $this->set_error(
        $response_this_file,
        'error.drupal.language_mapping',
        $this->t('Couldn\'t import images because of missing language mapping.')
      );
      return $response_this_file;
    }
    // $ent_values stores the media entity values for the different languages
    // as an array keyed by langcode.
    $ent_values = [];
    foreach ($language_mapping as $langcode => $easydb_langcode) {
      $ent_values[$langcode] = $this->metadata_mapping($file_metadata, $easydb_langcode) + [
        'langcode' => $langcode,
        // Set a default name if name isn't set by metadata_mapping().
        'name' => 'easydb image ' . $file->id(),
      ];
      $ent_values[$langcode]['field_media_image']['target_id'] = $file->id();
    }
    // Check if the site is multilingual and the media bundle "easydb_image" is
    // translatable. If not, there's only one language and $ent_values_onelang
    // contains only data for that default language.
    if (\Drupal::hasService('content_translation.manager') && \Drupal::service('content_translation.manager')->isEnabled('media', 'easydb_image')) {
      $ent_values_onelang = NULL;
    }
    else {
      // The current language exists as a key in $ent_values because
      // $ent_values is created for each $language_mapping element. And
      // $language_mapping either contains the current langcode (see
      // array_intersect_key() above) or is empty and then we would have
      // returned from this function earlier.
      $ent_values_onelang = $ent_values[\Drupal::languageManager()->getCurrentLanguage()->getId()];
    }
    if ($existing_media_entities) {
      // $existing_media_entities should contain only one (or no) entity.
      foreach ($existing_media_entities as $media_entity) {
        if ($ent_values_onelang) {  // if easydb_image is not translatable
          foreach ($ent_values_onelang as $key => $value) {
            $media_entity->set($key, $value);
          }
        }
        else {  // if easydb_image is translatable
          foreach ($ent_values as $langcode => $ent_values_current) {
            if ($media_entity->hasTranslation($langcode)) {
              // Update translation.
              $translation = $media_entity->getTranslation($langcode);
              foreach ($ent_values_current as $key => $value) {
                $translation->set($key, $value);
              }
            }
            else {
              $media_entity->addTranslation($langcode, $ent_values_current);
            }
          }
        }
        $media_entity->setNewRevision();
        $media_entity->save();
        $response_this_file['resourceid'] = $media_entity->id();
      }
      $response_this_file['action_taken'] = 'update';
    }
    else {  // if there are no $existing_media_entities
      if ($ent_values_onelang) {  // if easydb_image is not translatable
        $media_entity = Media::create($ent_values_onelang + ['bundle' => 'easydb_image']);
      }
      else {  // if easydb_image is translatable
        $media_entity = Media::create(['bundle' => 'easydb_image']);
        foreach ($ent_values as $langcode => $ent_values_current) {
          $media_entity->addTranslation($langcode, $ent_values_current);
        }
      }
      $media_entity->save();
      $response_this_file['resourceid'] = $media_entity->id();
      $response_this_file['action_taken'] = 'insert';
    }
    $response_this_file['url'] = file_create_url($file->getFileUri());
    // If there was no error, status is "done".
    if (empty($response_this_file['status'])) $response_this_file['status'] = 'done';
    return $response_this_file;
  }

  /**
   * Returns the the appropriate string for a given key in the file metadata
   * array if available.
   *
   * Checks if the key exists, if it's a plain string or a multilingual array,
   * and returns the appropriate value, i.e. the value for the requested
   * language in case of a multilingual array, or the plain string otherwise.
   * If anything fails, @c FALSE is returned.
   *
   * @param array $file_metadata The metadata received from easydb.
   * @param array $easydb_langcode The (easydb) language code.
   *
   * @return A string or @c FALSE.
   */
  protected function metadata_mapping_single(array $file_metadata, $key, $easydb_langcode) {
    if (array_key_exists($key, $file_metadata)) {
      if (is_array($file_metadata[$key])) {
        if (array_key_exists($easydb_langcode, $file_metadata[$key])) {
          return $file_metadata[$key][$easydb_langcode];
        }
        else {
          return FALSE;
        }
      }
      elseif (!empty($file_metadata[$key])) {  // $file_metadata[$key] is a string
        return $file_metadata[$key];
      }
    }
    return FALSE;
  }

  /**
   * Maps the metadata received from easydb to an entity values array.
   *
   * First, the relevant data is copied to the @c $data array. It's values are
   * @c FALSE or empty strings if they were not set in the file metadata.
   *
   * Then, the return array is filled with the available values. The default
   * media entity's name and img title field is the title metadata if
   * available, otherwise caption, otherwise alternative, otherwise filename.
   * The img alt field gets the same value if the "alternative" metadata field
   * is not available.
   *
   * @param array $file_metadata The metadata received from easydb.
   * @param array $easydb_langcode The (easydb) language code.
   *
   * @return An associative array with the following keys if the according data
   * was given in the @c $file_metadata parameter: 'name', 'field_easydb_uid',
   * 'field_easydb_title', 'field_easydb_description', 'field_easydb_caption',
   * 'field_easydb_keywords', 'field_easydb_copyright', and 'field_media_image',
   * an array with the keys 'alt' and 'title'.
   */
  protected function metadata_mapping(array $file_metadata, $easydb_langcode) {
    // Set $data from $file_metadata,
    // first for simple strings.
    foreach (['uid', 'filename'] as $key) {
      $data[$key] = (array_key_exists($key, $file_metadata) && !empty($file_metadata[$key])) ? $file_metadata[$key] : FALSE;
    }
    // Set $data for (possibly) multilingual fields.
    foreach (['title', 'description', 'caption', 'alternative', 'keywords', 'copyright'] as $key) {
      $data[$key] = $this->metadata_mapping_single($file_metadata, $key, $easydb_langcode);
    }
    // Set $name to the first available data from the list.
    foreach (['title', 'caption', 'alternative', 'filename'] as $key) {
      if ($name = $data[$key]) break;
    }

    // Fill the return array.
    $return = [
      'field_media_image' => [
        // Re-use $name if there's no alt text in the metadata.
        'alt' => $data['alternative'] ?: $name,
      ],
    ];
    if ($name) {
      $return['field_media_image']['title'] = $name;
      $return['name'] = $name;
    }
    foreach (['uid', 'title', 'description', 'caption', 'keywords', 'copyright'] as $key) {
      if ($data[$key]) $return['field_easydb_' . $key] = $data[$key];
    }
    return $return;
  }

  /**
   * Sends an error message to Drupal logger, drupal_set_message(), and the
   * response array.
   *
   * @param array $response_this_file The current element of the response array.
   * @param string $code The error code for easydb.
   * @param string $description The error description for easydb and Drupal log.
   * @param array $parameters The error parameters for easydb.
   */
  protected function set_error(&$response_this_file, $code, $description, $parameters = NULL) {
    \Drupal::logger('easydb')->error($description);
    drupal_set_message($description, 'error');
    $response_this_file['status'] = 'error';
    $response_this_file['error'] = [
      'code' => $code,
      'description' => $description,
    ];
    if ($parameters) $response_this_file['error']['parameters'] = $parameters;
  }

}
