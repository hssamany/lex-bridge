<?php

/**
 * Class to represent a Contact
 */
final class Contact
{
    public readonly string $lexContactId;
    public readonly int $lexCustomerNumber;
    public readonly string $companyName;
    
    public function __construct(array $data)
    {
        $this->lexContactId = $data['id'] ?? '';
        $this->lexCustomerNumber = $data['roles']['customer']['number'] ?? 0;
        $this->companyName = $data['company']['name'] ?? '';
    }
    
    /**
     * Create Contact instance from array data
     * 
     * @param array $data Contact data array
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }
    
    /**
     * Create array of Contact instances from API response data
     * 
     * @param array $data Response data containing 'content' array
     * @return array Array of Contact instances
     */
    public static function fromResponseData(array $data): array
    {
        $parsedContacts = [];
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $contactData) {
                $parsedContacts[] = self::fromArray($contactData);
            }
        }
        return $parsedContacts;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->lexContactId,
            'companyName' => $this->companyName,
            'customerNumber' => $this->lexCustomerNumber
        ];
    }
}
