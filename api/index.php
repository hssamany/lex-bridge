<?php

declare(strict_types=1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

require_once __DIR__ . '/../bootstrap.php';

/**
 * Simple API Router
 */
class ApiRouter
{
    private array $routes = [];
    
    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }
    
    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }
    
    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        
        // Try PATH_INFO first (from rewrite), then parse URI
        if (!empty($_SERVER['PATH_INFO'])) {
            $path = $_SERVER['PATH_INFO'];
        } else {
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            // Remove base path and api prefix
            $path = str_replace('/lex-bridge/api', '', $uri);
            $path = str_replace('/index.php', '', $path);
        }
        
        // Ensure path starts with /
        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }
        
        // Remove trailing slashes
        $path = rtrim($path, '/');
        if (empty($path)) {
            $path = '/';
        }
        
        if (!isset($this->routes[$method][$path])) {
            $this->jsonResponse([
                'error' => 'Not found', 
                'path' => $path, 
                'method' => $method,
                'available_routes' => array_keys($this->routes[$method] ?? [])
            ], 404);
            return;
        }
        
        try {
            $result = call_user_func($this->routes[$method][$path]);
            $this->jsonResponse($result);
        } catch (Exception $e) {
            error_log('API Error: ' . $e->getMessage());
            $this->jsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    
    private function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}

// Initialize router
$router = new ApiRouter();

// Contact routes
$router->get('/contacts', function() {
    $client = new HttpClient(API_KEY, API_BASE_URL);
    $service = new ContactService($client);
    $controller = new ContactController($service);
    
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
        'options' => ['default' => 0, 'min_range' => 0]
    ]);
    
    return $controller->getContacts($page);
});

// Invoice routes
$router->get('/invoices', function() {
    $client = new HttpClient(API_KEY, API_BASE_URL);
    $repository = new InvoiceRepository();
    $service = new InvoiceService($client, $repository);
    $controller = new InvoiceController($service);
    
    return $controller->getInvoices();
});

$router->post('/invoices/transfer', function() {
    $client = new HttpClient(API_KEY, API_BASE_URL);
    $repository = new InvoiceRepository();
    $service = new InvoiceService($client, $repository);
    $controller = new InvoiceController($service);
    
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

// Handle the request
$router->handle();
