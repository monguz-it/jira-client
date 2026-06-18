<?php

declare(strict_types=1);

/**
 * HTTP client for Jira Cloud REST API v3.
 *
 * Uses Basic Auth with email + API token.
 * All responses are decoded JSON arrays.
 * Throws RuntimeException on cURL or HTTP errors.
 */
class JiraClient
{
    /** @var string */
    private $url;

    /** @var string */
    private $email;

    /** @var string */
    private $token;

    public function __construct(string $url, string $email, string $token)
    {
        $this->url = $url;
        $this->email = $email;
        $this->token = $token;
    }

    public function getBaseUrl(): string
    {
        return $this->url;
    }

    /**
     * @param array<string, string> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $url = $this->url . $path;
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        return $this->request('GET', $url);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function post(string $path, array $body): array
    {
        return $this->request('POST', $this->url . $path, $body);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function put(string $path, array $body): array
    {
        return $this->request('PUT', $this->url . $path, $body);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $url, ?array $body = null): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->email . ':' . $this->token),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("cURL error: $error");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response, true) ?? [];

        if ($httpCode >= 400) {
            $errors = $data['errors'] ?? [];
            $msg = $data['errorMessages'][0]
                ?? (is_array($errors) && $errors ? reset($errors) : null)
                ?? "HTTP $httpCode";
            throw new RuntimeException('Jira API error: ' . (string) $msg);
        }

        return $data;
    }
}
