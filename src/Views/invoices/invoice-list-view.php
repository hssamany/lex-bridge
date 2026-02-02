<div class="invoices-container">
    <div class="invoices-toolbar-container">
        <form name="get-invoices" class="line-items-filter-form inline-form" style="margin-bottom: 0.5rem; justify-content: flex-start;">
            <div class="filter-group-col">
                <label for="invoices_date_from">Von:</label>
                <input 
                    type="date" 
                    id="invoices_date_from" 
                    name="geaendertAm_from" 
                    class="input-date" 
                    placeholder="TT.mm.jjjj"
                >
            </div>
            <div class="filter-group-col">
                <label for="invoices_date_to">Bis:</label>
                <input 
                    type="date" 
                    id="invoices_date_to" 
                    name="geaendertAm_to" 
                    class="input-date" 
                    placeholder="TT.mm.jjjj"
                >
            </div>
            <div class="filter-group-col">
                <label for="invoices_customer_search">KundenNr:</label>
                <input 
                    id="invoices_customer_search" 
                    name="customer_search" 
                    class="customer-search-combobox" 
                    list="invoices-customer-options" 
                    autocomplete="off" 
                    placeholder="KundenNr oder Firma..."
                >
                <input type="hidden" name="customer_id" value="">
                <datalist id="invoices-customer-options">
                    <option value="">Alle Kunden</option>
                </datalist>
            </div>
            <div class="filter-group-col">
                <label for="invoices_status">Status:</label>
                <select 
                    id="invoices_status" 
                    name="status" 
                    class="input-select"
                >
                    <option value="">Alle Status</option>
                    <option value="draft">Draft</option>
                    <option value="pending">Pending</option>
                    <option value="transmitting">Transmitting</option>
                    <option value="transmitted">Transmitted</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div class="filter-group-col" style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" title="Rechnungen aktualisieren">
                    <span class="btn-icon">↻</span>
                </button>
            </div>
        </form>
    </div>
    <table class="invoice-table">
        <thead>
            <tr>
                <th>Action</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Items</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody class="invoice-table-body">
            <tr class="invoices-empty-row">
                <td colspan="7" style="text-align: center;">Keine Rechnungen vorhanden.</td>
            </tr>
        </tbody>
    </table>
    <div class="invoices-total" aria-live="polite">Gesammt: 0</div>
</div>
