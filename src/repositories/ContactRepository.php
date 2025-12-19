<?php

require_once __DIR__ . '/../database/Database.php';
require_once __DIR__ . '/../models/Contact.php';

/**
 * Repository for Contact database operations
 */
class ContactRepository
{
    private PDO $db;
    
    public function __construct()
    {
        $this -> db = Database::getConnection();
    }
    
    /**
     * Update a contact in the customer table
     * 
     * @param Contact $contact Contact object to update
     * @return bool True if update was successful
     */
    public function updateContact(Contact $contact): bool
    {
        $sql = "UPDATE customer 
                SET lex_contact_id = :lex_contact_id,
                    lex_customer_number = :lex_customer_number                    
                WHERE company_name = :company_name
                ";
        
        $stmt = $this -> db -> prepare($sql);
        
        return $stmt -> execute
        ([
            ':lex_contact_id' => $contact->lexContactId,
            ':organization_id' => $contact->organizationId,
            ':version' => $contact->version,
            ':lex_customer_number' => $contact->lexCustomerNumber,
            ':company_name' => $contact->companyName,
            ':allow_tax_free_invoices' => $contact->allowTaxFreeInvoices ? 1 : 0,
            ':archived' => $contact->archived ? 1 : 0
        ]);
    }
    
    /**
     * Insert a new contact into the customer table
     * 
     * @param Contact $contact Contact object to insert
     * @return bool True if insert was successful
     */
    public function insertContact(Contact $contact): bool
    {
        $sql = "INSERT INTO customer 
                (lex_contact_id, organization_id, version, lex_customer_number, 
                 company_name, allow_tax_free_invoices, archived, created_at, updated_at)
                VALUES 
                (:lex_contact_id, :organization_id, :version, :lex_customer_number,
                 :company_name, :allow_tax_free_invoices, :archived, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':lex_contact_id' => $contact->lexContactId,
            ':organization_id' => $contact->organizationId,
            ':version' => $contact->version,
            ':lex_customer_number' => $contact->lexCustomerNumber,
            ':company_name' => $contact->companyName,
            ':allow_tax_free_invoices' => $contact->allowTaxFreeInvoices ? 1 : 0,
            ':archived' => $contact->archived ? 1 : 0
        ]);
    }
    
    /**
     * Insert or update a contact (upsert)
     * 
     * @param Contact $contact Contact object to save
     * @return bool True if operation was successful
     */
    public function saveContact(Contact $contact): bool
    {
        $sql = "INSERT INTO customer 
                (lex_contact_id, organization_id, version, lex_customer_number, 
                 company_name, allow_tax_free_invoices, archived, created_at, updated_at)
                VALUES 
                (:lex_contact_id, :organization_id, :version, :lex_customer_number,
                 :company_name, :allow_tax_free_invoices, :archived, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                    organization_id = VALUES(organization_id),
                    version = VALUES(version),
                    lex_customer_number = VALUES(lex_customer_number),
                    company_name = VALUES(company_name),
                    allow_tax_free_invoices = VALUES(allow_tax_free_invoices),
                    archived = VALUES(archived),
                    updated_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':lex_contact_id' => $contact->lexContactId,
            ':organization_id' => $contact->organizationId,
            ':version' => $contact->version,
            ':lex_customer_number' => $contact->lexCustomerNumber,
            ':company_name' => $contact->companyName,
            ':allow_tax_free_invoices' => $contact->allowTaxFreeInvoices ? 1 : 0,
            ':archived' => $contact->archived ? 1 : 0
        ]);
    }
    
    /**
     * Find a contact by lex_contact_id
     * 
     * @param string $lexContactId Lexware contact ID
     * @return Contact|null Contact object or null if not found
     */
    public function findByLexContactId(string $lexContactId): ?Contact
    {
        $sql = "SELECT * FROM customer WHERE lex_contact_id = :lex_contact_id LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':lex_contact_id' => $lexContactId]);
        
        $row = $stmt->fetch();
        
        if (!$row) {
            return null;
        }
        
        // Convert database row to Contact format
        $contactData = [
            'id' => $row['lex_contact_id'],
            'organizationId' => $row['organization_id'],
            'version' => (int)$row['version'],
            'roles' => [
                'customer' => [
                    'number' => (int)$row['lex_customer_number']
                ]
            ],
            'company' => [
                'name' => $row['company_name'],
                'allowTaxFreeInvoices' => (bool)$row['allow_tax_free_invoices']
            ],
            'archived' => (bool)$row['archived']
        ];
        
        return new Contact($contactData);
    }
}
