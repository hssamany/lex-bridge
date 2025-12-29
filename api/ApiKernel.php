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
        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        // Contact routes
        $this->router -> get('/contacts', function() {
            $controller = ControllerFactory::makeContactController($this->httpClient);
            $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
                'options' => ['default' => 0, 'min_range' => 0]
            ]);
            return $controller->getContacts($page);
        });

        // Invoice routes
        $this->router -> get('/invoices', function() {
            $controller = ControllerFactory::makeInvoiceController($this->httpClient);
            return $controller->getInvoices();
        });

        // Transfer invoice to Lexware
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
