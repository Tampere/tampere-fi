<?php

namespace Drupal\tre_content_migration\Commands;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Migrate\MigrateExecutable;
use Drush\Drupal\Migrate\MigrateMessage;

class TreContentMigrationCommands extends DrushCommands {

  /**
   * The entity storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $nodeStorage;

  /**
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected MigrationPluginManagerInterface $migrationPluginManager;

  /**
   * Constructs the commands object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MigrationPluginManagerInterface $migration_plugin_manager) {
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->migrationPluginManager = $migration_plugin_manager;
  }

  /**
   * Runs migration for news items from given glob of files.
   *
   * @param string $file_glob
   *   The fileglob for files to include as sources for the migration.
   *
   * @usage tre_content_migration:import-news '/opt/migrate_sources/issues-*.xml'
   *   Given files /opt/migrate_sources/issues-1.xml and
   *   /opt/migrate_sources/issues-2.xml, sets the news_items migration sources
   *   to list these files in 'sorted' order and runs the migration using these
   *   sources.
   *
   * @command tre_content_migration:import-news
   * @aliases import_news_xml
   */
  public function migrateNewsFromFiles(string $file_glob) {
    $file_list = glob($file_glob, GLOB_NOSORT | GLOB_ERR);
    sort($file_list);

    $this->importHandler($file_list, 'news_items');
  }

  /**
   * Runs migration for blog articles from given glob of files.
   *
   * @param string $file_glob
   *   The fileglob for files to include as sources for the migration.
   * @param string $blog_name
   *   The name for the blog the articles should be imported to.
   *
   * @usage tre_content_migration:import-blogs '/opt/migrate_sources/blogit-1.xml' 'Aikuisten oikeesti'
   *   Given files /opt/migrate_sources/blogit-1.xml and
   *   /opt/migrate_sources/blogit-2.xml, sets blog article migration sources
   *   to list the file /opt/migrate_sources/blogit-1.xml in 'sorted' order and
   *   sets the blog for this articles to 'Aikuisten oikeesti', then runs the
   *   migration using these sources.
   *
   * @command tre_content_migration:import-blogs
   * @aliases import_blogs_xml
   */
  public function migrateBlogsFromFiles(string $file_glob, string $blog_name) {
    $file_list = glob($file_glob, GLOB_NOSORT | GLOB_ERR);
    sort($file_list);

    $this->importHandler($file_list, 'blog_articles', $blog_name);
  }

  /**
   * Runs migration rollback for news items from given glob of files.
   *
   * @param string $file_glob
   *   The fileglob for files to include as sources for the migration.
   *
   * @usage tre_content_migration:rollback-news '/opt/migrate_sources/issues-*.xml'
   *   Given files /opt/migrate_sources/issues-1.xml and
   *   /opt/migrate_sources/issues-2.xml, sets the news_items migration sources
   *   to list these files in 'sorted' order and runs the migration rollback
   *   using these sources.
   *
   * @command tre_content_migration:rollback-news
   * @aliases rollback_news_xml
   */
  public function rollbackNewsFromFiles(string $file_glob) {
    $file_list = glob($file_glob, GLOB_NOSORT | GLOB_ERR);
    sort($file_list);

    $this->rollbackHandler($file_list, 'news_items');
  }

  /**
   * Runs migration rollback for blog articles from given glob of files.
   *
   * @param string $file_glob
   *   The fileglob for files to include as sources for the migration.
   *
   * @usage tre_content_migration:rollback-blogs '/opt/migrate_sources/blogit-*.xml'
   *   Given files /opt/migrate_sources/blogit-1.xml and
   *   /opt/migrate_sources/blogit-2.xml, sets blog_article migration sources
   *   to list these files in 'sorted' order and runs the migration rollback
   *   using these sources.
   *
   * @command tre_content_migration:rollback-blogs
   * @aliases rollback_blogs_xml
   */
  public function rollbackBlogsFromFiles(string $file_glob) {
    $file_list = glob($file_glob, GLOB_NOSORT | GLOB_ERR);
    sort($file_list);

    $this->rollbackHandler($file_list, 'blog_articles');
  }

  /**
   * Executes the migration.
   *
   * @param array|bool $file_list
   *   File list to import contents form.
   * @param string $plugin_id
   *   Migration plugin id.
   */
  public function importHandler($file_list, $plugin_id, $blog_name = '') {
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $overridden_configuration = [
      'source' => [
        'urls' => $file_list,
      ],
    ];
    if (!empty($blog_name)) {
      $overridden_configuration['process']['field_blog'][0]['default_value'] = $blog_name;
    }
    $migration = $this->migrationPluginManager->createInstance($plugin_id, $overridden_configuration);


    $migration->setStatus(MigrationInterface::STATUS_IDLE);
    $executable = new MigrateExecutable($migration, new MigrateMessage($this->logger()), $this->output());
    $executable->import();
  }

  /**
   * Rollback handler.
   *
   * @param array|bool $file_list
   *   File list to rollback migration for.
   * @param string $plugin_id
   *   Migration plugin id.
   */
  public function rollbackHandler($file_list, $plugin_id) {
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = $this->migrationPluginManager->createInstance($plugin_id, [
      'source' => [
        'urls' => $file_list,
      ],
    ]);
    $migration->setStatus(MigrationInterface::STATUS_IDLE);
    $executable = new MigrateExecutable($migration, new MigrateMessage($this->logger()), $this->output());
    $executable->rollback();
  }

}
