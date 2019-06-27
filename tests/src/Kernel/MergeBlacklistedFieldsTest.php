<?php

namespace Drupal\Tests\delivery\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\workspaces\Kernel\WorkspaceTestTrait;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

/**
 * Blacklisted fields merging test.
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
    'conflict',
    'revision_tree',
    'user',
    'system',
    'filter',
    'text',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', ['key_value_expire', 'sequences']);
    $this->installSchema('delivery', ['revision_tree_index', 'revision_tree_index_default']);

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('entity_test_mulrevpub');
    $this->installEntitySchema('user');

    $this->initializeWorkspacesModule();

    $this->installConfig(['system']);

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'entity_test_rev',
      'field_name' => 'field_test',
      'type' => 'text',
      'settings' => [],
      'cardinality' => 1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'entity_type' => 'entity_test_rev',
      'field_name' => 'field_test',
      'bundle' => 'entity_test_rev',
      'settings' => [],
      'third_party_settings' => [
        'conflict' => [
          'blacklisted' => FALSE,
        ],
      ],
    ]);
    $field->save();

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'entity_test_rev',
      'field_name' => 'field_test_blacklisted',
      'type' => 'text',
      'settings' => [],
      'cardinality' => 1,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'entity_type' => 'entity_test_rev',
      'field_name' => 'field_test_blacklisted',
      'bundle' => 'entity_test_rev',
      'settings' => [],
      'third_party_settings' => [
        'conflict' => [
          'blacklisted' => TRUE,
        ],
      ],
    ]);
    $field->save();

    $this->entityManager = $this->container->get('entity.manager');
  }

  /**
   * Test blacklisted fields merge.
   */
  public function testBlacklistFieldsMerge() {
    /** @var \Drupal\revision_tree\Entity\EntityRepository $repository */
    $repository = $this->container->get('entity.repository');
    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $storage */
    $storage = $this->entityManager->getStorage('entity_test_rev');

    /** @var \Drupal\Core\Conflict\ConflictResolver\ConflictResolverManager $conflictManager */
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

    $conflicts = $conflictManager->getConflicts($c, $d, $a);
    $this->assertEqual($conflicts, ['field_test' => 'conflict_local_remote']);
  }

}
