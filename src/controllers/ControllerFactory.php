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
}
