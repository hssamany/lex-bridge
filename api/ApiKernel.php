<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Api;

use DateTime;
use Luxullus\LexBridge\Http\HttpClient;
use Luxullus\LexBridge\Controllers\ControllerFactory;
use Luxullus\LexBridge\Logger;

final class ApiKernel {

    private ApiRouter $router;
    private HttpClient $httpClient;

    public function __construct()
    {
        Logger::info('[ApiKernel] Constructor started', 'ApiKernel');
        
        $this->httpClient = new HttpClient(API_KEY, API_BASE_URL);
        $this->router = new ApiRouter();

        Logger::info('[ApiKernel] Registering routes...', 'ApiKernel');
        
        $this->getInvoicesRouteRegistration();
        $this->postInvoiceRouteRegistration();
        $this->postInvoiceCreateRouteRegistration();
        $this->getContactsRouteRegistration();
        $this->postContactsSyncRouteRegistration();
        $this->postContactsArticleRouteRegistration();
        $this->getCustomersSearchRouteRegistration();
        $this->getArticlesSearchRouteRegistration();
        $this->postArticlesSyncRouteRegistration();
        $this->getLineItemsRouteRegistration();
        $this->postLineItemUpdateRouteRegistration();
        // Note: postInvoiceCreateRouteRegistration already called above - removed duplicate
        $this->getOrdersRouteRegistration();
        $this->postOrdersGenerateRouteRegistration();
        $this->postOrdersGenerateInvoicesRouteRegistration();
        
        Logger::info('[ApiKernel] All routes registered successfully', 'ApiKernel');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function parsePagination(array $payload = []): array
    {
        $pagination = [];

        $pageRaw = $payload['page'] ?? ($_GET['page'] ?? null);
        if (is_numeric($pageRaw)) {
            $pagination['page'] = (int) $pageRaw;
        }

        $pageSizeRaw = $payload['page_size'] ?? ($_GET['page_size'] ?? null);
        if (is_numeric($pageSizeRaw)) {
            $pagination['page_size'] = (int) $pageSizeRaw;
        }

        return $pagination;
    }

    private function getCustomersSearchRouteRegistration(): void
    {
        $this->router->get('/customers/search', function() {
            $controller = ControllerFactory::makeCustomerController($this->httpClient);
            $query = isset($_GET['q']) ? trim($_GET['q']) : null;
            Logger::info('[ApiKernel] Searching customers with query: ' . ($query ?? 'null'), 'ApiKernel');
            return $controller->searchCustomers($query);
        });
    }

    private function getArticlesSearchRouteRegistration(): void
    {
        $this->router->get('/articles/search', function() {
            $controller = ControllerFactory::makeArticleController($this->httpClient);
            $query = isset($_GET['q']) ? trim($_GET['q']) : null;
            Logger::info('[ApiKernel] Searching articles with query: ' . ($query ?? 'null'), 'ApiKernel');
            return $controller->searchArticles($query);
        });
    }

    private function postArticlesSyncRouteRegistration(): void
    {
        $this->router->post('/articles/sync', function() {
            $controller = ControllerFactory::makeArticleController($this->httpClient);

            $payload = json_decode(file_get_contents('php://input'), true);
            $page = null;

            if (is_array($payload) && array_key_exists('page', $payload)) {
                $pageCandidate = filter_var($payload['page'], FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0]
                ]);
                if ($pageCandidate !== false && $pageCandidate !== null) {
                    $page = $pageCandidate;
                }
            }

            if ($page === null && isset($_GET['page'])) {
                $pageCandidate = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0]
                ]);
                if ($pageCandidate !== false && $pageCandidate !== null) {
                    $page = $pageCandidate;
                }
            }

            return $controller->syncArticles($page);
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

            $pagination = $this->parsePagination();
            return $controller->getLineItems($filters, $pagination);
        });
    }

    private function postLineItemUpdateRouteRegistration(): void
    {
        $this->router->post('/line-items/update', function() {
            $controller = ControllerFactory::makeLineItemController($this->httpClient);
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                $data = [];
            }

            return $controller->updateLineItem($data);
        });
    }

    private function getOrdersRouteRegistration(): void
    {
        $this->router->get('/orders', function () {
            $controller = ControllerFactory::makeOrderController();

            $filters = [];

            $changedFromRaw = isset($_GET['geaendertAm_from']) ? trim((string) $_GET['geaendertAm_from']) : '';
            
            if ($changedFromRaw === '') {
                return [
                    'isSuccess' => false,
                    'error' => 'geaendertAm_from is required',
                ];
            }

            $filters['geaendertAm_from'] = $changedFromRaw;

            $changedToRaw = isset($_GET['geaendertAm_to']) ? trim((string) $_GET['geaendertAm_to']) : '';
            
            if ($changedToRaw !== '') {
                $filters['geaendertAm_to'] = $changedToRaw;
            }

            $customerIdRaw = $_GET['customer_id'] ?? null;
            
            if ($customerIdRaw !== null && $customerIdRaw !== '') {
                $customerId = filter_var($customerIdRaw, FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1],
                ]);

                if ($customerId !== false && $customerId !== null) {
                    $filters['customer_id'] = $customerId;
                }
            }
            Logger::info('XXXXXXXXXXXXXXXXXXXx-3: '. json_encode($filters), 'ApiKernel');
            $pagination = $this->parsePagination();
            return $controller->getOrders($filters, $pagination);
        });
    }

    private function postOrdersGenerateRouteRegistration(): void
    {
        $this->router->post('/orders/generate-line-items', function () {
            $controller = ControllerFactory::makeOrderController();

            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $payload = [];
            }

            $orderIds = [];

            if (isset($payload['order_ids']) && is_array($payload['order_ids'])) {
                $orderIds = $payload['order_ids'];
            } elseif (array_key_exists('order_id', $payload)) {
                $orderIds[] = $payload['order_id'];
            } elseif (isset($_POST['order_ids']) && is_array($_POST['order_ids'])) {
                $orderIds = $_POST['order_ids'];
            } elseif (isset($_POST['order_id'])) {
                $orderIds[] = $_POST['order_id'];
            }

            if (!$orderIds) {
                return [
                    'isSuccess' => false,
                    'error' => 'At least one order_id must be provided.',
                ];
            }

            return $controller->generateLineItemsForOrders($orderIds);
        });
    }

    private function postOrdersGenerateInvoicesRouteRegistration(): void
    {
        $this->router->post('/orders/generate-invoices', function () {
            
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $payload = [];
            }

            $orderIds = [];            

            if (isset($payload['order_ids']) && is_array($payload['order_ids'])) {
                $orderIds = $payload['order_ids'];
            } elseif (array_key_exists('order_id', $payload)) {
                $orderIds[] = $payload['order_id'];
            } elseif (isset($_POST['order_ids']) && is_array($_POST['order_ids'])) {
                $orderIds = $_POST['order_ids'];
            } elseif (isset($_POST['order_id'])) {
                $orderIds[] = $_POST['order_id'];
            }

            if (!$orderIds) {
                return [
                    'isSuccess' => false,
                    'error' => 'At least one order_id must be provided.',
                ];
            }
//---
            Logger::info('XXXXXX2' . json_encode($orderIds), 'ApiKernel');
            $orderController = ControllerFactory::makeOrderController();
            return $orderController->generateInvoicesFromOrders($orderIds);
        });
    }
    
    // Contact routes
    private function getContactsRouteRegistration(): void
    {
        $this->router -> get('/contacts', function() {
            $controller = ControllerFactory::makeContactController($this->httpClient);
            $pagination = $this->parsePagination();
            return $controller->getContacts($pagination);
        });
    }
    
    private function postContactsSyncRouteRegistration(): void
    {
        $this->router -> post('/contacts/sync', function() {
            $controller = ControllerFactory::makeContactController($this->httpClient);
            $page = 0;

            $payload = json_decode(file_get_contents('php://input'), true);
            if (is_array($payload) && array_key_exists('page', $payload)) {
                $pageCandidate = filter_var($payload['page'], FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0]
                ]);
                if ($pageCandidate !== false && $pageCandidate !== null) {
                    $page = $pageCandidate;
                }
            }

            if (isset($_GET['page'])) {
                $pageCandidate = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 0]
                ]);
                if ($pageCandidate !== false && $pageCandidate !== null) {
                    $page = $pageCandidate;
                }
            }
            $pagination = $this->parsePagination($payload);

            return $controller->syncContacts($page, $pagination);
        });
    }

    private function postContactsArticleRouteRegistration(): void
    {
        $this->router->post('/contacts/article', function () {
            $controller = ControllerFactory::makeContactController($this->httpClient);
            $payload = json_decode(file_get_contents('php://input'), true);
            if (!is_array($payload)) {
                $payload = [];
            }

            return $controller->updateContactArticle($payload);
        });
    }

    // Invoice routes
    private function getInvoicesRouteRegistration(): void
    {
        $this->router -> get('/invoices', function() {
            $controller = ControllerFactory::makeInvoiceController($this->httpClient);
            
            // Build filters from query parameters
            $filters = [];
            
            if (!empty($_GET['customer_id'])) {
                $filters['contact_id'] = (int) $_GET['customer_id'];
            }
            
            if (!empty($_GET['status'])) {
                $filters['status'] = $_GET['status'];
            }
            
            if (!empty($_GET['voucher_date_from'])) {
                $filters['from_date'] = $_GET['voucher_date_from'];
            }
            
            if (!empty($_GET['voucher_date_to'])) {
                $filters['to_date'] = $_GET['voucher_date_to'];
            }
            $pagination = $this->parsePagination();
            
            return $controller->getInvoices($filters, $pagination);
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

    private function postInvoiceCreateRouteRegistration(): void
    {
        $this->router->post('/invoices', function() {

            $controller = ControllerFactory::makeInvoiceController($this->httpClient);
            $data = json_decode(file_get_contents('php://input'), true);
            $customerId = $data['customer_id'] ?? null;
            $currency = $data['currency'] ?? null;
            $lineItems = $data['line_items'] ?? [];

            
            if (empty($lineItems)) {
                return [
                    'isSuccess' => false,
                    'error' => 'customer_id and line_items are required'
                ];
            }
            return $controller->createInvoiceWithItems($lineItems);
        });
    }

    public function handle(): void
    {
        error_log(sprintf(
            '[ApiKernel] handle() called - Method: %s, URI: %s, Query: %s',
            $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            $_SERVER['REQUEST_URI'] ?? '/',
            $_SERVER['QUERY_STRING'] ?? 'none'
        ));
        
        try {
            $this->router->handle();
            error_log('[ApiKernel] Request handled successfully');
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[ApiKernel] Exception during routing: %s (File: %s:%d)',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
            throw $e;
        }
    }
}
