<?php

/**
 * Class to represent API response
 */
final class HttpResponse
{
    private int $statusCode;
    private ?string $error;
    private string $body;
    
    public function __construct(int $statusCode, string $body, ?string $error = null)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
        $this->error = $error;
    }
    
    public function getStatusCode(): int
    {
        return $this -> statusCode;
    }
    
    public function getBody(): string
    {
        return $this -> body;
    }
    
    public function getError(): ?string
    {
        return $this -> error;
    }
    
    public function getMessage(): ?string
    {
        // 1. Check if there's a connection error first
        if ($this->error) {
            return $this->error;
        }
        
        // 2. Try to get from JSON body
        $data = $this->toArray();
        $bodyMessage = $data['message'] 
            ?? $data['msg'] 
            ?? $data['error_description'] 
            ?? $data['detail']
            ?? null;
        
        // 3. Fall back to status code description
        return $bodyMessage ?? $this->getStatusText();
    }
    
    public function isSuccess(): bool
    {
        return $this -> statusCode >= 200 && $this->statusCode < 300 && !$this->error;
    }
    
    public function toArray(): array
    {
        return json_decode($this->body, true) ?? [];
    }
    
    private function getStatusText(): string
    {
        return match($this->statusCode) {
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'HTTP Error ' . $this->statusCode
        };
    }

    /**
     * Process response data with a callback
     * 
     * @param callable $callback Function to process the decoded data
     * @return mixed Processed data or null if response is not successful
     */
    public function getData(callable $callback): mixed
    {
        if ($this->isSuccess()) {
            $data = $this->toArray();
            return $callback($data);
        }

        return null;
    }
}
