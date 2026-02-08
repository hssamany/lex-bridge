<div class="orders-container">
    <div class="orders-list" aria-live="polite" aria-busy="false">
        <div class="orders-toolbar-container">
            <!-- Toolbar will be rendered here by JavaScript -->
            <div class="orders-toolbar line-items-toolbar">
                <div class="line-items-toolbar-left">
                    <button type="button" class="btn btn-primary line-items-toolbar-btn orders-generate-button" disabled>
                        Positionen Erstellen <span class="btn-icon" style="font-size:1.1em;">➤</span>
                    </button>
                    <button type="button" class="btn btn-primary line-items-toolbar-btn orders-generate-invoices-button" disabled title="Rechnung direkt aus Bestellungen erstellen">
                        Rechnung Erstellen <span class="btn-icon" style="font-size:1.1em;">📄</span>
                    </button>
                    <label style="display: inline-flex; align-items: center; gap: 8px; margin-left: 20px; font-size: 0.95rem; color: #333;">
                        <input type="checkbox" class="orders-filter-processed" style="width: 16px; height: 16px; cursor: pointer;">
                        <span>Verarbeitete anzeigen</span>
                    </label>
                </div>
            </div>
        </div>
        <table class="orders-table">
            <thead>
                <tr>
                    <th><input type="checkbox" class="orders-select-all"></th>
                    <th>Kunde</th>
                    <th title="Lexware Kunden-Nr.">Lex-Nr.</th>
                    <th title="Kalenderwoche">KW</th>
                    <th title="Montag">Mo.</th>
                    <th title="Dienstag">Di.</th>
                    <th title="Mittwoch">Mi.</th>
                    <th title="Donnerstag">Do.</th>
                    <th title="Freitag">Fr.</th>
                    <th title="Bestelldatum">Bestel.dat</th>
                    <th title="Lexware Artikelnummer">Artikel-Nr.</th>
                    <th title="Verarbeitet">Verarbt.</th>
                </tr>
            </thead>
            <tbody class="orders-table-body">
                <tr class="orders-empty-row">
                    <td colspan="12" style="text-align: center;">Keine Bestellungen vorhanden.</td>
                </tr>
            </tbody>
        </table>
        <div class="table-paginator orders-paginator" data-paginator="orders"></div>
        <div class="orders-total" aria-live="polite">Gesammt: 0</div>
    </div>
</div>
