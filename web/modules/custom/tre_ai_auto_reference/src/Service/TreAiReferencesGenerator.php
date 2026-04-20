<?php

namespace Drupal\tre_ai_auto_reference\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

class TreAiReferencesGenerator {

    protected object $innerService;
    protected SimilarContentSearcher $similarSearcher;
    protected EntityTypeManagerInterface $entityTypeManager;

    public function __construct(object $inner_service, SimilarContentSearcher $similar_searcher, EntityTypeManagerInterface $entity_type_manager) {
        $this->innerService = $inner_service;
        $this->similarSearcher = $similar_searcher;
        $this->entityTypeManager = $entity_type_manager;
    }

    public function __call($method, $args) {
        return call_user_func_array([$this->innerService, $method], $args);
    }

    /**
     * Intercepts the AI suggestion request to inject Solr context into the prompt.
     */
    public function getAiSuggestions(NodeInterface $node, string $field_name, string $view_mode, string $prompt) {

        $examples = $this->similarSearcher->getSimilarContentContext($node, $field_name, 3);
        $storage = $this->entityTypeManager->getStorage('ai_auto_reference_prompt');
        $prompt_entity = $storage->load($prompt);
        $original_text = '';

        if ($prompt_entity) {
            $original_text = $prompt_entity->getPrompt();
            $replacement = !empty($examples) ? $examples : '';

            if (str_contains($original_text, '{RECENT_EXAMPLES}')) {
                $prompt_entity->set('prompt', str_replace('{RECENT_EXAMPLES}', $replacement, $original_text));
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
