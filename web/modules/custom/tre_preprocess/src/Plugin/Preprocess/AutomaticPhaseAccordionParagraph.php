<?php

namespace Drupal\tre_preprocess\Plugin\Preprocess;

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\tre_preprocess\TrePreProcessPluginBase;
use Drupal\tre_preprocess_utility_functions\Utils\HelperFunctionsInterface;

/**
 * Automatic phase accordion paragraph preprocessing.
 *
 * @Preprocess(
 *   id = "tre_preprocess.preprocess.paragraph__automatic_phase_accordion",
 *   hook = "paragraph__automatic_phase_accordion"
 * )
 */
class AutomaticPhaseAccordionParagraph extends TrePreProcessPluginBase {

  public const FIXED_PHASE_FIELDS = [
    'field_city_strategy' => [
      'labels' => [
        'fi' => 'Kaupunkistrategia',
        'en' => 'City Strategy',
      ],
      'corresponding_phase' => 'city_strategy',
      'category' => 'incomplete',
    ],
    'field_work_programme' => [
      'labels' => [
        'fi' => 'Työohjelma',
        'en' => 'Work programme',
      ],
      'corresponding_phase' => 'work_programme',
      'category' => 'incomplete',
    ],
    'field_city_strategy_work_program' => [
      'labels' => [
        'fi' => 'Kaupunkistrategia ja työohjelma',
        'en' => 'City Strategy and work programme',
      ],
      'corresponding_phase' => 'city_strategy_and_work_programme',
      'category' => 'incomplete',
    ],
    'field_start_phase' => [
      'labels' => [
        'fi' => 'Aloitusvaihe',
        'en' => 'Initial phase',
      ],
      'corresponding_phase' => 'start_phase',
      'category' => 'incomplete',
    ],
    'field_preparation_phase' => [
      'labels' => [
        'fi' => 'Valmisteluvaihe',
        'en' => 'Preparation phase',
      ],
      'corresponding_phase' => 'ready_phase',
      'category' => 'incomplete',
    ],
    'field_start_preparation_phase' => [
      'labels' => [
        'fi' => 'Aloitus- ja valmisteluvaihe',
        'en' => 'Initial and preparation phase',
      ],
      'corresponding_phase' => 'start_and_end_phase',
      'category' => 'incomplete',
    ],
    'field_proposal_phase' => [
      'labels' => [
        'fi' => 'Ehdotusvaihe',
        'en' => 'Proposal phase',
      ],
      'corresponding_phase' => 'proposal_phase',
      'category' => 'incomplete',
    ],
    'field_revised_proposal' => [
      'labels' => [
        'fi' => 'Tarkistettu ehdotus',
        'en' => 'Validated proposal',
      ],
      'corresponding_phase' => 'revised_proposal',
      'category' => 'incomplete',
    ],
    'field_acceptance_phase' => [
      'labels' => [
        'fi' => 'Hyväksyminen',
        'en' => 'Approval',
      ],
      'corresponding_phase' => 'acceptance',
      'category' => 'incomplete',
    ],
    'field_partially_binding_phase' => [
      'labels' => [
        'fi' => 'Osittain lainvoimainen',
        'en' => 'Partially legally binding',
      ],
      'corresponding_phase' => 'partially_binding',
      'category' => 'incomplete',
    ],
    'field_legal_phase' => [
      'labels' => [
        'fi' => 'Lainvoimainen',
        'en' => 'Legally binding',
      ],
      'corresponding_phase' => 'legal',
      'category' => 'in_effect',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function preprocess(array $variables): array {
    $paragraph = $variables['paragraph'];

    if (!($paragraph instanceof ParagraphInterface)) {
      return $variables;
    }

    /** @var \Drupal\paragraphs\ParagraphInterface $translated_phase_accordion */
    $translated_phase_accordion = $this->entityRepository->getTranslationFromContext($paragraph);

    $parent_node = $translated_phase_accordion->getParentEntity();

    if (!($parent_node instanceof NodeInterface)) {
      return $variables;
    }

    $node_storage = $this->entityTypeManager->getStorage('node');
    $latest_parent_revision = $node_storage->getLatestRevisionId($parent_node->id());
    $latest_parent_node = $node_storage->loadRevision($latest_parent_revision);

    $current_user = \Drupal::currentUser();

    // Check if user has access to the latest version.
    if ($latest_parent_node->access('view', $current_user)) {
      // Load the latest revision of zoning information page, so that we get
      // the updated attachment list for the accordion even in draft mode.
      /** @var \Drupal\node\NodeInterface $translated_parent_node */
      $translated_parent_node = $this->entityRepository->getTranslationFromContext($latest_parent_node);
    }
    else {
      // Load the latest published version if user doesn't have access rights.
      /** @var \Drupal\node\NodeInterface $translated_published_parent_node */
      $translated_parent_node = $this->entityRepository->getTranslationFromContext($parent_node);
    }

    $this->renderer->addCacheableDependency($variables, $translated_parent_node);

    $paragraph_list = [];
    $current_langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Loop through the 'fixed' phase fields of in the parent node.
    foreach (self::FIXED_PHASE_FIELDS as $phase_field => $phase_data) {
      $phase_names = $phase_data['labels'];
      $default_phase_name = $phase_names[$current_langcode];

      // In case the automatic_phase_paragraph is in a node which doesn't have
      // (all) the fixed field(s).
      if (!$translated_parent_node->hasField($phase_field)) {
        continue;
      }

      $phase_paragraphs = $translated_parent_node->get($phase_field)->referencedEntities();
      foreach ($phase_paragraphs as $phase_paragraph) {

        if (!($phase_paragraph instanceof ParagraphInterface)) {
          continue;
        }
        /** @var \Drupal\paragraphs\ParagraphInterface $translated_phase_paragraph */
        $translated_phase_paragraph = $this->entityRepository->getTranslationFromContext($phase_paragraph);

        if ($this->helperFunctions->getFieldValueString($translated_phase_paragraph, 'field_phase_display') !== HelperFunctionsInterface::BOOLEAN_FIELD_TRUE) {
          continue;
        }

        if ($translated_phase_paragraph->get('field_phase')->isEmpty()) {
          $translated_phase_paragraph->get('field_accordion_heading')->setValue($default_phase_name);
        }
        else {
          $translated_phase_paragraph->get('field_accordion_heading')->setValue($translated_phase_paragraph->get('field_phase')->getString());
        }

        $paragraph_list[$phase_data['corresponding_phase']] = $translated_phase_paragraph;
      }
    }

    $current_phase_passed = FALSE;
    $current_node_phase = $translated_parent_node->get('field_phase')->getString();
    foreach ($paragraph_list as $paragraph_phase => $phase_paragraph) {
      if (!$current_phase_passed && $paragraph_phase !== $current_node_phase) {
        $phase_status_value = 'ready';
      }
      elseif ($paragraph_phase === $current_node_phase) {
        $phase_status_value = 'current';
        $current_phase_passed = TRUE;
      }
      else {
        $phase_status_value = 'future';
      }

      $paragraph_list[$paragraph_phase]->get('field_process_phase_status')->setValue($phase_status_value);
    }

    $paragraph->get('field_process_accordions')->setValue(array_values($paragraph_list));

    // Since the field has been populated just now, the display must be built
    // anew.
    $variables['content']['field_process_accordions'] = $paragraph->get('field_process_accordions')->view();

    return $variables;
  }

}
