<?php
declare(strict_types=1);

class CustomerService
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
