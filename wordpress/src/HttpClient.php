<?php

require_once __DIR__ . '/HttpClientException.php';

class CoopCycle_HttpClient
{
    private $base_url;
    private $api_key;
    private $api_secret;
    private $last_access_token;

    public function __construct()
    {
        $this->base_url = get_option('coopcycle_base_url');
        $this->api_key = get_option('coopcycle_api_key');
        $this->api_secret = get_option('coopcycle_api_secret');
    }

    public function is_successful($response_code)
    {
        return $response_code >= 200 && $response_code < 300;
    }

    public function get($uri)
    {
        $url = $this->base_url . $uri;

        $access_token = $this->accessToken();

        $headers = array(
            'Content-Type' => 'application/ld+json',
            'Authorization' => sprintf('Bearer %s', $access_token)
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

        $access_token = $this->accessToken();

        $headers = array(
            'Content-Type' => 'application/ld+json',
            'Authorization' => sprintf('Bearer %s', $access_token)
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

    public function accessToken()
    {
        if (empty($this->last_access_token)) {

            $url = $this->base_url . '/oauth2/token';

            $headers = array(
                'Authorization' => sprintf('Basic %s', base64_encode(sprintf('%s:%s', $this->api_key, $this->api_secret)))
            );

            $response = wp_remote_post($url, array(
                'timeout' => 30,
                'sslverify' => false,
                'headers' => $headers,
                'body' => 'grant_type=client_credentials&scope=deliveries',
            ));

            $response_code = wp_remote_retrieve_response_code($response);
            if (is_wp_error($response) || !$this->is_successful($response_code)) {
                throw new HttpClientException($response);
            }

            $body = wp_remote_retrieve_body($response);

            $data = json_decode($body, true);

            $this->last_access_token = $data['access_token'];
        }

        return $this->last_access_token;
    }
}
