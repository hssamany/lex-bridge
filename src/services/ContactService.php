<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use Closure;
use Exception;
use Luxullus\LexBridge\Http\HttpClient;
use Luxullus\LexBridge\Http\HttpResponse;
use Luxullus\LexBridge\Models\Contact;
use Luxullus\LexBridge\Repositories\ContactRepository;
/**
 * Service class to manage contact operations
 */
final class ContactService
{
    private HttpClient $client;
    private ContactRepository $contactRepository;
    private ?Closure $contactFetcher;
    
    public function __construct(HttpClient $client, ?ContactRepository $contactRepository = null, ?callable $contactFetcher = null)
    {
        $this->client = $client;
        $this->contactRepository = $contactRepository ?? new ContactRepository();
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
        return $this->contactRepository->getCustomerContacts();
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
                    $this->contactRepository->updateContact($contact);
                } catch (Exception $e) {
                    error_log("Failed to update contact: " . $e->getMessage());
                }
            }
        } else {
            $errorMessage = $response->getMessage() ?? 'Failed to synchronize contacts from Lexware.';
        }

        return [
            'response' => $response,
            'error' => $errorMessage,
            'contacts' => $this->contactRepository->getCustomerContacts()
        ];
    }
}
