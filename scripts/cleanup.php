<?php


use Drupal\node\Entity\Node;

$article_nids = \Drupal::entityQuery('node')
  ->condition('type', 'article')
  ->execute();

foreach ($article_nids as $article_nid) {
  $article = Node::load($article_nid);
  $article->delete();
}

$rating_nids = \Drupal::entityQuery('node')
  ->condition('type', 'rating')
  ->execute();


foreach ($rating_nids as $rating_nid) {
  $rating = Node::load($rating_nid);
  $rating->delete();
}
