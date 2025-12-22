<?php

/**
 * Controller class to handle contact-related requests
 */
final class ContactController
{
    private ContactService $contactService;
    
    public function __construct(ContactService $contactService)
    {
        $this->contactService = $contactService;
    }    
    
    /**
     * Retrieve and display contacts
     * 
     * @param int $page Page number
     * @return array Formatted contact data
     */
    public function getContacts(int $page = 0): array
    {
        $result = $this->contactService->syncContacts($page);
        $response = $result['response'];
        $contacts = $result['contacts'];
        
        // Convert Contact objects to arrays for JSON serialization
        $formattedContacts = array_map(fn($contact) => $contact->toArray(), $contacts);
                
        return [
            'statusCode' => $response->getStatusCode(),
            'isSuccess' => $response->isSuccess(),
            'error' => $response->getError(),
            'contacts' => $formattedContacts
        ];
    }
}
