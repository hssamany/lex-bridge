<?php

/**
 * Class to represent a Contact
 */
final class Contact
{
    public readonly string $id;
    public readonly string $organizationId;
    public readonly int $version;
    public readonly int $customerNumber;
    public readonly string $companyName;
    public readonly bool $allowTaxFreeInvoices;
    public readonly array $billingAddresses;
    public readonly bool $archived;
    
    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? '';
        $this->organizationId = $data['organizationId'] ?? '';
        $this->version = $data['version'] ?? 0;
        $this->customerNumber = $data['roles']['customer']['number'] ?? 0;
        $this->companyName = $data['company']['name'] ?? '';
        $this->allowTaxFreeInvoices = $data['company']['allowTaxFreeInvoices'] ?? false;
        $this->billingAddresses = $data['addresses']['billing'] ?? [];
        $this->archived = $data['archived'] ?? false;
    }
    
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'organizationId' => $this->organizationId,
            'version' => $this->version,
            'roles' => [
                'customer' => [
                    'number' => $this->customerNumber
                ]
            ],
            'company' => [
                'name' => $this->companyName,
                'allowTaxFreeInvoices' => $this->allowTaxFreeInvoices
            ],
            'archived' => $this->archived
        ];
        
        if (!empty($this->billingAddresses)) {
            $result['addresses'] = [
                'billing' => $this->billingAddresses
            ];
        }
        
        return $result;
    }
    
    public static function create(
        string $id,
        string $organizationId,
        int $version,
        int $customerNumber,
        string $companyName,
        bool $allowTaxFreeInvoices = false,
        array $billingAddresses = [],
        bool $archived = false
    ): self {
        
        $contactData = [
            'id' => $id,
            'organizationId' => $organizationId,
            'version' => $version,
            'roles' => [
                'customer' => [
                    'number' => $customerNumber
                ]
            ],
            'company' => [
                'name' => $companyName,
                'allowTaxFreeInvoices' => $allowTaxFreeInvoices
            ],
            'archived' => $archived
        ];
        
        if (!empty($billingAddresses)) {
            $contactData['addresses'] = [
                'billing' => $billingAddresses
            ];
        }
        
        return new self($contactData);
    }
}
