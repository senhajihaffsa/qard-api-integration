<?php
// src/Service/QardClient.php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class QardClient
{
    private HttpClientInterface $httpClient;
    private string $apiKey;
    private string $baseUrl;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->apiKey = $params->get('QARD_API_KEY');
        $this->baseUrl = rtrim($params->get('QARD_API_BASE_URL'), '/');
        $this->logger = $logger;
    }

    private function getHeaders(): array
    {
        return [
            'X-API-KEY' => $this->apiKey,
            'Accept' => 'application/json'
        ];
    }

    public function createLegalUser(string $name, string $siren): ?array
    {
        try {
            $response = $this->httpClient->request('POST', "{$this->baseUrl}/api/v6/users/legal", [
                'headers' => $this->getHeaders(),
                'json' => [
                    'name' => $name,
                    'siren' => $siren
                ]
            ]);

            $data = $response->toArray();
            $userId = $data['id'] ?? null;
            $redirectUrl = $data['redirect_url'] ?? null;

            if ($userId && $this->isValidUuid($userId)) {
                return ['id' => $userId, 'redirect_url' => $redirectUrl];
            }

            return null;
        } catch (\Throwable $e) {
            $this->logger->error("Qard API error [createLegalUser]: " . $e->getMessage());
            return null;
        }
    }

    public function getCompanyProfile(string $userId): ?array
    {
        return $this->fetch("/api/v6/users/{$userId}/company-profile", "getCompanyProfile");
    }

    public function getCompanyOfficers(string $userId): ?array
    {
        return $this->fetch("/api/v6/users/{$userId}/company-officers", "getCompanyOfficers");
    }

    public function getFinancialStatements(string $userId): ?array
    {
        return $this->fetch("/api/v6/users/{$userId}/financial-statements", "getFinancialStatements");
    }

    public function syncUserData(string $userId): void
    {
        try {
            $this->httpClient->request('POST', "{$this->baseUrl}/api/v6/users/{$userId}/sync", [
                'headers' => $this->getHeaders()
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("Qard API error [syncUserData]: " . $e->getMessage());
        }
    }

    private function fetch(string $endpoint, string $context): ?array
    {
        try {
            $response = $this->httpClient->request('GET', "{$this->baseUrl}{$endpoint}", [
                'headers' => $this->getHeaders()
            ]);

            if (204 === $response->getStatusCode()) {
                $this->logger->warning("Qard API [{$context}]: No content (204)");
                return null;
            }

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error("Qard API error [{$context}]: " . $e->getMessage());
            return null;
        }
    }

    private function isValidUuid(string $uuid): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuid
        ) === 1;
    }
}
