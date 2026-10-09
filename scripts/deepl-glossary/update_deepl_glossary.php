<?php

/**
 * Replace the fi→en dictionary in our DeepL glossary.
 *
 * Reads a CSV file with a "Source,Target" header (one Finnish term per row,
 * its English translation in the second column) and replaces ALL entries of
 * the fi→en dictionary in the given DeepL glossary. Entries that are not in
 * the file are removed from DeepL.
 *
 * Uses the vendored deeplcom/deepl-php client (PUT /v3/glossaries/{id}/dictionaries).
 *
 * Usage:
 *   export DEEPL_API_KEY=...   # export first, so the key stays out of shell history
 *   php scripts/deepl-glossary/update_deepl_glossary.php scripts/deepl-glossary/glossary-utf0926.csv [--dry-run]
 *
 *   --dry-run  Validate the CSV and print a summary without calling the API.
 *   --dump     Fetch the current fi->en dictionary from DeepL, save a snapshot
 *              CSV next to this script and print a diff against the given CSV.
 *              Makes no changes.
 *
 * Before replacing the dictionary, the script always fetches the current
 * entries, saves them to a timestamped snapshot CSV (glossary-snapshot-*.csv,
 * git-ignored) and prints a diff against the new file, so nothing is lost
 * silently.
 *
 * The API key is read from the DEEPL_API_KEY environment variable and is
 * never stored in the repo.
 */

use DeepL\DeepLClient;
use DeepL\DeepLException;
use DeepL\MultilingualGlossaryDictionaryEntries;

require __DIR__ . '/../../vendor/autoload.php';

// Glossary configured in config/common/ai_provider_deepl.settings.yml.
// If this ever 404s, the glossary was likely recreated — fetch the new ID
// from the DeepL console and update both this constant and the config.
const GLOSSARY_ID = 'c6d4605f-b4a5-4cdd-8f09-c884e2a9c7d6';
const SOURCE_LANG = 'fi';
const TARGET_LANG = 'en';
// DeepL limit: each source/target text is limited to 1024 UTF-8 bytes.
const MAX_TERM_BYTES = 1024;

$argv = $_SERVER['argv'];
array_shift($argv);
$dry_run = in_array('--dry-run', $argv, TRUE);
$dump = in_array('--dump', $argv, TRUE);
$files = array_values(array_filter($argv, fn($arg) => $arg !== '--dry-run' && $arg !== '--dump'));

if (count($files) !== 1) {
  fwrite(STDERR, "Usage: php update_deepl_glossary.php <glossary.csv> [--dry-run|--dump]\n");
  exit(1);
}

$csv_file = $files[0];
if (!is_readable($csv_file)) {
  fwrite(STDERR, "Cannot read file: $csv_file\n");
  exit(1);
}

$entries = parseGlossaryCsv($csv_file);

echo "Parsed " . count($entries) . " entries from $csv_file\n";
echo "First: " . reset($entries) . "\n";
$last_key = array_key_last($entries);
echo "Last: $last_key -> " . $entries[$last_key] . "\n";

if ($dry_run) {
  echo "Dry run: no changes made to DeepL.\n";
  exit(0);
}

$api_key = getenv('DEEPL_API_KEY');
if ($api_key === FALSE || $api_key === '') {
  fwrite(STDERR, "DEEPL_API_KEY environment variable is not set.\n");
  exit(1);
}

$client = new DeepLClient($api_key);

try {
  // Sanity check: confirm we are talking to the right glossary before replacing.
  $info = $client->getMultilingualGlossary(GLOSSARY_ID);
  echo "Glossary: {$info->name} ({$info->glossaryId})\n";

  // Back up the current dictionary and show what will change, so the
  // overwrite can be reviewed and nothing is lost silently.
  $current = fetchCurrentEntries($client);
  $snapshot_file = writeSnapshot($current);
  echo "Saved current dictionary to $snapshot_file\n";
  printDiff($current, $entries);

  if ($dump) {
    echo "Dump: no changes made to DeepL.\n";
    exit(0);
  }

  // The constructor also validates terms (rejects control characters).
  $dictionary = new MultilingualGlossaryDictionaryEntries(SOURCE_LANG, TARGET_LANG, $entries);
  $result = $client->replaceMultilingualGlossaryDictionary(GLOSSARY_ID, $dictionary);
}
catch (DeepLException $e) {
  fail($e->getMessage());
}

echo "OK: replaced {$result->sourceLang}->{$result->targetLang} dictionary with {$result->entryCount} entries.\n";
if ($result->entryCount !== count($entries)) {
  fwrite(STDERR, "WARNING: DeepL reports {$result->entryCount} entries but the file had " . count($entries) . ".\n");
  exit(1);
}

/**
 * Parses and validates the glossary CSV.
 *
 * @return array<string, string>
 *   Source term => target term.
 */
function parseGlossaryCsv(string $csv_file): array {
  $content = file_get_contents($csv_file);
  if ($content === FALSE) {
    fail("Could not read file contents.");
  }
  if (!mb_check_encoding($content, 'UTF-8')) {
    fail("File is not valid UTF-8.");
  }

  $entries = [];
  $lines = preg_split('/\r\n|\n|\r/', $content);
  foreach ($lines as $i => $line) {
    // Skip the header row and blank lines.
    if ($i === 0 || trim($line) === '') {
      continue;
    }
    $fields = str_getcsv($line, ',', '"', '\\');
    if (count($fields) !== 2) {
      fail("Line " . ($i + 1) . " does not have exactly 2 columns: $line");
    }
    [$source, $target] = $fields;
    if (trim($source) === '' || trim($target) === '') {
      fail("Line " . ($i + 1) . " has an empty source or target.");
    }
    // The client serializes entries as TSV, so terms must not contain tabs.
    if (strpbrk($source . $target, "\t") !== FALSE) {
      fail("Line " . ($i + 1) . " contains a tab character.");
    }
    if (strlen($source) > MAX_TERM_BYTES || strlen($target) > MAX_TERM_BYTES) {
      fail("Line " . ($i + 1) . " exceeds the " . MAX_TERM_BYTES . "-byte term limit.");
    }
    if (array_key_exists($source, $entries)) {
      fail("Duplicate source term on line " . ($i + 1) . ": \"$source\"");
    }
    $entries[$source] = $target;
  }

  if (count($entries) === 0) {
    fail("File contains no entries.");
  }
  return $entries;
}

/**
 * Fetches the current fi->en dictionary entries from DeepL.
 *
 * @return array<string, string>
 *   Source term => target term.
 */
function fetchCurrentEntries(DeepLClient $client): array {
  $dictionaries = $client->getMultilingualGlossaryEntries(GLOSSARY_ID, SOURCE_LANG, TARGET_LANG);
  $entries = [];
  foreach ($dictionaries as $dictionary) {
    foreach ($dictionary->entries as $source => $target) {
      $entries[$source] = $target;
    }
  }
  return $entries;
}

/**
 * Saves the current dictionary to a timestamped CSV next to this script.
 *
 * @return string
 *   The path of the written snapshot file.
 */
function writeSnapshot(array $entries): string {
  $snapshot_file = __DIR__ . '/glossary-snapshot-' . date('Ymd-His') . '.csv';
  $handle = fopen($snapshot_file, 'w');
  if ($handle === FALSE) {
    fail("Could not write snapshot file: $snapshot_file");
  }
  fputcsv($handle, ['Source', 'Target'], ',', '"', '\\');
  foreach ($entries as $source => $target) {
    fputcsv($handle, [$source, $target], ',', '"', '\\');
  }
  fclose($handle);
  return $snapshot_file;
}

/**
 * Prints a diff between the current DeepL dictionary and the new CSV entries.
 */
function printDiff(array $current, array $new): void {
  $added = array_diff_key($new, $current);
  $removed = array_diff_key($current, $new);
  $changed = [];
  foreach (array_intersect_key($new, $current) as $source => $target) {
    if ($current[$source] !== $target) {
      $changed[$source] = [$current[$source], $target];
    }
  }

  echo "Diff (current DeepL -> new CSV):\n";
  echo "  Unchanged: " . (count($new) - count($added) - count($changed)) . "\n";
  echo "  Added:     " . count($added) . "\n";
  echo "  Changed:   " . count($changed) . "\n";
  echo "  Removed:   " . count($removed) . "\n";

  foreach ($added as $source => $target) {
    echo "  + $source -> $target\n";
  }
  foreach ($changed as $source => [$old, $new_target]) {
    echo "  ~ $source: \"$old\" => \"$new_target\"\n";
  }
  foreach ($removed as $source => $target) {
    echo "  - $source -> $target\n";
  }
}

/**
 * Prints a validation error and exits.
 */
function fail(string $message): void {
  fwrite(STDERR, "Validation error: $message\n");
  exit(1);
}
