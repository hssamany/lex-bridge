<?php

require_once __DIR__ . '/../repositories/ContactRepository.php';

/**
 * Controller class to handle contact-related requests
 */
final class ContactController
{
    private ContactService $contactService;
    private ContactRepository $contactRepository;
    
    public function __construct(ContactService $contactService)
    {
        $this->contactService = $contactService;
        $this->contactRepository = new ContactRepository();
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
        $contacts = $response->getData(fn($d) => Contact::fromResponseData($d)) ?? [];
        
        $formattedContacts = [];

        foreach ($contacts as $contact) {
            // Update contact in database based on company name
            try {
                $this->contactRepository->updateContact($contact);
                
            } catch (Exception $e) {
                // Log error but continue processing
                error_log("Failed to update contact: " . $e->getMessage());
            }
            
            $formattedContacts[] = [
                'id' => $contact->lexContactId,
                'companyName' => $contact->companyName,
                'customerNumber' => $contact->lexCustomerNumber
            ];
        }
        
        return [
            'statusCode' => $response->getStatusCode(),
            'isSuccess' => $response->isSuccess(),
            'error' => $response->getError(),
            'contacts' => $formattedContacts
        ];
    }
}
