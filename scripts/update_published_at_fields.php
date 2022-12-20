<?php
echo "Start\n";
$langcode = 'en';

$query = \Drupal::entityQuery('node')
         ->condition('type', ['news_item', 'blog_article', 'notice', 'rich_article', 'small_news_item'], 'IN')
         ->execute();

$date = date('m_d_Y_h_i_s');
$file_path = "/tmp/publish_date_update_log_" . $date . ".txt";
$myfile = fopen($file_path, "w") or die("Unable to open file!");

foreach ($query as $nid) {
    echo "updating nid: " . strval($nid);
    $txt = "nid: " . strval($nid) . "  ";
    $node = \Drupal\node\Entity\Node::load($nid);
   
    if ($node->field_date_of_publication->value){
        $node->published_at = $node->field_date_of_publication->date->getTimestamp();
        $txt .= "old_timestamp: " . strval($node->field_date_of_publication->date->getTimestamp()) . "  ";
        $txt .= "new_timestamp: " . strval($node->published_at->value) . "  ";
        
        foreach ($node->getTranslationLanguages() as $translation){
            $node->getTranslation($translation->getId())->published_at = $node->field_date_of_publication->date->getTimestamp();
            $txt .= "updated the translation for " . $translation->getId() . " ";
        }
        $node->save();

    } else {
        $txt .= "old_timestamp: " . "no date!" . "  ";
        $txt .= "new_timestamp: " . "no date!" . "  ";
    }
    $txt .= "\n";
    echo "\n";
    fwrite($myfile, $txt);
}
fclose($myfile);
echo "Log is available at (if you are using ddev, look inside ddev tmp folder): \n" . $file_path;
