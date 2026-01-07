<?php

declare(strict_types=1);

namespace Luxullus\LexBridge\Repositories;

use PDO;

class InvoiceItemRepository
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Creates invoice and its line items using the stored procedure.
     *
     * @param int $customerId
     * @param string|null $currency
     * @param array $lineItems (array of ["article_id" => int, "quantity" => float])
     * @return array ["invoice_id" => int, "error_code" => int, "error_message" => string]
     */
    public function createInvoiceWithItems($customerId, $currency, array $lineItems)
    {
        try {

            $stmt = $this->db->prepare('CALL create_invoice_from_selection(:customer_id, :currency, :line_items, @invoice_id, @error_code, @error_message)');
            $jsonLineItems = json_encode($lineItems);

            $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
            $stmt->bindValue(':currency', $currency, PDO::PARAM_STR);
            $stmt->bindValue(':line_items', $jsonLineItems, PDO::PARAM_STR);

            $stmt->execute();

            $result = $this->db->query('SELECT @invoice_id AS invoice_id, @error_code AS error_code, @error_message AS error_message')->fetch(PDO::FETCH_ASSOC);
            
            return $result;

        } catch (\PDOException $e) {
            // Log the exception as needed
            return [
                'invoice_id' => null,
                'error_code' => -1,
                'error_message' => 'Database error: ' . $e->getMessage(),
            ];
        }
    }
}
