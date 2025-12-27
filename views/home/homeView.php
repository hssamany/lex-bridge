<?php

/**
 * HomeView
 * 
 * View class for home.php template
 * Encapsulates rendering logic to keep templates clean
 */
class HomeView
{
    private ?string $status;
    private ?array $contactsData;
    private ?array $invoicesData;
    private ?string $error;
    
    public function __construct(?string $status, ?array $contactsData, ?string $error = null, ?array $invoicesData = null)
    {
        $this->status = $status;
        $this->contactsData = $contactsData;
        $this->error = $error;
        $this->invoicesData = $invoicesData;
    }
    
    /**
     * Render the operation status data attribute
     * Returns HTML attribute string with properly encoded session data
     */
    public function renderOperationStatusAttribute(): string
    {
        // Only include the attribute if there's a status
        if (!$this->status) {
            return '';
        }
        
        $statusData = [
            'status' => $this->status,
            'message' => $this->status === 'error' 
                ? ($this->error ?? 'An error occurred') 
                : 'Operation completed successfully'
        ];
        
        $statusDataJson = htmlspecialchars(json_encode($statusData), ENT_QUOTES, 'UTF-8');

        return "data-operation-status='" . $statusDataJson . "'";
    }
    
    /**
     * Get page title
     */
    public function getPageTitle(): string
    {
        return 'LEX Bridge';
    }
    
    /**
     * Get page heading
     */
    public function getPageHeading(): string
    {
        return 'LEX Bridge - Management System';
    }
    
    /**
     * Render contacts tab content
     */
    public function renderContactsTabContent(): void
    {
        // Make contactsData available to the included view
        $contactsData = $this->contactsData;
        include __DIR__ . '/../contacts/contact-list-view.php';
    }
    
    /**
     * Render invoices tab content
     */
    public function renderInvoicesTabContent(): void
    {
        // Make invoicesData available to the included view
        $invoicesData = $this->invoicesData;
        include __DIR__ . '/../invoices/invoice-list-view.php';
    }
}
