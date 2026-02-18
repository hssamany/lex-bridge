<div class="invoices-container tab-content-container">
    <?php require __DIR__ . '/invoices-filter-form.php'; ?>
    
    <table class="invoice-table">
        <thead>
            <tr>
                <th>Aktionen</th>
                <th>Kunde</th>
                <th>Datum</th>
                <th>Artikel</th>
                <th>Status</th>
                <th>Versuche</th>
                <th title="Gesamt Netto">Netto (€)</th>
                <th title="Gesamt Brutto">Brutto (€)</th>
            </tr>
        </thead>
        <tbody class="invoice-table-body">
            <tr class="invoices-empty-row">
                <td colspan="7" style="text-align: center;">Keine Rechnungen vorhanden.</td>
            </tr>
        </tbody>
    </table>
    <div class="table-paginator invoices-paginator sticky-paginator paginator-container" data-paginator="invoices"></div>
    <!-- Removed legacy total label; now handled by paginator -->
</div>
