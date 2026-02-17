<?php
/**
 * Reusable filter form component
 * 
 * @param string $formName - Form name attribute
 * @param string $dateFromId - ID for "Von" date input
 * @param string $dateFromName - Name for "Von" date input
 * @param string $dateToId - ID for "Bis" date input
 * @param string $dateToName - Name for "Bis" date input
 * @param string $customerSearchId - ID for customer search input
 * @param string $datalistId - ID for customer datalist
 * @param bool $dateFromRequired - Whether "Von" field is required
 * @param string $containerClass - Additional container class
 */

$formName = $formName ?? 'filter-form';
$dateFromId = $dateFromId ?? 'date_from';
$dateFromName = $dateFromName ?? 'date_from';
$dateToId = $dateToId ?? 'date_to';
$dateToName = $dateToName ?? 'date_to';
$customerSearchId = $customerSearchId ?? 'customer_search';
$datalistId = $datalistId ?? 'customer-options';
$dateFromRequired = $dateFromRequired ?? false;
$dateFromDefault = $dateFromDefault ?? ''; // Default value for Von field
$containerClass = $containerClass ?? '';
$includeStatus = $includeStatus ?? false;
$statusFieldId = $statusFieldId ?? 'status';
$statusFieldName = $statusFieldName ?? 'status';
$extraToggleClass = $extraToggleClass ?? '';
$extraToggleInputClass = $extraToggleInputClass ?? '';
$extraToggleLabel = $extraToggleLabel ?? '';
$extraToggleInputStyle = $extraToggleInputStyle ?? '';
?>
<div class="<?= htmlspecialchars($containerClass) ?>">
    <form name="<?= htmlspecialchars($formName) ?>" class="line-items-filter-form inline-form">
        <div class="filter-group-col">
            <label for="<?= htmlspecialchars($dateFromId) ?>">Von:</label>
            <input 
                type="date" 
                id="<?= htmlspecialchars($dateFromId) ?>" 
                name="<?= htmlspecialchars($dateFromName) ?>" 
                class="input-date" 
                placeholder="TT.mm.jjjj"
                value="<?= htmlspecialchars($dateFromDefault) ?>"
                <?= $dateFromRequired ? 'required' : '' ?>
            >
        </div>
        <div class="filter-group-col">
            <label for="<?= htmlspecialchars($dateToId) ?>">Bis:</label>
            <input 
                type="date" 
                id="<?= htmlspecialchars($dateToId) ?>" 
                name="<?= htmlspecialchars($dateToName) ?>" 
                class="input-date" 
                placeholder="TT.mm.jjjj"
            >
        </div>
        <div class="filter-group-col">
            <label for="<?= htmlspecialchars($customerSearchId) ?>">KundenNr:</label>
            <input 
                id="<?= htmlspecialchars($customerSearchId) ?>" 
                name="customer_search" 
                class="customer-search-combobox" 
                list="<?= htmlspecialchars($datalistId) ?>" 
                autocomplete="off" 
                placeholder="KundenNr oder Firma..."
            >
            <input type="hidden" name="customer_id" value="">
            <datalist id="<?= htmlspecialchars($datalistId) ?>">
                <option value="">Alle Kunden</option>
            </datalist>
        </div>
        <?php if ($includeStatus): ?>
        <div class="filter-group-col">
            <label for="<?= htmlspecialchars($statusFieldId) ?>">Status:</label>
            <select 
                id="<?= htmlspecialchars($statusFieldId) ?>" 
                name="<?= htmlspecialchars($statusFieldName) ?>" 
                class="input-select"
            >
                <option value="">Alle Status</option>
                <option value="draft">Draft</option>
                <option value="pending">Pending</option>
                <option value="transmitting">Transmitting</option>
                <option value="transmitted">Transmitted</option>
                <option value="failed">Failed</option>
            </select>
        </div>
        <?php endif; ?>
        <div class="filter-group-col filter-btn-group">
            <label style="visibility:hidden">Filtern</label>
            <button type="submit" class="btn btn-primary filter-submit-btn" title="Filter Senden" aria-label="Filter Senden">
                <span class="btn-icon" aria-hidden="true">⌕</span>
            </button>
        </div>
        <?php if ($extraToggleInputClass !== '' && $extraToggleLabel !== ''): ?>
        <div class="filter-group-col filter-extra-toggle">
            <label class="<?= htmlspecialchars($extraToggleClass) ?>">
                <input type="checkbox" class="<?= htmlspecialchars($extraToggleInputClass) ?>" style="<?= htmlspecialchars($extraToggleInputStyle) ?>">
                <span><?= htmlspecialchars($extraToggleLabel) ?></span>
            </label>
        </div>
        <?php endif; ?>
    </form>
</div>
