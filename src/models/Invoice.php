<?php

/**
 * Invoice Model
 * Represents an invoice that can be stored locally and transmitted to Lexware
 */
final class Invoice
{
    // Database fields
    public ?string $id = null; // UUID
    public int $contactId;
    public string $voucherDate;
    public bool $archived = false;
    public string $title = 'Rechnung';
    public ?string $introduction = null;
    public ?string $remark = null;
    
    // Total price
    public string $currency = 'EUR';
    public ?float $totalNetAmount = null;
    public ?float $totalGrossAmount = null;
    
    // Tax conditions
    public string $taxType = 'net';
    
    // Payment conditions
    public ?string $paymentTermLabel = null;
    public ?int $paymentTermDuration = null;
    public ?float $paymentDiscountPercentage = null;
    public ?int $paymentDiscountRange = null;
    
    // Shipping conditions
    public ?string $shippingDate = null;
    public ?string $shippingType = 'delivery';
    
    // Status tracking
    public string $status = 'draft';
    
    // Lex API response fields
    public ?string $lexId = null;
    public ?string $lexResourceUri = null;
    public int $lexVersion = 0;
    public ?string $lexCreatedDate = null;
    public ?string $lexUpdatedDate = null;
    
    // Error tracking
    public ?string $lastErrorMessage = null;
    public ?string $lastErrorCode = null;
    public int $transmissionAttempts = 0;
    public ?string $lastTransmissionAttempt = null;
    
    // Timestamps
    public ?string $createdAt = null;
    public ?string $updatedAt = null;
    public ?string $transmittedAt = null;
    
    // Related data (loaded from joins)
    public ?string $customerNumber = null;
    public ?string $companyName = null;
    public ?array $lineItems = null; // Array of InvoiceLineItem objects
    
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
     * Set line items from array data
     */
    public function setLineItemsFromLexware(array $lineItemsData): void
    {
        $this->lineItems = [];
        foreach ($lineItemsData as $index => $itemData) {
            $this->lineItems[] = InvoiceLineItem::fromLexware($itemData, $this->id, $index + 1);
        }
        
        // Calculate totals
        $this->calculateTotals();
    }
    
    /**
     * Calculate invoice totals from line items
     */
    public function calculateTotals(): void
    {
        if (empty($this->lineItems)) {
            $this->totalNetAmount = 0;
            $this->totalGrossAmount = 0;
            return;
        }
        
        $totalNet = 0;
        $totalGross = 0;
        
        foreach ($this->lineItems as $item) {
            if ($item->lineTotalNet !== null) {
                $totalNet += $item->lineTotalNet;
            }
            if ($item->lineTotalGross !== null) {
                $totalGross += $item->lineTotalGross;
            }
        }
        
        $this->totalNetAmount = round($totalNet, 2);
        $this->totalGrossAmount = round($totalGross, 2);
    }
    
    /**
     * Create invoice from database row
     */
    public static function fromDatabase(array $row): self
    {
        $invoice = new self();
        
        $invoice->id = $row['id'] ?? null;
        $invoice->contactId = $row['contact_id'];
        $invoice->voucherDate = $row['voucher_date'];
        $invoice->archived = (bool) ($row['archived'] ?? false);
        $invoice->title = $row['title'] ?? 'Rechnung';
        $invoice->introduction = $row['introduction'] ?? null;
        $invoice->remark = $row['remark'] ?? null;
        
        $invoice->currency = $row['currency'] ?? 'EUR';
        $invoice->totalNetAmount = $row['total_net_amount'] ?? null;
        $invoice->totalGrossAmount = $row['total_gross_amount'] ?? null;
        
        $invoice->taxType = $row['tax_type'] ?? 'net';
        
        $invoice->paymentTermLabel = $row['payment_term_label'] ?? null;
        $invoice->paymentTermDuration = $row['payment_term_duration'] ?? null;
        $invoice->paymentDiscountPercentage = $row['payment_discount_percentage'] ?? null;
        $invoice->paymentDiscountRange = $row['payment_discount_range'] ?? null;
        
        $invoice->shippingDate = $row['shipping_date'] ?? null;
        $invoice->shippingType = $row['shipping_type'] ?? 'delivery';
        
        $invoice->status = $row['status'] ?? 'draft';
        
        $invoice->lexId = $row['lex_id'] ?? null;
        $invoice->lexResourceUri = $row['lex_resource_uri'] ?? null;
        $invoice->lexVersion = $row['lex_version'] ?? 0;
        $invoice->lexCreatedDate = $row['lex_created_date'] ?? null;
        $invoice->lexUpdatedDate = $row['lex_updated_date'] ?? null;
        
        $invoice->lastErrorMessage = $row['last_error_message'] ?? null;
        $invoice->lastErrorCode = $row['last_error_code'] ?? null;
        $invoice->transmissionAttempts = $row['transmission_attempts'] ?? 0;
        $invoice->lastTransmissionAttempt = $row['last_transmission_attempt'] ?? null;
        
        $invoice->createdAt = $row['created_at'] ?? null;
        $invoice->updatedAt = $row['updated_at'] ?? null;
        $invoice->transmittedAt = $row['transmitted_at'] ?? null;
        
        // Related data from joins
        $invoice->customerNumber = $row['customer_number'] ?? null;
        $invoice->companyName = $row['company_name'] ?? null;
        
        return $invoice;
    }
    
    /**
     * Convert to array for database insert/update
     */
    public function toDatabase(): array
    {
        // Generate UUID if this is a new invoice
        if ($this->id === null) {
            $this->id = self::generateUuid();
        }
        
        return [
            'id' => $this->id,
            'contact_id' => $this->contactId,
            'voucher_date' => $this->voucherDate,
            'archived' => $this->archived,
            'title' => $this->title,
            'introduction' => $this->introduction,
            'remark' => $this->remark,
            'currency' => $this->currency,
            'total_net_amount' => $this->totalNetAmount,
            'total_gross_amount' => $this->totalGrossAmount,
            'tax_type' => $this->taxType,
            'payment_term_label' => $this->paymentTermLabel,
            'payment_term_duration' => $this->paymentTermDuration,
            'payment_discount_percentage' => $this->paymentDiscountPercentage,
            'payment_discount_range' => $this->paymentDiscountRange,
            'shipping_date' => $this->shippingDate,
            'shipping_type' => $this->shippingType,
            'status' => $this->status
        ];
    }
    
    /**
     * Convert to Lexware API JSON payload format
     */
    public function toLexwarePayload(): array
    {
        if (!$this->customerNumber) {
            throw new \Exception('Customer number is required for Lexware API');
        }
        
        // Build line items array
        $lineItemsPayload = [];
        if ($this->lineItems) {
            foreach ($this->lineItems as $item) {
                $lineItemsPayload[] = $item->toLexwarePayload();
            }
        }
        
        $payload = [
            'archived' => $this->archived,
            'voucherDate' => $this->formatDateForLexware($this->voucherDate),
            'address' => [
                'customerId' => $this->customerNumber
            ],
            'lineItems' => $lineItemsPayload,
            'totalPrice' => [
                'currency' => $this->currency
            ],
            'taxConditions' => [
                'taxType' => $this->taxType
            ],
            'title' => $this->title,
            'introduction' => $this->introduction ?? '',
            'remark' => $this->remark ?? ''
        ];
        
        // Add payment conditions if present
        if ($this->paymentTermLabel || $this->paymentTermDuration) {
            $payload['paymentConditions'] = [
                'paymentTermLabel' => $this->paymentTermLabel,
                'paymentTermDuration' => $this->paymentTermDuration
            ];
            
            if ($this->paymentDiscountPercentage !== null) {
                $payload['paymentConditions']['paymentDiscountConditions'] = [
                    'discountPercentage' => $this->paymentDiscountPercentage,
                    'discountRange' => $this->paymentDiscountRange
                ];
            }
        }
        
        // Add shipping conditions if present
        if ($this->shippingDate || $this->shippingType) {
            $payload['shippingConditions'] = [
                'shippingDate' => $this->formatDateForLexware($this->shippingDate),
                'shippingType' => $this->shippingType
            ];
        }
        
        return $payload;
    }
    
    /**
     * Update with Lexware API response
     */
    public function updateFromLexwareResponse(array $response): void
    {
        $this->lexId = $response['id'] ?? null;
        $this->lexResourceUri = $response['resourceUri'] ?? null;
        $this->lexVersion = $response['version'] ?? 0;
        $this->lexCreatedDate = $this->convertLexwareDate($response['createdDate'] ?? null);
        $this->lexUpdatedDate = $this->convertLexwareDate($response['updatedDate'] ?? null);
        $this->status = 'transmitted';
        $this->transmittedAt = date('Y-m-d H:i:s');
        $this->lastErrorMessage = null;
        $this->lastErrorCode = null;
    }
    
    /**
     * Mark invoice as having transmission error
     */
    public function markAsError(string $errorMessage, ?string $errorCode = null): void
    {
        $this->status = 'transmission_error';
        $this->lastErrorMessage = $errorMessage;
        $this->lastErrorCode = $errorCode;
        $this->transmissionAttempts++;
        $this->lastTransmissionAttempt = date('Y-m-d H:i:s');
    }
    
    /**
     * Check if invoice is transmitted to Lexware
     */
    public function isTransmitted(): bool
    {
        return $this->status === 'transmitted' && !empty($this->lexId);
    }
    
    /**
     * Check if invoice has transmission error
     */
    public function hasError(): bool
    {
        return $this->status === 'transmission_error';
    }
    
    /**
     * Format date for Lexware API (ISO 8601 with timezone)
     */
    private function formatDateForLexware(?string $date): ?string
    {
        if (!$date) {
            return null;
        }
        
        try {
            $dt = new \DateTime($date);
            return $dt->format('Y-m-d\TH:i:s.vP');
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Convert Lexware date to MySQL datetime
     */
    private function convertLexwareDate(?string $lexDate): ?string
    {
        if (!$lexDate) {
            return null;
        }
        
        try {
            $dt = new \DateTime($lexDate);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }
}
