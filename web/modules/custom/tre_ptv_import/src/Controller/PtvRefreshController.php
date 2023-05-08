<?php

namespace Drupal\tre_ptv_import\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Controller\NodeController;
use Drupal\node\NodeInterface;

/**
 * Controller class for the tre_ptv_import.ptv_refresh_task route.
 */
class PtvRefreshController extends NodeController {

  /**
   * Route callback for the tre_ptv_import.ptv_refresh_task route.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to mark for update.
   *
   * @return array
   *   The render array for the form to use for requesting the immediate update.
   */
  public function refreshNode(NodeInterface $node) {
    $form = $this->formBuilder()->getForm('\Drupal\tre_ptv_import\Form\PtvRefreshForm', $node);
    return $form;
  }

  /**
   * Access callback for the tre_ptv_import.ptv_refresh_task route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check the access for.
   * @param \Drupal\node\NodeInterface $node
   *   The node to check the access for.
   *
   * @return \Drupal\Core\Access\AccessResult|\Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultNeutral
   *   An access result depending on the checks.
   */
  public function checkAccess(AccountInterface $account, NodeInterface $node) {
    $access = _tre_ptv_import_has_refresh_permission($node);
    return AccessResult::allowedIf($access);
  }

}
