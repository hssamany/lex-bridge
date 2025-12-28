<?php

declare(strict_types=1);

/**
 * Main Application class - serves initial HTML only
 * All data loading happens via AJAX to /api/
 */
final class Application
{
    /**
     * Run the application - serve the SPA shell or handle API requests
     */
    public function run(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $isApiRequest = str_contains($uri, '/api/') || isset($_GET['api']);
        
        if ($isApiRequest) {
            $this->handleApiRequest();
            return;
        }
        
        $action = $_GET['action'] ?? 'home';
        
        try {
            match($action) {
                'home', '' => $this->displayHome(),
                default => $this->handle404()
            };
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    /**
     * Handle API requests
     */
    private function handleApiRequest(): void
    {
        header('Content-Type: application/json');
        
        $method = $_SERVER['REQUEST_METHOD'];
        $endpoint = $_GET['api'] ?? $_SERVER['REQUEST_URI'];
        
        try {

            $result = match(true) 
            {
                $method === 'GET' && str_contains($endpoint, 'invoices') => $this->handleGetInvoices(),
                $method === 'GET' && str_contains($endpoint, 'contacts') => $this->handleGetContacts(),
                $method === 'POST' && str_contains($endpoint, 'invoices/transfer') => $this->handleTransferInvoice(),
                
                default => $this->apiNotFound($endpoint, $method)
            };
            
            $this->sendJsonResponse($result);
            
        } catch (Exception $e) {
            $this->sendErrorResponse($e->getMessage(), 500);
        }
        
        exit;
    }
    
    /**
     * Handle GET /invoices endpoint
     */
    private function handleGetInvoices(): array
    {
        $controller = $this->createInvoiceController();
        return $controller->getInvoices();
    }
    
    /**
     * Handle GET /contacts endpoint
     */
    private function handleGetContacts(): array
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
            'options' => ['default' => 0, 'min_range' => 0]
        ]);
        
        $controller = $this->createContactController();
        return $controller->getContacts($page);
    }
    
    /**
     * Handle POST /invoices/transfer endpoint
     */
    private function handleTransferInvoice(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $invoiceId = $data['invoice_id'] ?? null;
        
        if (!$invoiceId) {
            return $this->sendErrorResponse('Invoice ID required', 400);
        }
        
        $controller = $this->createInvoiceController();
        return $controller->transferInvoiceToLexware($invoiceId);
    }
    
    /**
     * Create HTTP client instance
     */
    private function createHttpClient(): HttpClient
    {
        return new HttpClient(API_KEY, API_BASE_URL);
    }
    
    /**
     * Create InvoiceController with dependencies
     */
    private function createInvoiceController(): InvoiceController
    {
        $client = $this->createHttpClient();
        $repository = new InvoiceRepository();
        $service = new InvoiceService($client, $repository);
        return new InvoiceController($service);
    }
    
    /**
     * Create ContactController with dependencies
     */
    private function createContactController(): ContactController
    {
        $client = $this->createHttpClient();
        $service = new ContactService($client);
        return new ContactController($service);
    }
    
    /**
     * Send JSON response
     */
    private function sendJsonResponse(array $data, int $statusCode = 200): array
    {
        http_response_code($statusCode);
        echo json_encode($data);

        return $data;
    }
    
    /**
     * Send error response
     */
    private function sendErrorResponse(string $message, int $statusCode = 500): array
    {
        $error = ['error' => $message];
        http_response_code($statusCode);
        echo json_encode($error);

        return $error;
    }
    
    /**
     * Handle API not found
     */
    private function apiNotFound(string $endpoint, string $method): array
    {
        return $this->sendErrorResponse
        (
            "API endpoint not found: {$method} {$endpoint}",
            404
        );
    }
    
    /**
     * Display home page (SPA shell)
     */
    private function displayHome(): void
    {
        $status = $_GET['status'] ?? null;
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['error']);
        
        // Empty data - will be loaded via AJAX
        $emptyContactsData = $this->createEmptyContactsData();
        $emptyInvoicesData = $this->createEmptyInvoicesData();
        
        $homeView = new HomeView($status, $emptyContactsData, $error, $emptyInvoicesData);
        
        $this->render('home/home', ['homeView' => $homeView]);
    }
    
    /**
     * Create empty contacts data structure
     */
    private function createEmptyContactsData(): array
    {
        return [
            'statusCode' => 0,
            'isSuccess' => false,
            'error' => null,
            'contacts' => []
        ];
    }
    
    /**
     * Create empty invoices data structure
     */
    private function createEmptyInvoicesData(): array
    {
        return [
            'success' => false,
            'invoices' => []
        ];
    }
    
    /**
     * Handle 404 errors
     */
    private function handle404(): void
    {
        http_response_code(404);
        $this->renderErrorPage
        (
            '404 - Not Found',
            '404 - Page Not Found',
            'The requested page was not found.'
        );
        exit;
    }
    
    /**
     * Handle application errors
     */
    private function handleError(Exception $e): void
    {
        error_log('Application Error: ' . $e->getMessage());
        $_SESSION['error'] = 'An error occurred. Please try again.';
        $this->displayHome();
    }
    
    /**
     * Render error page
     */
    private function renderErrorPage(string $title, string $heading, string $message): void
    {
        $this->render('error', compact('title', 'heading', 'message'));
    }
    
    /**
     * Render view helper
     * 
     * @param string $view View name (without .php extension)
     * @param array $data Data to extract into view scope
     */
    private function render(string $view, array $data = []): void
    {
        extract($data);
        include __DIR__ . "/../views/{$view}.php";
    }
}
