<?php

namespace Drupal\tre_ai_auto_reference\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Decorates ai_auto_reference.ai_references_generator to inject Solr context.
 */
class TreAiReferencesGenerator {

  /**
   * The decorated inner service.
   */
  protected object $innerService;

  /**
   * The similar content searcher.
   */
  protected SimilarContentSearcher $similarSearcher;

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(object $inner_service, SimilarContentSearcher $similar_searcher, EntityTypeManagerInterface $entity_type_manager) {
    $this->innerService = $inner_service;
    $this->similarSearcher = $similar_searcher;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function __call($method, $args) {
    return call_user_func_array([$this->innerService, $method], $args);
  }

  /**
   * Intercepts the AI suggestion request to inject Solr context into prompt.
   */
  public function getAiSuggestions(NodeInterface $node, string $field_name, string $view_mode, string $prompt) {

    $examples = $this->similarSearcher->getSimilarContentContext($node, $field_name, 3);
    $storage = $this->entityTypeManager->getStorage('ai_prompt');
    $prompt_entity = $storage->load($prompt);
    $original_text = '';

    if ($prompt_entity) {
      $original_text = $prompt_entity->getPrompt();
      $replacement = !empty($examples) ? $examples : '';

      if (str_contains($original_text, '{RECENT_EXAMPLES}')) {
        $new_prompt = str_replace('{RECENT_EXAMPLES}', $replacement, $original_text);
        $prompt_entity->set('prompt', $new_prompt);
      }
    }

    // Call the original decorated service.
    $result = $this->innerService->getAiSuggestions($node, $field_name, $view_mode, $prompt);

    // Restore the original prompt entity in memory to prevent accidental saves.
    if ($prompt_entity && !empty($original_text)) {
      $prompt_entity->set('prompt', $original_text);
    }

    return $result;
  }

}
