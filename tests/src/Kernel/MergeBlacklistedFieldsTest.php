<?php

namespace Drupal\Tests\delivery\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\workspaces\Kernel\WorkspaceTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * Class MergeBlacklistedFieldsTest
 *
 * Blacklisted fields merging test.
 *
 * @package Drupal\Tests\delivery\Kernel
 * @group delivery
 */
class MergeBlacklistedFieldsTest extends KernelTestBase {

  use WorkspaceTestTrait;
  use UserCreationTrait;
  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  public static $modules = [
    'workspaces',
    'entity_test',
    'delivery',
    'revision_tree',
    'language',
    'conflict',
    'content_translation',
    'user',
    'system',
    'filter',
    'text',
    'field',
    'menu_link_content',
    'link',
  ];

  protected $entityTypeManager;

  public function register(ContainerBuilder $container) {
    parent::register($container);
    $container->removeDefinition('conflict_resolution.merge_invisible_fields');
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', ['key_value_expire', 'sequences']);

    $this->installEntitySchema('workspace');
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_revpub');
    $this->installEntitySchema('entity_test_mulrevpub');
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');

    $this->initializeWorkspacesModule();

    $this->installConfig(['system']);
    $this->installConfig(['conflict']);

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'entity_test_revpub',
      'field_name' => 'field_test',
      'type' => 'text',
      'settings' => [],
      'cardinality' => 1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'entity_type' => 'entity_test_revpub',
      'field_name' => 'field_test',
      'bundle' => 'entity_test_revpub',
      'settings' => [],
      'third_party_settings' => [
        'delivery' => [
          'blacklisted' => FALSE,
        ],
      ],
    ]);
    $field->save();

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'entity_test_revpub',
      'field_name' => 'field_test_blacklisted',
      'type' => 'text',
      'settings' => [],
      'cardinality' => 1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'entity_type' => 'entity_test_revpub',
      'field_name' => 'field_test_blacklisted',
      'bundle' => 'entity_test_revpub',
      'settings' => [],
      'third_party_settings' => [
        'delivery' => [
          'blacklisted' => TRUE,
        ],
      ],
    ]);
    $field->save();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Test blacklisted fields merge.
   */
  public function testBlacklistFieldsMerge() {
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('entity_test_revpub');

    /** @var \Drupal\conflict\ConflictResolver\ConflictResolverManagerInterface $conflictManager */
    $conflictManager = $this->container->get('conflict_resolver.manager');

    $this->switchToWorkspace('live');

    $a = $storage->create([
      'name' => 'Foooooo',
      'field_test' => 'test live value',
      'field_test_blacklisted' => 'test live value',
    ]);
    $a->save();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $x */
    $this->switchToWorkspace('stage');

    $b = $storage->createRevision($a);
    $b->field_test = 'test stage value';
    $b->field_test_blacklisted = 'test stage value';
    $b->save();

    $c = $storage->createRevision($b);
    $c->field_test = 'test stage value changed';
    $c->field_test_blacklisted = 'test stage value changed';
    $c->save();

    $this->switchToWorkspace('live');

    $d = $storage->createRevision($a);
    $d->field_test = 'test live value changed';
    $d->field_test_blacklisted = 'test live value changed';
    $d->save();

    $conflicts = $conflictManager->resolveConflicts($c, $d, $a);
    $this->assertEqual($conflicts, ['field_test' => 'conflict_local_remote']);
  }

}
