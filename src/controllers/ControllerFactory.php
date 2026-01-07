<?php
declare(strict_types=1);

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
}
