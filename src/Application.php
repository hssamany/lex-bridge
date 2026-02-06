<?php

declare(strict_types=1);

namespace Luxullus\LexBridge;

use Exception;
use Luxullus\LexBridge\Views\Home\HomeView;

/**
 * Main Application class - serves initial HTML only
 * All data loading happens via AJAX to /api/
 */
final class Application
{
    /**
     * Run the application - serve the SPA shell
     */
    public function run(): void
    {
        error_log(sprintf(
            '[Application] Starting - action: %s',
            $_GET['action'] ?? 'home'
        ));
        
        $action = $_GET['action'] ?? 'home';
        
        try {
            match($action) {
                'home', '' => $this->displayHome(),
                default => $this->handle404()
            };
            
            error_log('[Application] Completed successfully');
        } catch (Exception $e) {
            error_log(sprintf(
                '[Application] Exception caught: %s (File: %s:%d)',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
            $this->handleError($e);
        }
    }
    
    /**
     * Display home page (SPA shell)
     */
    private function displayHome(): void
    {
         error_log(sprintf(
            '[Application] displayHome(): %s',
            $_GET['action'] ?? 'home'
        ));

        $status = $_GET['status'] ?? null;
        $error = $_SESSION['error'] ?? null;
        unset($_SESSION['error']);
        
        // Empty data - will be loaded via AJAX
        $emptyContactsData = [
            'statusCode' => 0,
            'isSuccess' => false,
            'error' => null,
            'contacts' => []
        ];
        
        $emptyInvoicesData = [
            'success' => false,
            'invoices' => []
        ];
        
        $homeView = new HomeView(
            $status, 
            $emptyContactsData, 
            $error, 
            $emptyInvoicesData
        );
        
        $this->render
        (
            'Home/home', 
            ['homeView' => $homeView]
        );
    }
    
    /**
     * Handle 404 errors
     */
    private function handle404(): void
    {
        http_response_code(HttpStatus::NOT_FOUND);
        
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
        include __DIR__ . "/Views/{$view}.php";
    }
}
