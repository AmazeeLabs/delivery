<?php

namespace Drupal\delivery\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\delivery\Entity\DeliveryItem;
use Drupal\workspaces\Entity\Workspace;
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
    // Add an overview of the delivery cart, if any, but only if we are creating
    // a delivery.
    if ($this->entity->isNew()) {
      $cart = $this->userPrivateStore->get('delivery_cart');
      $items = [];
      if (!empty($cart)) {
        foreach ($cart as $entity_type_id => $entity_ids) {
          $entityStorage = $this->entityTypeManager->getStorage($entity_type_id);
          foreach ($entity_ids as $entity_id_data) {
            // @todo: Temporary fix for avoiding a timeout when having a lot of
            // entries in the cart, until we get a proper pagination.
            if (count($items) >= 100) {
              $items[] = $this->t('... and more');
              break 2;
            }
            $sourceWorkspace = Workspace::load($entity_id_data['workspace_id']);
            $entity = $entityStorage->loadRevision($entity_id_data['revision_id']);

            $items[] = $this->t('@entity_type: %entity_label (Workspace: @workspace) - <a href=":delivery_cart_remove">Remove</a>', [
              '@entity_type' => $entityStorage->getEntityType()->getLabel(),
              '%entity_label' => $entity->label(),
              '@workspace' => $sourceWorkspace->label(),
              ':delivery_cart_remove' => Url::fromRoute('entity.' . $entity_type_id . '.delivery_cart_remove', [$entity_type_id => $entity->id()], ['query' => \Drupal::destination()->getAsArray()])->toString(),
            ]);
          }
        }
      }
      if (!empty($items)) {
        $form['items_overview'] = [
          '#theme' => 'item_list',
          '#title' => t('Content to be delivered'),
          '#items' => $items,
        ];
      } else {
        $form['items_overview'] = [
          '#markup' => $this->t('You have no items in the delivery cart. To add content, just navigate to the view page of a content and click on the <em>Add to the delivery cart</em> tab.'),
        ];
      }
    }
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
