<?php

require_once __DIR__ . '/HttpClientException.php';

class CoopCycle_HttpClient
{
    private $base_url;
    private $api_token;

    public function __construct()
    {
        $this->base_url = get_option('coopcycle_base_url');
        $this->api_token = get_option('coopcycle_api_token');
    }

    public function is_successful($response_code)
    {
        return $response_code >= 200 && $response_code < 300;
    }

    public function get($uri)
    {
        $url = $this->base_url . $uri;

        $headers = array(
            'Content-Type' => 'application/ld+json',
            'Authorization' => sprintf('Bearer %s', $this->api_token)
        );

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => $headers,
        ));

        $response_code = wp_remote_retrieve_response_code($response);
        if (is_wp_error($response) || !$this->is_successful($response_code)) {
            throw new HttpClientException($response);
        }

        $body = wp_remote_retrieve_body($response);

        return json_decode($body, true);
    }

    public function post($uri, array $data)
    {
        $url = $this->base_url . $uri;

        $headers = array(
            'Content-Type' => 'application/ld+json',
            'Authorization' => sprintf('Bearer %s', $this->api_token)
        );

        $response = wp_remote_post($url, array(
            'timeout' => 30,
            'sslverify' => false,
            'headers' => $headers,
            'body' => json_encode($data),
        ));

        $response_code = wp_remote_retrieve_response_code($response);
        if (is_wp_error($response) || !$this->is_successful($response_code)) {
            throw new HttpClientException($response);
        }

        $body = wp_remote_retrieve_body($response);

        return json_decode($body, true);
    }
}
