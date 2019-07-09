<?php

namespace Drupal\delivery\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class DeliverySettingsForm extends ConfigFormBase {

  public function getFormId() {
    return 'delivery_settings';
  }

  protected function getEditableConfigNames() {
    return ['delivery.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('delivery.settings');
    $form['workspace_pages'] = [
      '#title' => $this->t('Workspace sensitive pages.'),
      '#description' => $this->t("Define a set of workspace sensitive pages. All other pages will be using the default workspace. Enter one page path per line. The '*' character is a wildcard. An example path is /node/* for every node page."),
      '#type' => 'textarea',
      '#default_value' => $config->get('workspace_pages'),
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('delivery.settings');
    $config->set('workspace_pages', $form_state->getValue('workspace_pages'));
    $config->save();
    parent::submitForm($form, $form_state);
  }


}
