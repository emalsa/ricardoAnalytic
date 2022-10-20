<?php

namespace Drupal\ra_article;

use Drupal\node\NodeInterface;

/**
 * Interface ArticleDetailFetchServiceInterface.
 */
interface ArticleSaleServiceInterface {

  public function createSaleNode(NodeInterface $article, $initialQuantity, $remainingQuantity, $price);

}
