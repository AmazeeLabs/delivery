<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for the delivery entity edit forms.
 *
 * @ingroup content_entity_example
 */
class DeliveryForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form_state->set('workspace_safe', TRUE);
    return $form;
  }

  public function save(array $form, FormStateInterface $form_state) {
    $id = parent::save($form, $form_state);
    $form_state->setRedirect('entity.delivery.canonical', [
      'delivery' => $this->entity->id(),
    ]);
    return $id;
  }


}
