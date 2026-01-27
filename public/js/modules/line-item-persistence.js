// public/js/modules/line-item-persistence.js
// Handles line-item article state, signatures, and persistence scheduling

(function () {
'use strict';

class LineItemPersistence {
    constructor() {
        this.lineItemPersistTimers = new WeakMap();
        this.persistDelay = 250;
    }

    normalizeArticleValue(value) {
        if (value === null || value === undefined) {
            return '';
        }

        if (typeof value === 'string') {
            return value.trim();
        }

        return String(value).trim();
    }

    normalizeArticleData(articleData) {
        const data = articleData || {};
        return {
            id: this.normalizeArticleValue(data.id),
            number: this.normalizeArticleValue(data.number),
            name: this.normalizeArticleValue(data.name),
            netAmount: this.normalizeArticleValue(data.netAmount),
            grossAmount: this.normalizeArticleValue(data.grossAmount),
            taxRate: this.normalizeArticleValue(data.taxRate),
            currency: this.normalizeArticleValue(data.currency),
            validFrom: this.normalizeArticleValue(data.validFrom),
            validUntil: this.normalizeArticleValue(data.validUntil)
        };
    }

    formatArticleDisplayNumber(value, fractionDigits) {
        if (value === null || value === undefined || value === '') {
            return '';
        }

        const numeric = Number(value);
        if (Number.isNaN(numeric)) {
            return '';
        }

        const digits = typeof fractionDigits === 'number' ? fractionDigits : 2;
        return numeric.toLocaleString('de-DE', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
        });
    }

    updateLineItemCells(row, data) {
        if (!row) {
            return;
        }

        const nameCell = row.querySelector('.line-item-name-cell');
        if (nameCell) {
            if (!nameCell.dataset.originalValue) {
                nameCell.dataset.originalValue = nameCell.textContent || '';
            }
            nameCell.textContent = data?.name ? data.name : (nameCell.dataset.originalValue || '');
        }

        const applyNumericValue = (selector, value, digits) => {
            const cell = row.querySelector(selector);
            if (!cell) {
                return;
            }

            if (!cell.dataset.originalValue) {
                cell.dataset.originalValue = cell.textContent || '';
            }

            if (value === null || value === undefined || value === '') {
                cell.textContent = cell.dataset.originalValue || '';
                return;
            }

            cell.textContent = this.formatArticleDisplayNumber(value, digits);
        };

        applyNumericValue('.line-item-net-cell', data?.netAmount, 2);
        applyNumericValue('.line-item-gross-cell', data?.grossAmount, 2);
        applyNumericValue('.line-item-tax-cell', data?.taxRate, 2);
    }

    readOptionDataset(option, key) {
        if (!option) {
            return '';
        }

        const datasetValue = option.dataset ? option.dataset[key] : undefined;
        if (datasetValue !== undefined) {
            return datasetValue;
        }

        const attributeValue = option.getAttribute(`data-${key.replace(/([A-Z])/g, '-$1').toLowerCase()}`);
        return attributeValue ?? '';
    }

    extractArticleFromOption(option) {
        if (!option) {
            return {
                id: '',
                number: '',
                name: '',
                netAmount: '',
                grossAmount: '',
                taxRate: '',
                currency: '',
                validFrom: '',
                validUntil: ''
            };
        }

        return {
            id: option.dataset.articleId || option.getAttribute('data-article-id') || '',
            number: this.readOptionDataset(option, 'articleNumber'),
            name: this.readOptionDataset(option, 'articleName'),
            netAmount: this.readOptionDataset(option, 'netAmount'),
            grossAmount: this.readOptionDataset(option, 'grossAmount'),
            taxRate: this.readOptionDataset(option, 'taxRatePercentage'),
            currency: this.readOptionDataset(option, 'currency'),
            validFrom: this.readOptionDataset(option, 'validFrom'),
            validUntil: this.readOptionDataset(option, 'validUntil')
        };
    }

    toNumberOrNull(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        if (typeof value === 'number') {
            return Number.isNaN(value) ? null : value;
        }

        const numeric = Number(value);
        return Number.isNaN(numeric) ? null : numeric;
    }

    normalizeSignatureValue(value) {
        if (value === null || value === undefined || value === '') {
            return '';
        }

        if (typeof value === 'number') {
            if (!Number.isFinite(value)) {
                return '';
            }
            const normalized = Number(value.toFixed(4));
            return normalized.toString();
        }

        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (trimmed === '') {
                return '';
            }
            const numeric = Number(trimmed);
            if (!Number.isNaN(numeric)) {
                return this.normalizeSignatureValue(numeric);
            }
            return trimmed;
        }

        const numeric = Number(value);
        if (!Number.isNaN(numeric)) {
            return this.normalizeSignatureValue(numeric);
        }

        return String(value);
    }

    computeArticleSignature(articleData, label) {
        const safeData = articleData || {};
        const currencyValue = typeof safeData.currency === 'string'
            ? safeData.currency.toUpperCase()
            : safeData.currency;

        return JSON.stringify({
            id: this.normalizeSignatureValue(safeData.id),
            number: this.normalizeSignatureValue(safeData.number),
            name: this.normalizeSignatureValue(safeData.name),
            net: this.normalizeSignatureValue(safeData.netAmount),
            gross: this.normalizeSignatureValue(safeData.grossAmount),
            tax: this.normalizeSignatureValue(safeData.taxRate),
            currency: this.normalizeSignatureValue(currencyValue),
            validFrom: this.normalizeSignatureValue(safeData.validFrom),
            validUntil: this.normalizeSignatureValue(safeData.validUntil),
            label: this.normalizeSignatureValue(label)
        });
    }

    writeRowArticleState(row, articleData, label, options = {}) {
        if (!row) {
            return '';
        }

        const safeData = this.normalizeArticleData(articleData);
        const safeLabel = this.normalizeArticleValue(label);
        const { skipSchedule = false, markPersistedSignature = false } = options;

        const wrapper = row.querySelector('.article-selector');
        if (wrapper) {
            const setField = (selector, value) => {
                const field = wrapper.querySelector(selector);
                if (field) {
                    field.value = value ?? '';
                }
            };

            const input = wrapper.querySelector('.article-search-combobox');
            if (input) {
                input.value = safeLabel;
            }

            setField('.article-id-field', safeData.id);
            setField('.article-number-field', safeData.number);
            setField('.article-name-field', safeData.name);
            setField('.article-net-field', safeData.netAmount);
            setField('.article-gross-field', safeData.grossAmount);
            setField('.article-tax-field', safeData.taxRate);
            setField('.article-currency-field', safeData.currency);
            setField('.article-valid-from-field', safeData.validFrom);
            setField('.article-valid-until-field', safeData.validUntil);
            setField('.article-label-field', safeLabel);
        }

        row.dataset.articleId = safeData.id;
        row.dataset.articleNumber = safeData.number;
        row.dataset.articleName = safeData.name;
        row.dataset.articleNet = safeData.netAmount;
        row.dataset.articleGross = safeData.grossAmount;
        row.dataset.articleTax = safeData.taxRate;
        row.dataset.articleCurrency = safeData.currency;
        row.dataset.articleValidFrom = safeData.validFrom;
        row.dataset.articleValidUntil = safeData.validUntil;
        row.dataset.articleLabel = safeLabel;

        row.dataset.selectedArticleId = safeData.id;
        row.dataset.selectedArticleNumber = safeData.number;
        row.dataset.selectedArticleName = safeData.name;
        row.dataset.selectedArticleNet = safeData.netAmount;
        row.dataset.selectedArticleGross = safeData.grossAmount;
        row.dataset.selectedArticleTax = safeData.taxRate;
        row.dataset.selectedArticleCurrency = safeData.currency;
        row.dataset.selectedArticleValidFrom = safeData.validFrom;
        row.dataset.selectedArticleValidUntil = safeData.validUntil;
        row.dataset.selectedArticleLabel = safeLabel;

        this.updateLineItemCells(row, {
            name: safeData.name,
            netAmount: safeData.netAmount,
            grossAmount: safeData.grossAmount,
            taxRate: safeData.taxRate
        });

        const signature = this.computeArticleSignature(safeData, safeLabel);
        row.dataset.currentArticleSignature = signature;
        if (markPersistedSignature) {
            row.dataset.persistedArticleSignature = signature;
        }

        if (!skipSchedule) {
            this.scheduleLineItemPersist(row, safeData, safeLabel, signature);
        }

        return signature;
    }

    buildLineItemPersistPayload(row, articleData, displayValue) {
        if (!row) {
            return null;
        }

        const wrapper = row.querySelector('.article-selector');
        const readField = (selector) => {
            if (!wrapper) {
                return '';
            }
            const field = wrapper.querySelector(selector);
            return field ? field.value : '';
        };

        const safeData = articleData || {};
        return {
            line_item_id: row.dataset.lineItemId || '',
            article_id: safeData.id || readField('.article-id-field') || null,
            article_number: safeData.number || readField('.article-number-field') || null,
            article_name: safeData.name || readField('.article-name-field') || null,
            article_label: displayValue || readField('.article-label-field') || null,
            currency: safeData.currency || readField('.article-currency-field') || null,
            net_amount: this.toNumberOrNull(safeData.netAmount ?? readField('.article-net-field')),
            gross_amount: this.toNumberOrNull(safeData.grossAmount ?? readField('.article-gross-field')),
            tax_rate_percentage: this.toNumberOrNull(safeData.taxRate ?? readField('.article-tax-field')),
            article_valid_from: safeData.validFrom || readField('.article-valid-from-field') || null,
            article_valid_until: safeData.validUntil || readField('.article-valid-until-field') || null
        };
    }

    async persistLineItemSelection(row, articleData, displayValue, signature) {
        if (!row) {
            return;
        }

        const payload = this.buildLineItemPersistPayload(row, articleData, displayValue);
        if (!payload || !payload.line_item_id) {
            return;
        }

        let response;
        try {
            response = await fetch(LexBridge.resolveApiUrl('line-items/update'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
        } catch (error) {
            console.error('Line item update network error:', error);
            if (window.lexBridge?.toastNotifier) {
                window.lexBridge.toastNotifier.show('Speichern der Position fehlgeschlagen', 'error');
            }
            return;
        }

        const text = await response.text();
        let data;
        try {
            data = text ? JSON.parse(text) : {};
        } catch (error) {
            console.error('Line item update parse error:', error);
            if (window.lexBridge?.toastNotifier) {
                window.lexBridge.toastNotifier.show('Speichern der Position fehlgeschlagen', 'error');
            }
            return;
        }

        if (!response.ok || !data?.isSuccess) {
            const message = data?.error || `Line item update failed (${response.status})`;
            console.error('Line item update error:', message);
            if (window.lexBridge?.toastNotifier) {
                window.lexBridge.toastNotifier.show('Speichern der Position fehlgeschlagen', 'error');
            }
            return;
        }

        if (data.lineItem) {
            this.updateRowFromServerResponse(row, data.lineItem);
            return;
        }

        if (signature) {
            row.dataset.persistedArticleSignature = signature;
        }
    }

    scheduleLineItemPersist(row, articleData, displayValue, signature) {
        if (!row || !row.dataset) {
            return;
        }

        const persistSignature = signature || this.computeArticleSignature(articleData, displayValue);
        const persistedSignature = row.dataset.persistedArticleSignature || '';
        if (persistSignature === persistedSignature) {
            return;
        }

        const existingTimer = this.lineItemPersistTimers.get(row);
        if (existingTimer) {
            clearTimeout(existingTimer);
        }

        const timer = setTimeout(() => {
            this.lineItemPersistTimers.delete(row);
            this.persistLineItemSelection(row, articleData, displayValue, persistSignature);
        }, this.persistDelay);

        this.lineItemPersistTimers.set(row, timer);
    }

    applySelectionFromInput(input, option) {
        if (!input) {
            return;
        }

        const row = input.closest('tr');
        if (!row) {
            return;
        }

        const articleData = this.extractArticleFromOption(option);
        const displayValue = option ? input.value : input.value.trim() === '' ? '' : input.value;
        const shouldPersist = Boolean(option) || input.value.trim() === '';

        this.writeRowArticleState(row, articleData, displayValue, {
            skipSchedule: !shouldPersist
        });
    }

    updateRowFromServerResponse(row, lineItem) {
        if (!row || !lineItem) {
            return;
        }

        const grossAmount = (lineItem.gross_amount ?? lineItem.line_total_gross) ?? '';
        this.writeRowArticleState(row, {
            id: lineItem.article_id ?? '',
            number: lineItem.article_number ?? '',
            name: lineItem.name ?? '',
            netAmount: lineItem.net_amount ?? '',
            grossAmount,
            taxRate: lineItem.tax_rate_percentage ?? '',
            currency: lineItem.currency ?? '',
            validFrom: lineItem.article_valid_from ?? '',
            validUntil: lineItem.article_valid_until ?? ''
        }, lineItem.article_label ?? '', {
            skipSchedule: true,
            markPersistedSignature: true
        });
    }

    initializeLineItemPersistenceState(table) {
        if (!table) {
            return;
        }

        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const articleData = {
                id: row.dataset.articleId || '',
                number: row.dataset.articleNumber || '',
                name: row.dataset.articleName || '',
                netAmount: row.dataset.articleNet || '',
                grossAmount: row.dataset.articleGross || '',
                taxRate: row.dataset.articleTax || '',
                currency: row.dataset.articleCurrency || '',
                validFrom: row.dataset.articleValidFrom || '',
                validUntil: row.dataset.articleValidUntil || ''
            };
            const input = row.querySelector('.article-search-combobox');
            const label = input ? input.value : '';
            this.writeRowArticleState(row, articleData, label, {
                skipSchedule: true,
                markPersistedSignature: true
            });
        });
    }

    applyLineItemDialogResult(row, editorData) {
        if (!row || !editorData) {
            return;
        }

        const articleData = {
            id: editorData.articleId ?? editorData.article_id,
            number: editorData.articleNumber ?? editorData.article_number,
            name: editorData.articleName ?? editorData.article_name,
            netAmount: editorData.netAmount ?? editorData.net_amount,
            grossAmount: editorData.grossAmount ?? editorData.gross_amount,
            taxRate: editorData.taxRate ?? editorData.tax_rate ?? editorData.tax_rate_percentage,
            currency: editorData.currency,
            validFrom: editorData.validFrom ?? editorData.article_valid_from,
            validUntil: editorData.validUntil ?? editorData.article_valid_until
        };
        const articleLabel = editorData.articleLabel ?? editorData.article_label;

        this.writeRowArticleState(row, articleData, articleLabel);
    }
}

if (!window.lexBridge) {
    window.lexBridge = {};
}

if (!window.lexBridge.lineItemPersistence) {
    const persistence = new LineItemPersistence();
    window.lexBridge.lineItemPersistence = persistence;

    if (!window.lexBridge.writeRowArticleState) {
        window.lexBridge.writeRowArticleState = persistence.writeRowArticleState.bind(persistence);
    }
    if (!window.lexBridge.initializeLineItemPersistenceState) {
        window.lexBridge.initializeLineItemPersistenceState = persistence.initializeLineItemPersistenceState.bind(persistence);
    }
    if (!window.lexBridge.applyLineItemDialogResult) {
        window.lexBridge.applyLineItemDialogResult = persistence.applyLineItemDialogResult.bind(persistence);
    }
}
})();
