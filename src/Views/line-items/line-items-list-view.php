<div class="line-items-container" style="display: flex; align-items: flex-start; gap: 8px;">
    <div style="flex: 1;">
        <div class="line-items-actions-bar">
            <button type="button" id="send-invoice-btn" class="btn btn-primary" title="Rechnung erstellen" aria-label="Rechnung erstellen" disabled>
                <span class="btn-icon" aria-hidden="true">➜</span>
                <span>Rechn. Erstellen</span>
            </button>
            <button type="button" id="sync-articles-btn" class="btn btn-secondary" title="Artikel synchronisieren" aria-label="Artikel synchronisieren">
                <span class="btn-icon" aria-hidden="true">↻</span>
                <span>Artikeln synchronisieren</span>
            </button>
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
