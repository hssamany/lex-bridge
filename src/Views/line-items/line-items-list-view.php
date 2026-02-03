<div class="line-items-container" style="display: flex; align-items: flex-start; gap: 8px;">
    <div style="flex: 1;">
        <div class="line-items-actions-bar">
            <button type="button" id="send-invoice-btn" class="btn btn-primary line-items-toolbar-btn" title="Rechnung erstellen" aria-label="Rechnung erstellen" disabled>
                <span>Rechn. Erstellen</span>
                <span class="btn-icon" style="font-size:1.1em;">➤</span>
            </button>
            <button type="button" id="sync-articles-btn" class="btn btn-secondary line-items-toolbar-btn" title="Artikel synchronisieren" aria-label="Artikel synchronisieren">
                <span class="btn-icon" aria-hidden="true">↻</span>
                <span>Artikeln synchronisieren</span>
            </button>
            <label style="margin-left: auto; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem;">
                <input type="checkbox" class="line-items-filter-invoiced" style="cursor: pointer;">
                <span>In Rechnung gestellte anzeigen</span>
            </label>
        </div>
        <div class="line-items-list" aria-live="polite">
            <table class="line-items-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" class="line-items-select-all"></th>
                        <th>Pos.</th>
                        <th>Bezeichnung</th>
                        <th>Menge</th>
                        <th>Netto</th>
                        <th>Brutto</th>
                        <th>Steuer %</th>
                        <th>Erstellt am</th>
                        <th>Uhrzeit</th>
                    </tr>
                </thead>
                <tbody class="line-items-table-body">
                    <tr class="line-items-empty-row">
                        <td colspan="9" style="text-align: center;">Keine Positionen vorhanden.</td>
                    </tr>
                </tbody>
            </table>
            <div class="line-items-total" aria-live="polite">Gesammt: 0</div>
        </div>
    </div>
</div>
