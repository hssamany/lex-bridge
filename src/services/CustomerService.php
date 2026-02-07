<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use Closure;
use Exception;
use Luxullus\LexBridge\Http\HttpClient;
use Luxullus\LexBridge\Http\HttpResponse;
use Luxullus\LexBridge\Logger;
use Luxullus\LexBridge\Models\Contact;
use Luxullus\LexBridge\Models\Customer;
use Luxullus\LexBridge\Repositories\CustomerRepository;

/**
 * Service class to manage customer and contact operations
 */
final class CustomerService
{
    private CustomerRepository $repository;
    private HttpClient $client;
    private ?Closure $contactFetcher;

    public function __construct(
        HttpClient $client,
        ?CustomerRepository $repository = null,
        ?callable $contactFetcher = null
    ) {
        $this->client = $client;
        $this->repository = $repository ?? new CustomerRepository();
        $this->contactFetcher = $contactFetcher !== null ? Closure::fromCallable($contactFetcher) : null;
    }

    /**
     * Search customers by customer number or company name.
     *
     * @param string|null $query
     * @return array<int, Customer>
     */
    public function searchCustomers(?string $query): array
    {
        $normalizedQuery = $this->normalizeSearchQuery($query);
        return $this->repository->searchCustomers($normalizedQuery);
    }

    /**
     * Retrieve contacts from Lexware API.
     *
     * @param int $page Page number (default: 0)
     * @return HttpResponse
     */
    public function getContacts(int $page = 0): HttpResponse
    {
        if ($this->contactFetcher !== null) {
            return ($this->contactFetcher)($page);
        }

        return $this->client->get('/contacts?page=' . $page);
    }

    /**
     * Return the contacts persisted locally in the customer table.
     *
     * @return array<int, array<string, string|null>>
     */
    public function listContacts(): array
    {
        return $this->repository->getCustomerContacts();
    }

    /**
     * Sync contacts from Lexware API, persist them, and return locally stored rows.
     *
     * @param int $page Page number
     * @return array{response:HttpResponse,contacts:array<int,array<string,string|null>>,error:string|null}
     */
    public function syncContacts(int $page = 0): array
    {
        $response = $this->getContacts($page);
        $errorMessage = null;

        if ($response->isSuccess()) {
            $contacts = $this->extractContactsFromResponse($response);
            $this->persistContacts($contacts);
        } else {
            $errorMessage = $response->getMessage() ?? 'Failed to synchronize contacts from Lexware.';
            Logger::info('Contact sync failed', ['page' => $page, 'error' => $errorMessage]);
        }

        return [
            'response' => $response,
            'error' => $errorMessage,
            'contacts' => $this->repository->getCustomerContacts()
        ];
    }

    /**
     * Update customer-article mapping.
     *
     * @param int $customerId
     * @param int|null $articleId Null to remove mapping
     * @return array{statusCode:int,isSuccess:bool,error:string|null,message:string|null,contacts:array<int,array<string,string|null>>}
     */
    public function updateCustomerArticle(int $customerId, ?int $articleId): array
    {
        try {
            $this->repository->updateCustomerArticleMapping($customerId, $articleId);
        } catch (Exception $exception) {
            Logger::exception($exception, 'CustomerService - Update Customer Article Mapping');
            
            return [
                'statusCode' => 500,
                'isSuccess' => false,
                'error' => 'Aktualisierung der Artikelzuordnung fehlgeschlagen: ' . $exception->getMessage(),
                'message' => null,
                'contacts' => $this->repository->getCustomerContacts(),
            ];
        }

        $message = $articleId === null
            ? 'Artikelzuordnung entfernt.'
            : 'Artikelzuordnung aktualisiert.';

        Logger::info('Customer article mapping updated', [
            'customer_id' => $customerId,
            'article_id' => $articleId
        ]);

        return [
            'statusCode' => 200,
            'isSuccess' => true,
            'error' => null,
            'message' => $message,
            'contacts' => $this->repository->getCustomerContacts(),
        ];
    }

    /**
     * Normalize search query input.
     */
    private function normalizeSearchQuery(?string $query): ?string
    {
        if ($query === null) {
            return null;
        }

        $trimmed = trim($query);
        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Extract and transform contacts from API response.
     *
     * @return array<int, Contact>
     */
    private function extractContactsFromResponse(HttpResponse $response): array
    {
        $contacts = $response->getData(fn($d) => Contact::fromResponseData($d)) ?? [];

        if (empty($contacts)) {
            Logger::info('No contacts returned from API');
        }

        return $contacts;
    }

    /**
     * Persist contacts to database with error handling.
     *
     * @param array<int, Contact> $contacts
     */
    private function persistContacts(array $contacts): void
    {
        $successCount = 0;
        $errorCount = 0;

        foreach ($contacts as $contact) {
            try {
                $this->repository->updateContact($contact);
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                Logger::exception($e, 'CustomerService - Update Contact', [
                    'company_name' => $contact->companyName ?? '(unknown)'
                ]);
            }
        }

        Logger::info('Contact persistence completed', [
            'total' => count($contacts),
            'success' => $successCount,
            'errors' => $errorCount
        ]);
    }
}
