<?php

declare(strict_types=1);

class ApiKernel
{
    private ApiRouter $router;
    private HttpClient $httpClient;

    public function __construct()
    {
        $this->httpClient = new HttpClient(API_KEY, API_BASE_URL);
        $this->router = new ApiRouter();

        $this->postInvoiceRouteRegistration();
        $this->getContactsRouteRegistration();
        $this->postContactRouteRegistration();
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
