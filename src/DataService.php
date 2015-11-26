<?php

namespace ActiveCollab\Quickbooks;

use ActiveCollab\Quickbooks\Data\Entity;
use Guzzle\Service\Client as GuzzleClient;
use Guzzle\Http\Exception\BadResponseException;
use League\OAuth1\Client\Credentials\TokenCredentials;
use ActiveCollab\Quickbooks\Quickbooks;

class DataService
{
    const API_VERSION = 3;

    /**
     * @var string
     */
    protected $consumer_key, $consumer_key_secret, $access_token, $access_token_secret, $realmId;

    /**
     * @var string|null
     */
    protected $user_agent = null;

    /**
     * @var string
     */
    protected $entity = '';

    public function __construct($consumer_key, $consumer_key_secret, $access_token, $access_token_secret, $realmId)
    {
        $this->consumer_key = $consumer_key;
        $this->consumer_key_secret = $consumer_key_secret;
        $this->access_token = $access_token;
        $this->access_token_secret = $access_token_secret;
        $this->realmId = $realmId;
    } 

    public function getApiUrl()
    {
        return 'https://quickbooks.api.intuit.com/v'.self::API_VERSION;
    }

    public function createHttpClient()
    {
        return new GuzzleClient();
    }

    public function createServer($client_credentials)
    {
        return new Quickbooks($client_credentials);
    }

    public function getTokenCredentials()
    {
        $tokenCredentials = new TokenCredentials();
        $tokenCredentials->setIdentifier($this->access_token);
        $tokenCredentials->setSecret($this->access_token_secret);

        return $tokenCredentials;
    }

    public function setUserAgent($user_agent = null)
    {
        $this->user_agent = $user_agent;

        return $this;
    }

    public function getUserAgent()
    {
        return $this->user_agent;
    }

    public function setEntity($entity)
    {
        $this->entity = $entity;

        return $this;
    }

    public function getEntityUrl()
    {
        return $this->getApiUrl() . '/' . $this->entity;
    }

    public function create($payload)
    {
        return $this->request('POST', $this->getEntityUrl(), $payload);
    }

    public function read($id)
    {
        $uri = $this->getEntityUrl() . '/' . $id;

        return $this->request('GET', $uri);
    }

    public function update($payload)
    {
        $uri = $this->getEntityUrl() . '?operation=update';

        return $this->request('POST', $uri, $payload);
    }

    public function delete($payload)
    {
        $uri = $this->getEntityUrl() . '?operation=delete';

        $this->request('POST', $uri, $payload);

        return null;
    }

    public function query($query = null)
    {
        if ($query === null) {
            $query = "select * from {$this->entity}";
        }

        $uri = $this->getApiUrl() . '?query=' . $query;

        return $this->request('GET', $uri);
    }

    public function getHeaders($method, $uri) 
    {
        $server = $this->createServer([ $this->consumer_key, $this->consumer_key_secret ]);

        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => $server->protocolHeader($method, $uri, $this->getTokenCredentials()),
        ];

        if (!empty($this->user_agent)) {
            $headers['User-Agent'] = $this->user_agent;
        }

        return $headers;
    }

    public function request($method, $uri, $body = null)
    {   
        $client = $this->createHttpClient();

        $headers = $this->getHeaders($method, $uri);

        try {
            $response = $client->createRequest($method, $uri, $headers, $body)->send()->json();

            $keys = array_keys($response);
            $values = array_values($response);

            $entity = '\\ActiveCollab\\Quickbooks\\Data\\' . (isset($keys[0]) ? $keys[0] : null);
            $data = isset($values[0]) ? $values[0] : [];

            return class_exists($entity) ? new $entity($data) : new Entity($data);
        } catch (BadResponseException $e) {
            $response = $e->getResponse();
            $body = $response->getBody();
            $statusCode = $response->getStatusCode();

            throw new \Exception(
                "Received error [$body] with status code [$statusCode] when sending request."
            );
        }
    }

}