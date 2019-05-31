<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class DeliveryIndexForm.
 */
class DeliveryIndexForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delivery_index_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['update'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Index'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState) {
    \Drupal::service('delivery.revision_index')->updateIndex();
    \Drupal::service('delivery.revision_index')->updateIndex([], '', TRUE);
  }

}
