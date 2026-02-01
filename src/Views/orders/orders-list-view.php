<div class="orders-container">
    <div class="orders-list" aria-live="polite" aria-busy="false">
        <div class="orders-toolbar-container">
            <!-- Toolbar will be rendered here by JavaScript -->
            <div class="orders-toolbar line-items-toolbar">
                <div class="line-items-toolbar-left">
                    <button type="button" class="btn btn-primary line-items-toolbar-btn orders-generate-button" disabled>
                        Positionen Erstellen <span class="btn-icon" style="font-size:1.1em;">➤</span>
                    </button>
                </div>
            </div>
        </div>
        <table class="orders-table">
            <thead>
                <tr>
                    <th><input type="checkbox" class="orders-select-all"></th>
                    <th>Kunde</th>
                    <th>Lex Kunden-Nr.</th>
                    <th>Jahr</th>
                    <th>KW</th>
                    <th>Mo</th>
                    <th>Di</th>
                    <th>Mi</th>
                    <th>Do</th>
                    <th>Fr</th>
                    <th>Bestelldatum</th>
                    <th>Artikel-Nr.</th>
                </tr>
            </thead>
            <tbody class="orders-table-body">
                <tr class="orders-empty-row">
                    <td colspan="12" style="text-align: center;">Keine Bestellungen vorhanden.</td>
                </tr>
            </tbody>
        </table>
        <div class="orders-total" aria-live="polite">Gesammt: 0</div>
    </div>
</div>
