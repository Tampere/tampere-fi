<?php

namespace Drupal\tre_content_migration\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Process plugin for generating paragraphs of type link_list.
 *
 * The link_list paragraphs are created with external_link_with_link_text
 * paragraphs attached.
 *
 * It may be worth noting that running migration rollback will leave the
 * paragraphs behind, but they can be removed using the
 * admin/config/system/delete-orphans tool.
 *
 * @MigrateProcessPlugin(
 *   id = "link_list_paragraph_item",
 *   handle_multiples = TRUE
 * )
 */
class LinkListParagraphItem extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $paragraphs = [];

    if ($value instanceof \SimpleXMLElement && $value->children()) {
      foreach ($value as $item) {
        $paragraphs[] = $this->createParagraphItem($item);
      }
    }

    return array_filter($paragraphs);
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() : bool {
    return TRUE;
  }

  /**
   * Generates a link_list paragraph entity.
   *
   * @param \SimpleXMLElement $value
   *   An element containing a <url> element and a <link> element.
   *
   * @return array
   *   The values needed for attaching the paragraph in the host entity.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function createParagraphItem(\SimpleXMLElement $value) : array {
    $link_url = (string) $value->xpath('url')[0];
    $link_text = (string) $value->xpath('text')[0];

    if (empty($link_text) || filter_var($link_url, FILTER_VALIDATE_URL) === FALSE) {
      return [];
    }

    $external_link_paragraph = Paragraph::create([
      'type' => 'external_link_with_link_text',
      'field_external_link_w_link_text' => [
        'uri' => $link_url,
        'title' => $link_text,
      ],
    ]);
    $external_link_paragraph->save();

    $paragraph = Paragraph::create([
      'type' => 'link_list',
      'field_links' => [
        'target_id' => $external_link_paragraph->id(),
        'target_revision_id' => $external_link_paragraph->getRevisionId(),
      ],
    ]);

    $paragraph->save();

    return [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
  }

}
