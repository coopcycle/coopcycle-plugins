<?php

class HttpClientException extends \RuntimeException
{
    public function __construct($response)
    {
        $status = wp_remote_retrieve_response_code($response);

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        $message = 'An error occurred';
        if (JSON_ERROR_NONE === json_last_error()) {
            if (isset($data['@type']) && 'hydra:Error' === $data['@type']) {
                $message = $data['hydra:description'];
            }
        }

        parent::__construct($message, (int) $status);
    }
}
