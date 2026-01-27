// public/js/modules/article-search-controller.js
// Manages article combobox search behaviour and delegates persistence updates

(function () {
'use strict';

    class ArticleSearchController {

        constructor(lineItemPersistence) 
        {
            this.lineItemPersistence = lineItemPersistence || null;
            this.articleDebounceTimers = new WeakMap();
            this.articleCache = new Map();
            this.cacheTtl = 5 * 60 * 1000;
            this.debounceDelay = 300;
            this.listenersAttached = false;

            this.onFocusCapture = this.onFocusCapture.bind(this);
            this.onInputCapture = this.onInputCapture.bind(this);
            this.onChangeCapture = this.onChangeCapture.bind(this);
        }

        setLineItemPersistence(persistence) {
            this.lineItemPersistence = persistence;
        }

        attachListeners() {
            if (this.listenersAttached) {
                return;
            }

            document.addEventListener('focus', this.onFocusCapture, true);
            document.addEventListener('input', this.onInputCapture, true);
            document.addEventListener('change', this.onChangeCapture, true);
            this.listenersAttached = true;
        }

        onFocusCapture(event) {
            const target = event.target;
            if (!target || !target.classList.contains('article-search-combobox')) {
                return;
            }

            this.ensureArticleOptions(target);
        }

        onInputCapture(event) {
            const target = event.target;
            if (!target || !target.classList.contains('article-search-combobox')) {
                return;
            }

            this.ensureArticleOptions(target);
            this.handleArticleSearch(target);
            this.syncArticleSelection(target);
        }

        onChangeCapture(event) {
            const target = event.target;
            if (!target || !target.classList.contains('article-search-combobox')) {
                return;
            }

            this.syncArticleSelection(target);
        }

        getArticleCacheKey(query) {
            const normalized = (query || '').trim().toLowerCase();
            return normalized === '' ? '__all__' : normalized;
        }

        getCachedArticles(cacheKey) {
            const entry = this.articleCache.get(cacheKey);
            if (!entry) {
                return null;
            }

            if (entry.expiresAt > Date.now()) {
                return entry;
            }

            this.articleCache.delete(cacheKey);
            return null;
        }

        setCachedArticles(cacheKey, data, promise) {
            this.articleCache.set(cacheKey, {
                data,
                promise: promise ?? null,
                expiresAt: Date.now() + this.cacheTtl
            });
        }

        clearArticleCache() {
            this.articleCache.clear();
        }

        async requestArticles(query) {
            const normalized = (query || '').trim();
            const cacheKey = this.getArticleCacheKey(normalized);
            const cachedEntry = this.getCachedArticles(cacheKey);

            if (cachedEntry?.data) {
                return cachedEntry.data;
            }

            if (cachedEntry?.promise) {
                return cachedEntry.promise;
            }

            const baseUrl = LexBridge.resolveApiUrl('articles/search');
            const url = normalized ? `${baseUrl}?q=${encodeURIComponent(normalized)}` : baseUrl;

            const fetchPromise = fetch(url)
                .then(async response => {

                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error(`Article search HTTP error ${response.status}: ${errorText}`);
                    }

                    const text = await response.text();

                    try {
                        const parsed = text ? JSON.parse(text) : [];
                        return Array.isArray(parsed) ? parsed : [];
                    } catch (error) {
                        throw new Error(`Article search parse error: ${error instanceof Error ? error.message : String(error)}`);
                    }
                })
                .then(data => {
                    this.setCachedArticles(cacheKey, data, null);
                    return data;
                })
                .catch(error => {
                    console.error('Article search error:', error);
                    this.articleCache.delete(cacheKey);
                    return [];
                });

            this.setCachedArticles(cacheKey, cachedEntry?.data ?? [], fetchPromise);
            return fetchPromise;
        }

        populateArticleDatalist(datalist, articles) 
        {
            datalist.innerHTML = '<option value="">Artikel wählen</option>';

            if (!Array.isArray(articles)) {
                return;
            }

            articles.forEach(article => {
                if (!article) {
                    return;
                }

                const option = document.createElement('option');
                const number = article.article_number || article.number || '';
                const name = article.name || article.title || '';
                const labelParts = [number, name].filter(Boolean);

                option.value = labelParts.join(' - ');
                option.dataset.articleId = String(article.id ?? '');
                option.setAttribute('data-article-id', String(article.id ?? ''));
                option.dataset.articleNumber = String(number ?? '');
                option.setAttribute('data-article-number', String(number ?? ''));
                option.dataset.articleName = name || '';
                option.setAttribute('data-article-name', name || '');

                const netAmount = article.net_amount ?? article.netAmount ?? '';
                option.dataset.netAmount = netAmount !== null && netAmount !== undefined ? String(netAmount) : '';
                option.setAttribute('data-net-amount', option.dataset.netAmount);

                const grossAmount = article.gross_amount ?? article.grossAmount ?? '';
                option.dataset.grossAmount = grossAmount !== null && grossAmount !== undefined ? String(grossAmount) : '';
                option.setAttribute('data-gross-amount', option.dataset.grossAmount);

                const taxRate = article.tax_rate_percentage ?? article.taxRatePercentage ?? '';
                option.dataset.taxRatePercentage = taxRate !== null && taxRate !== undefined ? String(taxRate) : '';
                option.setAttribute('data-tax-rate-percentage', option.dataset.taxRatePercentage);

                const currency = article.currency ?? article.currency_code ?? '';
                option.dataset.currency = currency || '';
                option.setAttribute('data-currency', option.dataset.currency);

                const validFrom = article.valid_from ?? article.validFrom ?? '';
                option.dataset.validFrom = validFrom || '';
                option.setAttribute('data-valid-from', option.dataset.validFrom);

                const validUntil = article.valid_until ?? article.validUntil ?? '';
                option.dataset.validUntil = validUntil || '';
                option.setAttribute('data-valid-until', option.dataset.validUntil);

                datalist.appendChild(option);
            });
        }

        ensureArticleOptions(input, datalistOverride) {
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

            const cachedAll = this.getCachedArticles('__all__');
            if (cachedAll?.data) {
                this.populateArticleDatalist(datalist, cachedAll.data);
                this.syncArticleSelection(input, datalist);
                return;
            }

            this.populateArticleDatalist(datalist, []);
            datalist.dataset.loading = 'true';

            this.requestArticles('')
                .then(articles => {
                    if (input.value.trim() !== '') {
                        return;
                    }

                    this.populateArticleDatalist(datalist, Array.isArray(articles) ? articles : []);
                    this.syncArticleSelection(input, datalist);
                })
                .finally(() => {
                    delete datalist.dataset.loading;
                });
        }

        syncArticleSelection(input, datalistOverride) {
            const persistence = this.resolvePersistence();
            if (!persistence) {
                return;
            }

            const listId = input.getAttribute('list');
            const datalist = datalistOverride || (listId ? document.getElementById(listId) : null);
            if (!datalist) {
                persistence.applySelectionFromInput(input, null);
                return;
            }

            const value = input.value.trim();
            if (!value) {
                persistence.applySelectionFromInput(input, null);
                return;
            }

            const options = datalist.options || datalist.children;
            for (let index = 0; index < options.length; index += 1) {
                const option = options[index];
                if (option.value !== value) {
                    continue;
                }

                persistence.applySelectionFromInput(input, option);
                return;
            }

            persistence.applySelectionFromInput(input, null);
        }

        handleArticleSearch(input) {
            const listId = input.getAttribute('list');
            const datalist = listId ? document.getElementById(listId) : null;
            if (!datalist) {
                console.warn('Article datalist not found for input', input);
                return;
            }

            const query = input.value.trim();
            if (!query) {
                this.ensureArticleOptions(input, datalist);
                return;
            }

            const existingTimer = this.articleDebounceTimers.get(input);
            if (existingTimer) {
                clearTimeout(existingTimer);
            }

            this.syncArticleSelection(input, datalist);

            const newTimer = setTimeout(() => {
                this.requestArticles(query)
                    .then(articles => {
                        if (input.value.trim() !== query) {
                            return;
                        }

                        this.populateArticleDatalist(datalist, articles);
                        this.syncArticleSelection(input, datalist);
                    });
            }, this.debounceDelay);

            this.articleDebounceTimers.set(input, newTimer);
        }

        resolvePersistence() {
            if (!this.lineItemPersistence && window.lexBridge?.lineItemPersistence) {
                this.lineItemPersistence = window.lexBridge.lineItemPersistence;
            }
            return this.lineItemPersistence;
        }
    }

    if (!window.lexBridge) {
        window.lexBridge = {};
    }

    const existingPersistence = window.lexBridge.lineItemPersistence || null;
    const controller = window.lexBridge.articleSearchController instanceof ArticleSearchController
        ? window.lexBridge.articleSearchController
        : new ArticleSearchController(existingPersistence);

    controller.setLineItemPersistence(existingPersistence);
    controller.attachListeners();

    window.lexBridge.articleSearchController = controller;

    if (!window.lexBridge.clearArticleCache) {
        window.lexBridge.clearArticleCache = controller.clearArticleCache.bind(controller);
    }
    if (!window.lexBridge.syncArticleSelection) {
        window.lexBridge.syncArticleSelection = controller.syncArticleSelection.bind(controller);
    }
})();
