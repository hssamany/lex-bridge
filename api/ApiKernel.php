
<?php

declare(strict_types=1);

namespace Lukullus\LexBridge\API;

use Lukullus\LexBridge\HttpClient;
use Lukullus\LexBridge\ControllerFactory;

class ApiKernel
{
    private ApiRouter $router;
    private HttpClient $httpClient;

    public function __construct()
    {
        $this->httpClient = new HttpClient(API_KEY, API_BASE_URL);
        $this->router = new ApiRouter();

        $this->getInvoicesRouteRegistration();
        $this->postInvoiceRouteRegistration();
        $this->getContactsRouteRegistration();
        $this->postContactRouteRegistration();
        $this->getCustomersSearchRouteRegistration();
        $this->getLineItemsRouteRegistration();
        // Customer search route for AJAX dropdown
    }

    private function getCustomersSearchRouteRegistration(): void
    {
        $this->router->get('/customers/search', function() {
            $controller = ControllerFactory::makeCustomerController($this->httpClient);
            $query = isset($_GET['q']) ? trim($_GET['q']) : null;
            return $controller->searchCustomers($query);
        });
    }

    private function getLineItemsRouteRegistration(): void
    {
        $this->router->get('/line-items', function() {
            $controller = ControllerFactory::makeLineItemController($this->httpClient);

            $filters = [];

            $createdAtFrom = isset($_GET['created_at_from']) ? trim((string)$_GET['created_at_from']) : '';
            if ($createdAtFrom !== '') {
                $fromDate = DateTime::createFromFormat('Y-m-d', $createdAtFrom);
                if ($fromDate instanceof DateTime) {
                    $filters['created_at_from'] = $fromDate->format('Y-m-d 00:00:00');
                }
            }

            $createdAtTo = isset($_GET['created_at_to']) ? trim((string)$_GET['created_at_to']) : '';
            if ($createdAtTo !== '') {
                $toDate = DateTime::createFromFormat('Y-m-d', $createdAtTo);
                if ($toDate instanceof DateTime) {
                    $filters['created_at_to'] = $toDate->format('Y-m-d 23:59:59');
                }
            }

            $customerId = filter_input(INPUT_GET, 'customer_id', FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1]
            ]);
            if ($customerId !== null && $customerId !== false) {
                $filters['customer_id'] = $customerId;
            }

            return $controller->getLineItems($filters);
        });
    }
    
    // Contact routes
    private function getContactsRouteRegistration(): void
    {
        $this->router -> get('/contacts', function() {
            $controller = ControllerFactory::makeContactController($this->httpClient);
            $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
                'options' => ['default' => 0, 'min_range' => 0]
            ]);
            return $controller->getContacts($page);
        });
    }
    
    // Create new contact
    private function postContactRouteRegistration(): void
    {
        $this->router -> post('/contacts', function() {
            $controller = ControllerFactory::makeContactController($this->httpClient);
            $data = json_decode(file_get_contents('php://input'), true);
            return $controller->createContact($data);
        });
    }

    // Invoice routes
    private function getInvoicesRouteRegistration(): void
    {
        $this->router -> get('/invoices', function() {
            $controller = ControllerFactory::makeInvoiceController($this->httpClient);
            return $controller->getInvoices();
        });
    }

    // Transfer invoice to Lexware
    private function postInvoiceRouteRegistration(): void
    {     
        $this->router -> post('/invoices/transfer', function() {

            $controller = ControllerFactory::makeInvoiceController($this->httpClient);
            $data = json_decode(file_get_contents('php://input'), true);
            $invoiceId = $data['invoice_id'] ?? $_POST['invoice_id'] ?? null;

            if (!$invoiceId) {
                return [
                    'isSuccess' => false,
                    'error' => 'Invoice ID is required'
                ];
            }

            return $controller->transferInvoiceToLexware($invoiceId);
        });
    }

    public function handle(): void
    {
        $this->router->handle();
    }
}
