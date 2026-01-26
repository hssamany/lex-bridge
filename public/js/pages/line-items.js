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
            fetch(LexBridge.resolveApiUrl(`customers/search?q=${encodeURIComponent(query)}`))
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

        const baseUrl = LexBridge.resolveApiUrl('articles/search');
        const url = normalizedQuery
            ? `${baseUrl}?q=${encodeURIComponent(normalizedQuery)}`
            : baseUrl;

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

        fetch(LexBridge.resolveApiUrl('line-items/update'), {
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

    if (!window.lexBridge.writeRowArticleState) {
        window.lexBridge.writeRowArticleState = writeRowArticleState;
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
                const response = await fetch(LexBridge.resolveApiUrl('invoices'), {
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

                const response = await fetch(LexBridge.resolveApiUrl('articles/sync'), {
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
        this.listContainer = null;
        this.tableElement = null;
        this.tbodyElement = null;
        this.emptyStateElement = null;
        this.sendInvoiceButton = null;
        this.syncArticlesButton = null;
        this.rowTemplate = document.getElementById('line-item-row-template');
        this.listHandlersAttached = false;
        this.boundListChangeHandler = this.handleListChange.bind(this);
        this.boundListClickHandler = this.handleListClick.bind(this);

        if (!LineItemsPage.handlerSetup) {
            this.setupFilterDelegation();
            LineItemsPage.handlerSetup = true;
        }

        this.setupFilterFormDirect();
        this.cacheDomReferences();
        this.setupToolbarHandlers();
        this.setupListDelegation();
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

    cacheDomReferences() {
        const latestContainer = document.querySelector('.line-items-list');

        if (latestContainer && this.listContainer && this.listHandlersAttached && this.listContainer !== latestContainer) {
            this.listContainer.removeEventListener('change', this.boundListChangeHandler);
            this.listContainer.removeEventListener('click', this.boundListClickHandler);
            this.listHandlersAttached = false;
        }

        this.listContainer = latestContainer || null;

        if (!this.listContainer) {
            this.tableElement = null;
            this.tbodyElement = null;
            this.emptyStateElement = null;
            this.sendInvoiceButton = null;
            this.syncArticlesButton = null;
            this.listHandlersAttached = false;
            return;
        }

        this.tableElement = this.listContainer.querySelector('.line-items-table') || null;
        this.tbodyElement = this.tableElement?.querySelector('[data-role="line-items-tbody"]') || null;
        this.emptyStateElement = this.listContainer.querySelector('[data-role="line-items-empty"]') || null;
        this.sendInvoiceButton = this.listContainer.querySelector('#send-invoice-btn') || null;
        this.syncArticlesButton = this.listContainer.querySelector('#sync-articles-btn') || null;

        if (!this.rowTemplate) {
            this.rowTemplate = document.getElementById('line-item-row-template');
        }
    }

    setupToolbarHandlers() {
        this.cacheDomReferences();

        if (this.sendInvoiceButton && !this.sendInvoiceButton.dataset.handlerAttached) {
            this.sendInvoiceButton.dataset.handlerAttached = 'true';
            this.sendInvoiceButton.addEventListener('click', () => {
                this.handleCreateInvoiceFromSelection();
            });
        }

        if (this.syncArticlesButton && !this.syncArticlesButton.dataset.handlerAttached) {
            this.syncArticlesButton.dataset.handlerAttached = 'true';
            this.syncArticlesButton.addEventListener('click', () => {
                this.syncArticlesFromLexware(this.syncArticlesButton);
            });
        }
    }

    setupListDelegation() {
        this.cacheDomReferences();

        if (!this.listContainer || this.listHandlersAttached) {
            return;
        }

        this.listContainer.addEventListener('change', this.boundListChangeHandler);
        this.listContainer.addEventListener('click', this.boundListClickHandler);
        this.listHandlersAttached = true;
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

            const response = await fetch(`${LexBridge.resolveApiUrl('line-items')}?${params.toString()}`);
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

    handleListChange(event) {
        const target = event.target;
        if (!target || !this.listContainer) {
            return;
        }

        if (target.classList.contains('line-items-select-all')) {
            const selectAllChecked = target.checked;
            const checkboxes = this.listContainer.querySelectorAll('.line-item-select-checkbox');
            checkboxes.forEach((checkbox) => {
                checkbox.checked = selectAllChecked;
            });
        } else if (target.classList.contains('line-item-select-checkbox')) {
            if (!target.checked) {
                const selectAll = this.listContainer.querySelector('.line-items-select-all');
                if (selectAll) {
                    selectAll.checked = false;
                }
            } else {
                const checkboxes = Array.from(this.listContainer.querySelectorAll('.line-item-select-checkbox'));
                const allChecked = checkboxes.length > 0 && checkboxes.every((checkbox) => checkbox.checked);
                const selectAll = this.listContainer.querySelector('.line-items-select-all');
                if (selectAll) {
                    selectAll.checked = allChecked;
                }
            }
        }

        this.updateSendButtonState();
    }

    handleListClick(event) {
        const target = event.target instanceof Element ? event.target.closest('.line-item-edit-btn') : null;
        if (!target) {
            return;
        }

        event.preventDefault();

        const row = target.closest('tr[data-line-item-id]');
        if (row && this.editorDialog) {
            this.editorDialog.open(row);
        }
    }

    updateSendButtonState() {
        if (!this.sendInvoiceButton || !this.listContainer) {
            return;
        }

        const anyChecked = this.listContainer.querySelector('.line-item-select-checkbox:checked');
        this.sendInvoiceButton.disabled = !anyChecked;
    }

    toggleEmptyState(shouldShow) {
        if (!this.emptyStateElement) {
            return;
        }

        if (shouldShow) {
            this.emptyStateElement.removeAttribute('hidden');
        } else {
            this.emptyStateElement.setAttribute('hidden', '');
        }
    }

    applyArticleState(row, articleData, label, options = {}) {
        const helper = window.lexBridge?.writeRowArticleState;
        if (typeof helper === 'function') {
            helper(row, articleData, label, options);
        } else {
            console.warn('writeRowArticleState helper is unavailable.');
        }
    }

    renderLineItemRow(item, index) {
        if (!this.rowTemplate || !this.rowTemplate.content) {
            return null;
        }

        const clone = this.rowTemplate.content.cloneNode(true);
        const row = clone.querySelector('tr');
        if (!row) {
            return null;
        }

        const rowId = item?.id != null ? String(item.id) : '';
        row.dataset.lineItemId = rowId;

        const positionValue = item?.line_order != null ? String(item.line_order) : '';
        row.dataset.lineOrder = positionValue;

        const quantityValue = item?.quantity != null ? String(item.quantity) : '';
        row.dataset.quantity = quantityValue;

        const netValue = item?.net_amount != null ? String(item.net_amount) : '';
        const grossValueRaw = item?.gross_amount ?? item?.line_total_gross;
        const grossValue = grossValueRaw != null ? String(grossValueRaw) : '';
        const taxValue = item?.tax_rate_percentage != null ? String(item.tax_rate_percentage) : '';
        const currencyValue = item?.currency != null ? String(item.currency) : '';
        const articleIdValue = item?.article_id != null ? String(item.article_id) : '';
        const articleNumberValue = item?.article_number != null ? String(item.article_number) : '';
        const articleNameValue = item?.name != null ? String(item.name) : '';
        const articleLabelValue = item?.article_label
            || (articleNumberValue && articleNameValue ? `${articleNumberValue} - ${articleNameValue}` : articleNameValue)
            || '';
        const validFromValue = item?.article_valid_from != null ? String(item.article_valid_from) : '';
        const validUntilValue = item?.article_valid_until != null ? String(item.article_valid_until) : '';

        row.dataset.articleId = articleIdValue;
        row.dataset.articleNumber = articleNumberValue;
        row.dataset.articleName = articleNameValue;
        row.dataset.articleCurrency = currencyValue;
        row.dataset.articleNet = netValue;
        row.dataset.articleGross = grossValue;
        row.dataset.articleTax = taxValue;
        row.dataset.articleValidFrom = validFromValue;
        row.dataset.articleValidUntil = validUntilValue;
        row.dataset.articleLabel = articleLabelValue;

        const checkbox = row.querySelector('.line-item-select-checkbox');
        if (checkbox) {
            checkbox.dataset.lineItemId = rowId;
            checkbox.checked = false;
        }

        const articleInput = row.querySelector('[data-role="article-input"]');
        const datalist = row.querySelector('[data-role="article-datalist"]');
        if (articleInput && datalist) {
            const uniqueId = `article-options-${Date.now()}-${index}-${Math.random().toString(16).slice(2)}`;
            datalist.id = uniqueId;
            articleInput.setAttribute('list', uniqueId);

            if (articleLabelValue && articleIdValue) {
                const option = document.createElement('option');
                option.value = articleLabelValue;
                option.dataset.articleId = articleIdValue;
                if (articleNumberValue) {
                    option.dataset.articleNumber = articleNumberValue;
                }
                if (articleNameValue) {
                    option.dataset.articleName = articleNameValue;
                }
                if (netValue) {
                    option.dataset.netAmount = netValue;
                }
                if (grossValue) {
                    option.dataset.grossAmount = grossValue;
                }
                if (taxValue) {
                    option.dataset.taxRatePercentage = taxValue;
                }
                if (currencyValue) {
                    option.dataset.currency = currencyValue;
                }
                if (validFromValue) {
                    option.dataset.validFrom = validFromValue;
                }
                if (validUntilValue) {
                    option.dataset.validUntil = validUntilValue;
                }
                datalist.appendChild(option);
            }
        }

        const setCellText = (selector, value) => {
            const cell = row.querySelector(selector);
            if (cell) {
                cell.textContent = value ?? '';
            }
        };

        setCellText('[data-field="position"]', positionValue);
        setCellText('.line-item-name-cell', articleNameValue);

        const quantityDisplay = quantityValue !== '' ? this.formatNumber(quantityValue, 3) : '';
        const netAmountDisplay = netValue !== '' ? this.formatNumber(netValue, 2) : '';
        const grossAmountDisplay = grossValue !== '' ? this.formatNumber(grossValue, 2) : '';
        const taxRateDisplay = taxValue !== '' ? this.formatNumber(taxValue, 2) : '';

        setCellText('[data-field="quantity"]', quantityDisplay);
        setCellText('.line-item-net-cell', netAmountDisplay);
        setCellText('.line-item-gross-cell', grossAmountDisplay);
        setCellText('.line-item-tax-cell', taxRateDisplay);

        const { date: createdDate, time: createdTime } = this.splitDateTime(item?.created_at);
        setCellText('[data-field="created-date"]', createdDate);
        setCellText('[data-field="created-time"]', createdTime);

        const articleData = {
            id: articleIdValue,
            number: articleNumberValue,
            name: articleNameValue,
            netAmount: netValue,
            grossAmount: grossValue,
            taxRate: taxValue,
            currency: currencyValue,
            validFrom: validFromValue,
            validUntil: validUntilValue
        };

        this.applyArticleState(row, articleData, articleLabelValue, {
            skipSchedule: true,
            markPersistedSignature: true
        });

        return row;
    }

    updateLineItemsList(data) {
        this.cacheDomReferences();

        this.setupToolbarHandlers();
        this.setupListDelegation();

        if (!this.listContainer || !this.tableElement || !this.tbodyElement) {
            console.warn('Line items table structure not found');
            return;
        }

        const items = Array.isArray(data?.lineItems) ? data.lineItems : [];

        try {
            this.listContainer.dataset.lineItems = JSON.stringify({ lineItems: items });
            this.listContainer.dataset.lineItemsLoaded = 'true';
        } catch (error) {
            console.warn('Could not store line items dataset:', error);
        }

        const selectAll = this.listContainer.querySelector('.line-items-select-all');
        if (selectAll) {
            selectAll.checked = false;
        }

        this.tbodyElement.innerHTML = '';

        if (items.length === 0) {
            this.toggleEmptyState(true);
            this.updateSendButtonState();
            return;
        }

        const fragment = document.createDocumentFragment();
        items.forEach((item, index) => {
            const row = this.renderLineItemRow(item, index);
            if (row) {
                fragment.appendChild(row);
            }
        });

        this.tbodyElement.appendChild(fragment);
        this.toggleEmptyState(false);

        if (window.lexBridge?.initializeLineItemPersistenceState) {
            window.lexBridge.initializeLineItemPersistenceState(this.tableElement);
        }

        this.updateSendButtonState();
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
