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
        $response = $this->contactService->getContacts($page);        
        $contacts = $response->getData([$this, 'processResult']) ?? [];
        
        $formattedContacts = [];
        foreach ($contacts as $contact) {
            $formattedContacts[] = [
                'id' => $contact->id,
                'companyName' => $contact->companyName,
                'customerNumber' => $contact->customerNumber
            ];
        }
        
        return [
            'statusCode' => $response->getStatusCode(),
            'isSuccess' => $response->isSuccess(),
            'error' => $response->getError(),
            'contacts' => $formattedContacts
        ];
    }
    

    public function processResult($data) 
    {
        $parsedContacts = [];
        
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $contactData) {
                $parsedContacts[] = new Contact($contactData);
            }
        }
        return $parsedContacts;
    }
}
