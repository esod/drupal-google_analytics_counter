<?php

namespace Drupal\google_analytics_counter\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterCommon;
use Drupal\google_analytics_counter\GoogleAnalyticsCounterFeed;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Exception\RuntimeException;



/**
 * Class GoogleAnalyticsCounterAdminSettingsForm.
 *
 * @package Drupal\google_analytics_counter\Form
 */
class GoogleAnalyticsCounterAdminSettingsForm extends ConfigFormBase {

  /**
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Drupal\google_analytics_counter\GoogleAnalyticsCounterCommon definition.
   *
   * @var \Drupal\google_analytics_counter\GoogleAnalyticsCounterCommon
   */
  protected $common;
  /**
   * Constructs a new SiteMaintenanceModeForm.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   * @param \Drupal\google_analytics_counter\GoogleAnalyticsCounterCommon $common
   *   Google Analytics Counter Common object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state, GoogleAnalyticsCounterCommon $common) {
    parent::__construct($config_factory);
    $this->state = $state;
    $this->common = $common;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('google_analytics_counter.common')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'google_analytics_counter_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['google_analytics_counter.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('google_analytics_counter.settings');

    $form['cron_interval'] = array(
      '#type' => 'number',
      '#title' => $this->t('Minimum time to wait before fetching Google Analytics data'),
      '#default_value' => $config->get('general_settings.cron_interval'),
      '#description' => $this->t('Google Analytics data is fetched and processed during cron. If cron runs too frequently, the Google Analytics daily quota may be <a href="https://developers.google.com/analytics/devguides/reporting/core/v3/limits-quotas" target="_blank">exceeded</a>.<br />Set the minimum number of <em>minutes</em> that need to pass before the Google Analytics Counter cron runs. Default: 30 minutes.'),
      '#required' => TRUE,
    );

    $form['chunk_to_fetch'] = array(
      '#type' => 'number',
      '#title' => $this->t('Number of items to fetch from Google Analytics in one request'),
      '#default_value' => $config->get('general_settings.chunk_to_fetch'),
      '#min' => 1,
      '#max' => 10000,
      '#description' => $this->t('How many items will be fetched from Google Analytics in one request (during a cron run). The maximum allowed by Google is 10000. Default: 1000 items.'),
      '#required' => TRUE,
    );

    $form['api_dayquota'] = array(
      '#type' => 'number',
      '#title' => $this->t('Maximum GA API requests per day'),
      '#default_value' => $config->get('general_settings.api_dayquota'),
      '#size' => 9,
      '#maxlength' => 9,
      '#description' => $this->t('This is the daily limit of requests <strong>per view (profile)</strong> per day (cannot be increased). You don\'t need to change this value until Google changes their quota policy. <br />See <a href="https://developers.google.com/analytics/devguides/reporting/core/v3/limits-quotas" target="_blank">Limits and Quotas on API Requests</a> for information on Google\'s quota policies. To exceed Google\'s quota limits, look for <a href="https://developers.google.com/analytics/devguides/reporting/core/v3/limits-quotas#full_quota" target="_blank">Exceeding quota limits</a> on the same page.'),
      '#required' => TRUE,
    );

    $form['cache_length'] = array(
      '#type' => 'number',
      '#title' => t('Google Analytics query cache'),
      '#description' => t('Limit the minimum time in hours to elapse between getting fresh data for the same query from Google Analytics. Defaults to 1 day.'),
      '#default_value' => $config->get('general_settings.cache_length') / 3600,
      '#required' => TRUE,
    );

    // Todo. Could be more flexible.
    $start_date = [
      '-1 day' => $this->t('-1 day'),
      '-7 days' => $this->t('-7 days'),
      '-30 days' => $this->t('-30 days'),
      '-90 days' => $this->t('-90 days'),
      '-365 days' => $this->t('-365 days'),
      '2005-01-01' => $this->t('Since 2005-01-01'),
    ];

    // Todo: Could be more flexible.
    $form['start_date'] = array(
      '#type' => 'select',
      '#title' => $this->t('Start Date for Google Analytics queries'),
      '#default_value' => $config->get('general_settings.start_date'),
      '#description' => $this->t('The earliest valid start date for Google Analytics is 2005-01-01.'),
      '#required' => TRUE,
      '#options' => $start_date,
    );

    $form['overwrite_statistics'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Override the counter of the core statistics module'),
      '#default_value' => $config->get('general_settings.overwrite_statistics'),
      '#disabled' => !\Drupal::moduleHandler()->moduleExists('statistics'),
      '#description' => $this->t('Overwriting the total count of cores statistics module is not advised but may be useful in some situations.')
    );

    $options = $this->common->getWebPropertiesOptions();
    if (!$options) {
      $options = [$config->get('general_settings.profile_id') => 'Un-authenticated (' . $config->get('general_settings.profile_id') . ')'];
    }
    $form['profile_id'] = array(
      '#type' => 'select',
      '#title' => $this->t('Reports profile'),
      '#options' => $options,
      '#default_value' => $config->get('general_settings.profile_id'),
      '#description' => $this->t('Choose your Google Analytics profile. The options depend on the authenticated account.'),
    );

    $form['setup'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Initial setup'),
      '#description' => $this->t("The google key details can only be changed when not authenticated."),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#disabled' => \Drupal::service('google_analytics_counter.common')->isAuthenticated(),
    );
    $form['setup']['client_id'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('general_settings.client_id'),
      '#size' => 30,
      '#description' => $this->t('Client ID created for the app in the access tab of the <a href="http://code.google.com/apis/console" target="_blank">Google API Console</a>'),
      '#weight' => -9,
    );
    $form['setup']['client_secret'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#default_value' => $config->get('general_settings.client_secret'),
      '#size' => 30,
      '#description' => $this->t('Client Secret created for the app in the Google API Console'),
      '#weight' => -8,
    );

    $form['setup']['redirect_uri'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Redirect URI'),
      '#default_value' => $config->get('general_settings.redirect_uri'),
      '#size' => 30,
      '#description' => $this->t('Use to override the host for the callback uri (necessary on some servers, e.g. when using SSL and Varnish). Leave blank to use default (blank will work for most cases).<br /> Default: @default_uri/authentication', ['@default_uri' => GoogleAnalyticsCounterFeed::currentUrl()]),
      '#weight' => -7,
    );

    if ($config->get('general_settings.profile_id') <> '') {
      return parent::buildForm($form, $form_state);
    }
    else {
      $t_args = [
        ':href' => Url::fromRoute('google_analytics_counter.admin_auth_form', [], ['absolute' => TRUE])
          ->toString(),
        '@href' => 'authenticate here',
      ];
      drupal_set_message($this->t('No Google Analytics profile has been authenticated! Google Analytics Counter can not fetch any new data. Please <a href=:href>@href</a>.', $t_args), 'warning', FALSE);
      return parent::buildForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('google_analytics_counter.settings');
    $config->set('general_settings.cron_interval', $form_state->getValue('cron_interval'))
      ->set('general_settings.chunk_to_fetch', $form_state->getValue('chunk_to_fetch'))
      ->set('general_settings.api_dayquota', $form_state->getValue('api_dayquota'))
      ->set('general_settings.cache_length', $form_state->getValue('cache_length') * 3600)
      ->set('general_settings.start_date', $form_state->getValue('start_date'))
      ->set('general_settings.overwrite_statistics', $form_state->getValue('overwrite_statistics'))
      ->set('general_settings.profile_id', $form_state->getValue('profile_id'))
      ->set('general_settings.client_id', $form_state->getValue('client_id'))
      ->set('general_settings.client_secret', $form_state->getValue('client_secret'))
      ->set('general_settings.redirect_uri', $form_state->getValue('redirect_uri'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
