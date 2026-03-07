<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Controllers;

use Luxullus\LexBridge\Logger;
use Luxullus\LexBridge\Services\CustomerService;

/**
 * Controller class to handle contact-related requests
 */
final class ContactController
{
    private CustomerService $customerService;
    
    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }    
    
    /**
     * Retrieve and display contacts
     * 
     * @return array Formatted contact data
     */
    public function getContacts(array $pagination = []): array
    {
        try {
            $result = $this->customerService->listContacts($pagination);
            $contacts = $result['contacts'] ?? [];

            return [
                'statusCode' => 200,
                'isSuccess' => true,
                'error' => null,
                'contacts' => $contacts,
                'total_count' => $result['total_count'] ?? 0,
                'page' => $result['page'] ?? 1,
                'page_size' => $result['page_size'] ?? 25,
                'total_pages' => $result['total_pages'] ?? 1,
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

    public function syncContacts(int $page = 0, array $pagination = []): array
    {
        $result = $this->customerService->syncContacts($page, $pagination);
        $response = $result['response'];
        $contacts = $result['contacts'];
        $isSuccess = $response->isSuccess();

        $error = null;
        if (!$isSuccess) {
            $error = $result['error'] ?? $response->getMessage() ?? 'Sync failed with status ' . $response->getStatusCode();
        }

        return [
            'statusCode' => $response->getStatusCode(),
            'isSuccess' => $isSuccess,
            'error' => $error,
            'contacts' => $contacts,
            'total_count' => $result['total_count'] ?? 0,
            'page' => $result['page'] ?? 1,
            'page_size' => $result['page_size'] ?? 25,
            'total_pages' => $result['total_pages'] ?? 1,
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

        return $this->customerService->updateCustomerArticle($customerId, $articleId);
    }
}
