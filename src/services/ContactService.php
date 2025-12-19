<?php

/**
 * Service class to manage contact operations
 */
final class ContactService
{
    private HttpClient $client;
    
    public function __construct(HttpClient $client)
    {
        $this->client = $client;
    }
    
    /**
     * Retrieve contacts from the API
     * 
     * @param int $page Page number (default: 0)
     * @return HttpResponse
     */
    public function getContacts(int $page = 0): HttpResponse
    {
        return $this->client->get('/contacts?page=' . $page);
    }
}
