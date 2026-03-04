<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Repositories;

use PDO;
use Luxullus\LexBridge\Models\Contact;
use Luxullus\LexBridge\Database\Database;
use Luxullus\LexBridge\Logger;

final class CustomerRepository
{
    private PDO $db;
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
     * Search customers by customer number or company name.
     *
     * @param string|null $query
     * @return array<int, array<string, mixed>>
     */
    public function searchCustomers(?string $query): array
    {
        if ($query === null || $query === '') {
            return [];
        }

        $sql = <<<SQL
            SELECT 
                id, 
                Nummer AS customer_number, 
                Name AS company_name 
                
            FROM {$this->customerTable}
            WHERE Nummer LIKE :customerNumber OR Name LIKE :companyName
            ORDER BY Nummer ASC 
            LIMIT 20
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':customerNumber' => $query . '%',
            ':companyName' => $query . '%'
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Update contact metadata for a customer row.
     */
    public function updateContact(Contact $contact): int
    {
        $sql = <<<SQL
            UPDATE {$this->customerTable}
            SET lex_contact_id = :lex_contact_id,
                lex_customer_number = :lex_customer_number
            WHERE (:customer_number - CAST(Nummer AS UNSIGNED)) = 10000
            AND Nummer IS NOT NULL
        SQL;

        $stmt = $this->db->prepare($sql);

        $params = [
            ':lex_contact_id' => $contact->lexContactId,
            ':lex_customer_number' => $contact->lexCustomerNumber,
            ':customer_number' => $contact->lexCustomerNumber
        ];

        $success = $stmt->execute($params);
        $rowCount = $stmt->rowCount();

        // Debug logging
        Logger::info('CustomerRepository::updateContact - ' . json_encode([
            'companyName' => $contact->companyName,
            'lexContactId' => $contact->lexContactId,
            'lexCustomerNumber' => $contact->lexCustomerNumber,
            'rowsAffected' => $rowCount,
            'executeSuccess' => $success
        ]));

        return $rowCount;
    }

    /**
     * Fetch contacts with article mappings from the customer table.
     *
     * @param array{limit:int,offset:int} $pagination
     * @return array{items:array<int,array<string,mixed>>,total_count:int}
     */
    public function getCustomerContacts(array $pagination): array
    {
        $baseSql = <<<SQL
            FROM {$this->customerTable} AS c
            LEFT JOIN {$this->customerArticleTable} AS ca ON ca.customer_id = c.id
            LEFT JOIN {$this->articleTable} AS a ON a.id = ca.article_id
        SQL;

        $countSql = "SELECT COUNT(*) AS total {$baseSql}";
        $countStmt = $this->db->query($countSql);
        $totalCount = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $sql = <<<SQL
            SELECT c.Name AS company_name,
                   c.id AS customer_id,
                   c.Nummer AS customer_number,
                   c.lex_contact_id,
                   c.lex_customer_number,
                   ca.article_id,
                   a.article_number,
                   a.name AS article_name
              {$baseSql}
             ORDER BY c.Nummer ASC
             LIMIT :limit OFFSET :offset
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total_count' => $totalCount,
        ];
    }

    /**
     * Delete customer-article mapping.
     */
    public function deleteCustomerArticleMapping(int $customerId): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->customerArticleTable} WHERE customer_id = :customer_id"
        );
        $stmt->execute([':customer_id' => $customerId]);
    }

    /**
     * Clear article mapping for all customers except the specified one.
     */
    public function clearArticleMappingForOtherCustomers(int $articleId, int $exceptCustomerId): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->customerArticleTable} 
             WHERE article_id = :article_id AND customer_id <> :customer_id"
        );
        $stmt->execute([
            ':article_id' => $articleId,
            ':customer_id' => $exceptCustomerId,
        ]);
    }

    /**
     * Find existing customer-article mapping.
     *
     * @return array<string, mixed>|null
     */
    public function findCustomerArticleMapping(int $customerId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT customer_id, article_id 
             FROM {$this->customerArticleTable} 
             WHERE customer_id = :customer_id"
        );
        $stmt->execute([':customer_id' => $customerId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Update existing customer-article mapping.
     */
    public function updateCustomerArticleMapping(int $customerId, int $articleId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->customerArticleTable} 
             SET article_id = :article_id 
             WHERE customer_id = :customer_id"
        );
        $stmt->execute([
            ':customer_id' => $customerId,
            ':article_id' => $articleId,
        ]);
    }

    /**
     * Insert new customer-article mapping.
     */
    public function insertCustomerArticleMapping(int $customerId, int $articleId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->customerArticleTable} (customer_id, article_id) 
             VALUES (:customer_id, :article_id)"
        );
        $stmt->execute([
            ':customer_id' => $customerId,
            ':article_id' => $articleId,
        ]);
    }

    /**
     * Find customer row by Lexware contact ID.
     *
     * @return array<string, mixed>|null
     */
    public function findByLexContactId(string $lexContactId): ?array
    {
        $sql = <<<SQL
            SELECT *
              FROM {$this->customerTable}
             WHERE lex_contact_id = :lex_contact_id
             LIMIT 1
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':lex_contact_id' => $lexContactId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
