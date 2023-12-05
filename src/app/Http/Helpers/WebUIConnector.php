<?php

namespace App\Http\Helpers;

use App\Models\Config;

class WebUIConnector
{
    private static self|null $instance = null;

    public static function getInstance() : self
    {
        if(self::$instance == null){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function sendRequest(string $endpoint)
    {
        $baseURL = Config::where('key', 'A1111-URL')->first()->value;

        if(str_ends_with($baseURL, '/') && !str_starts_with($endpoint, '/')){
            $baseURL.='/';
        }
        $options = array(
            CURLOPT_URL            => $baseURL.$endpoint,
            CURLOPT_HEADER         => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,    // for https
            CURLOPT_VERBOSE        => false,
            CURLOPT_TIMEOUT        => 60,
        );

        $ch = curl_init();

        curl_setopt_array( $ch, $options );
        $raw_response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        if($statusCode >= 400 && $statusCode <= 600){
            throw new \Exception('A1111 is not available at the moment - please try again later.');
        }
        $body = substr($raw_response, $header_size);
        if(curl_errno($ch) === 0){
            return json_decode($body, true);
        } else {
            throw new \Exception('Something went wrong connecting to A1111!');
        }
    }

    public function getLoras() : array
    {
        return $this->sendRequest('/sdapi/v1/loras');
    }

    public function testConnection() : bool
    {
        try {
            $this->sendRequest('/app_id');
            return true;
        } catch (\Exception){
            return false;
        }
    }
}
