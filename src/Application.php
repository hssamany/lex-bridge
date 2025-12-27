<?php

declare(strict_types=1);

/**
 * Main Application class - handles routing and request lifecycle
 */
final class Application
{
    private HttpClient $apiClient;
    private ContactService $contactService;
    private ContactController $contactController;
    private InvoiceService $invoiceService;
    private InvoiceController $invoiceController;
    
    public function __construct(string $apiKey, string $baseUrl)
    {
        $this->apiClient = new HttpClient($apiKey, $baseUrl);
        
        // Contact dependencies
        $this->contactService = new ContactService($this->apiClient);
        $this->contactController = new ContactController($this->contactService);
        
        // Invoice dependencies
        $invoiceRepository = new InvoiceRepository();
        $this->invoiceService = new InvoiceService($this->apiClient, $invoiceRepository);
        $this->invoiceController = new InvoiceController($this->invoiceService);
    }
    
    /**
     * Run the application - handle routing
     */
    public function run(): void
    {
        $action = $_GET['action'] ?? 'home';
        
        try {
            match($action) {
                'get-contacts' => $this->handleGetContacts(),
                'get-invoices' => $this->handleGetInvoices(),
                'home' => $this->displayHome(),
                default => $this->handle404()
            };
        } catch (Exception $e) {
            $this-> handleError($e);
        }
    }
    
    /**
     * Handle get-contacts action
     */
    private function handleGetContacts(): void
    {
        $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
            'options' => ['default' => 0, 'min_range' => 0]
        ]);
        
        $_SESSION['contactsData'] = $this->contactController->getContacts($page);
        
        $this->redirect('?action=home&status=success');
    }
    
    /**
     * Handle get-invoices action
     */
    private function handleGetInvoices(): void
    {
        $_SESSION['invoicesData'] = $this->invoiceController->getInvoices();
        
        $this->redirect('?action=home&status=success');
    }
    
    /**
     * Display home page
     */
    private function displayHome(): void
    {
        $contactsData = $_SESSION['contactsData'] ?? [
            'statusCode' => 0,
            'isSuccess' => false,
            'error' => null,
            'contacts' => []
        ];
        
        $invoicesData = $_SESSION['invoicesData'] ?? [
            'success' => false,
            'invoices' => []
        ];
        
        $status = $_GET['status'] ?? null;
        
        // Clear one-time messages
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['error']);
        
        // Create view instance
        require_once __DIR__ . '/../views/home/homeView.php';
        $homeView = new HomeView($status, $contactsData, $error, $invoicesData);
        
        // Display view
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
            <p>The requested action was not found.</p>
            <a href="?action=home">Go Home</a>
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
        $this->redirect('?action=home&status=error');
    }
    
    /**
     * Redirect helper
     */
    private function redirect(string $url): void
    {
        header('Location: ' . $url, true, 303);
        exit;
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
