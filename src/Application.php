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
        // Check if this is an API request by URI or query param
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $apiParam = $_GET['api'] ?? null;
        
        if (str_contains($uri, '/api/') || $apiParam) {
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
        $uri = $_SERVER['REQUEST_URI'];
        $apiParam = $_GET['api'] ?? '';
        
        // Determine the endpoint from URI or api parameter
        $endpoint = $apiParam ?: $uri;
        
        try {
            if ($method === 'GET' && str_contains($endpoint, 'invoices')) {
                $client = new HttpClient(API_KEY, API_BASE_URL);
                $repository = new InvoiceRepository();
                $service = new InvoiceService($client, $repository);
                $controller = new InvoiceController($service);
                
                $result = $controller->getInvoices();
                echo json_encode($result);
                
            } elseif ($method === 'GET' && str_contains($endpoint, 'contacts')) {
                $client = new HttpClient(API_KEY, API_BASE_URL);
                $service = new ContactService($client);
                $controller = new ContactController($service);
                
                $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
                    'options' => ['default' => 0, 'min_range' => 0]
                ]);
                
                $result = $controller->getContacts($page);
                echo json_encode($result);
                
            } elseif ($method === 'POST' && str_contains($endpoint, 'transfer')) {
                $data = json_decode(file_get_contents('php://input'), true);
                $invoiceId = $data['invoice_id'] ?? null;
                
                if (!$invoiceId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invoice ID required']);
                    return;
                }
                
                $client = new HttpClient(API_KEY, API_BASE_URL);
                $repository = new InvoiceRepository();
                $service = new InvoiceService($client, $repository);
                $controller = new InvoiceController($service);
                
                $result = $controller->transferInvoiceToLexware($invoiceId);
                echo json_encode($result);
                
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'API endpoint not found', 'endpoint' => $endpoint, 'method' => $method]);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        
        exit;
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
        $contactsData = [
            'statusCode' => 0,
            'isSuccess' => false,
            'error' => null,
            'contacts' => []
        ];
        
        $invoicesData = [
            'success' => false,
            'invoices' => []
        ];
        
        require_once __DIR__ . '/../views/home/homeView.php';
        $homeView = new HomeView($status, $contactsData, $error, $invoicesData);
        
        $this->render('home/home', compact('contactsData', 'invoicesData', 'status', 'error', 'homeView'));
    }
    
    /**
     * Handle 404 errors
     */
    private function handle404(): void
    {
        http_response_code(404);
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>404 - Not Found</title>
        </head>
        <body>
            <h1>404 - Page Not Found</h1>
            <p>The requested page was not found.</p>
            <a href="/lex-bridge/">Go Home</a>
        </body>
        </html>';
        exit;
    }
    
    /**
     * Handle application errors
     */
    private function handleError(Exception $e): void
    {
        error_log('Error in Application: ' . $e->getMessage());
        $_SESSION['error'] = 'An error occurred. Please try again.';
        $this->displayHome();
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
