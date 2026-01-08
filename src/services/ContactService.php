<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use Exception;
use Luxullus\LexBridge\Http\HttpClient;
use Luxullus\LexBridge\Http\HttpResponse;
use Luxullus\LexBridge\Models\Contact;
use Luxullus\LexBridge\Repositories\ContactRepository;
/**
 * Service class to manage contact operations
 */
final class ContactService
{
    private HttpClient $client;
    private ContactRepository $contactRepository;
    
    public function __construct(HttpClient $client)
    {
        $this->client = $client;
        $this->contactRepository = new ContactRepository();
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
    
    /**
     * Sync contacts from API and return HttpResponse with Contact objects
     * 
     * @param int $page Page number
     * @return array ['response' => HttpResponse, 'contacts' => Contact[]]
     */
    public function syncContacts(int $page = 0): array
    {
        $response = $this->getContacts($page);
        
        $contacts = [];
        
        if ($response->isSuccess()) {

            $contacts = $response->getData(fn($d) => Contact::fromResponseData($d));
            $contacts ??=   [];
            
            // Update database
            foreach ($contacts as $contact) {
                try {
                    $this->contactRepository->updateContact($contact);
                } catch (Exception $e) {
                    error_log("Failed to update contact: " . $e->getMessage());
                }
            }
        }
        
        return [
            'response' => $response,
            'contacts' => $contacts
        ];
    }
}
