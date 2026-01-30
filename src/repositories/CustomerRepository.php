<?php

declare(strict_types=1);


namespace Luxullus\LexBridge\Repositories;

use PDO;
use Luxullus\LexBridge\Models\Customer;
use Luxullus\LexBridge\Database\Database;


class CustomerRepository
{
    private \PDO $db;
    private string $customerTable;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->customerTable = \lexbridge_table('customer');
    }

    /**
     * Search customers by customer number or company name
     * @param string|null $query
     * @return Customer[]
     */
    public function searchCustomers(?string $query): array
    {
        $query = $query ?? '';

        $sql = "SELECT id, kundenNummer AS customer_number, company_name FROM {$this->customerTable}";
        $params = [];

        if ($query !== '') {
            $sql .= " WHERE kundenNummer LIKE :customerNumber OR company_name LIKE :companyName";
            $params[':customerNumber'] = $query . '%';
            $params[':companyName'] = $query . '%';
        }

        $sql .= " ORDER BY kundenNummer ASC LIMIT 20";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $customers = [];
        foreach ($rows as $row) {
            $customer = new Customer();
            $customer->id = isset($row['id']) ? (int)$row['id'] : 0;
            $customer->customer_number = isset($row['customer_number']) ? (string)$row['customer_number'] : '';
            $customer->company_name = isset($row['company_name']) ? (string)$row['company_name'] : '';
            $customers[] = $customer;
        }

        return $customers;
    }
}
