<div class="line-items-container tab-content-container">
    <?php require __DIR__ . '/line-items-filter-form.php'; ?>
    <div style="flex: 1;">
        <div class="line-items-list table-scrollable" aria-live="polite">
            <table class="line-items-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" class="line-items-select-all"></th>
                        <th>Kunde</th>
                        <th>Pos.</th>
                        <th>Bezeichnung</th>
                        <th>Menge</th>
                        <th>Netto (€)</th>
                        <th>Brutto (€)</th>
                        <th>Steuer %</th>
                        <th>Erstellt am</th>
                        <th>Uhrzeit</th>
                        <th>Lieferdatum</th>
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
