// demo code
// I created a drupal service to consume the api of twitter.

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\State;

/**
 * TwitterManager service class.
 */
class TwitterManager {

  /**
   * Endpoint url to get tweets by user account.
   */
  const URL_ACCOUNTS = 'https://api.twitter.com/1.1/statuses/user_timeline.json';

  /**
   * Endpoint url to get tweets by hashtag.
   */
  const URL_HASHTAG = 'https://api.twitter.com/1.1/search/tweets.json';

  /**
   * Request method for request.
   */
  const REQUEST_METHOD = 'GET';

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Formatter serialization.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected $json;

  /**
   * State system definition.
   *
   * @var \Drupal\Core\State\State
   */
  protected $state;

  /**
   * Twitter API definition.
   *
   * @var \TwitterAPIExchange
   */
  protected $twitterExchange;

  /**
   * Constructor class.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   It's an instance of config factory service.
   * @param \Drupal\Component\Serialization\Json $json
   *   It's an Instance of config serialization json service.
   * @param \Drupal\Core\State\State $state
   *   It's an Instance of state service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Json $json, State $state) {
    $this->configFactory = $config_factory;
    $this->json = $json;
    $this->state = $state;
    $this->twitterExchange = new \TwitterAPIExchange($this->getAccess());
  }

  /**
   * Gets access tokens.
   *
   * @return array
   *   A collection of access token.
   */
  public function getAccess() {
    $config = $this->configFactory->get('globant_twitter.settings');

    $settings = [
      'oauth_access_token' => $config->get('twitter_access_token') ?? '',
      'oauth_access_token_secret' => $config->get('twitter_access_token_secret') ?? '',
      'consumer_key' => $config->get('twitter_consumer_key') ?? '',
      'consumer_secret' => $config->get('twitter_consumer_secret') ?? '',
    ];

    return $settings;
  }

  /**
   * Gets all tweets of account of a searching by hashtag.
   *
   * @param mixed $query
   *   Data collection to run the request such as account or hashtag.
   * @param string $type
   *   String variable with information to identify the endpoint.
   *
   * @return json
   *   Json data with information of tweets.
   */
  public function getTweets($query, string $type = 'account') {
    $url = ($type == 'account') ? self::URL_ACCOUNTS : self::URL_HASHTAG;

    $this->twitterExchange->setGetfield($query);
    $this->twitterExchange->buildOauth($url, self::REQUEST_METHOD);

    $response = $this->json
      ->decode(
        $this->twitterExchange
          ->performRequest(
            TRUE, [
              CURLOPT_SSL_VERIFYHOST => 0,
              CURLOPT_SSL_VERIFYPEER => 0,
            ]
          )
      );

    if (!empty($response["errors"][0]["message"])) {
      $smf_errors = $this->state->get('social_media_errors');
      $smf_errors['twitter'][$response['errors'][0]['code']] = sprintf('Twitter error: %s - %s', $response['errors'][0]['code'], $response['errors'][0]['message']);
      $this->state->set('social_media_errors', $smf_errors);
      $response = [];
    }

    return $response;
  }

}



// I created a configuration form to save some data.

namespace Drupal\custom_twitter\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * TwitterSettingsForm config form class.
 */
class TwitterSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_twitter_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['custom_twitter.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('custom_twitter.settings');
    $form['twitter_access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access token'),
      '#default_value' => $config->get('twitter_access_token'),
      '#required' => TRUE,
    ];
    $form['twitter_access_token_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access token secret'),
      '#default_value' => $config->get('twitter_access_token_secret'),
      '#required' => TRUE,
    ];
    $form['twitter_consumer_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer key'),
      '#default_value' => $config->get('twitter_consumer_key'),
      '#required' => TRUE,
    ];
    $form['twitter_consumer_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer secret'),
      '#default_value' => $config->get('twitter_consumer_secret'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('custom_twitter.settings');
    $values = $form_state->getValues();

    foreach ($values as $key => $value) {
      if (strpos($key, 'twitter_') !== FALSE) {
        $config->set($key, $value);
      }
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}

