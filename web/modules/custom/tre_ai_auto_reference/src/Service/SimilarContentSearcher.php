<?php

namespace Drupal\tre_ai_auto_reference\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\ParseMode\ParseModePluginManager;
use Psr\Log\LoggerInterface;

/**
 * Service to find historically similar content for AI context.
 */
class SimilarContentSearcher {

    protected EntityTypeManagerInterface $entityTypeManager;
    protected LoggerInterface $logger;
    protected ParseModePluginManager $parseModeManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        LoggerInterface $logger,
        ParseModePluginManager $parseModeManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->logger = $logger;
        $this->parseModeManager = $parseModeManager;
    }

    /**
     * Retrieves a formatted string of similar content context from Solr.
     */
    public function getSimilarContentContext(NodeInterface $node, string $field_name, int $limit = 3): string {
        $search_text = $node->getTitle();
        $additional_text = '';

        // Dynamically scan the node for any text-based fields to use as search context.
        foreach ($node->getFieldDefinitions() as $name => $definition) {
            $type = $definition->getType();

            // Look for standard Drupal text field types.
            if (in_array($type, ['text', 'text_long', 'text_with_summary', 'string', 'string_long'])) {
                if ($name !== 'title' && $node->hasField($name) && !$node->get($name)->isEmpty()) {
                    $value = $node->get($name)->value;
                    if (!empty($value)) {
                        $additional_text .= ' ' . strip_tags((string) $value);
                    }
                }
            }

            // Stop once we have gathered enough text for a healthy Solr query (~400 chars).
            if (mb_strlen($additional_text) > 400) {
                break;
            }
        }

        // Combine Title and the extracted text.
        $search_text .= ' ' . mb_substr($additional_text, 0, 400);
        $search_text = trim($search_text);

        if (empty($search_text)) {
            return '';
        }

        try {
            $index = $this->entityTypeManager->getStorage('search_api_index')->load('content');

            if (!$index || !$index->status()) {
                $this->logger->warning('Search API index "content" not found or disabled.');
                return '';
            }

            $query = $index->query();

            // Force an OR search for optimal text relevance matching.
            $parse_mode = $this->parseModeManager->createInstance('terms');
            $parse_mode->setConjunction('OR');
            $query->setParseMode($parse_mode);

            $query->keys($search_text);
            $query->range(0, $limit);
            $query->addCondition('type', $node->bundle());
            $query->addCondition('status', 1);

            if (!$node->isNew()) {
                $query->addCondition('nid', $node->id(), '<>');
            }

            $results = $query->execute();
            $result_items = $results->getResultItems();

            if (empty($result_items)) {
                $this->logger->debug('AI Context (@field): Solr returned 0 matching results for Node @nid.', [
                    '@field' => $field_name,
                    '@nid' => $node->id(),
                ]);
                return '';
            }

            $nids = [];
            foreach ($result_items as $item) {
                preg_match('/node\/(\d+)/', $item->getId(), $matches);
                if (!empty($matches[1])) {
                    $nids[] = $matches[1];
                }
            }

            $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);
            $examples = $this->formatExamples($nodes, $field_name);

            // Log the final context string.
            if (!empty($examples)) {
                $this->logger->debug('AI Context (@field): Found @count historical matches for Node @nid. <br>Context injected:<pre>@examples</pre>', [
                    '@field' => $field_name,
                    '@count' => count($nodes),
                    '@nid' => $node->id(),
                    '@examples' => $examples,
                ]);
            } else {
                $this->logger->debug('AI Context (@field): Found historical matches for Node @nid, but none contained tags for this field.', [
                    '@field' => $field_name,
                    '@nid' => $node->id(),
                ]);
            }

            return $examples;
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch similar content for AI context: @message', ['@message' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Formats historical nodes into a readable prompt string.
     */
    private function formatExamples(array $nodes, string $field_name): string {
        $formatted = [];

        foreach ($nodes as $node) {
            if ($node->hasField($field_name) && !$node->get($field_name)->isEmpty()) {
                $tags = [];
                foreach ($node->get($field_name)->referencedEntities() as $term) {
                    $tags[] = $term->getName();
                }
                if (!empty($tags)) {
                    $formatted[] = '- Title: "' . $node->getTitle() . '" | Tags assigned: ' . implode(', ', $tags);
                }
            }
        }

        if (empty($formatted)) {
            return '';
        }

        return "CONTEXT: Here is how we categorized similar content in the past for this specific category:\n" . implode("\n", $formatted) . "\n\n";
    }
}
