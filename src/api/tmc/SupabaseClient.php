<?php
/**
 * The Model Church — Supabase REST API Client
 * Connects to Supabase Postgres via HTTP REST API
 * Used for all custom TMC features (not ChurchCRM core)
 */

class SupabaseClient
{
    private string $url;
    private string $anonKey;
    private string $serviceKey;

    public function __construct()
    {
        $this->url        = rtrim($_ENV['SUPABASE_URL'] ?? getenv('SUPABASE_URL') ?? '', '/');
        $this->anonKey    = $_ENV['SUPABASE_ANON_KEY'] ?? getenv('SUPABASE_ANON_KEY') ?? '';
        $this->serviceKey = $_ENV['SUPABASE_SERVICE_KEY'] ?? getenv('SUPABASE_SERVICE_KEY') ?? '';

        if (empty($this->url) || empty($this->anonKey)) {
            throw new RuntimeException('Supabase credentials not configured. Check your .env file.');
        }
    }

    /**
     * SELECT rows from a table
     * @param string $table  Table name
     * @param string $query  PostgREST query string (e.g. "order=created_at.desc&limit=50")
     * @param bool   $useServiceKey  Use service key (bypasses RLS)
     */
    public function select(string $table, string $query = '', bool $useServiceKey = false): array
    {
        $url = "{$this->url}/rest/v1/{$table}" . ($query ? "?{$query}" : '');
        return $this->request('GET', $url, null, $useServiceKey);
    }

    /**
     * INSERT a row into a table
     */
    public function insert(string $table, array $data, bool $useServiceKey = false): array
    {
        $url = "{$this->url}/rest/v1/{$table}";
        return $this->request('POST', $url, $data, $useServiceKey);
    }

    /**
     * UPDATE rows matching a filter
     * @param string $filter  PostgREST filter (e.g. "id=eq.5")
     */
    public function update(string $table, string $filter, array $data, bool $useServiceKey = false): array
    {
        $url = "{$this->url}/rest/v1/{$table}?{$filter}";
        return $this->request('PATCH', $url, $data, $useServiceKey);
    }

    /**
     * DELETE rows matching a filter
     */
    public function delete(string $table, string $filter, bool $useServiceKey = false): array
    {
        $url = "{$this->url}/rest/v1/{$table}?{$filter}";
        return $this->request('DELETE', $url, null, $useServiceKey);
    }

    /**
     * Core HTTP request handler
     */
    private function request(string $method, string $url, ?array $body, bool $useServiceKey): array
    {
        $key = $useServiceKey ? $this->serviceKey : $this->anonKey;

        $headers = [
            "apikey: {$key}",
            "Authorization: Bearer {$key}",
            "Content-Type: application/json",
            "Prefer: return=representation",
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => $error, 'status' => 0];
        }

        $decoded = json_decode($response, true) ?? [];

        return [
            'data'   => $decoded,
            'status' => $httpCode,
            'ok'     => $httpCode >= 200 && $httpCode < 300,
        ];
    }
}
