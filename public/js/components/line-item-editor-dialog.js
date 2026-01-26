(function () {
    if (window.LineItemEditorDialog) {
        return;
    }

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

    window.LineItemEditorDialog = LineItemEditorDialog;
})();
