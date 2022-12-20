<?php

namespace Drupal\tre_content_migration\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\TermInterface;

/**
 * Process plugin for generating paragraphs of type contact_information_liftups.
 *
 * The contact_information_liftups paragraphs are created with person_liftup
 * paragraphs attached.
 *
 * It may be worth noting that running migration rollback will leave the
 * paragraphs behind, but they can be removed using the
 * admin/config/system/delete-orphans tool.
 *
 * @MigrateProcessPlugin(
 *   id = "contact_liftup_paragraph_item",
 *   handle_multiples = TRUE
 * )
 */
class ContactLiftupParagraphItem extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $person_liftup_paragraphs = [];

    // Create paragraph items for external links.
    if ($value instanceof \SimpleXMLElement && $value->children()) {
      foreach ($value as $item) {
        $person_liftup_paragraphs[] = $this->createParagraphItem($item);
      }
    }

    // Create paragraph item for contact_information_liftups.
    if (!empty(array_filter($person_liftup_paragraphs))) {
      $paragraphs = Paragraph::create([
        'type' => 'contact_information_liftups',
        'field_contact_information_liftup' => $person_liftup_paragraphs,
      ]);

      if ($paragraphs->save()) {
        return [
          'target_id' => $paragraphs->id(),
          'target_revision_id' => $paragraphs->getRevisionId(),
        ];
      }
    }

    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function multiple() : bool {
    return TRUE;
  }

  /**
   * Generates a person_liftup paragraph entity.
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
    $person_name = (string) $value->xpath('name')[0];
    $person_title = (string) $value->xpath('title')[0];

    // We need atleast name to proceed.
    if (empty($person_name)) {
      return [];
    }

    // Prepare displayed_fields for person_liftup paragraph.
    $displayed_fields[] = 'name';
    $person_query_properties = [
      'title' => $person_name,
      'status' => 1,
    ];

    $personnel_titles_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['name' => $person_title]);
    $personnel_titles_term = reset($personnel_titles_terms);

    // Add field_hr_title as person query param, if the term is found.
    if ($personnel_titles_term instanceof TermInterface) {
      $person_query_properties['field_hr_title'] = $personnel_titles_term->id();
      // Prepare displayed_fields for person_liftup paragraph.
      $displayed_fields[] = 'title';
    }

    // Find person node by person name.
    $person_nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties($person_query_properties);
    $person_node = reset($person_nodes);

    if ($person_node instanceof NodeInterface) {
      $person_node_nid = $person_node->id();

      $paragraph = Paragraph::create([
        'type' => 'person_liftup',
        'field_person_liftup' => $person_node_nid,
        'field_pl_displayed_fields' => $displayed_fields,
      ]);

      if ($paragraph->save()) {
        return [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ];
      }
    }

    return [];
  }

}
