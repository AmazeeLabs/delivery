<?php

namespace Drupal\delivery\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\delivery\Entity\DeliveryItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the delivery entity edit forms.
 *
 * @ingroup content_entity_example
 */
class DeliveryForm extends ContentEntityForm {

  /**
   * The private temp store.
   * @var PrivateTempStore
   */
  protected $userPrivateStore;

  /**
   * {@inheritDoc}
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, PrivateTempStoreFactory $privateTempStoreFactory = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->userPrivateStore = $privateTempStoreFactory->get('delivery');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('user.private_tempstore')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form_state->set('workspace_safe', TRUE);
    return $form;
  }

  public function save(array $form, FormStateInterface $form_state) {
    // On save, we get all the entries from the delivery cart and create
    // delivery items from them.
    $cart = $this->userPrivateStore->get('delivery_cart');
    if (!empty($cart)) {
      foreach ($cart as $entity_type_id => $entity_ids) {
        foreach ($entity_ids as $entity_id_data) {
          // We creat a delivery item for each target workspace.
          foreach ($this->entity->workspaces->referencedEntities() as $workspace) {
            $item = DeliveryItem::create([
              // The source workspace will be the one set in the cart. Each
              // entry in the cart contains the source revision and workspace.
              // @todo: we may even just hide the source workspace from the
              // delivery as it is not really used in this case.
              'source_workspace' => $entity_id_data['workspace_id'],
              'target_workspace' => $workspace->id(),
              'entity_type' => $entity_type_id,
              'entity_id' => $entity_id_data['entity_id'],
              'source_revision' => $entity_id_data['revision_id'],
            ]);
            $item->save();
            $this->entity->items[] = $item->id();
          }
        }
      }
    }
    $id = parent::save($form, $form_state);
    // Empty the delivery cart once we created the delivery.
    $this->userPrivateStore->delete('delivery_cart');

    $form_state->setRedirect('entity.delivery.canonical', [
      'delivery' => $this->entity->id(),
    ]);
    return $id;
  }

}
