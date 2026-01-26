<div class="line-items-container">
    <div class="line-items-list" data-line-items='{"lineItems":[]}' data-line-items-loaded="false">
        <div class="line-items-toolbar">
            <div class="line-items-toolbar-left">
                <button
                    type="button"
                    id="send-invoice-btn"
                    class="btn btn-primary line-items-toolbar-btn"
                    disabled
                >
                    Erstellen <span class="btn-icon" aria-hidden="true">➤</span>
                </button>
            </div>
            <div class="line-items-toolbar-right">
                <button
                    type="button"
                    id="sync-articles-btn"
                    class="btn btn-secondary line-items-toolbar-btn"
                    title="Artikel aus Lexware synchronisieren"
                >
                    <span class="btn-icon" aria-hidden="true">↻</span> Artikel synchr
                </button>
            </div>
        </div>

        <div class="line-items-table-wrapper">
            <table class="line-items-table">
                <thead>
                    <tr>
                        <th>
                            <input type="checkbox" class="line-items-select-all" aria-label="Alle Positionen auswählen">
                        </th>
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
                <tbody data-role="line-items-tbody"></tbody>
            </table>
            <p class="line-items-empty" data-role="line-items-empty" hidden>Keine Positionen gefunden.</p>
        </div>
    </div>
</div>

<template id="line-item-row-template">
    <tr
        data-line-item-id=""
        data-line-order=""
        data-quantity=""
        data-article-id=""
        data-article-number=""
        data-article-name=""
        data-article-currency=""
        data-article-net=""
        data-article-gross=""
        data-article-tax=""
        data-article-valid-from=""
        data-article-valid-until=""
        data-article-label=""
    >
        <td>
            <input type="checkbox" class="line-item-select-checkbox" data-line-item-id="">
        </td>
        <td>
            <button type="button" class="btn btn-secondary btn-sm line-item-edit-btn" aria-label="Bearbeiten">
                <span class="btn-icon" aria-hidden="true">✎</span>
            </button>
        </td>
        <td data-field="position"></td>
        <td class="line-item-name-cell" data-field="article-name"></td>
        <td>
            <div class="article-selector">
                <input
                    type="text"
                    class="article-search-combobox"
                    list=""
                    placeholder="Artikel wählen"
                    autocomplete="off"
                    data-role="article-input"
                >
                <input type="hidden" class="article-id-field" value="">
                <input type="hidden" class="article-number-field" value="">
                <input type="hidden" class="article-name-field" value="">
                <input type="hidden" class="article-net-field" value="">
                <input type="hidden" class="article-gross-field" value="">
                <input type="hidden" class="article-tax-field" value="">
                <input type="hidden" class="article-currency-field" value="">
                <input type="hidden" class="article-valid-from-field" value="">
                <input type="hidden" class="article-valid-until-field" value="">
                <input type="hidden" class="article-label-field" value="">
                <datalist data-role="article-datalist">
                    <option value="">Artikel wählen</option>
                </datalist>
            </div>
        </td>
        <td data-field="quantity"></td>
        <td class="line-item-net-cell" data-field="net"></td>
        <td class="line-item-gross-cell" data-field="gross"></td>
        <td class="line-item-tax-cell" data-field="tax"></td>
        <td data-field="created-date"></td>
        <td data-field="created-time"></td>
    </tr>
</template>
