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
    
    public function isSuccess(): bool
    {
        return $this -> statusCode >= 200 && $this->statusCode < 300 && !$this->error;
    }
    
    public function toArray(): array
    {
        return json_decode($this->body, true) ?? [];
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
