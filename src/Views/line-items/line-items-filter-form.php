<div id="line-items-filter-container">
    <form name="get-line-items" class="line-items-filter-form inline-form">
        <div class="filter-group-col">
            <label for="created_at_from">Von:</label>
            <input type="date" id="created_at_from" name="created_at_from" class="input-date" placeholder="TT.mm.jjjj">
        </div>
        <div class="filter-group-col">
            <label for="created_at_to">Bis:</label>
            <input type="date" id="created_at_to" name="created_at_to" class="input-date" placeholder="TT.mm.jjjj">
        </div>
        <div class="filter-group-col">
            <label for="customer_search">KundenNr:</label>
            <input id="customer_search" name="customer_search" class="customer-search-combobox" list="customer-options" autocomplete="off" placeholder="KundenNr oder Firma...">
            <input type="hidden" name="customer_id" value="">
            <datalist id="customer-options">
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
</div>
