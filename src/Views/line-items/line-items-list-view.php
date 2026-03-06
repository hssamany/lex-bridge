<div class="line-items-container tab-content-container">
    <?php require __DIR__ . '/line-items-filter-form.php'; ?>
    <div style="flex: 1;">
        <div class="line-items-list table-scrollable" aria-live="polite">
            <table class="line-items-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" class="line-items-select-all"></th>
                        <th title="Kunde">Kunde</th>
                        <th title="Position">Pos.</th>
                        <th title="Bezeichnung">Bezeichnung</th>
                        <th title="Menge">Menge</th>
                        <th title="Netto-Betrag in Euro">Netto (€)</th>
                        <th title="Brutto-Betrag in Euro">Brutto (€)</th>
                        <th title="Steuersatz in Prozent">Steuer %</th>
                        <th title="Erstellt am">Erstellt am</th>
                        <th title="Uhrzeit der Erstellung">Uhrzeit</th>
                        <th title="Lieferdatum">Lieferdatum</th>
                    </tr>
                </thead>
                <tbody class="line-items-table-body">
                    <tr class="line-items-empty-row">
                        <td colspan="11" style="text-align: center;">Keine Positionen vorhanden.</td>
                    </tr>
                </tbody>
            </table>
            <div class="table-paginator line-items-paginator sticky-paginator paginator-container" data-paginator="line-items"></div>
            <!-- Removed legacy total label; now handled by paginator -->
        </div>
    </div>
</div>
