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
use Luxullus\LexBridge\Services\Pagination;

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
        
        if ($normalizedQuery === null || $normalizedQuery === '') {
            return [];
        }

        $rows = $this->repository->searchCustomers($normalizedQuery);
        
        return $this->transformToCustomerModels($rows);
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
     * @param array<string, mixed> $pagination
     * @return array{contacts:array<int,array<string,string|null>>,total_count:int,page:int,page_size:int,total_pages:int}
     */
    public function listContacts(array $pagination = []): array
    {
        $paginationState = Pagination::normalize($pagination);
        $result = $this->repository->getCustomerContacts($paginationState);
        $rows = $result['items'] ?? [];
        $totalCount = (int) ($result['total_count'] ?? 0);

        $contacts = $this->enrichContactData($rows);

        return [
            'contacts' => $contacts,
            'total_count' => $totalCount,
            'page' => $paginationState['page'],
            'page_size' => $paginationState['page_size'],
            'total_pages' => Pagination::totalPages($totalCount, $paginationState['page_size']),
        ];
    }

    /**
     * Sync contacts from Lexware API, persist them, and return locally stored rows.
     *
     * @param int $page Page number
     * @return array{response:HttpResponse,contacts:array<int,array<string,string|null>>,error:string|null}
     */
    public function syncContacts(int $page = 0, array $pagination = []): array
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

        $listResult = $this->listContacts($pagination);
        $enrichedContacts = $listResult['contacts'];

        return [
            'response' => $response,
            'error' => $errorMessage,
            'contacts' => $enrichedContacts,
            'total_count' => $listResult['total_count'],
            'page' => $listResult['page'],
            'page_size' => $listResult['page_size'],
            'total_pages' => $listResult['total_pages'],
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
            $this->performArticleMapping($customerId, $articleId);
        } catch (Exception $exception) {
            Logger::exception($exception, 'CustomerService - Update Customer Article Mapping');
            
            $rowsResult = $this->repository->getCustomerContacts(Pagination::normalize());
            $rows = $rowsResult['items'] ?? [];
            
            return [
                'statusCode' => 500,
                'isSuccess' => false,
                'error' => 'Aktualisierung der Artikelzuordnung fehlgeschlagen: ' . $exception->getMessage(),
                'message' => null,
                'contacts' => $this->enrichContactData($rows),
            ];
        }

        $message = $articleId === null
            ? 'Artikelzuordnung entfernt.'
            : 'Artikelzuordnung aktualisiert.';

        Logger::info('Customer article mapping updated', [
            'customer_id' => $customerId,
            'article_id' => $articleId
        ]);

        $rowsResult = $this->repository->getCustomerContacts(Pagination::normalize());
        $rows = $rowsResult['items'] ?? [];

        return [
            'statusCode' => 200,
            'isSuccess' => true,
            'error' => null,
            'message' => $message,
            'contacts' => $this->enrichContactData($rows),
        ];
    }

    /**
     * Find contact by Lexware contact ID.
     */
    public function findContactByLexwareId(string $lexContactId): ?Contact
    {
        $row = $this->repository->findByLexContactId($lexContactId);
        
        if ($row === null) {
            return null;
        }

        return $this->transformRowToContact($row);
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
     * Transform database rows to Customer models.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, Customer>
     */
    private function transformToCustomerModels(array $rows): array
    {
        $customers = [];
        
        foreach ($rows as $row) {
            $customer = new Customer();
            $customer->id = isset($row['id']) ? (int) $row['id'] : 0;
            $customer->customer_number = isset($row['customer_number']) ? (string) $row['customer_number'] : '';
            $customer->company_name = isset($row['company_name']) ? (string) $row['company_name'] : '';
            $customers[] = $customer;
        }

        return $customers;
    }

    /**
     * Enrich contact data with formatted fields.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, string|null>>
     */
    private function enrichContactData(array $rows): array
    {
        return array_map(function (array $row): array {
            $articleLabel = $this->buildArticleLabel(
                $row['article_number'] ?? null,
                $row['article_name'] ?? null
            );

            return [
                'customerId' => isset($row['customer_id']) ? (int) $row['customer_id'] : null,
                'companyName' => $row['company_name'] ?? '',
                'customerNumber' => $row['customer_number'] ?? '',
                'lexContactId' => $row['lex_contact_id'] ?? '',
                'lexCustomerNumber' => $row['lex_customer_number'] ?? '',
                'articleId' => isset($row['article_id']) ? (int) $row['article_id'] : null,
                'articleLabel' => $articleLabel
            ];
        }, $rows);
    }

    /**
     * Build article label from number and name.
     */
    private function buildArticleLabel(?string $articleNumber, ?string $articleName): ?string
    {
        if (empty($articleNumber) && empty($articleName)) {
            return null;
        }

        $number = $articleNumber ?? '';
        $name = $articleName ?? '';
        
        return trim($number . ' - ' . $name, ' -');
    }

    /**
     * Perform article mapping with business logic.
     */
    private function performArticleMapping(int $customerId, ?int $articleId): void
    {
        if ($articleId === null) {
            $this->repository->deleteCustomerArticleMapping($customerId);
            return;
        }

        // Business rule: Ensure article is only mapped to one customer
        $this->repository->clearArticleMappingForOtherCustomers($articleId, $customerId);

        // Upsert the mapping
        $existingMapping = $this->repository->findCustomerArticleMapping($customerId);
        
        if ($existingMapping !== null) {
            $this->repository->updateCustomerArticleMapping($customerId, $articleId);
        } else {
            $this->repository->insertCustomerArticleMapping($customerId, $articleId);
        }
    }

    /**
     * Transform database row to Contact model.
     *
     * @param array<string, mixed> $row
     */
    private function transformRowToContact(array $row): Contact
    {
        $contactData = [
            'id' => $row['lex_contact_id'] ?? '',
            'organizationId' => $row['organization_id'] ?? null,
            'version' => isset($row['version']) ? (int) $row['version'] : 0,
            'roles' => [
                'customer' => [
                    'number' => isset($row['lex_customer_number']) ? (int) $row['lex_customer_number'] : 0
                ]
            ],
            'company' => [
                'name' => $row['Name'] ?? '',
                'allowTaxFreeInvoices' => isset($row['allow_tax_free_invoices']) ? (bool) $row['allow_tax_free_invoices'] : false
            ],
            'archived' => isset($row['archived']) ? (bool) $row['archived'] : false
        ];

        return new Contact($contactData);
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
