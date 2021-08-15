<?php

namespace Drupal\ra_article\Commands;

use Drush\Commands\DrushCommands;

/**
 * A drush command file.
 */
class Commands extends DrushCommands {

  /**
   * Drush command that displays the given text.
   *
   * @command delete-all-content
   * @aliases ra_article:delete-all
   */
  public function deleteAll() {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager */
    $entityTypeManager = \Drupal::service('entity_type.manager');
    $this->logger()->notice('Start deleting nodes');
    $query = $entityTypeManager->getStorage('node')->getQuery();
    $allNodes = $query->execute();
    $count = 0;
    foreach ($allNodes as $nid) {
      $node = $entityTypeManager->getStorage('node')->load($nid);
      $node->delete();
      $count++;
      if ($count % 100 === 0) {
        $this->logger()->notice('Deleted now: ' . $count . ' nodes');
      }
    }
    $this->logger()->notice('Deletion nodes finished');
    $this->logger()->notice('------');
    $this->logger()->notice('------');

    $this->logger()->notice('Start deleting taxonomies');
    $query = $entityTypeManager->getStorage('taxonomy_term')->getQuery();
    $allTerms = $query->execute();
    $count = 0;
    foreach ($allTerms as $nid) {
      $term = $entityTypeManager->getStorage('taxonomy_term')->load($nid);
      $term->delete();
      $count++;
      if ($count % 100 === 0) {
        $this->logger()->notice('Deleted now: ' . $count . ' terms');
      }
    }
  }

}
