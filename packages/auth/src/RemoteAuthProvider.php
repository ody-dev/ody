<?php

namespace Ody\Auth;

use Swoole\Coroutine\Http\Client;

/**
 * Remote Authentication Provider
 * Communicates with a centralized auth server
 */
class RemoteAuthProvider implements AuthProviderInterface
{
    protected $authServiceHost;
    protected $authServicePort;
    protected $serviceId;
    protected $serviceSecret;
    protected $serviceToken;
    protected $tokenExpiration;

    public function __construct(string $authServiceHost, int $authServicePort, string $serviceId, string $serviceSecret)
    {
        $this->authServiceHost = $authServiceHost;
        $this->authServicePort = $authServicePort;
        $this->serviceId = $serviceId;
        $this->serviceSecret = $serviceSecret;

        // Authenticate service immediately
        $this->authenticateService();
    }

    protected function authenticateService()
    {
        $client = new Client($this->authServiceHost, $this->authServicePort);
        $client->setHeaders([
            'Content-Type' => 'application/json'
        ]);

        $client->post('/auth/service', json_encode([
            'serviceId' => $this->serviceId,
            'serviceSecret' => $this->serviceSecret
        ]));

        if ($client->statusCode === 200) {
            $response = json_decode($client->body, true);
            $this->serviceToken = $response['token'];
            $this->tokenExpiration = time() + $response['expiresIn'];
            return true;
        }

        return false;
    }

    public function authenticate(string $username, string $password)
    {
        $client = new Client($this->authServiceHost, $this->authServicePort);
        $client->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getServiceToken()
        ]);

        $client->post('/auth/login', json_encode([
            'username' => $username,
            'password' => $password
        ]));

        if ($client->statusCode === 200) {
            return json_decode($client->body, true);
        }

        return false;
    }

    protected function getServiceToken()
    {
        // Refresh service token if needed
        if (empty($this->serviceToken) || time() > ($this->tokenExpiration - 300)) {
            $this->authenticateService();
        }

        return $this->serviceToken;
    }

    public function validateToken(string $token)
    {
        $client = new Client($this->authServiceHost, $this->authServicePort);
        $client->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getServiceToken()
        ]);

        $client->post('/auth/validate', json_encode([
            'token' => $token
        ]));

        if ($client->statusCode === 200) {
            $response = json_decode($client->body, true);
            if ($response['valid'] === true) {
                return $response;
            }
        }

        return false;
    }

    public function refreshToken(string $refreshToken)
    {
        $client = new Client($this->authServiceHost, $this->authServicePort);
        $client->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getServiceToken()
        ]);

        $client->post('/auth/refresh', json_encode([
            'refreshToken' => $refreshToken
        ]));

        if ($client->statusCode === 200) {
            return json_decode($client->body, true);
        }

        return false;
    }

    public function getUser($id)
    {
        $client = new Client($this->authServiceHost, $this->authServicePort);
        $client->setHeaders([
            'Authorization' => 'Bearer ' . $this->getServiceToken()
        ]);

        $client->get('/auth/user/' . $id);

        if ($client->statusCode === 200) {
            return json_decode($client->body, true);
        }

        return false;
    }

    public function revokeToken(string $token)
    {
        $client = new Client($this->authServiceHost, $this->authServicePort);
        $client->setHeaders([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getServiceToken()
        ]);

        $client->post('/auth/revoke', json_encode([
            'token' => $token
        ]));

        return $client->statusCode === 200;
    }
}