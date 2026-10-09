<?php

namespace Drupal\tre_ai_alt_text;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\file\Entity\File;

/**
 * AJAX callbacks for the TRE AI Alt Text feature.
 */
class AiAltTextCallbacks implements TrustedCallbackInterface {

  /**
   * Maximum alt text length in characters (including spaces).
   */
  private const MAX_ALT_TEXT_LENGTH = 160;

  /**
   * Image style used to resize the image before sending it to the AI.
   *
   * A plain scale style (no cropping) so the AI sees the whole image.
   */
  private const IMAGE_STYLE = 'hero_wave_medium_1200w';

  /**
   * The image field on the image media type.
   */
  private const IMAGE_FIELD = 'field_media_image';

  /**
   * Prompt template.
   *
   * {LANGUAGE} is replaced with the target response language.
   */
  private const AI_PROMPT = "Analysoi kuva ja kirjoita sille voimassa olevat saavutettavuuden WCAG-vaatimukset täyttävä alt-teksti eli kuvan tekstivastine.\n\n"
    . "Noudata alt-tekstin laatimisessa seuraavia ohjeita:\n"
    . "- Kerro kuvan olennaisin sisältö. Tarvittaessa voit kertoa toisella lauseella joitakin yksityiskohtia, jos niillä on merkitystä kuvan sisällön ymmärtämisessä.\n"
    . "- Kuvaile vain käyttäjän ymmärtämisen kannalta merkitykselliset asiat.\n"
    . "- Älä tulkitse tunteita, tarkoitusperiä, henkilöllisyyksiä, sukupuolia äläkä etnistä alkuperää. Pyri mahdollisimman neutraaliin henkilöiden kuvailuun. Älä kuvaile henkilöiden ulkonäköä ja kehoa millään tavalla. Älä esimerkiksi kuvaile pituutta tai olemuksen kokoa.\n"
    . "- Jos kyseessä on ulkona kuvattu maisemakuva, sisällytä mukaan tieto, onko syksy, talvi, kevät vai kesä silloin, kun se on ilmiselvää. Jos vuodenajasta on epävarmuus, älä mainitse mitään vuodenaikaa.\n"
    . "- Älä aloita sanoilla \"Kuvassa näkyy\" tai \"Tässä kuvassa\" tai muulla vastaavalla ilmauksella, joka kertoo, että kyseessä on kuva. Joskus sisällön mukaisesti, jos kyseessä ei ole valokuva, voi kuitenkin olla tarpeen tähdentää, että on kyse maalauksesta, infografiikasta tai piirroskuvituksesta.\n"
    . "- Jos kuva sisältää tekstiä, sisällytä siitä alt-tekstiin vain olennaisimman tiedon ymmärtämisen kannalta olennainen teksti.\n"
    . "- Kirjoita alt-teksti selkeällä ja ymmärrettävällä kielellä.\n"
    . "- Aloita alt-teksti aina isolla alkukirjaimella ja lopeta alt-teksti aina pisteeseen. Voit tehdä alt-tekstin yhdellä tai kahdella lauseella.\n"
    . "- Anna lopputulos yhtenä valmiina alt-tekstinä ilman selityksiä.\n"
    . "- Alt-tekstin pituus on maksimissaan 160 merkkiä.\n\n"
    . "Kirjoita alt-teksti {LANGUAGE}.";

  /**
   * Maps language codes to language names used in the prompt.
   *
   * Unlisted codes fall back to Finnish.
   */
  private const LANGUAGE_NAMES = [
    'fi' => 'suomeksi',
    'en' => 'brittienglanniksi',
  ];

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['generateAltText'];
  }

  /**
   * Finds the relative path to a field inside a form array.
   *
   * @param array $root
   *   The form array (or part of it) to search.
   * @param string $field_name
   *   The field name to look for.
   *
   * @return string[]|null
   *   The relative path as an array of keys, or NULL if not found.
   */
  public static function findFieldPath(array $root, string $field_name): ?array {
    if (isset($root[$field_name]) && is_array($root[$field_name])) {
      return [$field_name];
    }
    foreach ($root as $key => $value) {
      if (is_array($value)) {
        $path = self::findFieldPath($value, $field_name);
        if ($path !== NULL) {
          return array_merge([(string) $key], $path);
        }
      }
    }
    return NULL;
  }

  /**
   * Shows the AI button only when an image is present (#process callback).
   */
  public static function processImageField(array $element, FormStateInterface $form_state, array $form): array {
    if (isset($element['ai_alt_text'])) {
      $element['ai_alt_text']['#access'] = !empty($element['#value']['fids']);
    }
    return $element;
  }

  /**
   * AJAX callback: sends the image to the AI and populates the preview area.
   */
  public static function generateAltText(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $user_input = $form_state->getUserInput();

    // Determine which button was pressed: the edit form or a media
    // library add dialog delta.
    $suffix = 'edit';
    foreach ($user_input as $key => $value) {
      if ($key === 'ai_alt_text_request') {
        $suffix = 'edit';
        break;
      }
      if (is_string($key) && str_starts_with($key, 'ai_alt_text_request_') && !empty($value)) {
        $suffix = substr($key, strlen('ai_alt_text_request_'));
        break;
      }
    }

    if ($suffix === 'edit') {
      /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
      $form_object = $form_state->getFormObject();
      /** @var \Drupal\media\MediaInterface $media */
      $media = $form_object->getEntity();
      $field_path = [self::IMAGE_FIELD, 'widget', 0, 'ai_alt_text'];
      $fids = $user_input[self::IMAGE_FIELD][0]['fids'] ?? '';
      // Follow the language selector on the form: on the add form the entity
      // is new and its language defaults to the site default, so the selector
      // value (or the request language as a fallback) is the source of truth.
      // On the edit form the entity's own language wins.
      $lang = $media->isNew()
        ? ($user_input['langcode'][0]['value'] ?? \Drupal::languageManager()->getCurrentLanguage()->getId())
        : $media->language()->getId();
      $alt_target = self::IMAGE_FIELD . '[0][alt]';
    }
    else {
      $media = NULL;
      $field_path = ['media', $suffix, 'fields', self::IMAGE_FIELD, 'widget', 0, 'ai_alt_text'];
      $fids = $user_input['media'][$suffix]['fields'][self::IMAGE_FIELD][0]['fids'] ?? '';
      // The dialog has no entity of its own; use the request language.
      $lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $alt_target = 'media[' . $suffix . '][fields][' . self::IMAGE_FIELD . '][0][alt]';
    }

    try {
      // Validate the submitted fid before sending anything to the AI.
      $fid = (int) $fids;
      $file = $fid > 0 ? File::load($fid) : NULL;
      if (!$file || !str_starts_with($file->getMimeType(), 'image/')) {
        $response->addCommand(new MessageCommand(
          (string) t('No image is selected. Please upload an image first.'),
          NULL,
          ['type' => 'error'],
        ));
        return $response;
      }

      // On the edit form, make sure the fid matches the media's image field.
      if ($media !== NULL && !$media->isNew() && $media->hasField(self::IMAGE_FIELD)) {
        $items = $media->get(self::IMAGE_FIELD)->getValue();
        $expected = $items[0]['target_id'] ?? NULL;
        if ($expected !== NULL && (int) $expected !== $fid) {
          $response->addCommand(new MessageCommand(
            (string) t('No image is selected. Please upload an image first.'),
            NULL,
            ['type' => 'error'],
          ));
          return $response;
        }
      }

      $config = \Drupal::config('ai_auto_reference.settings');
      $provider_option = $config->get('provider');

      if (!$provider_option) {
        $response->addCommand(new MessageCommand(
          (string) t('AI provider is not configured. Please configure the AI Auto Reference settings first.'),
          NULL,
          ['type' => 'error'],
        ));
        return $response;
      }

      // Resize before encoding to keep the payload (and token count) down.
      // createDerivative() returns the derivative path on success (the
      // docblock says bool), so resolve the path via buildUri() and verify the
      // file exists to handle the failure case.
      $image_path = FALSE;
      $image_style = \Drupal::entityTypeManager()->getStorage('image_style')->load(self::IMAGE_STYLE);
      if ($image_style) {
        $image_path = $image_style->buildUri($file->getFileUri());
        $image_style->createDerivative($file->getFileUri(), $image_path);
      }
      $image_binary = is_file($image_path) ? file_get_contents($image_path) : FALSE;
      if ($image_binary === FALSE) {
        $image_binary = file_get_contents($file->getFileUri());
      }
      if ($image_binary === FALSE) {
        $response->addCommand(new MessageCommand(
          (string) t('Failed to read the image file. Please try again.'),
          NULL,
          ['type' => 'error'],
        ));
        return $response;
      }

      $language_name = self::LANGUAGE_NAMES[$lang] ?? 'suomeksi';
      $prompt = str_replace('{LANGUAGE}', $language_name, self::AI_PROMPT);

      // Call the configured AI provider. Skip moderation: the second
      // moderation call would carry the full base64 image and can fail
      // with "Request too large".
      $ai_provider_manager = \Drupal::service('ai.provider');
      /** @var \Drupal\ai\OperationType\Chat\ChatInterface $ai_provider */
      $ai_provider = $ai_provider_manager->loadProviderFromSimpleOption($provider_option);
      $ai_model = $ai_provider_manager->getModelNameFromSimpleOption($provider_option);

      $image = new ImageFile($image_binary, $file->getMimeType(), $file->getFilename());
      $messages = new ChatInput([new ChatMessage('user', $prompt, [$image])]);
      $alt_text = trim($ai_provider->chat($messages, $ai_model, ['skip_moderation'])->getNormalized()->getText());

      // Hard-enforce the 160-character limit at the last sentence boundary.
      if (mb_strlen($alt_text) > self::MAX_ALT_TEXT_LENGTH) {
        $truncated = mb_substr($alt_text, 0, self::MAX_ALT_TEXT_LENGTH);
        $cut = FALSE;
        foreach (['.', '!', '?'] as $punct) {
          $pos = mb_strrpos($truncated, $punct);
          if ($pos !== FALSE && $pos >= 60 && ($cut === FALSE || $pos > $cut)) {
            $cut = $pos;
          }
        }
        $alt_text = $cut !== FALSE
          ? mb_substr($alt_text, 0, $cut + 1)
          : rtrim($truncated);
      }

      if (empty($alt_text)) {
        $response->addCommand(new MessageCommand(
          (string) t('The AI returned an empty response. Please try again.'),
          NULL,
          ['type' => 'error'],
        ));
        return $response;
      }

      // Resolve the image field element and show the preview.
      // phpcs:ignore DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable
      $field = &$form;
      foreach ($field_path as $key) {
        $field = &$field[$key];
      }
      $field['preview']['#attributes']['style'] = 'display:block';
      $field['preview']['#attributes']['data-alt-target'] = $alt_target;
      // The notice is UI text, so it follows the interface language rather
      // than the content language chosen in the selector.
      $notice = (string) t('Please note – check that the alt text is clear and free from errors before approving it for use, and make any necessary amendments. If the image shows a recognisable location in Tampere, please state this in the text using the correct place names.');
      $field['preview']['content'] = [
        '#markup' => Markup::create(
          '<div class="messages messages--warning">'
          . $notice
          . '</div>'
          . '<p class="ai-alt-text-text">'
          . Xss::filterAdmin($alt_text)
          . '</p>'
          . '<button type="button"'
          . ' class="button button--primary ai-alt-text-accept">'
          . t('Use this alt text')
          . '</button> <button type="button"'
          . ' class="button ai-alt-text-discard">'
          . t('Discard')
          . '</button>',
        ),
      ];

      $wrapper_id = 'ai-alt-text-wrapper-' . $suffix;
      $response->addCommand(new ReplaceCommand('#' . $wrapper_id, $field));
    }
    catch (\Exception $e) {
      \Drupal::logger('tre_ai_alt_text')->error(
        'AI alt text generation failed: @error',
        ['@error' => $e->getMessage()],
      );
      $response->addCommand(new MessageCommand(
        (string) t('Failed to generate AI alt text. Please try again.'),
        NULL,
        ['type' => 'error'],
      ));
    }

    return $response;
  }

}
