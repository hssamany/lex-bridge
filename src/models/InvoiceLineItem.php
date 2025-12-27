<?php

/**
 * InvoiceLineItem Model
 * Represents a single line item in an invoice
 */
final class InvoiceLineItem
{
    public ?string $id = null; // UUID
    public string $invoiceId; // UUID reference
    public int $lineOrder;
    public string $type; // 'custom', 'text', 'material', 'service'
    public string $name;
    public ?string $description = null;
    public ?float $quantity = null;
    public ?string $unitName = null;
    public ?string $currency = 'EUR';
    public ?float $netAmount = null;
    public ?float $taxRatePercentage = null;
    public ?float $discountPercentage = 0;
    public ?float $lineTotalNet = null;
    public ?float $lineTotalGross = null;
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    
    /**
     * Generate a new UUID v4
     */
    public static function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Create from database row
     */
    public static function fromDatabase(array $row): self
    {
        $item = new self();
        
        $item->id = $row['id'] ?? null;
        $item->invoiceId = $row['invoice_id'];
        $item->lineOrder = (int) $row['line_order'];
        $item->type = $row['type'];
        $item->name = $row['name'];
        $item->description = $row['description'] ?? null;
        $item->quantity = $row['quantity'] ?? null;
        $item->unitName = $row['unit_name'] ?? null;
        $item->currency = $row['currency'] ?? 'EUR';
        $item->netAmount = $row['net_amount'] ?? null;
        $item->taxRatePercentage = $row['tax_rate_percentage'] ?? null;
        $item->discountPercentage = $row['discount_percentage'] ?? 0;
        $item->lineTotalNet = $row['line_total_net'] ?? null;
        $item->lineTotalGross = $row['line_total_gross'] ?? null;
        $item->createdAt = $row['created_at'] ?? null;
        $item->updatedAt = $row['updated_at'] ?? null;
        
        return $item;
    }
    
    /**
     * Create from Lexware API line item format
     */
    public static function fromLexware(array $data, string $invoiceId, int $lineOrder): self
    {
        $item = new self();
        $item->id = self::generateUuid();
        $item->invoiceId = $invoiceId;
        $item->lineOrder = $lineOrder;
        $item->type = $data['type'] ?? 'custom';
        $item->name = $data['name'] ?? '';
        $item->description = $data['description'] ?? null;
        
        // Only set pricing for non-text items
        if ($item->type !== 'text') {
            $item->quantity = $data['quantity'] ?? 1;
            $item->unitName = $data['unitName'] ?? null;
            $item->currency = $data['unitPrice']['currency'] ?? 'EUR';
            $item->netAmount = $data['unitPrice']['netAmount'] ?? 0;
            $item->taxRatePercentage = $data['unitPrice']['taxRatePercentage'] ?? 0;
            $item->discountPercentage = $data['discountPercentage'] ?? 0;
            
            // Calculate line totals
            $item->calculateTotals();
        }
        
        return $item;
    }
    
    /**
     * Calculate line totals based on quantity, price, discount, and tax
     */
    public function calculateTotals(): void
    {
        if ($this->type === 'text' || $this->quantity === null || $this->netAmount === null) {
            $this->lineTotalNet = null;
            $this->lineTotalGross = null;
            return;
        }
        
        // Calculate net total with discount
        $netBeforeDiscount = $this->quantity * $this->netAmount;
        $discountAmount = $netBeforeDiscount * ($this->discountPercentage / 100);
        $this->lineTotalNet = $netBeforeDiscount - $discountAmount;
        
        // Calculate gross total with tax
        $taxAmount = $this->lineTotalNet * ($this->taxRatePercentage / 100);
        $this->lineTotalGross = $this->lineTotalNet + $taxAmount;
        
        // Round to 2 decimal places
        $this->lineTotalNet = round($this->lineTotalNet, 2);
        $this->lineTotalGross = round($this->lineTotalGross, 2);
    }
    
    /**
     * Convert to array for database insert/update
     */
    public function toDatabase(): array
    {
        // Generate UUID if this is a new line item
        if ($this->id === null) {
            $this->id = self::generateUuid();
        }
        
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoiceId,
            'line_order' => $this->lineOrder,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_name' => $this->unitName,
            'currency' => $this->currency,
            'net_amount' => $this->netAmount,
            'tax_rate_percentage' => $this->taxRatePercentage,
            'discount_percentage' => $this->discountPercentage,
            'line_total_net' => $this->lineTotalNet,
            'line_total_gross' => $this->lineTotalGross
        ];
    }
    
    /**
     * Convert to Lexware API format
     */
    public function toLexwarePayload(): array
    {
        $payload = [
            'type' => $this->type,
            'name' => $this->name
        ];
        
        if ($this->description) {
            $payload['description'] = $this->description;
        }
        
        // Only add pricing for non-text items
        if ($this->type !== 'text') {
            $payload['quantity'] = $this->quantity;
            $payload['unitName'] = $this->unitName;
            $payload['unitPrice'] = [
                'currency' => $this->currency,
                'netAmount' => $this->netAmount,
                'taxRatePercentage' => $this->taxRatePercentage
            ];
            $payload['discountPercentage'] = $this->discountPercentage;
        }
        
        return $payload;
    }
    
    /**
     * Check if this is a text item (no pricing)
     */
    public function isTextItem(): bool
    {
        return $this->type === 'text';
    }
}
