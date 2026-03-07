<?php
$formName = 'get-bestellg';
$dateFromId = 'orders_date_from';
$dateFromName = 'order_date_from';
$dateToId = 'orders_date_to';
$dateToName = 'order_date_to';
$customerSearchId = 'orders_customer_search';
$datalistId = 'orders-customer-options';
$dateFromRequired = true;
$containerClass = 'orders-filter-container';
$dateFromDefault = date('Y-m-d', strtotime('-3 months')); // Set to 3 months ago to capture more data
$extraToggleClass = 'orders-filter-toggle';
$extraToggleInputClass = 'orders-filter-processed';
$extraToggleLabel = 'Verarbeitete anzeigen';
$extraToggleInputStyle = 'width: 16px; height: 16px; cursor: pointer;';

require __DIR__ . '/../shared/filter-form.php';
