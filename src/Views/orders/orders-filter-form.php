<?php
$formName = 'get-bestellg';
$dateFromId = 'orders_changed_from';
$dateFromName = 'geaendertAm_from';
$dateToId = 'orders_changed_to';
$dateToName = 'geaendertAm_to';
$customerSearchId = 'orders_customer_search';
$datalistId = 'orders-customer-options';
$dateFromRequired = true;
$containerClass = 'orders-filter-container';

require __DIR__ . '/../shared/filter-form.php';
