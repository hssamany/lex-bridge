<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../models/Customer.php';

class CustomerRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Search customers by customer number or company name
     * @param string|null $query
     * @return Customer[]
     */
    public function searchCustomers(?string $query): array
    {
        $query = $query ?? '';

        $sql = "SELECT id, customer_number, company_name FROM customer";
        $params = [];

        if ($query !== '') {
            $sql .= " WHERE customer_number LIKE :customerNumber OR company_name LIKE :companyName";
            $params[':customerNumber'] = $query . '%';
            $params[':companyName'] = $query . '%';
        }

        $sql .= " ORDER BY customer_number ASC LIMIT 20";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
