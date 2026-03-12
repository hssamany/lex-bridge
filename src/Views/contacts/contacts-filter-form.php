<?php
/**
 * Contacts filter form
 * Simple filter with customer number and name fields
 */
?>
<div class="contacts-filter-container">
    <form name="get-contacts" class="line-items-filter-form inline-form">
        <div class="filter-group-col">
            <label for="contacts_customer_name">Kunden Name:</label>
            <input 
                type="text" 
                id="contacts_customer_name" 
                name="customer_name" 
                class="customer-search-combobox" 
                placeholder="z.B. Müller..."
                autocomplete="off"
            >
        </div>
        <div class="filter-group-col">
            <label for="contacts_customer_number">Kunden Nr.:</label>
            <input 
                type="text" 
                id="contacts_customer_number" 
                name="customer_number" 
                class="customer-search-combobox" 
                placeholder="z.B. 245..."
                autocomplete="off"
            >
        </div>
        
        <div class="filter-group-col filter-btn-group">
            <button type="submit" class="btn btn-primary filter-btn filter-submit-btn" title="Filter Senden" aria-label="Filter Senden">
                <span class="btn-icon" aria-hidden="true">⌕</span>
            </button>
            <button type="reset" class="btn btn-secondary filter-btn" title="Filter zurücksetzen" aria-label="Filter zurücksetzen">
                <span class="btn-icon" aria-hidden="true">✕</span>
            </button>
        </div>
    </form>
</div>
