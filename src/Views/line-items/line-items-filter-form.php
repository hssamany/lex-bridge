<?php
$formName = 'get-line-items';
$dateFromId = 'created_at_from';
$dateFromName = 'created_at_from';
$dateToId = 'created_at_to';
$dateToName = 'created_at_to';
$customerSearchId = 'customer_search';
$datalistId = 'customer-options';
$dateFromRequired = false;
$containerClass = 'line-items-filter-container';
$extraToggleClass = 'line-items-filter-toggle';
$extraToggleInputClass = 'line-items-filter-invoiced';
$extraToggleLabel = 'In Rechnung gestellte anzeigen';
$extraToggleInputStyle = 'cursor: pointer;';

require __DIR__ . '/../shared/filter-form.php';
