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
            $errorDetails = sprintf(
                'Database error: %s (Code: %s, File: %s:%d)',
                $e->getMessage(),
                $e->getCode(),
                basename($e->getFile()),
                $e->getLine()
            );
            error_log('ContactController::getContacts PDOException: ' . $errorDetails);
            return [
                'statusCode' => 500,
                'isSuccess' => false,
                'error' => $errorDetails,
                'contacts' => []
            ];
        } catch (\Exception $e) {
            $errorDetails = sprintf(
                'Error: %s (File: %s:%d)',
                $e->getMessage(),
                basename($e->getFile()),
                $e->getLine()
            );
            error_log('ContactController::getContacts Exception: ' . $errorDetails);
            return [
                'statusCode' => 500,
                'isSuccess' => false,
                'error' => $errorDetails,
                'contacts' => []
            ];
        } catch (\Throwable $e) {
            $errorDetails = sprintf(
                'Fatal error: %s (File: %s:%d)',
                $e->getMessage(),
                basename($e->getFile()),
                $e->getLine()
            );
            error_log('ContactController::getContacts Throwable: ' . $errorDetails);
            return [
                'statusCode' => 500,
                'isSuccess' => false,
                'error' => $errorDetails,
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
