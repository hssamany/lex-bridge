<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use Closure;
use Exception;
use Luxullus\LexBridge\Http\HttpClient;
use Luxullus\LexBridge\Http\HttpResponse;
use Luxullus\LexBridge\Logger;
use Luxullus\LexBridge\Models\Contact;
use Luxullus\LexBridge\Repositories\CustomerRepository;

/**
 * Service class to manage contact operations
 */
final class ContactService
{
    private HttpClient $client;
    private CustomerRepository $customerRepository;
    private ?Closure $contactFetcher;
    
    public function __construct(HttpClient $client, ?CustomerRepository $customerRepository = null, ?callable $contactFetcher = null)
    {
        $this->client = $client;
        $this->customerRepository = $customerRepository ?? new CustomerRepository();
        $this->contactFetcher = $contactFetcher !== null ? Closure::fromCallable($contactFetcher) : null;
    }
    
    /**
     * Retrieve contacts from the API
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
        return $this->customerRepository->getCustomerContacts();
    }
    
    /**
     * Sync contacts from API, persist them, and return locally stored rows.
     *
     * @param int $page Page number
     * @return array{response: HttpResponse, contacts: array<int, array<string, string|null>>}
     */
    public function syncContacts(int $page = 0): array
    {
        $response = $this->getContacts($page);

        $contacts = [];
        $errorMessage = null;

        if ($response->isSuccess()) {
            $contacts = $response->getData(fn($d) => Contact::fromResponseData($d)) ?? [];

            foreach ($contacts as $contact) {
                try {
                    $this->customerRepository->updateContact($contact);
                } catch (Exception $e) {
                    Logger::exception($e, 'ContactService - Update Contact');
                }
            }
        } else {
            $errorMessage = $response->getMessage() ?? 'Failed to synchronize contacts from Lexware.';
        }

        return [
            'response' => $response,
            'error' => $errorMessage,
            'contacts' => $this->customerRepository->getCustomerContacts()
        ];
    }

    public function updateCustomerArticle(int $customerId, ?int $articleId): array
    {
        try {
            $this->customerRepository->updateCustomerArticleMapping($customerId, $articleId);
        } catch (Exception $exception) {
            return [
                'statusCode' => 500,
                'isSuccess' => false,
                'error' => 'Aktualisierung der Artikelzuordnung fehlgeschlagen: ' . $exception->getMessage(),
                'contacts' => $this->customerRepository->getCustomerContacts(),
            ];
        }

        $message = $articleId === null
            ? 'Artikelzuordnung entfernt.'
            : 'Artikelzuordnung aktualisiert.';

        return [
            'statusCode' => 200,
            'isSuccess' => true,
            'error' => null,
            'message' => $message,
            'contacts' => $this->customerRepository->getCustomerContacts(),
        ];
    }
}
