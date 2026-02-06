(function () {
'use strict';

    class ContactsPage {

        static handlerSetup = false; // Track if handler is already set up
        static articleHandlersSetup = false;
        
        constructor(lexBridge) {
            this.lexBridge = lexBridge;
            this.currentPage = 0;
            this.currentContacts = [];
            this.articleSearchTimers = new WeakMap();
            this.articleCache = new Map();
            this.articleCacheTtl = 5 * 60 * 1000;
            this.articleCacheCleanupTimer = null;
            this.init();
        }
        
        init() 
        {
            // Event delegation will be set up globally
            if (!ContactsPage.handlerSetup) {
                this.setupRefreshButton();
                ContactsPage.handlerSetup = true;
            }

            this.setupArticleHandlers();
            this.autoLoadIfEmpty();
        }
        
        /**
         * Auto-load contacts if the list is empty
         */
        async autoLoadIfEmpty() 
        {
            // Wait a tick for the tab to be fully rendered
            setTimeout(async () => {
                const tbody = document.querySelector('.contacts-container tbody');
                if (tbody && tbody.children.length === 0) {
                    await this.loadContacts(0);
                }
            }, 100);
        }
        
        /**
         * Setup refresh button using event delegation (only once)
         */
        setupRefreshButton() {
            // Use event delegation on document to catch form submit even if form is added later
            document.addEventListener('submit', async (e) => {
                if (e.target.matches('form[name="get-kontakte"]')) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    await this.syncAndReload(0);
                    return false;
                }
            }, true); // Use capture phase to intercept before other handlers
        }
        
        /**
         * Setup refresh button directly on the form element (called after tab is visible)
         */
        setupRefreshButtonDirect() {
            const refreshForm = document.querySelector('form[name="get-kontakte"]');
            
            if (refreshForm) {
                // Remove the action attribute to prevent navigation
                refreshForm.removeAttribute('action');
                refreshForm.setAttribute('data-original-action', '?action=get-kontakte');
                
                const button = refreshForm.querySelector('button[type="submit"]');
                
                if (button && !button.dataset.ajaxHandlerAttached) {
                    button.dataset.ajaxHandlerAttached = 'true';
                    
                    // Remove submit type to prevent form submission
                    button.type = 'button';
                    
                    button.addEventListener('click', async (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        await this.syncAndReload(0);
                    });
                }
            }
        }
        
        /**
         * Load contacts via AJAX
         */
        async loadContacts(page = 0) 
        {
            try {
                const contactUrl = LexBridge.resolveApiUrl(`contacts?page=${page}`);                
                const response = await fetch(contactUrl);
                
                // Check content type
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text.substring(0, 500));
                    throw new Error('Server returned invalid response format. Response: ' + text.substring(0, 200));
                }
                
                // Get response text first
                const text = await response.text();
                if (!text || text.trim() === '') {
                    throw new Error('Server returned empty response. Check database connection and table configuration.');
                }
                
                // Parse JSON
                let data;
                try {
                    data = JSON.parse(text);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    console.error('Response text:', text.substring(0, 500));
                    throw new Error('Invalid JSON from server: ' + text.substring(0, 200));
                }

                // Check for error in response
                if (!data.isSuccess && data.error) {
                    throw new Error(data.error);
                }

                const contacts = Array.isArray(data.contacts) ? data.contacts : [];
                this.updateContactList({ contacts });

                return contacts;

            } catch (error) {
                console.error('Error loading contacts:', error);
                this.lexBridge.toastNotifier.show(
                    'Error loading contacts: ' + error.message,
                    'error'
                );
                
                // Show empty state
                this.updateContactList({ contacts: [] });
                
                return [];
            }
        }

        async syncContacts(page = 0) {
            const response = await fetch(LexBridge.resolveApiUrl(`contacts/sync?page=${page}`), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ page })
            });

            const data = await response.json();

            const contacts = Array.isArray(data.contacts) ? data.contacts : [];
            const hasContacts = contacts.length > 0;

            if (!response.ok || (!data.isSuccess && !hasContacts)) {
                throw new Error(data.error || 'Failed to sync contacts');
            }

            return data;
        }

        async syncAndReload(page = 0) {
            const button = document.querySelector('form[name="get-kontakte"] button');
            const originalText = button ? button.innerHTML : '';

            try {
                if (button) {
                    button.disabled = true;
                    button.innerHTML = '<span class="btn-icon spinning">↻</span> Synchronizing...';
                }

                const syncData = await this.syncContacts(page);
                const contacts = Array.isArray(syncData.contacts) ? syncData.contacts : [];

                this.updateContactList({ contacts });

                if (syncData.isSuccess) {
                    this.lexBridge.toastNotifier.show(
                        `Synchronized ${contacts.length} contacts`,
                        'success'
                    );
                } else {
                    const warningMessage = syncData.error
                        ? `Synchronized ${contacts.length} contacts (with warnings: ${syncData.error})`
                        : `Synchronized ${contacts.length} contacts (with warnings)`;
                    this.lexBridge.toastNotifier.show(warningMessage, 'warning');
                }
            } catch (error) {
                console.error('Contact sync failed:', error);
                this.lexBridge.toastNotifier.show(
                    'Contact synchronization failed: ' + error.message,
                    'error'
                );
                await this.loadContacts(page);
            } finally {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = originalText;
                }
            }
        }
        
        /**
         * Update contact list in DOM
         */
        updateContactList(data) {
            const tbody = document.querySelector('.contacts-container tbody');
            if (!tbody) {
                return;
            }

            const contacts = Array.isArray(data.contacts) ? data.contacts : [];
            this.currentContacts = contacts;

            if (contacts.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center;">Keine Kontakte vorhanden</td></tr>';
                this.updateContactTotal(0);
                return;
            }

            tbody.innerHTML = contacts
                .map((contact, index) => this.createContactRow(contact, index))
                .join('');

            this.updateContactTotal(contacts.length);
        }
        
        /**
         * Create contact row HTML
         */
        createContactRow(contact, index = 0) {
            const customerId = contact.customerId != null ? Number(contact.customerId) : null;
            const articleId = contact.articleId != null ? Number(contact.articleId) : null;
            const articleLabel = contact.articleLabel || '';
            const rowKey = customerId && customerId > 0 ? `c${customerId}` : `idx${index}`;
            const datalistId = `contact-article-options-${rowKey}`;

            return `
                <tr
                    data-customer-id="${customerId ?? ''}"
                    data-current-article-id="${articleId ?? ''}"
                    data-current-article-label="${this.escapeHtml(articleLabel)}"
                >
                    <td>${this.escapeHtml(contact.companyName || '')}</td>
                    <td>${this.escapeHtml(contact.customerNumber || '')}</td>
                    <td>${this.escapeHtml(contact.lexCustomerNumber || '')}</td>
                    <td>
                        <div
                            class="contact-article-editor"
                            data-customer-id="${customerId ?? ''}"
                            data-current-article-id="${articleId ?? ''}"
                            data-current-article-label="${this.escapeHtml(articleLabel)}"
                        >
                            <input
                                type="text"
                                class="contact-article-input"
                                list="${datalistId}"
                                value="${this.escapeHtml(articleLabel)}"
                                placeholder="Artikel wählen..."
                                autocomplete="off"
                            >
                            <input type="hidden" class="contact-article-id-field" value="${articleId ?? ''}">
                            <datalist id="${datalistId}">
                                ${articleLabel && articleId ? `<option value="${this.escapeHtml(articleLabel)}" data-article-id="${articleId}"></option>` : ''}
                            </datalist>
                            <button type="button" class="contact-article-clear" title="Zuordnung entfernen" aria-label="Artikelzuordnung entfernen">&times;</button>
                        </div>
                    </td>
                </tr>
            `;
        }

        updateContactTotal(count) {
            const totalElement = document.querySelector('.contacts-container p strong');
            if (totalElement && totalElement.parentElement) {
                totalElement.parentElement.innerHTML = `<strong>Total:</strong> ${count} contacts`;
            }
        }

        setupArticleHandlers() {
            if (ContactsPage.articleHandlersSetup) {
                return;
            }

            document.addEventListener('focus', (event) => {
                const target = event.target;
                if (!this.isContactArticleInput(target)) {
                    return;
                }

                this.handleArticleFocus(target);
            }, true);

            document.addEventListener('input', (event) => {
                const target = event.target;
                if (!this.isContactArticleInput(target)) {
                    return;
                }

                this.scheduleArticleSearch(target);
            });

            document.addEventListener('change', (event) => {
                const target = event.target;
                if (!this.isContactArticleInput(target)) {
                    return;
                }

                this.handleArticleSelection(target);
            });

            document.addEventListener('blur', (event) => {
                const target = event.target;
                if (!this.isContactArticleInput(target)) {
                    return;
                }

                this.handleArticleBlur(target);
            }, true);

            document.addEventListener('click', (event) => {
                const button = event.target;
                if (!(button instanceof HTMLButtonElement) || !button.classList.contains('contact-article-clear')) {
                    return;
                }

                this.clearArticleSelection(button);
            });

            ContactsPage.articleHandlersSetup = true;
        }

        isContactArticleInput(element) {
            return element instanceof HTMLInputElement && element.classList.contains('contact-article-input');
        }

        getArticleEditor(element) {
            if (!(element instanceof HTMLElement)) {
                return null;
            }

            return element.closest('.contact-article-editor');
        }

        getArticleDatalist(input) {
            if (!(input instanceof HTMLInputElement)) {
                return null;
            }

            const listId = input.getAttribute('list');
            return listId ? document.getElementById(listId) : null;
        }

        handleArticleFocus(input) {
            const editor = this.getArticleEditor(input);
            if (!editor) {
                return;
            }

            const datalist = this.getArticleDatalist(input);
            if (!datalist) {
                return;
            }

            if (!datalist.dataset.preloaded) {
                datalist.dataset.preloaded = 'loading';
                this.requestArticles('')
                    .then((articles) => {
                        this.populateArticleDatalist(
                            datalist,
                            articles,
                            editor.dataset.currentArticleLabel || '',
                            editor.dataset.currentArticleId || ''
                        );
                        datalist.dataset.preloaded = 'true';
                    })
                    .catch((error) => {
                        console.error('Initial article preload failed:', error);
                        datalist.dataset.preloaded = '';
                    });
            }

            this.scheduleArticleSearch(input);
        }

        scheduleArticleSearch(input) {
            const editor = this.getArticleEditor(input);
            if (!editor) {
                return;
            }

            const datalist = this.getArticleDatalist(input);
            if (!datalist) {
                return;
            }

            const existingTimer = this.articleSearchTimers.get(input);
            if (existingTimer) {
                clearTimeout(existingTimer);
            }

            const timer = setTimeout(() => {
                this.requestArticles(input.value || '')
                    .then((articles) => {
                        this.populateArticleDatalist(
                            datalist,
                            articles,
                            editor.dataset.currentArticleLabel || '',
                            editor.dataset.currentArticleId || ''
                        );
                    })
                    .catch((error) => {
                        console.error('Article search failed:', error);
                    })
                    .finally(() => {
                        this.articleSearchTimers.delete(input);
                    });
            }, 250);

            this.articleSearchTimers.set(input, timer);
        }

        requestArticles(query) {
            const trimmed = (query || '').trim();
            const cacheKey = trimmed === '' ? '__all__' : trimmed.toLowerCase();
            const cached = this.articleCache.get(cacheKey);
            const now = Date.now();

            if (cached && cached.expiresAt > now && Array.isArray(cached.data)) {
                return Promise.resolve(cached.data);
            }

            const url = trimmed === ''
                ? LexBridge.resolveApiUrl('articles/search')
                : `${LexBridge.resolveApiUrl('articles/search')}?q=${encodeURIComponent(trimmed)}`;

            return fetch(url)
                .then(async (response) => {
                    const text = await response.text();
                    if (!response.ok) {
                        throw new Error(`Article search failed (${response.status}): ${text}`);
                    }

                    if (text.trim() === '') {
                        return [];
                    }

                    try {
                        const data = JSON.parse(text);
                        return Array.isArray(data) ? data : [];
                    } catch (error) {
                        throw new Error('Artikelantwort konnte nicht gelesen werden.');
                    }
                })
                .then((articles) => {
                    this.articleCache.set(cacheKey, {
                        data: articles,
                        expiresAt: now + this.articleCacheTtl,
                    });
                    return articles;
                })
                .catch((error) => {
                    console.error('Article search error:', error);
                    return [];
                });
        }

        populateArticleDatalist(datalist, articles, currentLabel, currentId) {
            if (!(datalist instanceof HTMLDataListElement)) {
                return;
            }

            const options = ['<option value=""></option>'];

            const seen = new Set();

            if (Array.isArray(articles)) {
                articles.forEach((article) => {
                    if (!article) {
                        return;
                    }

                    const id = article.id ?? article.article_id;
                    if (id == null) {
                        return;
                    }

                    const number = article.article_number || article.number || '';
                    const name = article.name || article.title || '';
                    const labelParts = [];
                    if (number) {
                        labelParts.push(String(number));
                    }
                    if (name) {
                        labelParts.push(String(name));
                    }
                    const label = labelParts.join(' - ');
                    if (label === '') {
                        return;
                    }

                    const optionLabel = this.escapeHtml(label);
                    const optionId = this.escapeHtml(String(id));
                    if (seen.has(optionId + ':' + optionLabel)) {
                        return;
                    }
                    seen.add(optionId + ':' + optionLabel);

                    const numberEscaped = this.escapeHtml(String(number || ''));
                    const nameEscaped = this.escapeHtml(String(name || ''));

                    options.push(
                        `<option value="${optionLabel}" data-article-id="${optionId}" data-article-number="${numberEscaped}" data-article-name="${nameEscaped}"></option>`
                    );
                });
            }

            const hasCurrent = currentLabel && currentId
                ? seen.has(`${this.escapeHtml(String(currentId))}:${this.escapeHtml(String(currentLabel))}`)
                : false;

            if (currentLabel && currentId && !hasCurrent) {
                const labelEscaped = this.escapeHtml(String(currentLabel));
                const idEscaped = this.escapeHtml(String(currentId));
                options.push(`<option value="${labelEscaped}" data-article-id="${idEscaped}"></option>`);
            }

            datalist.innerHTML = options.join('');
        }

        handleArticleSelection(input) {
            const editor = this.getArticleEditor(input);
            if (!editor) {
                return;
            }

            const value = (input.value || '').trim();
            const datalist = this.getArticleDatalist(input);
            const option = this.findMatchingOption(datalist, value);

            if (value === '') {
                this.persistArticleSelection(editor, null, '');
                return;
            }

            if (!option || !option.dataset.articleId) {
                if (this.lexBridge?.toastNotifier) {
                    this.lexBridge.toastNotifier.show('Bitte wählen Sie einen Artikel aus der Liste.', 'warning');
                }
                this.resetEditorValue(editor);
                return;
            }

            const articleId = parseInt(option.dataset.articleId, 10);
            if (!Number.isInteger(articleId) || articleId <= 0) {
                if (this.lexBridge?.toastNotifier) {
                    this.lexBridge.toastNotifier.show('Ungültige Artikel-ID.', 'error');
                }
                this.resetEditorValue(editor);
                return;
            }

            const label = option.value || value;
            const meta = {
                number: option.dataset.articleNumber || '',
                name: option.dataset.articleName || '',
            };

            this.persistArticleSelection(editor, articleId, label, meta);
        }

        handleArticleBlur(input) {
            const editor = this.getArticleEditor(input);
            if (!editor) {
                return;
            }

            const currentLabel = editor.dataset.currentArticleLabel || '';
            if ((input.value || '').trim() === '') {
                input.value = currentLabel;
            }
        }

        findMatchingOption(datalist, value) {
            if (!datalist) {
                return null;
            }

            const options = datalist.options?.length ? Array.from(datalist.options) : Array.from(datalist.children);

            for (const option of options) {
                if (option instanceof HTMLOptionElement && option.value === value) {
                    return option;
                }
            }

            return null;
        }

        resetEditorValue(editor) {
            const input = editor.querySelector('.contact-article-input');
            if (input instanceof HTMLInputElement) {
                input.value = editor.dataset.currentArticleLabel || '';
                input.disabled = false;
            }

            const hidden = editor.querySelector('.contact-article-id-field');
            if (hidden instanceof HTMLInputElement) {
                hidden.value = editor.dataset.currentArticleId || '';
            }

            const clearBtn = editor.querySelector('.contact-article-clear');
            if (clearBtn instanceof HTMLButtonElement) {
                clearBtn.disabled = false;
            }
        }

        persistArticleSelection(editor, articleId, label, meta = {}) {
            const input = editor.querySelector('.contact-article-input');
            const clearBtn = editor.querySelector('.contact-article-clear');
            const hidden = editor.querySelector('.contact-article-id-field');

            const customerIdRaw = editor.dataset.customerId || editor.closest('tr')?.dataset.customerId || '';
            const customerId = parseInt(customerIdRaw, 10);
            if (!Number.isInteger(customerId) || customerId <= 0) {
                if (this.lexBridge?.toastNotifier) {
                    this.lexBridge.toastNotifier.show('Kunde konnte nicht ermittelt werden.', 'error');
                }
                this.resetEditorValue(editor);
                return;
            }

            const previousId = editor.dataset.currentArticleId || '';
            const nextId = articleId != null ? String(articleId) : '';

            if (previousId === nextId) {
                if (input instanceof HTMLInputElement) {
                    input.value = label || editor.dataset.currentArticleLabel || '';
                }
                if (hidden instanceof HTMLInputElement) {
                    hidden.value = nextId;
                }
                return;
            }

            if (input instanceof HTMLInputElement) {
                input.disabled = true;
            }
            if (clearBtn instanceof HTMLButtonElement) {
                clearBtn.disabled = true;
            }

            const payload = { customer_id: customerId };
            if (articleId !== null) {
                payload.article_id = articleId;
            }

            fetch(LexBridge.resolveApiUrl('contacts/article'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            })
                .then(async (response) => {
                    const text = await response.text();
                    let data = {};
                    if (text.trim() !== '') {
                        try {
                            data = JSON.parse(text);
                        } catch (error) {
                            throw new Error('Antwort konnte nicht interpretiert werden.');
                        }
                    }

                    if (!response.ok || !data.isSuccess) {
                        const message = data.error || `Artikelzuordnung fehlgeschlagen (Status ${response.status}).`;
                        throw new Error(message);
                    }

                    const contacts = Array.isArray(data.contacts) ? data.contacts : this.currentContacts;
                    this.updateContactList({ contacts });

                    if (this.lexBridge?.toastNotifier) {
                        const successMessage = data.message || 'Artikelzuordnung aktualisiert.';
                        this.lexBridge.toastNotifier.show(successMessage, 'success');
                    }
                })
                .catch((error) => {
                    console.error('Persisting contact article failed:', error);
                    if (this.lexBridge?.toastNotifier) {
                        this.lexBridge.toastNotifier.show(error.message || 'Artikelzuordnung fehlgeschlagen.', 'error');
                    }
                    this.resetEditorValue(editor);
                })
                .finally(() => {
                    if (input instanceof HTMLInputElement && input.isConnected) {
                        input.disabled = false;
                    }
                    if (clearBtn instanceof HTMLButtonElement && clearBtn.isConnected) {
                        clearBtn.disabled = false;
                    }
                    if (hidden instanceof HTMLInputElement && hidden.isConnected) {
                        hidden.value = nextId;
                    }
                });
        }

        clearArticleSelection(button) {
            const editor = this.getArticleEditor(button);
            if (!editor) {
                return;
            }

            const currentId = editor.dataset.currentArticleId || '';
            if (currentId === '') {
                this.resetEditorValue(editor);
                if (this.lexBridge?.toastNotifier) {
                    this.lexBridge.toastNotifier.show('Keine Artikelzuordnung vorhanden.', 'info');
                }
                return;
            }

            const input = editor.querySelector('.contact-article-input');
            if (input instanceof HTMLInputElement) {
                input.value = '';
            }

            this.persistArticleSelection(editor, null, '');
        }
        
        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml(text) {
            if (window.lexBridgeUtils && typeof window.lexBridgeUtils.escapeHtml === 'function') {
                return window.lexBridgeUtils.escapeHtml(text);
            }
            const div = document.createElement('div');
            div.textContent = text == null ? '' : String(text);
            return div.innerHTML;
        }
    }

    if (!window.lexBridge) {
        window.lexBridge = {};
    }

    window.lexBridge.ContactsPage = ContactsPage;
})();
