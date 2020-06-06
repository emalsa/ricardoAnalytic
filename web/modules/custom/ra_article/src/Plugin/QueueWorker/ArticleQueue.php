<?php

namespace Drupal\ra_article\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\ra_article\ArticleCrawlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the article_queue queueworker.
 *
 * @QueueWorker (
 *   id = "article_queue",
 *   title = @Translation("Article"),
 *   cron = {"time" = 55}
 * )
 */
class ArticleQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\ra_article\ArticleCrawlerInterface
   */
  protected $articleCrawler;

  /**
   * ArticleQueue constructor.
   *
   * @param  array  $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param  \Drupal\ra_article\ArticleCrawlerInterface  $articleCrawler
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ArticleCrawlerInterface $articleCrawler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->articleCrawler = $articleCrawler;
  }

  /**
   * @param  \Symfony\Component\DependencyInjection\ContainerInterface  $container
   * @param  array  $configuration
   * @param  string  $plugin_id
   * @param  mixed  $plugin_definition
   *
   * @return \Drupal\ra_article\Plugin\QueueWorker\ArticleQueue|static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ra_article.article_crawler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->articleCrawler->processArticle($data['article_id']);
  }

}
