(function () {
    'use strict';

    const DEFAULT_PAGE_SIZE = 10;
    const DEFAULT_PAGE_SIZES = [10, 25, 50, 100];

    class Paginator 
    {

        constructor(container, options = {}) {
            this.container = container;
            this.onChange = typeof options.onChange === 'function' ? options.onChange : null;
            
            this.pageSizeOptions = Array.isArray(options.pageSizeOptions) && options.pageSizeOptions.length
                ? options.pageSizeOptions
                : DEFAULT_PAGE_SIZES;

            this.state = {
                page: 1,
                pageSize: options.pageSize || DEFAULT_PAGE_SIZE,
                totalCount: 0,
                totalPages: 1,
            };

            if (this.container) {
                this.container.addEventListener('click', (event) => this.handleClick(event));
                this.container.addEventListener('change', (event) => this.handleChange(event));
            }
        }

        render({ page, pageSize, totalCount, filteredCount } = {}) {
            if (!this.container) {
                return;
            }

            const normalizedPageSize = this.normalizePageSize(pageSize ?? this.state.pageSize);
            const normalizedTotalCount = Number.isFinite(Number(totalCount)) ? Number(totalCount) : 0;
            const normalizedFilteredCount = Number.isFinite(Number(filteredCount)) ? Number(filteredCount) : null;
            const totalPages = Math.max(1, Math.ceil(normalizedTotalCount / normalizedPageSize));
            const normalizedPage = Math.min(Math.max(1, Number(page) || this.state.page || 1), totalPages);

            this.state = {
                page: normalizedPage,
                pageSize: normalizedPageSize,
                totalCount: normalizedTotalCount,
                totalPages,
            };

            const pageButtons = [];
            for (let i = 1; i <= totalPages; i += 1) {
                pageButtons.push(
                    `<button type="button" class="paginator-page${i === normalizedPage ? ' is-active' : ''}" data-page="${i}">${i}</button>`
                );
            }

            const pageOptions = Array.from({ length: totalPages }, (_, index) => {
                const value = index + 1;
                const selected = value === normalizedPage ? 'selected' : '';
                return `<option value="${value}" ${selected}>${value}</option>`;
            }).join('');

            const sizeOptions = this.pageSizeOptions.map((size) => {
                const selected = size === normalizedPageSize ? 'selected' : '';
                return `<option value="${size}" ${selected}>${size}</option>`;
            }).join('');

            const disableFirst = normalizedPage === 1 ? 'disabled' : '';
            const disableLast = normalizedPage === totalPages ? 'disabled' : '';

            // Format total label
            let totalLabel = `Gesamt: ${normalizedTotalCount}`;
            if (
                normalizedFilteredCount !== null &&
                normalizedFilteredCount !== normalizedTotalCount &&
                normalizedFilteredCount > 0
            ) {
                totalLabel = `Gesamt: ${normalizedFilteredCount} von ${normalizedTotalCount}`;
            }

            this.container.innerHTML = `
                <div class="table-paginator">
                    <span class="paginator-info">${totalLabel}</span>
                    <div class="paginator-controls">
                        <button type="button" class="paginator-nav" data-page="first" ${disableFirst} aria-label="Erste Seite">«</button>
                        <button type="button" class="paginator-nav" data-page="prev" ${disableFirst} aria-label="Vorherige Seite">‹</button>
                        <div class="paginator-pages">${pageButtons.join('')}</div>
                        <button type="button" class="paginator-nav" data-page="next" ${disableLast} aria-label="Nächste Seite">›</button>
                        <button type="button" class="paginator-nav" data-page="last" ${disableLast} aria-label="Letzte Seite">»</button>
                    </div>
                    <div class="paginator-selectors">
                        <label class="paginator-label">Seite
                            <select class="paginator-page-select">${pageOptions}</select>
                        </label>
                        <label class="paginator-label">Pro Seite
                            <select class="paginator-page-size">${sizeOptions}</select>
                        </label>
                    </div>
                </div>
            `;
        }

        handleClick(event) {
            const target = event.target.closest('[data-page]');
            if (!target) {
                return;
            }

            event.preventDefault();

            const action = target.getAttribute('data-page');
            let nextPage = this.state.page;

            switch (action) {
                case 'first':
                    nextPage = 1;
                    break;
                case 'prev':
                    nextPage = Math.max(1, this.state.page - 1);
                    break;
                case 'next':
                    nextPage = Math.min(this.state.totalPages, this.state.page + 1);
                    break;
                case 'last':
                    nextPage = this.state.totalPages;
                    break;
                default: {
                    const parsed = parseInt(action, 10);
                    if (!Number.isNaN(parsed)) {
                        nextPage = parsed;
                    }
                }
            }

            this.emitChange(nextPage, this.state.pageSize);
        }

        handleChange(event) {
            const target = event.target;
            if (!(target instanceof HTMLSelectElement)) {
                return;
            }

            if (target.classList.contains('paginator-page-select')) {
                const nextPage = parseInt(target.value, 10) || 1;
                this.emitChange(nextPage, this.state.pageSize);
                return;
            }

            if (target.classList.contains('paginator-page-size')) {
                const nextSize = parseInt(target.value, 10) || DEFAULT_PAGE_SIZE;
                this.emitChange(1, nextSize);
            }
        }

        emitChange(page, pageSize) {
            if (this.onChange) {
                this.onChange({ page, pageSize });
            }
        }

        normalizePageSize(value) {
            const parsed = parseInt(String(value), 10);
            if (this.pageSizeOptions.includes(parsed)) {
                return parsed;
            }

            return DEFAULT_PAGE_SIZE;
        }
    }

    if (!window.lexBridgeUtils) {
        window.lexBridgeUtils = {};
    }

    window.lexBridgeUtils.Paginator = Paginator;
})();
