<?php
$statusTranslations = require __DIR__ . '/../../Config/invoice-status.php';

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
