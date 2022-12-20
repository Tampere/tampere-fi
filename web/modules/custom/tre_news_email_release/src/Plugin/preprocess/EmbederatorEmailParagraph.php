<?php

namespace Drupal\tre_news_email_release\Plugin\preprocess;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;

/**
 * Embederator embed paragraph preprocessing for news email delivery.
 *
 * @Preprocess(
 *   id = "tre_news_email_release.preprocess.paragraph__embederator_embed__news_media_delivery",
 *   hook = "paragraph__embederator_embed__news_media_delivery"
 * )
 */
class EmbederatorEmailParagraph extends TrePreProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if (!($paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    /** @var \Drupal\paragraphs\ParagraphInterface $translated_embed */
    $translated_embed = $this->entityRepository->getTranslationFromContext($paragraph);
    if ($translated_embed->get('field_embederator_embed')->isEmpty()) {
      return $variables;
    }

    $embeds_in_field = $translated_embed->get('field_embederator_embed')->referencedEntities();
    /** @var \Drupal\embederator\EmbederatorInterface $embederator_entity */
    $embederator_entity = current($embeds_in_field);

    // Inspired by
    // https://www.drupal.org/project/drupal/issues/2699835#comment-12481711.
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $bundle_config_field */
    $bundle_config_field = $embederator_entity->get($embederator_entity->getEntityType()->getKey('bundle'));
    /** @var \Drupal\embederator\Entity\EmbederatorType $bundle_config */
    $bundle_config = current($bundle_config_field->referencedEntities());
    $embederator_markup = $bundle_config->getMarkupHtml();

    $iframe_src = '';
    $html = $this->getNonRootHtml($embederator_markup);
    $document = new \DOMDocument('1.0', 'UTF-8');
    $document->loadHTML($html);
    $xpath = new \DOMXPath($document);

    // There is actually only one iframe in the HTML, but DOMXPath::query()
    // returns a DOMNodeList which must be iterated over.
    foreach ($xpath->query('//iframe/@src') as $node) {
      $iframe_src = $node->nodeValue;
    }

    $variables['embederator_embed_url'] = $this->token->replace($iframe_src, ['embederator' => $embederator_entity]);
    return $variables;
  }

  /**
   * Builds an full html string based on a partial.
   *
   * @param string $partial
   *   A subset of a full html string. For instance the contents of the body
   *   element.
   *
   * @return string
   *   The full HTML document.
   *
   * @see \Drupal\migrate_plus\Plugin\migrate\process\Dom::getNonRootHtml()
   */
  protected function getNonRootHtml(string $partial) {
    $replacements = [
      "\n" => '',
      '!encoding' => 'utf-8',
      '!value' => $partial,
    ];
    // Prepend the html with a header using the configured source encoding.
    // By default, loadHTML() assumes ISO-8859-1.
    $html_template = <<<EOD
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><meta http-equiv="Content-Type" content="text/html; charset=!encoding" /></head>
<body>!value</body>
</html>
EOD;
    return strtr($html_template, $replacements);
  }

}
