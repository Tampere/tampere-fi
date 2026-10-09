<?php

namespace Drupal\tre_ai_page_summary;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * AJAX callbacks for the TRE AI Page Summary feature.
 */
class AiPageSummaryCallbacks implements TrustedCallbackInterface {

  /**
   * Maximum summary length in characters (including spaces).
   */
  private const MAX_SUMMARY_LENGTH = 450;

  /**
   * Prompt template.
   *
   * {CONTENTS} is replaced with the rendered page text.
   * {LANGUAGE} is replaced with the target response language.
   */
  private const AI_PROMPT = "Sivun sisältö:\n{CONTENTS}\n\n"
    . "Tekisitkö muokkauksessa olevan sivun sisällöstä tiivistelmän. "
    . "Tiivistelmän pitää olla kertovaa tekstiä, joka on pituudeltaan "
    . "maksimissaan 450 merkkiä välilyönteineen. Älä käytä listauksia, "
    . "vaan kerro sivun pääasiat yhdessä tai kahdessa tekstikappaleessa. "
    . "Kirjoita tiivistelmä {LANGUAGE}.\n\n"
    . "Älä aloita tekstiä johdantomaisesti ilmauksella \"Sivu käsittelee\","
    . " \"Sivu kertoo\", \"Sivulla käsitellään\", \"Sivulla kerrotaan\","
    . " \"Sivu esittelee\", \"Sivu nimeää\" äläkä muullakaan vastaavalla"
    . " ilmauksella. Älä viittaa tiivistelmätekstissä ollenkaan"
    . " verkkosivuun, jonka sisällön tiivistelmä tiivistää, vaan aloita"
    . " tiivistelmä suoraan asialla äläkä myöhemmässäkään tekstissä"
    . " mainitse lainkaan sanaa \"sivu\" tai \"sivulla\" viittaamassa"
    . " tiivistelmän käsittelemään verkkosivuun. Älä toivota tekstissä"
    . " käyttäjää tervetulleeksi sivulle äläkä tervetulleeksi minnekään"
    . " muuallekaan. Tekstin pitää olla tiivistelmä eikä johdantomainen"
    . " toimintakehote.\n\n"
    . "Käytä mahdollisimman selkeää ja ymmärrettävää kieltä ja sanastoa: "
    . "Tee lyhyitä lauseita, joissa käytät aktiivisia verbejä. Vältä "
    . "liiallista passiivimuodon käyttöä. Käytä mieluummin sivulauseita "
    . "kuin lauseenvastikkeita. Vältä seuraavien sanojen käyttöä:\n\n"
    . "johtuen, koskien, liittyen, riippuen\n"
    . "lähtökohtaisesti, pääsääntöisesti\n"
    . "puitteissa, kohdalla, parissa, yhteydessä\n"
    . "seurauksena\nsuhteen\nsuorittaa, toteuttaa\n"
    . "taholta, toimesta\ntoimenpide\nmikäli";

  /**
   * Maps language codes to language names used in the prompt.
   *
   * Unlisted codes fall back to Finnish.
   */
  private const LANGUAGE_NAMES = [
    'fi' => 'suomeksi',
    'en' => 'englanniksi',
  ];

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['generateSummary'];
  }

  /**
   * AJAX callback: renders the node, calls AI, and populates the preview area.
   */
  public static function generateSummary(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    try {
      $node = $form_state->getFormObject()->getEntity();

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

      // Render the node in the default (front-end) theme,
      // same pattern as AiReferenceGenerator.
      $theme_manager = \Drupal::service('theme.manager');
      $theme_initialization = \Drupal::service('theme.initialization');
      $active_theme = $theme_manager->getActiveTheme();
      $default_theme = \Drupal::configFactory()->get('system.theme')->get('default');
      $theme_manager->setActiveTheme($theme_initialization->initTheme($default_theme));

      $render_output = \Drupal::entityTypeManager()
        ->getViewBuilder('node')
        ->view($node, 'full');
      $rendered_html = (string) \Drupal::service('renderer')->renderInIsolation($render_output);

      $theme_manager->setActiveTheme($active_theme);

      $converter = new HtmlConverter();
      $contents = $converter->convert($rendered_html);
      $contents = trim(preg_replace('/\s+/', ' ', $contents));

      $lang = $node->language()->getId();
      $language_name = self::LANGUAGE_NAMES[$lang] ?? 'Finnish';
      $prompt = str_replace(
        ['{CONTENTS}', '{LANGUAGE}'],
        [$contents, $language_name],
        self::AI_PROMPT,
      );

      // Call the configured AI provider.
      $ai_provider_manager = \Drupal::service('ai.provider');
      $ai_provider = $ai_provider_manager->loadProviderFromSimpleOption($provider_option);
      $ai_model = $ai_provider_manager->getModelNameFromSimpleOption($provider_option);

      $messages = new ChatInput([new ChatMessage('user', $prompt)]);
      $summary = trim($ai_provider->chat($messages, $ai_model)->getNormalized()->getText());

      // Hard-enforce the 450-character limit at the last sentence boundary.
      if (mb_strlen($summary) > self::MAX_SUMMARY_LENGTH) {
        $truncated = mb_substr($summary, 0, self::MAX_SUMMARY_LENGTH);
        $cut = FALSE;
        foreach (['.', '!', '?'] as $punct) {
          $pos = mb_strrpos($truncated, $punct);
          if ($pos !== FALSE && $pos >= 100 && ($cut === FALSE || $pos > $cut)) {
            $cut = $pos;
          }
        }
        $summary = $cut !== FALSE
          ? mb_substr($summary, 0, $cut + 1)
          : rtrim($truncated);
      }

      if (empty($summary)) {
        $response->addCommand(new MessageCommand(
          (string) t('The AI returned an empty response. Please try again.'),
          NULL,
          ['type' => 'error'],
        ));
        return $response;
      }

      // Show the preview. Unset #group so the field renders correctly inline.
      unset($form['field_page_summary']['#group']);
      $form['field_page_summary']['ai_summary_preview']['#attributes']['style'] = 'display:block';
      $notice = (string) t(
        // phpcs:ignore Generic.Files.LineLength.TooLong
        'Please note – check that the summary text is clear and accurate before approving it for use.',
        [],
        ['langcode' => $lang],
      );
      $form['field_page_summary']['ai_summary_preview']['content'] = [
        '#markup' => Markup::create(
          '<div class="messages messages--warning">'
          . $notice
          . '</div>'
          . '<p class="ai-page-summary-text">'
          . Xss::filterAdmin($summary)
          . '</p>'
          . '<button type="button"'
          . ' class="button button--primary ai-page-summary-accept">'
          . t('Use this summary')
          . '</button> <button type="button"'
          . ' class="button ai-page-summary-discard">'
          . t('Discard')
          . '</button>',
        ),
      ];

      $response->addCommand(new ReplaceCommand('#ai-page-summary-wrapper', $form['field_page_summary']));
    }
    catch (\Exception $e) {
      \Drupal::logger('tre_ai_page_summary')->error(
        'AI summary generation failed for node @nid: @error',
        ['@nid' => $form_state->getFormObject()->getEntity()->id(), '@error' => $e->getMessage()],
      );
      $response->addCommand(new MessageCommand(
        (string) t('Failed to generate AI summary. Please try again.'),
        NULL,
        ['type' => 'error'],
      ));
    }

    return $response;
  }

}
