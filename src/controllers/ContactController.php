<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Controllers;

use Luxullus\LexBridge\Logger;
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
     * @return array Formatted contact data
     */
    public function getContacts(): array
    {
        try {
            $contacts = $this->contactService->listContacts();

            return [
                'statusCode' => 200,
                'isSuccess' => true,
                'error' => null,
                'contacts' => $contacts
            ];
        } catch (\PDOException $e) {
            Logger::exception($e, 'ContactController - Get Contacts');
            return [
                'statusCode' => 500,
                'isSuccess' => false,
                'error' => 'A database error occurred. Please try again later.',
                'contacts' => []
            ];
        } catch (\Exception $e) {
            Logger::exception($e, 'ContactController - Get Contacts');
            return [
                'statusCode' => 500,
                'isSuccess' => false,
                'error' => 'An error occurred while retrieving contacts.',
                'contacts' => []
            ];
        } catch (\Throwable $e) {
            Logger::exception($e, 'ContactController - Get Contacts');
            return [
                'statusCode' => 500,
                'isSuccess' => false,
                'error' => 'An unexpected error occurred.',
                'contacts' => []
            ];
        }
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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateContactArticle(array $payload): array
    {
        $customerIdRaw = $payload['customer_id'] ?? null;
        $articleIdRaw = $payload['article_id'] ?? null;

        $customerId = filter_var($customerIdRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($customerId === false || $customerId === null) {
            return [
                'statusCode' => 422,
                'isSuccess' => false,
                'error' => 'customer_id ist erforderlich.',
            ];
        }

        $articleId = null;
        if ($articleIdRaw !== null && $articleIdRaw !== '') {
            $articleId = filter_var($articleIdRaw, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);

            if ($articleId === false || $articleId === null) {
                return [
                    'statusCode' => 422,
                    'isSuccess' => false,
                    'error' => 'article_id ist ungültig.',
                ];
            }
        }

        return $this->contactService->updateCustomerArticle($customerId, $articleId);
    }
}
