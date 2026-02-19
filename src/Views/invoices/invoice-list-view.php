<?php
$statusTranslations = require __DIR__ . '/../../Config/invoice-status.php';
?>
<script>
window.invoiceStatusTranslations = <?= json_encode($statusTranslations, JSON_UNESCAPED_UNICODE) ?>;
</script>
<div class="invoices-container tab-content-container">
    <?php
    // Setup filter form variables
    $formName = 'get-invoices';
    $dateFromId = 'invoices_date_from';
    $dateFromName = 'voucher_date_from';
    $dateToId = 'invoices_date_to';
    $dateToName = 'voucher_date_to';
    $customerSearchId = 'invoices_customer_search';
    $datalistId = 'invoices-customer-options';
    $dateFromRequired = false;
    $containerClass = 'invoices-filter-container';
    $includeStatus = true;
    $statusFieldId = 'invoices_status';
    $statusFieldName = 'status';
    $statusOptions = array_keys($statusTranslations);
    require __DIR__ . '/../shared/filter-form.php';
    ?>
    
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
