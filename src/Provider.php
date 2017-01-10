<?php

namespace Katsana\Socialite;

use Illuminate\Support\Arr;
use SocialiteProviders\Manager\OAuth2\User;
use Laravel\Socialite\Two\ProviderInterface;
use SocialiteProviders\Manager\OAuth2\AbstractProvider;

class Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * Unique Provider Identifier.
     */
    const IDENTIFIER = 'KATSANA';

    /**
     * List of scopes.
     *
     * @var array
     */
    protected $scopes = ['*'];

    /**
     * Environment setting.
     *
     * @var string|null
     */
    protected static $environment;

    /**
     * Endpoint.
     *
     * @var string
     */
    protected static $endpoints = [
        'production' => [
            'api' => 'https://api.katsana.com',
            'oauth' => 'https://my.katsana.com/oauth',
        ],
        'carbon' => [
            'api' => 'https://carbon.api.katsana.com',
            'oauth' => 'https://carbon.katsana.com/oauth',
        ],
    ];

    /**
     * Set API environment.
     *
     * @param string|null $environment
     */
    public static function setEnvironment($environment = null)
    {
        static::$environment = $environment;
    }

    /**
     * Get environment endpoint.
     *
     * @return array
     */
    protected function getEnvironmentEndpoint()
    {
        $environment = static::$environment;

        if (is_null($environment)) {
            $environment = $this->getConfig('environment', 'production');
        }

        return static::$endpoints[$environment];
    }


    /**
     * Get the authentication URL for the provider.
     *
     * @param  string  $state
     *
     * @return string
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase(
            $this->getEnvironmentEndpoint()['oauth'].'/authorize', $state
        );
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl()
    {
        return $this->getEnvironmentEndpoint()['oauth'].'/token';
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param  string  $token
     *
     * @return array
     */
    protected function getUserByToken($token)
    {
        $client = $this->getSdkClient();

        $response = $this->getHttpClient()->get(
            $this->getEnvironmentEndpoint()['api'].'/profile', [
            'headers' => [
                'Accept' => 'application/vnd.KATSANA.v1+json',
                'Authorization' => "Bearer {$token}",
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Map the raw user array to a Socialite User instance.
     *
     * @param  array  $user
     * @return \Laravel\Socialite\Two\User
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id' => $user['id'],
            'name' => $user['fullname'],
            'email' => $user['email'],
            'avatar' => Arr::get($user, 'avatar.url'),
        ]);
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param  string  $code
     * @return array
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }

    /**
     * Get KATSANA SDK Client.
     *
     * @return \Katsana\Sdk\Client
     */
    protected function getSdkClient()
    {
        $app = Container::getInstance();

        if ($app->bound('katsana')) {
            return $app->make('katsana');
        }

        return Client::make($this->clientId, $this->clientSecret);
    }
}
