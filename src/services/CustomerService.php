<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Services;

use Luxullus\LexBridge\Models\Customer;
use Luxullus\LexBridge\Repositories\CustomerRepository;

final class CustomerService
{
    private CustomerRepository $repository;

    public function __construct(CustomerRepository $repository)
    {
        $this->repository = $repository;
    }

    public function searchCustomers(?string $query): array
    {
        return $this->repository->searchCustomers($query);
    }
}
