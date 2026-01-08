<form name="get-line-items" class="line-items-filter-form inline-form">
    <div class="filter-group-col">
        <label for="created_at_from">Von:</label>
        <input type="date" id="created_at_from" name="created_at_from" class="input-date">
    </div>
    <div class="filter-group-col">
        <label for="created_at_to">Bis:</label>
        <input type="date" id="created_at_to" name="created_at_to" class="input-date">
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
        <button type="submit" class="btn btn-primary" style="width:40px; height:40px; display:flex; align-items:center; justify-content:center; font-size:1.3rem;" title="Filtern">
            <span class="btn-icon" style="font-size:1.5em;">🔍</span>
        </button>
    </div>
</form>
