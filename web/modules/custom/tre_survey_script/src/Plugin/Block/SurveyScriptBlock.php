<?php

namespace Drupal\tre_survey_script\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a 'SurveryScriptBlock' to enable search in page header.
 */
#[Block(
  id: "survey_script_block",
  admin_label: new TranslatableMarkup("Survey script block"),
)]
class SurveyScriptBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#markup' => '',
    ];
  }

}
