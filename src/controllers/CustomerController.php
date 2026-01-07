<?php
declare(strict_types=1);

class CustomerController
{
    private CustomerService $service;

    public function __construct(CustomerService $service)
    {
        $this->service = $service;
    }

    // AJAX: /customers/search?q=...
    public function searchCustomers(string $query = null): array
    {
        return $this->service->searchCustomers($query);
    }
}
