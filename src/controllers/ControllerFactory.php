<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Controllers;

use Luxullus\LexBridge\Services\ContactService;
use Luxullus\LexBridge\Controllers\InvoiceController;
use Luxullus\LexBridge\Services\InvoiceService;
use Luxullus\LexBridge\Repositories\InvoiceRepository;
use Luxullus\LexBridge\Controllers\CustomerController;
use Luxullus\LexBridge\Services\CustomerService;
use Luxullus\LexBridge\Repositories\CustomerRepository;
use Luxullus\LexBridge\Controllers\LineItemController;
use Luxullus\LexBridge\Services\LineItemService;
use Luxullus\LexBridge\Repositories\LineItemRepository;
use Luxullus\LexBridge\Controllers\ArticleController;
use Luxullus\LexBridge\Services\ArticleService;
use Luxullus\LexBridge\Repositories\ArticleRepository;
use Luxullus\LexBridge\Controllers\OrderController;
use Luxullus\LexBridge\Services\OrderService;
use Luxullus\LexBridge\Repositories\OrderRepository;
use Luxullus\LexBridge\Http\HttpClient;


final class ControllerFactory
{
    public static function makeContactController(HttpClient $client): ContactController
    {
        $service = new ContactService($client);
        return new ContactController($service);
    }

    public static function makeInvoiceController(HttpClient $client): InvoiceController
    {
        $repository = new InvoiceRepository();
        $service = new InvoiceService($client, $repository);
        return new InvoiceController($service);
    }
    public static function makeCustomerController(HttpClient $client): CustomerController
    {
        $repository = new CustomerRepository();
        $service = new CustomerService($repository);
        return new CustomerController($service);
    }
    public static function makeLineItemController(HttpClient $client): LineItemController
    {
        $repository = new LineItemRepository();
        $service = new LineItemService($repository);
        return new LineItemController($service);
    }

    public static function makeArticleController(HttpClient $client): ArticleController
    {
        $repository = new ArticleRepository();
        $service = new ArticleService($repository, $client);
        return new ArticleController($service);
    }

    public static function makeOrderController(): OrderController
    {
        $repository = new OrderRepository();
        $service = new OrderService($repository);
        return new OrderController($service);
    }
}
