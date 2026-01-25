<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Controllers;

use Luxullus\LexBridge\Services\ContactService;

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
    public function getContacts(): array
    {
        $contacts = $this->contactService->listContacts();

        return [
            'statusCode' => 200,
            'isSuccess' => true,
            'error' => null,
            'contacts' => $contacts
        ];
    }

    public function syncContacts(int $page = 0): array
    {
        $result = $this->contactService->syncContacts($page);
        $response = $result['response'];
        $contacts = $result['contacts'];
        $hasContacts = !empty($contacts);
        $isSuccess = $response->isSuccess() || $hasContacts;

        return [
            'statusCode' => $response->getStatusCode(),
            'isSuccess' => $isSuccess,
            'error' => $result['error'],
            'contacts' => $contacts
        ];
    }
}
