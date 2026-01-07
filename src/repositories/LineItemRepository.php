<?php
declare(strict_types=1);

require_once __DIR__ . '/../database/Database.php';

class LineItemRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Fetch line items with optional filters
     *
     * @param array $filters
     * @return array
     */
    public function findLineItems(array $filters = []): array
    {
        $sql = "SELECT 
                    c.customer_number,
                    c.company_name,
                    c.id AS customer_id,
                    li.invoice_id,
                    li.line_order,
                    li.name,
                    li.quantity,
                    li.net_amount,
                    li.tax_rate_percentage,
                    li.line_total_net,
                    li.line_total_gross,
                    li.created_at,
                    i.voucher_date
                FROM invoice_line_items li
                INNER JOIN invoices i ON li.invoice_id = i.id
                LEFT JOIN customer c ON i.contact_id = c.id";

        $where = [];
        $params = [];

        if (!empty($filters['created_at_from'])) {
            $where[] = 'li.created_at >= :created_from';
            $params[':created_from'] = $filters['created_at_from'];
        }

        if (!empty($filters['created_at_to'])) {
            $where[] = 'li.created_at <= :created_to';
            $params[':created_to'] = $filters['created_at_to'];
        }

        if (!empty($filters['customer_id'])) {
            $where[] = 'i.contact_id = :customer_id';
            $params[':customer_id'] = (int)$filters['customer_id'];
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY li.created_at DESC, li.line_order ASC LIMIT 200';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
