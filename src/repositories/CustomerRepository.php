<?php

declare(strict_types=1);


namespace Luxullus\LexBridge\Repositories;

use PDO;
use Luxullus\LexBridge\Models\Customer;
use Luxullus\LexBridge\Models\Contact;
use Luxullus\LexBridge\Database\Database;


class CustomerRepository
{
    private \PDO $db;
    private string $customerTable;
    private string $customerArticleTable;
    private string $articleTable;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->customerTable = \lexbridge_table('customer');
        $this->customerArticleTable = \lexbridge_table('customers_article');
        $this->articleTable = \lexbridge_table('articles');
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

    /**
     * Update contact metadata for a customer row.
     */
    public function updateContact(Contact $contact): bool
    {
        $sql = <<<SQL
            UPDATE {$this->customerTable}
            SET lex_contact_id = :lex_contact_id,
                lex_customer_number = :lex_customer_number
            WHERE company_name = :company_name
        SQL;

        $stmt = $this->db->prepare($sql);

        $params = [
            ':lex_contact_id' => $contact->lexContactId,
            ':lex_customer_number' => $contact->lexCustomerNumber,
            ':company_name' => $contact->companyName
        ];

        return $stmt->execute($params);
    }

    /**
     * Fetch contacts persisted in the customer table for UI display.
     *
     * @return array<int, array<string, string|null>>
     */
    public function getCustomerContacts(): array
    {
        $sql = <<<SQL
            SELECT c.company_name,
                   c.id AS customer_id,
                   c.kundenNummer AS customer_number,
                   c.lex_customer_number,
                   ca.article_id,
                   a.article_number,
                   a.name AS article_name
              FROM {$this->customerTable} AS c
              LEFT JOIN {$this->customerArticleTable} AS ca ON ca.customer_id = c.id
              LEFT JOIN {$this->articleTable} AS a ON a.id = ca.article_id
             ORDER BY c.company_name ASC
        SQL;

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $row): array {
            $articleLabel = null;
            if (!empty($row['article_number']) || !empty($row['article_name'])) {
                $number = $row['article_number'] ?? '';
                $name = $row['article_name'] ?? '';
                $articleLabel = trim($number . ' - ' . $name, ' -');
            }

            return [
                'customerId' => isset($row['customer_id']) ? (int) $row['customer_id'] : null,
                'companyName' => $row['company_name'] ?? '',
                'customerNumber' => $row['customer_number'] ?? '',
                'lexCustomerNumber' => $row['lex_customer_number'] ?? '',
                'articleId' => isset($row['article_id']) ? (int) $row['article_id'] : null,
                'articleLabel' => $articleLabel
            ];
        }, $rows ?: []);
    }

    public function updateCustomerArticleMapping(int $customerId, ?int $articleId): void
    {
        if ($articleId === null) {
            $stmt = $this->db->prepare("DELETE FROM {$this->customerArticleTable} WHERE customer_id = :customer_id");
            $stmt->execute([':customer_id' => $customerId]);
            return;
        }

        $clearStmt = $this->db->prepare("DELETE FROM {$this->customerArticleTable} WHERE article_id = :article_id AND customer_id <> :customer_id");
        $clearStmt->execute([
            ':article_id' => $articleId,
            ':customer_id' => $customerId,
        ]);

        $existsStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->customerArticleTable} WHERE customer_id = :customer_id");
        $existsStmt->execute([':customer_id' => $customerId]);
        $exists = (int) $existsStmt->fetchColumn() > 0;

        if ($exists) {
            $stmt = $this->db->prepare("UPDATE {$this->customerArticleTable} SET article_id = :article_id WHERE customer_id = :customer_id");
        } else {
            $stmt = $this->db->prepare("INSERT INTO {$this->customerArticleTable} (customer_id, article_id) VALUES (:customer_id, :article_id)");
        }

        $stmt->execute([
            ':customer_id' => $customerId,
            ':article_id' => $articleId,
        ]);
    }

    public function findByLexContactId(string $lexContactId): ?Contact
    {
        $sql = <<<SQL
            SELECT *
              FROM {$this->customerTable}
             WHERE lex_contact_id = :lex_contact_id
             LIMIT 1
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':lex_contact_id' => $lexContactId]);

        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        $contactData = [
            'id' => $row['lex_contact_id'],
            'organizationId' => $row['organization_id'],
            'version' => (int) $row['version'],
            'roles' => [
                'customer' => [
                    'number' => (int) $row['lex_customer_number']
                ]
            ],
            'company' => [
                'name' => $row['company_name'],
                'allowTaxFreeInvoices' => (bool) $row['allow_tax_free_invoices']
            ],
            'archived' => (bool) $row['archived']
        ];

        return new Contact($contactData);
    }
}
