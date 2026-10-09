<?php

declare(strict_types=1);

namespace Drupal\tre_group_module_custom\Hook;

use Drupal\Core\Hook\Attribute\RemoveHook;
use Drupal\group_content_menu\Hook\NodeFormAlter;

#[RemoveHook(
  'form_node_form_alter',
  class: NodeFormAlter::class,
  method: 'alter',
)]
final class Hooks {

}