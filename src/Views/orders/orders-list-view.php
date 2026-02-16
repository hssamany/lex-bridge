<div class="orders-container tab-content-container">
    <?php require __DIR__ . '/orders-filter-form.php'; ?>
    <div class="orders-list table-scrollable" aria-live="polite" aria-busy="false">
        <table class="orders-table">
            <thead>
                <tr>
                    <th><input type="checkbox" class="orders-select-all"></th>
                    <th>Kunde</th>
                    <th title="Lexware Kunden-Nr.">Lex-Nr.</th>
                    <th title="Kalenderwoche">KW</th>
                    <th title="Montag">Mo.</th>
                    <th title="Dienstag">Di.</th>
                    <th title="Mittwoch">Mi.</th>
                    <th title="Donnerstag">Do.</th>
                    <th title="Freitag">Fr.</th>
                    <th title="Bestelldatum">Bestel.dat</th>
                    <th title="Lexware Artikelnummer">Artikel-Nr.</th>
                    <th title="Verarbeitet">Verarbt.</th>
                </tr>
            </thead>
            <tbody class="orders-table-body">
                <tr class="orders-empty-row">
                    <td colspan="12" style="text-align: center;">Keine Bestellungen vorhanden.</td>
                </tr>
            </tbody>
        </table>
        <div class="table-paginator orders-paginator sticky-paginator paginator-container" data-paginator="orders"></div>
        <!-- Removed legacy total label; now handled by paginator -->
    </div>
</div>
