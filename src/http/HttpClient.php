<?php

/**
 * Class to handle HTTP API requests
 */
final class HttpClient
{
    private string $apiKey;
    private string $baseUrl;
    private array $headers;
    
    public function __construct(string $apiKey, string $baseUrl)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];
    }
    
    /**
     * Send POST request to API endpoint
     */
    public function post(string $endpoint, array $data): HttpResponse
    {
        $url = $this -> baseUrl . $endpoint;
        $ch = curl_init($url);        

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        
        curl_close($ch);
        
        return new HttpResponse($httpCode, $responseBody, $error);
    }
    
    /**
     * Send GET request to API endpoint
     */
    public function get(string $endpoint): HttpResponse
    {
        $url = $this->baseUrl . $endpoint;
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        
        curl_close($ch);
        
        return new HttpResponse($httpCode, $responseBody, $error);
    }
}
