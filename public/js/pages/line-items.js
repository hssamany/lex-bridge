// public/js/pages/line-items.js
// Handles AJAX customer search for the Line-Items tab using event delegation


(function () {
    const debounceTimers = new WeakMap();
    const articleDebounceTimers = new WeakMap();

    function syncCustomerSelection(input, datalistOverride) {
        if (!input) {
            return;
        }

        const form = input.closest('form');
        if (!form) {
            return;
        }

        const hiddenField = form.querySelector('input[type="hidden"][name="customer_id"]');
        if (!hiddenField) {
            return;
        }

        const listId = input.getAttribute('list');
        const datalist = datalistOverride || (listId ? document.getElementById(listId) : null);

        hiddenField.value = '';

        if (!datalist) {
            return;
        }

        const value = input.value.trim();
        if (!value) {
            return;
        }

        const options = datalist.options || datalist.children;
        for (let index = 0; index < options.length; index += 1) {
            const option = options[index];
            if (option.value !== value) {
                continue;
            }

            const customerId = option.dataset.customerId || option.getAttribute('data-customer-id');
            if (customerId) {
                hiddenField.value = customerId;
            }
            break;
        }
    }

    function handleCustomerSearch(input) {
        const listId = input.getAttribute('list');
        const datalist = listId ? document.getElementById(listId) : null;
        if (!datalist) {
            console.warn('Customer datalist not found for input', input);
            return;
        }

        const query = input.value.trim();
        if (!query) {
            datalist.innerHTML = '<option value="">Alle Kunden</option>';
            syncCustomerSelection(input, datalist);
            return;
        }

        const timer = debounceTimers.get(input);
        if (timer) {
            clearTimeout(timer);
        }

        syncCustomerSelection(input, datalist);

        const newTimer = setTimeout(() => {
            fetch(`/lex-bridge/api/customers/search?q=${encodeURIComponent(query)}`)
                .then(async res => {
                    if (!res.ok) {
                        const errorText = await res.text();
                        console.error('Customer search HTTP error:', res.status, errorText);
                        return null;
                    }
                    const text = await res.text();
                    try {
                        return JSON.parse(text);
                    } catch (error) {
                        console.error('Customer search response parse error:', error, text);
                        return null;
                    }
                })
                .then(data => {
                    datalist.innerHTML = '<option value="">Alle Kunden</option>';
                    if (Array.isArray(data)) {
                        data.forEach(cust => {
                            const opt = document.createElement('option');
                            const number = cust.customer_number || '';
                            const name = cust.company_name || '';
                            opt.value = `${number}${number && name ? ' - ' : ''}${name}`;
                            opt.dataset.customerId = String(cust.id ?? '');
                            opt.setAttribute('data-customer-id', String(cust.id ?? ''));
                            datalist.appendChild(opt);
                        });
                    }

                    syncCustomerSelection(input, datalist);
                })
                .catch(error => {
                    console.error('Customer search error:', error);
                });
        }, 300);

        debounceTimers.set(input, newTimer);
    }

    function populateArticleDatalist(datalist, articles) {
        datalist.innerHTML = '<option value="">Artikel wählen</option>';

        if (!Array.isArray(articles)) {
            return;
        }

        articles.forEach(article => {
            const opt = document.createElement('option');
            const number = article.article_number || article.number || '';
            const name = article.name || article.title || '';
            const labelParts = [number, name].filter(Boolean);
            opt.value = labelParts.join(' - ');
            opt.dataset.articleId = String(article.id ?? '');
            opt.setAttribute('data-article-id', String(article.id ?? ''));
            opt.dataset.articleNumber = String(number ?? '');
            opt.setAttribute('data-article-number', String(number ?? ''));
            opt.dataset.articleName = name || '';
            opt.setAttribute('data-article-name', name || '');

            const netAmount = article.net_amount ?? article.netAmount ?? '';
            opt.dataset.netAmount = netAmount !== null && netAmount !== undefined ? String(netAmount) : '';
            opt.setAttribute('data-net-amount', opt.dataset.netAmount);

            const grossAmount = article.gross_amount ?? article.grossAmount ?? '';
            opt.dataset.grossAmount = grossAmount !== null && grossAmount !== undefined ? String(grossAmount) : '';
            opt.setAttribute('data-gross-amount', opt.dataset.grossAmount);

            const taxRate = article.tax_rate_percentage ?? article.taxRatePercentage ?? '';
            opt.dataset.taxRatePercentage = taxRate !== null && taxRate !== undefined ? String(taxRate) : '';
            opt.setAttribute('data-tax-rate-percentage', opt.dataset.taxRatePercentage);

            const currency = article.currency ?? article.currency_code ?? '';
            opt.dataset.currency = currency || '';
            opt.setAttribute('data-currency', opt.dataset.currency);

            const validFrom = article.valid_from ?? article.validFrom ?? '';
            opt.dataset.validFrom = validFrom || '';
            opt.setAttribute('data-valid-from', opt.dataset.validFrom);

            const validUntil = article.valid_until ?? article.validUntil ?? '';
            opt.dataset.validUntil = validUntil || '';
            opt.setAttribute('data-valid-until', opt.dataset.validUntil);

            datalist.appendChild(opt);
        });
    }

    const ARTICLE_CACHE_TTL = 5 * 60 * 1000;
    const articleCache = new Map();
    const lineItemPersistTimers = new WeakMap();

    function clearArticleCache() {
        articleCache.clear();
    }

    function getArticleCacheKey(query) {
        const normalized = (query || '').trim().toLowerCase();
        return normalized === '' ? '__all__' : normalized;
    }

    function getCachedArticles(cacheKey) {
        const entry = articleCache.get(cacheKey);
        if (!entry) {
            return null;
        }

        if (entry.expiresAt > Date.now()) {
            return entry;
        }

        articleCache.delete(cacheKey);
        return null;
    }

    function setCachedArticles(cacheKey, data, promise) {
        articleCache.set(cacheKey, {
            data,
            promise: promise ?? null,
            expiresAt: Date.now() + ARTICLE_CACHE_TTL
        });
    }

    function requestArticles(query) {
        const normalizedQuery = (query || '').trim();
        const cacheKey = getArticleCacheKey(normalizedQuery);

        const cachedEntry = getCachedArticles(cacheKey);
        if (cachedEntry?.data) {
            return Promise.resolve(cachedEntry.data);
        }

        if (cachedEntry?.promise) {
            return cachedEntry.promise;
        }

        const url = normalizedQuery
            ? `/lex-bridge/api/articles/search?q=${encodeURIComponent(normalizedQuery)}`
            : '/lex-bridge/api/articles/search';

        const fetchPromise = fetch(url)
            .then(async res => {
                if (!res.ok) {
                    const errorText = await res.text();
                    throw new Error(`Article search HTTP error ${res.status}: ${errorText}`);
                }

                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (error) {
                    throw new Error(`Article search parse error: ${error instanceof Error ? error.message : String(error)}`);
                }
            })
            .then(data => {
                const result = Array.isArray(data) ? data : [];
                setCachedArticles(cacheKey, result, null);
                return result;
            })
            .catch(error => {
                console.error('Article search error:', error);
                articleCache.delete(cacheKey);
                return [];
            });

        setCachedArticles(cacheKey, cachedEntry?.data ?? [], fetchPromise);

        return fetchPromise;
    }

    function formatArticleDisplayNumber(value, fractionDigits) {
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

    function updateLineItemCells(row, data) {
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

            cell.textContent = formatArticleDisplayNumber(value, digits);
        };

        applyNumericValue('.line-item-net-cell', data?.netAmount, 2);
        applyNumericValue('.line-item-gross-cell', data?.grossAmount, 2);
        applyNumericValue('.line-item-tax-cell', data?.taxRate, 2);
    }

    function applyArticleSelectionDetails(input, option) {
        if (!input) {
            return;
        }

        const wrapper = input.closest('.article-selector');
        if (!wrapper) {
            return;
        }

        const readDataset = (key) => {
            if (!option) {
                return '';
            }
            const datasetValue = option.dataset[key];
            if (datasetValue !== undefined) {
                return datasetValue;
            }
            const attributeValue = option.getAttribute(`data-${key.replace(/([A-Z])/g, '-$1').toLowerCase()}`);
            return attributeValue ?? '';
        };

        const articleData = {
            id: option ? (option.dataset.articleId || option.getAttribute('data-article-id') || '') : '',
            number: readDataset('articleNumber'),
            name: readDataset('articleName'),
            netAmount: readDataset('netAmount'),
            grossAmount: readDataset('grossAmount'),
            taxRate: readDataset('taxRatePercentage'),
            currency: readDataset('currency'),
            validFrom: readDataset('validFrom'),
            validUntil: readDataset('validUntil')
        };

        const row = input.closest('tr');
        if (row) {
            updateLineItemCells(row, articleData);

            const signature = computeArticleSignature(articleData, input.value);
            const shouldPersist = Boolean(option) || input.value.trim() === '';
            if (shouldPersist) {
                row.dataset.selectedArticleId = articleData.id || '';
                row.dataset.selectedArticleNumber = articleData.number || '';
                row.dataset.selectedArticleName = articleData.name || '';
                row.dataset.selectedArticleNet = articleData.netAmount || '';
                row.dataset.selectedArticleGross = articleData.grossAmount || '';
                row.dataset.selectedArticleTax = articleData.taxRate || '';
                row.dataset.selectedArticleCurrency = articleData.currency || '';
                row.dataset.selectedArticleValidFrom = articleData.validFrom || '';
                row.dataset.selectedArticleValidUntil = articleData.validUntil || '';
                row.dataset.selectedArticleLabel = option ? input.value : '';

                const setWrapperField = (selector, value) => {
                    const field = wrapper.querySelector(selector);
                    if (field) {
                        field.value = value ?? '';
                    }
                };

                setWrapperField('.article-id-field', articleData.id);
                setWrapperField('.article-number-field', articleData.number);
                setWrapperField('.article-name-field', articleData.name);
                setWrapperField('.article-net-field', articleData.netAmount);
                setWrapperField('.article-gross-field', articleData.grossAmount);
                setWrapperField('.article-tax-field', articleData.taxRate);
                setWrapperField('.article-currency-field', articleData.currency);
                setWrapperField('.article-valid-from-field', articleData.validFrom);
                setWrapperField('.article-valid-until-field', articleData.validUntil);
                setWrapperField('.article-label-field', option ? input.value : '');

                row.dataset.currentArticleSignature = signature;
                scheduleLineItemPersist(row, articleData, input.value, signature);
            }
        }
    }

    function toNumberOrNull(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        if (typeof value === 'number') {
            return Number.isNaN(value) ? null : value;
        }

        const numeric = Number(value);
        return Number.isNaN(numeric) ? null : numeric;
    }

    function normalizeSignatureValue(value) {
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
                return normalizeSignatureValue(numeric);
            }
            return trimmed;
        }

        const numeric = Number(value);
        if (!Number.isNaN(numeric)) {
            return normalizeSignatureValue(numeric);
        }

        return String(value);
    }

    function computeArticleSignature(articleData, label) {
        const safeData = articleData || {};
        const currencyValue = typeof safeData.currency === 'string'
            ? safeData.currency.toUpperCase()
            : safeData.currency;

        return JSON.stringify({
            id: normalizeSignatureValue(safeData.id),
            number: normalizeSignatureValue(safeData.number),
            name: normalizeSignatureValue(safeData.name),
            net: normalizeSignatureValue(safeData.netAmount),
            gross: normalizeSignatureValue(safeData.grossAmount),
            tax: normalizeSignatureValue(safeData.taxRate),
            currency: normalizeSignatureValue(currencyValue),
            validFrom: normalizeSignatureValue(safeData.validFrom),
            validUntil: normalizeSignatureValue(safeData.validUntil),
            label: normalizeSignatureValue(label)
        });
    }

    function normalizeArticleValue(value) {
        if (value === null || value === undefined) {
            return '';
        }

        if (typeof value === 'string') {
            return value.trim();
        }

        return String(value).trim();
    }

    function normalizeArticleData(articleData) {
        const data = articleData || {};
        return {
            id: normalizeArticleValue(data.id),
            number: normalizeArticleValue(data.number),
            name: normalizeArticleValue(data.name),
            netAmount: normalizeArticleValue(data.netAmount),
            grossAmount: normalizeArticleValue(data.grossAmount),
            taxRate: normalizeArticleValue(data.taxRate),
            currency: normalizeArticleValue(data.currency),
            validFrom: normalizeArticleValue(data.validFrom),
            validUntil: normalizeArticleValue(data.validUntil)
        };
    }

    function writeRowArticleState(row, articleData, label, options = {}) {
        if (!row) {
            return '';
        }

        const safeData = normalizeArticleData(articleData);
        const safeLabel = normalizeArticleValue(label);
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

        updateLineItemCells(row, {
            name: safeData.name,
            netAmount: safeData.netAmount,
            grossAmount: safeData.grossAmount,
            taxRate: safeData.taxRate
        });

        const signature = computeArticleSignature(safeData, safeLabel);
        row.dataset.currentArticleSignature = signature;
        if (markPersistedSignature) {
            row.dataset.persistedArticleSignature = signature;
        }

        if (!skipSchedule) {
            scheduleLineItemPersist(row, safeData, safeLabel, signature);
        }

        return signature;
    }

    function buildLineItemPersistPayload(row, articleData, displayValue) {
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
        const payload = {
            line_item_id: row.dataset.lineItemId || '',
            article_id: safeData.id || readField('.article-id-field') || null,
            article_number: safeData.number || readField('.article-number-field') || null,
            article_name: safeData.name || readField('.article-name-field') || null,
            article_label: displayValue || readField('.article-label-field') || null,
            currency: safeData.currency || readField('.article-currency-field') || null,
            net_amount: toNumberOrNull(safeData.netAmount ?? readField('.article-net-field')),
            gross_amount: toNumberOrNull(safeData.grossAmount ?? readField('.article-gross-field')),
            tax_rate_percentage: toNumberOrNull(safeData.taxRate ?? readField('.article-tax-field')),
            article_valid_from: safeData.validFrom || readField('.article-valid-from-field') || null,
            article_valid_until: safeData.validUntil || readField('.article-valid-until-field') || null
        };

        return payload;
    }

    function updateRowFromServerResponse(row, lineItem) {
        if (!row || !lineItem) {
            return;
        }

        const grossAmount = (lineItem.gross_amount ?? lineItem.line_total_gross) ?? '';
        writeRowArticleState(row, {
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

    function persistLineItemSelection(row, articleData, displayValue, signature) {
        if (!row) {
            return;
        }

        const payload = buildLineItemPersistPayload(row, articleData, displayValue);
        if (!payload || !payload.line_item_id) {
            return;
        }

        fetch('/lex-bridge/api/line-items/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
            .then(async response => {
                const text = await response.text();
                let data;
                try {
                    data = text ? JSON.parse(text) : {};
                } catch (error) {
                    throw new Error('Line item update parse error');
                }

                if (!response.ok || !data?.isSuccess) {
                    const message = data?.error || `Line item update failed (${response.status})`;
                    throw new Error(message);
                }

                if (data.lineItem) {
                    updateRowFromServerResponse(row, data.lineItem);
                    return;
                }

                if (signature) {
                    row.dataset.persistedArticleSignature = signature;
                }
            })
            .catch(error => {
                console.error('Line item update error:', error);
                if (window.lexBridge?.toastNotifier) {
                    window.lexBridge.toastNotifier.show('Speichern der Position fehlgeschlagen', 'error');
                }
            });
    }

    function scheduleLineItemPersist(row, articleData, displayValue, signature) {
        if (!row || !row.dataset) {
            return;
        }

        const persistSignature = signature || computeArticleSignature(articleData, displayValue);
        const persistedSignature = row.dataset.persistedArticleSignature || '';
        if (persistSignature === persistedSignature) {
            return;
        }

        const existingTimer = lineItemPersistTimers.get(row);
        if (existingTimer) {
            clearTimeout(existingTimer);
        }

        const timer = setTimeout(() => {
            lineItemPersistTimers.delete(row);
            persistLineItemSelection(row, articleData, displayValue, persistSignature);
        }, 250);

        lineItemPersistTimers.set(row, timer);
    }

    function initializeLineItemPersistenceState(table) {
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
            writeRowArticleState(row, articleData, label, {
                skipSchedule: true,
                markPersistedSignature: true
            });
        });
    }

    function applyLineItemDialogResult(row, editorData) {
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

        writeRowArticleState(row, articleData, articleLabel);
    }

    function ensureArticleOptions(input, datalistOverride) {
        const listId = input.getAttribute('list');
        const datalist = datalistOverride || (listId ? document.getElementById(listId) : null);
        if (!datalist) {
            return;
        }

        if (input.value.trim() !== '') {
            return;
        }

        if (datalist.dataset.loading === 'true') {
            return;
        }

        const currentOptions = datalist.options || datalist.children;
        if (currentOptions && currentOptions.length > 1) {
            return;
        }

        const cachedAll = getCachedArticles('__all__');
        if (cachedAll?.data) {
            populateArticleDatalist(datalist, cachedAll.data);
            syncArticleSelectionInternal(input, datalist);
            return;
        }

        populateArticleDatalist(datalist, []);
        datalist.dataset.loading = 'true';

        requestArticles('')
            .then(articles => {
                if (input.value.trim() !== '') {
                    return;
                }

                populateArticleDatalist(datalist, Array.isArray(articles) ? articles : []);
                syncArticleSelectionInternal(input, datalist);
            })
            .finally(() => {
                delete datalist.dataset.loading;
            });
    }

    function syncArticleSelectionInternal(input, datalistOverride) {
        if (!input) {
            return;
        }

        const listId = input.getAttribute('list');
        const datalist = datalistOverride || (listId ? document.getElementById(listId) : null);
        if (!datalist) {
            applyArticleSelectionDetails(input, null);
            return;
        }

        const value = input.value.trim();
        if (!value) {
            applyArticleSelectionDetails(input, null);
            return;
        }

        const options = datalist.options || datalist.children;
        for (let index = 0; index < options.length; index += 1) {
            const option = options[index];
            if (option.value !== value) {
                continue;
            }

            applyArticleSelectionDetails(input, option);
            return;
        }

        applyArticleSelectionDetails(input, null);
    }

    if (!window.lexBridge) {
        window.lexBridge = {};
    }

    if (!window.lexBridge.syncArticleSelection) {
        window.lexBridge.syncArticleSelection = syncArticleSelectionInternal;
    }
    if (!window.lexBridge.initializeLineItemPersistenceState) {
        window.lexBridge.initializeLineItemPersistenceState = initializeLineItemPersistenceState;
    }
    if (!window.lexBridge.applyLineItemDialogResult) {
        window.lexBridge.applyLineItemDialogResult = applyLineItemDialogResult;
    }

    function handleArticleSearch(input) {
        const listId = input.getAttribute('list');
        const datalist = listId ? document.getElementById(listId) : null;
        if (!datalist) {
            console.warn('Article datalist not found for input', input);
            return;
        }

        const query = input.value.trim();
        if (!query) {
            ensureArticleOptions(input, datalist);
            return;
        }

        const timer = articleDebounceTimers.get(input);
        if (timer) {
            clearTimeout(timer);
        }

        syncArticleSelectionInternal(input, datalist);

        const newTimer = setTimeout(() => {
            requestArticles(query)
                .then(articles => {
                    if (input.value.trim() !== query) {
                        return;
                    }

                    populateArticleDatalist(datalist, articles);
                    syncArticleSelectionInternal(input, datalist);
                });
        }, 300);

        articleDebounceTimers.set(input, newTimer);
    }

    document.addEventListener('input', function (event) {
        const target = event.target;
        if (!target || !target.classList.contains('customer-search-combobox')) {
            return;
        }
        handleCustomerSearch(target);
        syncCustomerSelection(target);
    }, true); // capture to ensure we catch events even if re-rendered

    document.addEventListener('change', function (event) {
        const target = event.target;
        if (!target || !target.classList.contains('customer-search-combobox')) {
            return;
        }
        syncCustomerSelection(target);
    }, true); // capture to ensure we catch events even if re-rendered

    document.addEventListener('focus', function (event) {
        const target = event.target;
        if (!target || !target.classList.contains('article-search-combobox')) {
            return;
        }

        ensureArticleOptions(target);
    }, true);

    document.addEventListener('input', function (event) {
        const target = event.target;
        if (!target || !target.classList.contains('article-search-combobox')) {
            return;
        }
        ensureArticleOptions(target);
        handleArticleSearch(target);
        syncArticleSelectionInternal(target);
    }, true);

    document.addEventListener('change', function (event) {
        const target = event.target;
        if (!target || !target.classList.contains('article-search-combobox')) {
            return;
        }
        syncArticleSelectionInternal(target);
    }, true);
})();

class LineItemEditorDialog {
    constructor(page) {
        this.page = page;
        this.dialog = null;
        this.form = null;
        this.articleContainer = null;
        this.articleWrapper = null;
        this.articleInput = null;
        this.boundArticleHandler = null;
        this.nameInput = null;
        this.articleNumberInput = null;
        this.articleIdInput = null;
        this.currencyInput = null;
        this.netAmountInput = null;
        this.grossAmountInput = null;
        this.taxRateInput = null;
        this.validFromInput = null;
        this.validUntilInput = null;
        this.saveButton = null;
        this.cancelButton = null;
        this.titleElement = null;
        this.currentRow = null;
    }

    ensureDialog() {
        if (this.dialog) {
            return;
        }

        if (!document.getElementById('line-item-editor-styles')) {
            const style = document.createElement('style');
            style.id = 'line-item-editor-styles';
            style.textContent = '.line-item-editor-dialog{border:none;border-radius:8px;padding:0;width:100%;max-width:520px;box-sizing:border-box;font-family:inherit;}\n.line-item-editor-dialog::backdrop{background:rgba(0,0,0,0.35);}\n.line-item-editor-form{display:flex;flex-direction:column;gap:16px;padding:20px;box-sizing:border-box;}\n.line-item-editor-title{margin:0;font-size:1.2em;}\n.line-item-editor-body{display:flex;flex-direction:column;gap:12px;}\n.line-item-editor-field{display:flex;flex-direction:column;gap:4px;}\n.line-item-editor-field label{font-size:0.85em;color:#333;}\n.line-item-editor-field input{width:100%;padding:6px 8px;border:1px solid #ccc;border-radius:4px;font-size:0.95em;box-sizing:border-box;}\n.line-item-editor-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:12px;}\n.line-item-editor-footer{display:flex;justify-content:flex-end;gap:8px;}\n.line-item-editor-dialog.line-item-editor-open{display:block;}\n.line-item-editor-dialog .article-selector{display:flex;flex-direction:column;gap:4px;}\n.line-item-editor-dialog .article-selector input[type="text"]{padding:6px 8px;}';
            document.head.appendChild(style);
        }

        const dialog = document.createElement('dialog');
        dialog.className = 'line-item-editor-dialog';
        dialog.innerHTML = '<form method="dialog" class="line-item-editor-form">\n                <h2 class="line-item-editor-title">Position bearbeiten</h2>\n                <div class="line-item-editor-body">\n                    <div class="line-item-editor-field">\n                        <label>Artikel</label>\n                        <div class="line-item-editor-article"></div>\n                    </div>\n                    <div class="line-item-editor-grid">\n                        <div class="line-item-editor-field">\n                            <label for="line-item-editor-article-number">Artikelnummer</label>\n                            <input type="text" id="line-item-editor-article-number" autocomplete="off" disabled>\n                        </div>\n                        <div class="line-item-editor-field">\n                            <label for="line-item-editor-article-id">Artikel-ID</label>\n                            <input type="text" id="line-item-editor-article-id" autocomplete="off" disabled>\n                        </div>\n                    </div>\n                    <div class="line-item-editor-field">\n                        <label for="line-item-editor-name">Bezeichnung</label>\n                        <input type="text" id="line-item-editor-name" autocomplete="off" disabled>\n                    </div>\n                    <div class="line-item-editor-grid">\n                        <div class="line-item-editor-field">\n                            <label for="line-item-editor-net">Netto</label>\n                            <input type="number" step="0.01" id="line-item-editor-net" autocomplete="off" disabled>\n                        </div>\n                        <div class="line-item-editor-field">\n                            <label for="line-item-editor-gross">Brutto</label>\n                            <input type="number" step="0.01" id="line-item-editor-gross" autocomplete="off" disabled>\n                        </div>\n                        <div class="line-item-editor-field">\n                            <label for="line-item-editor-tax">Steuer %</label>\n                            <input type="number" step="0.01" id="line-item-editor-tax" autocomplete="off" disabled>\n                        </div>\n                        <div class="line-item-editor-field">\n                            <label for="line-item-editor-currency">Währung</label>\n                            <input type="text" id="line-item-editor-currency" maxlength="3" autocomplete="off" disabled>\n                        </div>\n                    </div>\n                    <div class="line-item-editor-grid">\n                        <div class="line-item-editor-field">\n                            <label for="line-item-editor-valid-from">Gültig ab</label>\n                            <input type="text" id="line-item-editor-valid-from" placeholder="YYYY-MM-DD" disabled>\n                        </div>\n                        <div class="line-item-editor-field">\n                            <label for="line-item-editor-valid-until">Gültig bis</label>\n                            <input type="text" id="line-item-editor-valid-until" placeholder="YYYY-MM-DD" disabled>\n                        </div>\n                    </div>\n                </div>\n                <div class="line-item-editor-footer">\n                    <button type="button" class="btn btn-secondary" data-action="cancel">Abbrechen</button>\n                    <button type="submit" class="btn btn-primary" data-action="save">Speichern</button>\n                </div>\n            </form>';
        document.body.appendChild(dialog);

        this.dialog = dialog;
        this.form = dialog.querySelector('.line-item-editor-form');
        this.articleContainer = dialog.querySelector('.line-item-editor-article');
        this.titleElement = dialog.querySelector('.line-item-editor-title');
        this.articleNumberInput = dialog.querySelector('#line-item-editor-article-number');
        this.articleIdInput = dialog.querySelector('#line-item-editor-article-id');
        this.nameInput = dialog.querySelector('#line-item-editor-name');
        this.netAmountInput = dialog.querySelector('#line-item-editor-net');
        this.grossAmountInput = dialog.querySelector('#line-item-editor-gross');
        this.taxRateInput = dialog.querySelector('#line-item-editor-tax');
        this.currencyInput = dialog.querySelector('#line-item-editor-currency');
        this.validFromInput = dialog.querySelector('#line-item-editor-valid-from');
        this.validUntilInput = dialog.querySelector('#line-item-editor-valid-until');
        this.saveButton = dialog.querySelector('[data-action="save"]');
        this.cancelButton = dialog.querySelector('[data-action="cancel"]');

        this.form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.handleSave();
        });

        if (this.cancelButton) {
            this.cancelButton.addEventListener('click', () => {
                this.close();
            });
        }

        dialog.addEventListener('cancel', (event) => {
            event.preventDefault();
            this.close();
        });

        dialog.addEventListener('close', () => {
            this.currentRow = null;
        });

        const syncHiddenFields = () => {
            this.syncHiddenFieldsFromInputs();
        };

        const inputs = [
            this.articleNumberInput,
            this.articleIdInput,
            this.nameInput,
            this.netAmountInput,
            this.grossAmountInput,
            this.taxRateInput,
            this.currencyInput,
            this.validFromInput,
            this.validUntilInput
        ];
        inputs.forEach(input => {
            if (input) {
                input.addEventListener('input', syncHiddenFields);
            }
        });
    }

    escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    renderArticleSelector(values) {
        this.ensureDialog();

        if (this.articleInput && this.boundArticleHandler) {
            this.articleInput.removeEventListener('input', this.boundArticleHandler);
            this.articleInput.removeEventListener('change', this.boundArticleHandler);
        }

        if (this.articleContainer) {
            this.articleContainer.innerHTML = '';
        }

        const safeValues = {
            label: values?.label || '',
            id: values?.id || '',
            number: values?.number || '',
            name: values?.name || '',
            netAmount: values?.netAmount || '',
            grossAmount: values?.grossAmount || '',
            taxRate: values?.taxRate || '',
            currency: values?.currency || '',
            validFrom: values?.validFrom || '',
            validUntil: values?.validUntil || ''
        };

        const datalistId = `line-item-editor-options-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
        const wrapper = document.createElement('div');
        wrapper.className = 'article-selector';

        const presetOption = safeValues.label && safeValues.id
            ? `<option value="${this.escapeHtml(safeValues.label)}" data-article-id="${this.escapeHtml(safeValues.id)}" data-article-number="${this.escapeHtml(safeValues.number)}" data-article-name="${this.escapeHtml(safeValues.name)}" data-net-amount="${this.escapeHtml(safeValues.netAmount)}" data-gross-amount="${this.escapeHtml(safeValues.grossAmount)}" data-tax-rate-percentage="${this.escapeHtml(safeValues.taxRate)}" data-currency="${this.escapeHtml(safeValues.currency)}" data-valid-from="${this.escapeHtml(safeValues.validFrom)}" data-valid-until="${this.escapeHtml(safeValues.validUntil)}"></option>`
            : '';

        wrapper.innerHTML = `
            <input type="text" class="article-search-combobox" list="${datalistId}" value="${this.escapeHtml(safeValues.label)}" placeholder="Artikel wählen">
            <input type="hidden" class="article-id-field" value="${this.escapeHtml(safeValues.id)}">
            <input type="hidden" class="article-number-field" value="${this.escapeHtml(safeValues.number)}">
            <input type="hidden" class="article-name-field" value="${this.escapeHtml(safeValues.name)}">
            <input type="hidden" class="article-net-field" value="${this.escapeHtml(safeValues.netAmount)}">
            <input type="hidden" class="article-gross-field" value="${this.escapeHtml(safeValues.grossAmount)}">
            <input type="hidden" class="article-tax-field" value="${this.escapeHtml(safeValues.taxRate)}">
            <input type="hidden" class="article-currency-field" value="${this.escapeHtml(safeValues.currency)}">
            <input type="hidden" class="article-valid-from-field" value="${this.escapeHtml(safeValues.validFrom)}">
            <input type="hidden" class="article-valid-until-field" value="${this.escapeHtml(safeValues.validUntil)}">
            <input type="hidden" class="article-label-field" value="${this.escapeHtml(safeValues.label)}">
            <datalist id="${datalistId}">
                <option value="">Artikel wählen</option>
                ${presetOption}
            </datalist>
        `;

        if (this.articleContainer) {
            this.articleContainer.appendChild(wrapper);
        }

        this.articleWrapper = wrapper;
        this.articleInput = wrapper.querySelector('.article-search-combobox');
        this.boundArticleHandler = () => {
            this.syncDialogArticleSelection();
        };
        if (this.articleInput) {
            this.articleInput.addEventListener('input', this.boundArticleHandler);
            this.articleInput.addEventListener('change', this.boundArticleHandler);
        }

        setTimeout(() => {
            this.syncDialogArticleSelection();
        }, 0);
    }

    syncDialogArticleSelection() {
        if (!this.articleInput) {
            return;
        }

        const listId = this.articleInput.getAttribute('list');
        const datalist = listId ? document.getElementById(listId) : null;
        const value = this.articleInput.value.trim();

        let option = null;
        if (datalist) {
            const options = datalist.options || datalist.children;
            for (let index = 0; index < options.length; index += 1) {
                const candidate = options[index];
                if (candidate.value === value) {
                    option = candidate;
                    break;
                }
            }
        }

        if (option) {
            const data = this.extractArticleOption(option);
            this.applyArticleDataToDialog(data, value);
        } else {
            this.applyArticleDataToDialog(null, value);
        }
    }

    extractArticleOption(option) {
        if (!option) {
            return null;
        }

        const readDataset = (key) => {
            if (!option) {
                return '';
            }
            if (option.dataset && option.dataset[key] !== undefined) {
                return option.dataset[key];
            }
            const attributeValue = option.getAttribute(`data-${key.replace(/([A-Z])/g, '-$1').toLowerCase()}`);
            return attributeValue ?? '';
        };

        return {
            id: option.dataset.articleId || option.getAttribute('data-article-id') || '',
            number: readDataset('articleNumber'),
            name: readDataset('articleName'),
            netAmount: readDataset('netAmount'),
            grossAmount: readDataset('grossAmount'),
            taxRate: readDataset('taxRatePercentage'),
            currency: readDataset('currency'),
            validFrom: readDataset('validFrom'),
            validUntil: readDataset('validUntil')
        };
    }

    applyArticleDataToDialog(data, label) {
        if (!this.articleWrapper) {
            return;
        }

        const setField = (selector, value) => {
            const field = this.articleWrapper.querySelector(selector);
            if (field) {
                field.value = value ?? '';
            }
        };

        setField('.article-label-field', label ?? '');

        if (data) {
            setField('.article-id-field', data.id ?? '');
            setField('.article-number-field', data.number ?? '');
            setField('.article-name-field', data.name ?? '');
            setField('.article-net-field', data.netAmount ?? '');
            setField('.article-gross-field', data.grossAmount ?? '');
            setField('.article-tax-field', data.taxRate ?? '');
            setField('.article-currency-field', data.currency ?? '');
            setField('.article-valid-from-field', data.validFrom ?? '');
            setField('.article-valid-until-field', data.validUntil ?? '');

            if (this.articleNumberInput) {
                this.articleNumberInput.value = data.number ?? '';
            }
            if (this.articleIdInput) {
                this.articleIdInput.value = data.id ?? '';
            }
            if (this.nameInput && data.name !== undefined) {
                this.nameInput.value = data.name ?? '';
            }
            if (this.netAmountInput && data.netAmount !== undefined) {
                this.netAmountInput.value = data.netAmount ?? '';
            }
            if (this.grossAmountInput && data.grossAmount !== undefined) {
                this.grossAmountInput.value = data.grossAmount ?? '';
            }
            if (this.taxRateInput && data.taxRate !== undefined) {
                this.taxRateInput.value = data.taxRate ?? '';
            }
            if (this.currencyInput && data.currency !== undefined) {
                this.currencyInput.value = data.currency ?? '';
            }
            if (this.validFromInput && data.validFrom !== undefined) {
                this.validFromInput.value = data.validFrom ?? '';
            }
            if (this.validUntilInput && data.validUntil !== undefined) {
                this.validUntilInput.value = data.validUntil ?? '';
            }
        }

        this.syncHiddenFieldsFromInputs();
    }

    updateHiddenField(selector, value) {
        if (!this.articleWrapper) {
            return;
        }
        const field = this.articleWrapper.querySelector(selector);
        if (field) {
            field.value = value ?? '';
        }
    }

    syncHiddenFieldsFromInputs() {
        this.updateHiddenField('.article-number-field', this.articleNumberInput ? this.articleNumberInput.value : '');
        this.updateHiddenField('.article-id-field', this.articleIdInput ? this.articleIdInput.value : '');
        this.updateHiddenField('.article-name-field', this.nameInput ? this.nameInput.value : '');
        this.updateHiddenField('.article-net-field', this.netAmountInput ? this.netAmountInput.value : '');
        this.updateHiddenField('.article-gross-field', this.grossAmountInput ? this.grossAmountInput.value : '');
        this.updateHiddenField('.article-tax-field', this.taxRateInput ? this.taxRateInput.value : '');
        this.updateHiddenField('.article-currency-field', this.currencyInput ? this.currencyInput.value : '');
        this.updateHiddenField('.article-valid-from-field', this.validFromInput ? this.validFromInput.value : '');
        this.updateHiddenField('.article-valid-until-field', this.validUntilInput ? this.validUntilInput.value : '');
        this.updateHiddenField('.article-label-field', this.articleInput ? this.articleInput.value : '');
    }

    normalizeDecimal(value) {
        if (value === null || value === undefined) {
            return '';
        }

        const trimmed = String(value).trim();
        if (trimmed === '') {
            return '';
        }

        return trimmed.replace(',', '.');
    }

    collectRowData(row) {
        const dataset = row?.dataset || {};
        const wrapper = row ? row.querySelector('.article-selector') : null;
        const readWrapper = (selector) => {
            if (!wrapper) {
                return '';
            }
            const field = wrapper.querySelector(selector);
            return field ? field.value : '';
        };

        return {
            label: dataset.selectedArticleLabel || dataset.articleLabel || readWrapper('.article-label-field') || '',
            id: dataset.selectedArticleId || dataset.articleId || readWrapper('.article-id-field') || '',
            number: dataset.selectedArticleNumber || dataset.articleNumber || readWrapper('.article-number-field') || '',
            name: dataset.selectedArticleName || dataset.articleName || readWrapper('.article-name-field') || '',
            currency: dataset.selectedArticleCurrency || dataset.articleCurrency || readWrapper('.article-currency-field') || '',
            netAmount: dataset.selectedArticleNet || dataset.articleNet || readWrapper('.article-net-field') || '',
            grossAmount: dataset.selectedArticleGross || dataset.articleGross || readWrapper('.article-gross-field') || '',
            taxRate: dataset.selectedArticleTax || dataset.articleTax || readWrapper('.article-tax-field') || '',
            validFrom: dataset.selectedArticleValidFrom || dataset.articleValidFrom || readWrapper('.article-valid-from-field') || '',
            validUntil: dataset.selectedArticleValidUntil || dataset.articleValidUntil || readWrapper('.article-valid-until-field') || ''
        };
    }

    open(row) 
    {
        if (!row) {
            return;
        }

        this.ensureDialog();
        this.currentRow = row;

        const data = this.collectRowData(row);
        this.renderArticleSelector(data);

        if (this.articleNumberInput) {
            this.articleNumberInput.value = data.number ?? '';
        }
        if (this.articleIdInput) {
            this.articleIdInput.value = data.id ?? '';
        }
        if (this.nameInput) {
            this.nameInput.value = data.name ?? '';
        }
        if (this.currencyInput) {
            this.currencyInput.value = data.currency ?? '';
        }
        if (this.netAmountInput) {
            this.netAmountInput.value = data.netAmount ?? '';
        }
        if (this.grossAmountInput) {
            this.grossAmountInput.value = data.grossAmount ?? '';
        }
        if (this.taxRateInput) {
            this.taxRateInput.value = data.taxRate ?? '';
        }
        if (this.validFromInput) {
            this.validFromInput.value = data.validFrom ?? '';
        }
        if (this.validUntilInput) {
            this.validUntilInput.value = data.validUntil ?? '';
        }

        this.syncHiddenFieldsFromInputs();

        if (this.titleElement) {
            const rowId = row.dataset.lineItemId || '';
            const positionNumber = row.dataset.lineOrder || '';
            const labelNumber = positionNumber || rowId;
            this.titleElement.textContent = labelNumber ? `Position Nr. ${labelNumber}` : 'Position';
        }

        if (this.dialog) {
            this.dialog.classList.remove('line-item-editor-open');
        }

        if (typeof this.dialog?.showModal === 'function') {
            if (!this.dialog.open) {
                this.dialog.showModal();
            }
        } else if (this.dialog) {
            this.dialog.setAttribute('open', 'open');
            this.dialog.classList.add('line-item-editor-open');
        }

        if (this.articleInput) {
            this.articleInput.focus();
            this.articleInput.select();
        }
    }

    handleSave() {
        if (!this.currentRow) {
            this.close();
            return;
        }

        const readWrapperField = (selector) => {
            if (!this.articleWrapper) {
                return '';
            }
            const field = this.articleWrapper.querySelector(selector);
            return field ? field.value : '';
        };

        const payload = {
            articleLabel: this.articleInput ? this.articleInput.value : '',
            articleId: this.articleIdInput ? this.articleIdInput.value : readWrapperField('.article-id-field'),
            articleNumber: this.articleNumberInput ? this.articleNumberInput.value : readWrapperField('.article-number-field'),
            articleName: this.nameInput ? this.nameInput.value : readWrapperField('.article-name-field'),
            netAmount: this.netAmountInput ? this.netAmountInput.value : readWrapperField('.article-net-field'),
            grossAmount: this.grossAmountInput ? this.grossAmountInput.value : readWrapperField('.article-gross-field'),
            taxRate: this.taxRateInput ? this.taxRateInput.value : readWrapperField('.article-tax-field'),
            currency: this.currencyInput ? this.currencyInput.value : readWrapperField('.article-currency-field'),
            validFrom: this.validFromInput ? this.validFromInput.value : readWrapperField('.article-valid-from-field'),
            validUntil: this.validUntilInput ? this.validUntilInput.value : readWrapperField('.article-valid-until-field')
        };

        if (payload.currency) {
            payload.currency = payload.currency.toUpperCase();
        }

        payload.netAmount = this.normalizeDecimal(payload.netAmount);
        payload.grossAmount = this.normalizeDecimal(payload.grossAmount);
        payload.taxRate = this.normalizeDecimal(payload.taxRate);

        this.syncHiddenFieldsFromInputs();

        if (window.lexBridge?.applyLineItemDialogResult) {
            window.lexBridge.applyLineItemDialogResult(this.currentRow, payload);
        } else {
            console.warn('applyLineItemDialogResult is not available');
        }

        this.close();
    }

    close() {
        if (!this.dialog) {
            return;
        }

        if (typeof this.dialog.close === 'function') {
            if (this.dialog.open) {
                this.dialog.close();
            }
        } else {
            this.dialog.removeAttribute('open');
        }

        this.dialog.classList.remove('line-item-editor-open');

        this.currentRow = null;
    }
}

class LineItemsPage {
        getSelectedLineItemIds() {
            return Array.from(document.querySelectorAll('.line-item-select-checkbox:checked'))
                .map(cb => cb.getAttribute('data-line-item-id'))
                .filter(Boolean);
        }

        async handleCreateInvoiceFromSelection() {
            // Get selected line item IDs
            const selectedIds = this.getSelectedLineItemIds();
            if (!selectedIds.length) {
                alert('Bitte wählen Sie mindestens eine Position aus.');
                return;
            }

            // Get customer ID from filter form
            const form = document.querySelector('form[name="get-line-items"]');
            let customerId = '';
            if (form) {
                const hidden = form.querySelector('input[type="hidden"][name="customer_id"]');
                if (hidden) customerId = hidden.value;
            }
            if (!customerId) {
                alert('Bitte wählen Sie einen Kunden aus.');
                return;
            }

            // Prepare lineItems array (just IDs, or you can fetch more info if needed)
            const lineItems = selectedIds.map(id => ({ id }));

            // Optionally, ask for currency or use a default
            const currency = 'EUR';

            // Send to API
            try {
                const response = await fetch('/lex-bridge/api/invoices', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ customer_id: customerId, currency, line_items: lineItems })
                });
                const result = await response.json();
                if (response.ok && result.invoice_id) {
                    alert('Rechnung erfolgreich erstellt!');
                } else {
                    alert('Fehler beim Erstellen der Rechnung: ' + (result.error || 'Unbekannter Fehler'));
                }
            } catch (e) {
                alert('Fehler beim Senden der Anfrage: ' + e.message);
            }
        }

        async syncArticlesFromLexware(button) {
            const targetButton = button instanceof HTMLElement ? button : null;
            const originalLabel = targetButton ? targetButton.innerHTML : null;

            try {
                if (targetButton) {
                    targetButton.disabled = true;
                    targetButton.innerHTML = '<span class="btn-icon spinning">↻</span> Synchronisieren...';
                }

                const response = await fetch('/lex-bridge/api/articles/sync', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: '{}'
                });

                const text = await response.text();
                let result;
                try {
                    result = text ? JSON.parse(text) : {};
                } catch (parseError) {
                    throw new Error('Antwort der Artikelsynchronisation konnte nicht gelesen werden.');
                }

                if (!response.ok || !result?.isSuccess) {
                    const errorMessage = Array.isArray(result?.errors) && result.errors.length
                        ? result.errors.join(', ')
                        : result?.error || `Synchronisation fehlgeschlagen (${response.status})`;
                    throw new Error(errorMessage);
                }

                clearArticleCache();

                if (this.lexBridge?.toastNotifier) {
                    const created = result.created ?? 0;
                    const updated = result.updated ?? 0;
                    const prices = result.price_updates ?? 0;
                    this.lexBridge.toastNotifier.show(`Artikel synchronisiert (neu: ${created}, aktualisiert: ${updated}, Preise: ${prices})`, 'success');
                } else {
                    alert('Artikel erfolgreich synchronisiert.');
                }
            } catch (error) {
                console.error('Article sync error:', error);
                const message = error instanceof Error ? error.message : String(error);
                if (this.lexBridge?.toastNotifier) {
                    this.lexBridge.toastNotifier.show(message, 'error');
                } else {
                    alert(message);
                }
            } finally {
                if (targetButton && originalLabel !== null) {
                    targetButton.disabled = false;
                    targetButton.innerHTML = originalLabel;
                }
            }
        }
    static handlerSetup = false;

    constructor(lexBridge) {
        this.lexBridge = lexBridge;
        this.editorDialog = new LineItemEditorDialog(this);
        this.lineItemsClickHandler = null;
        if (!LineItemsPage.handlerSetup) {
            this.setupFilterDelegation();
            LineItemsPage.handlerSetup = true;
        }
        this.setupFilterFormDirect();
        // Send-invoice button is rendered dynamically with the line-items list
    }

    setupFilterDelegation() {
        document.addEventListener('submit', async (event) => {
            const form = event.target;
            if (!form || !form.matches('form[name="get-line-items"]')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            await this.handleFilterSubmit(form);
        }, true);
    }

    setupFilterFormDirect() {
        const form = document.querySelector('form[name="get-line-items"]');
        if (!form || form.dataset.ajaxHandlerAttached === 'true') {
            return;
        }
        form.dataset.ajaxHandlerAttached = 'true';
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            event.stopPropagation();
            await this.handleFilterSubmit(form);
        });
    }

    async handleFilterSubmit(form) {
        this.ensureCustomerSelection(form);

        const formData = new FormData(form);
        const params = new URLSearchParams();

        const formEntries = formData.entries();
        for (const [key, value] of formEntries) {
            if (key === 'customer_search') {
                continue;
            }
            if (value !== null && value !== '') {
                params.append(key, value);
            }
        }

        const button = form.querySelector('button[type="submit"]');
        const originalLabel = button ? button.innerHTML : null;

        try {
            if (button) {
                button.disabled = true;
                button.innerHTML = '<span class="btn-icon spinning">↻</span> Filtern...';
            }

            const response = await fetch(`/lex-bridge/api/line-items?${params.toString()}`);
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Line items request failed (${response.status}): ${errorText}`);
            }

            const data = await response.json();
            this.updateLineItemsList(data);

            if (this.lexBridge?.toastNotifier) {
                this.lexBridge.toastNotifier.show('Line items aktualisiert', 'success');
            }

        } 
        catch (error) {
            console.error('Line items filter error:', error);
            if (this.lexBridge?.toastNotifier) {
                this.lexBridge.toastNotifier.show('Fehler beim Laden der Positionen', 'error');
            }
        } finally {
            if (button && originalLabel !== null) {
                button.disabled = false;
                button.innerHTML = originalLabel;
            }
        }
    }

    updateLineItemsList(data) {
        const container = document.querySelector('.line-items-list');
        if (!container) {
            console.warn('Line items list container not found');
            return;
        }

        if (this.lineItemsChangeHandler) {
            container.removeEventListener('change', this.lineItemsChangeHandler);
            this.lineItemsChangeHandler = null;
        }

        if (this.lineItemsClickHandler) {
            container.removeEventListener('click', this.lineItemsClickHandler);
            this.lineItemsClickHandler = null;
        }

        container.innerHTML = '';

        const toolbar = document.createElement('div');
        toolbar.className = 'line-items-toolbar';

        const sendBtn = document.createElement('button');
        sendBtn.type = 'button';
        sendBtn.id = 'send-invoice-btn';
        sendBtn.className = 'btn btn-primary';
        sendBtn.classList.add('line-items-toolbar-btn');
        sendBtn.innerHTML = 'Erstellen <span class="btn-icon" style="font-size:1.1em;">➤</span>';
        sendBtn.disabled = true;
        sendBtn.addEventListener('click', () => {
            this.handleCreateInvoiceFromSelection();
        });
        const actionGroupLeft = document.createElement('div');
        actionGroupLeft.className = 'line-items-toolbar-left';
        actionGroupLeft.appendChild(sendBtn);
        toolbar.appendChild(actionGroupLeft);

        const actionGroupRight = document.createElement('div');
        actionGroupRight.className = 'line-items-toolbar-right';

        const syncBtn = document.createElement('button');
        syncBtn.type = 'button';
        syncBtn.id = 'sync-articles-btn';
        syncBtn.className = 'btn btn-secondary';
        syncBtn.classList.add('line-items-toolbar-btn');
        syncBtn.innerHTML = '<span class="btn-icon" aria-hidden="true">↻</span> Artikel synchr';
        syncBtn.addEventListener('click', () => {
            this.syncArticlesFromLexware(syncBtn);
        });
        actionGroupRight.appendChild(syncBtn);
        toolbar.appendChild(actionGroupRight);

        container.appendChild(toolbar);

        const items = Array.isArray(data?.lineItems) ? data.lineItems : [];
        if (items.length === 0) {
            const emptyState = document.createElement('p');
            emptyState.className = 'line-items-empty';
            emptyState.textContent = 'Keine Positionen gefunden.';
            container.appendChild(emptyState);
            return;
        }

        const tableRows = items.map((item, index) => {
            const position = item.line_order != null ? item.line_order : '';
            const quantityValue = item.quantity != null ? String(item.quantity) : '';
            const netValue = item.net_amount != null ? String(item.net_amount) : '';
            const grossValue = (item.gross_amount ?? item.line_total_gross) != null
                ? String(item.gross_amount ?? item.line_total_gross)
                : '';
            const taxValue = item.tax_rate_percentage != null ? String(item.tax_rate_percentage) : '';
            const currencyValue = item.currency ?? '';
            const articleIdValue = item.article_id ?? '';
            const articleNumberValue = item.article_number ?? '';
            const articleNameValue = item.name ?? '';
            const articleLabelValue = item.article_label
                || (articleNumberValue && articleNameValue ? `${articleNumberValue} - ${articleNameValue}` : articleNameValue)
                || '';
            const validFromValue = item.article_valid_from ?? '';
            const validUntilValue = item.article_valid_until ?? '';
            const { date: createdDate, time: createdTime } = this.splitDateTime(item.created_at);

            const checkbox = `<input type="checkbox" class="line-item-select-checkbox" data-line-item-id="${this.escapeHtml(item.id ?? '')}">`;
            const articleListId = `article-options-${index}-${item.id ?? 'row'}`;
            const safeArticleLabel = this.escapeHtml(articleLabelValue);
            const safeArticleId = this.escapeHtml(articleIdValue);
            const safeArticleNumber = this.escapeHtml(articleNumberValue);
            const safeArticleName = this.escapeHtml(articleNameValue);
            const safeNetValue = this.escapeHtml(netValue);
            const safeGrossValue = this.escapeHtml(grossValue);
            const safeTaxValue = this.escapeHtml(taxValue);
            const safeCurrencyValue = this.escapeHtml(currencyValue);
            const safeValidFrom = this.escapeHtml(validFromValue);
            const safeValidUntil = this.escapeHtml(validUntilValue);

            const presetOption = safeArticleLabel && safeArticleId
                ? `<option value="${safeArticleLabel}" data-article-id="${safeArticleId}"></option>`
                : '';

            const articleCell = `
                <div class="article-selector">
                    <input type="text" class="article-search-combobox" list="${articleListId}" value="${safeArticleLabel}" placeholder="Artikel wählen">
                    <input type="hidden" class="article-id-field" value="${safeArticleId}">
                    <input type="hidden" class="article-number-field" value="${safeArticleNumber}">
                    <input type="hidden" class="article-name-field" value="${safeArticleName}">
                    <input type="hidden" class="article-net-field" value="${safeNetValue}">
                    <input type="hidden" class="article-gross-field" value="${safeGrossValue}">
                    <input type="hidden" class="article-tax-field" value="${safeTaxValue}">
                    <input type="hidden" class="article-currency-field" value="${safeCurrencyValue}">
                    <input type="hidden" class="article-valid-from-field" value="${safeValidFrom}">
                    <input type="hidden" class="article-valid-until-field" value="${safeValidUntil}">
                    <input type="hidden" class="article-label-field" value="${safeArticleLabel}">
                    <datalist id="${articleListId}">
                        <option value="">Artikel wählen</option>
                        ${presetOption}
                    </datalist>
                </div>
            `;

            const quantityDisplay = quantityValue !== '' ? this.formatNumber(quantityValue, 3) : '';
            const netAmountDisplay = netValue !== '' ? this.formatNumber(netValue, 2) : '';
            const grossAmountDisplay = grossValue !== '' ? this.formatNumber(grossValue, 2) : '';
            const taxRateDisplay = taxValue !== '' ? this.formatNumber(taxValue, 2) : '';

            const editButton = `
                <button type="button" class="btn btn-secondary btn-sm line-item-edit-btn" aria-label="Bearbeiten">
                    <span class="btn-icon" aria-hidden="true">✎</span>
                </button>
            `;

            return `
                <tr
                    data-line-item-id="${this.escapeHtml(item.id ?? '')}"
                    data-line-order="${this.escapeHtml(position)}"
                    data-quantity="${this.escapeHtml(quantityValue)}"
                    data-article-id="${safeArticleId}"
                    data-article-number="${safeArticleNumber}"
                    data-article-name="${safeArticleName}"
                    data-article-currency="${safeCurrencyValue}"
                    data-article-net="${safeNetValue}"
                    data-article-gross="${safeGrossValue}"
                    data-article-tax="${safeTaxValue}"
                    data-article-valid-from="${safeValidFrom}"
                    data-article-valid-until="${safeValidUntil}"
                    data-article-label="${safeArticleLabel}"
                >
                    <td>${checkbox}</td>
                    <td>${editButton}</td>
                    <td>${this.escapeHtml(position)}</td>
                    <td class="line-item-name-cell">${this.escapeHtml(articleNameValue || '')}</td>
                    <td>${articleCell}</td>
                    <td>${this.escapeHtml(quantityDisplay)}</td>
                    <td class="line-item-net-cell">${this.escapeHtml(netAmountDisplay)}</td>
                    <td class="line-item-gross-cell">${this.escapeHtml(grossAmountDisplay)}</td>
                    <td class="line-item-tax-cell">${this.escapeHtml(taxRateDisplay)}</td>
                    <td>${this.escapeHtml(createdDate)}</td>
                    <td>${this.escapeHtml(createdTime)}</td>
                </tr>
            `;
        }).join('');

        const table = document.createElement('table');
        table.className = 'line-items-table';
        table.innerHTML = `
            <thead>
                <tr>
                    <th><input type="checkbox" class="line-items-select-all"></th>
                    <th>Aktion</th>
                    <th>Pos.</th>
                    <th>Bezeichnung</th>
                    <th>Artikel</th>
                    <th>Menge</th>
                    <th>Netto</th>
                    <th>Brutto</th>
                    <th>Steuer(%)</th>
                    <th>Erst.Datum</th>
                    <th>Uhrzeit</th>
                </tr>
            </thead>
            <tbody>
                ${tableRows}
            </tbody>
        `;
        container.appendChild(table);
        if (window.lexBridge?.initializeLineItemPersistenceState) {
            window.lexBridge.initializeLineItemPersistenceState(table);
        }

        const updateButtonState = () => {
            const anyChecked = container.querySelector('.line-item-select-checkbox:checked');
            sendBtn.disabled = !anyChecked;
        };

        updateButtonState();

        this.lineItemsChangeHandler = (event) => {
            const target = event.target;
            if (!target) {
                return;
            }

            if (target.classList.contains('line-items-select-all')) {
                const checkboxes = container.querySelectorAll('.line-item-select-checkbox');
                checkboxes.forEach(cb => {
                    cb.checked = target.checked;
                });
                updateButtonState();
                return;
            }

            if (target.classList.contains('line-item-select-checkbox')) {
                updateButtonState();
                return;
            }

            if (target.classList.contains('article-search-combobox')) {
                this.syncArticleSelection(target);
            }
        };

        container.addEventListener('change', this.lineItemsChangeHandler);

        this.lineItemsClickHandler = (event) => {
            const target = event.target instanceof Element ? event.target.closest('.line-item-edit-btn') : null;
            if (!target) {
                return;
            }

            event.preventDefault();

            const row = target.closest('tr[data-line-item-id]');
            if (row && this.editorDialog) {
                this.editorDialog.open(row);
            }
        };

        container.addEventListener('click', this.lineItemsClickHandler);
    }

    escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    formatNumber(value, fractionDigits) {
        const number = Number(value);
        if (Number.isNaN(number)) {
            return '';
        }

        const digits = typeof fractionDigits === 'number' ? fractionDigits : 2;
        return number.toLocaleString('de-DE', {
            minimumFractionDigits: digits,
            maximumFractionDigits: digits
        });
    }

    syncArticleSelection(input) {
        if (!input) {
            return;
        }

        const wrapper = input.closest('.article-selector');
        if (!wrapper) {
            return;
        }

        const hiddenField = wrapper.querySelector('.article-id-field');
        if (!hiddenField) {
            return;
        }

        hiddenField.value = '';

        const listId = input.getAttribute('list');
        const datalist = listId ? document.getElementById(listId) : null;
        if (!datalist) {
            return;
        }

        const value = input.value.trim();
        if (!value) {
            return;
        }

        const options = datalist.options || datalist.children;
        for (let index = 0; index < options.length; index += 1) {
            const option = options[index];
            if (option.value !== value) {
                continue;
            }

            const articleId = option.dataset.articleId || option.getAttribute('data-article-id');
            if (articleId) {
                hiddenField.value = articleId;
            }
            break;
        }
    }

    ensureCustomerSelection(form) {
        if (!form) {
            return;
        }

        const customerInput = form.querySelector('.customer-search-combobox');
        if (!customerInput) {
            return;
        }

        const event = new Event('change');
        customerInput.dispatchEvent(event);
    }

    splitDateTime(value) {
        if (!value) {
            return { date: '', time: '' };
        }

        const parsedDate = new Date(value);
        if (!Number.isNaN(parsedDate.getTime())) {
            return {
                date: parsedDate.toLocaleDateString('de-DE'),
                time: parsedDate.toLocaleTimeString('de-DE', {
                    hour: '2-digit',
                    minute: '2-digit'
                })
            };
        }

        const [datePartRaw = '', timePartRaw = ''] = value.split(/[T ]/);
        const date = this.formatIsoDate(datePartRaw);
        const time = this.formatTimeString(timePartRaw);

        return { date, time };
    }

    formatIsoDate(value) {
        if (!value) {
            return '';
        }

        const parts = value.split('-');
        if (parts.length !== 3) {
            return value;
        }

        const [year, month, dayWithRest] = parts;
        const day = dayWithRest?.substring(0, 2) || dayWithRest;

        if (!year || !month || !day) {
            return value;
        }

        return `${day}.${month}.${year}`;
    }

    formatTimeString(value) {
        if (!value) {
            return '';
        }

        const [timePart] = value.split(/[Z+-]/);
        const normalized = timePart?.trim() || '';
        if (!normalized) {
            return '';
        }

        const [hour = '', minute = ''] = normalized.split(':');
        if (!hour) {
            return normalized.substring(0, 5);
        }

        return `${hour.padStart(2, '0')}:${minute.padStart(2, '0')}`;
    }
}


window.LineItemsPage = LineItemsPage;

// Utility to show/hide both form and sendBtn together
window.showLineItemsFilter = function(show) {
    const container = document.getElementById('line-items-filter-container');
    if (container) {
        container.style.display = show ? '' : 'none';
    }
};


// Show/hide the filter container when switching tabs
function handleTabSwitch(tabId) {
    if (tabId === 'line-items') {
        showLineItemsFilter(true);
    } else {
        showLineItemsFilter(false);
    }
}

// Example: listen for tab switch events (customize as needed)
document.addEventListener('tabchange', function(e) {
    if (e.detail && e.detail.tabId) {
        handleTabSwitch(e.detail.tabId);
    }
});
