<form name="get-orders" class="orders-filter-form line-items-filter-form inline-form">
    <div class="filter-group-col">
        <label for="orders_changed_from">Von:</label>
        <input type="date" id="orders_changed_from" name="geaendertAm_from" class="input-date" required>
    </div>
    <div class="filter-group-col">
        <label for="orders_changed_to">Bis:</label>
        <input type="date" id="orders_changed_to" name="geaendertAm_to" class="input-date">
    </div>
    <div class="filter-group-col">
        <label for="orders_customer_search">KundenNr:</label>
        <input id="orders_customer_search" name="customer_search" class="customer-search-combobox" list="orders-customer-options" autocomplete="off" placeholder="KundenNr oder Firma...">
        <input type="hidden" name="customer_id" value="">
        <datalist id="orders-customer-options">
            <option value="">Alle Kunden</option>
        </datalist>
    </div>
    <div class="filter-group-col filter-btn-group">
        <label style="visibility:hidden">Filtern</label>
        <button type="submit" class="btn btn-primary filter-submit-btn" title="Filtern" aria-label="Filtern">
            <span class="btn-icon" aria-hidden="true">🔍</span>
        </button>
    </div>
</form>
