<?php

if (!defined('ABSPATH')) {
    exit;
}

final class KGV24_API_Client
{
    public const OPTION_NAME = 'kgv24_settings';

    private const DEFAULT_API_BASE_URL = 'https://kleingartenverein24.de';

    public function get_settings(): array
    {
        $settings = get_option(self::OPTION_NAME, []);

        if (!is_array($settings)) {
            $settings = [];
        }

        return wp_parse_args(
            $settings,
            [
                'api_base_url' => self::DEFAULT_API_BASE_URL,
                'api_token' => '',
                'last_auth_status' => '',
                'last_auth_message' => '',
                'last_auth_checked_at' => '',
            ]
        );
    }

    public function get_api_base_url(): string
    {
        $settings = $this->get_settings();
        $base_url = trim((string) $settings['api_base_url']);

        return $base_url !== '' ? untrailingslashit($base_url) : self::DEFAULT_API_BASE_URL;
    }

    public function get_api_token(): string
    {
        $settings = $this->get_settings();

        return trim((string) $settings['api_token']);
    }

    public function has_token(): bool
    {
        return $this->get_api_token() !== '';
    }

    public function test_authentication()
    {
        return $this->request('GET', '/api/tenant/session');
    }

    public function get_plots()
    {
        $response = $this->request('GET', '/api/tenant/plots');

        if (is_wp_error($response)) {
            return $response;
        }

        return $this->extract_items($response);
    }

    public function request(string $method, string $path, array $args = [])
    {
        if (!$this->has_token()) {
            return new WP_Error(
                'kgv24_missing_token',
                __('Bitte hinterlege zuerst den tenant-gebundenen KGV24 API-Key in den Plugin-Einstellungen.', 'kgv24')
            );
        }

        $url = $this->get_api_base_url() . '/' . ltrim($path, '/');
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->get_api_token(),
        ];

        $request_args = wp_parse_args(
            $args,
            [
                'method' => strtoupper($method),
                'headers' => $headers,
                'timeout' => 15,
            ]
        );
        $request_args['headers'] = wp_parse_args($request_args['headers'], $headers);

        $response = wp_remote_request($url, $request_args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = null;

        if ($body !== '') {
            $data = json_decode($body, true);

            if (JSON_ERROR_NONE !== json_last_error()) {
                return new WP_Error(
                    'kgv24_invalid_json',
                    __('Die KGV24 API hat keine gueltige JSON-Antwort geliefert.', 'kgv24')
                );
            }
        }

        if ($status_code < 200 || $status_code >= 300) {
            $message = $this->extract_error_message($data);

            if ($message === '' && in_array($status_code, [401, 403], true)) {
                $message = __('Der KGV24 API-Key wurde abgelehnt oder der API-Zugriff ist durch Abo-/Trial-Status blockiert.', 'kgv24');
            }

            return new WP_Error(
                'kgv24_api_error',
                $message ?: sprintf(
                    /* translators: %d is an HTTP status code. */
                    __('KGV24 API-Anfrage fehlgeschlagen. HTTP-Status: %d.', 'kgv24'),
                    $status_code
                ),
                [
                    'status' => $status_code,
                    'response' => $data,
                ]
            );
        }

        return is_array($data) ? $data : [];
    }

    public function extract_items(array $data): array
    {
        foreach (['items', 'hydra:member', 'member', 'data', 'results'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_values(array_filter($data[$key], 'is_array'));
            }
        }

        if ($this->is_list_array($data)) {
            return array_values(array_filter($data, 'is_array'));
        }

        return [];
    }

    private function is_list_array(array $data): bool
    {
        if ($data === []) {
            return true;
        }

        return array_keys($data) === range(0, count($data) - 1);
    }

    private function extract_error_message($data): string
    {
        if (!is_array($data)) {
            return '';
        }

        foreach (['detail', 'message', 'error', 'title', 'hydra:description'] as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '') {
                return $data[$key];
            }
        }

        return '';
    }
}
