<?php if (!empty($invoicesData['invoices'])): ?>
    <div 
        class="invoices-container" 
        data-invoices='<?php echo htmlspecialchars(json_encode($invoicesData), ENT_QUOTES, 'UTF-8'); ?>'
    >
        <h2>Invoices</h2>
        
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invoicesData['invoices'] as $invoice): ?>
                    <tr>
                        <td>
                            <form method="POST" action="?action=transfer-invoice" class="inline-form" data-invoice-id="<?= htmlspecialchars($invoice['id']) ?>">
                                <input type="hidden" name="invoice_id" value="<?= htmlspecialchars($invoice['id']) ?>">
                                <button type="submit" class="btn-icon-only transfer-invoice-btn" title="Transfer to Lexware">▶</button>
                            </form>
                        </td>
                        <td><?= htmlspecialchars(mb_substr($invoice['company_name'] ?? 'N/A', 0, 20)) ?><?= mb_strlen($invoice['company_name'] ?? '') > 20 ? '...' : '' ?></td>
                        <td><?= htmlspecialchars($invoice['voucher_date'] ?? '') ?></td>
                        <td><?= htmlspecialchars($invoice['item_count'] ?? '0') ?></td>
                        <td><?= htmlspecialchars($invoice['status']) ?></td>
                        <td><?= htmlspecialchars($invoice['transmission_attempts'] ?? '0') ?></td>
                        <td><?= isset($invoice['total_gross_amount']) ? htmlspecialchars(number_format($invoice['total_gross_amount'], 2)) : '0.00' ?> €</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><strong>Total:</strong> <?= count($invoicesData['invoices']) ?> invoices</p>
    </div>

<?php else: ?>
    <div class="invoices-container">
        <h2>Invoices</h2>
        
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <!-- Empty - will be populated via AJAX -->
            </tbody>
        </table>
        <p><strong>Total:</strong> 0 invoices</p>
    </div>
    
<?php endif; ?>
