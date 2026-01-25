<?php if (!empty($contactsData['contacts'])): ?>
    <div 
        class="contacts-container" 
        data-contacts='<?php echo htmlspecialchars(json_encode($contactsData), ENT_QUOTES, 'UTF-8'); ?>'
    >
        <table class="contact-table">
            <thead>
                <tr>
                    <th>Kunden Name</th>
                    <th>Kunden Nr.</th>
                    <th>Lex Kunden Nr.</th>
                    <th>Artikel</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contactsData['contacts'] as $contact): ?>
                    <tr>
                        <td><?= htmlspecialchars($contact['companyName'] ?? '') ?></td>
                        <td><?= htmlspecialchars($contact['customerNumber'] ?? '') ?></td>
                        <td><?= htmlspecialchars($contact['lexCustomerNumber'] ?? '') ?></td>
                        <td><?= htmlspecialchars($contact['articleLabel'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><strong>Total:</strong> <?= count($contactsData['contacts']) ?> contacts</p>
    </div>

<?php else: ?>
    <div class="contacts-container">
        <table class="contact-table">
            <thead>
                <tr>
                    <th>Kunden Name</th>
                    <th>Kunden Nr.</th>
                    <th>Lex Kunden Nr.</th>
                    <th>Artikel</th>
                </tr>
            </thead>
            <tbody>
                <!-- Empty - will be populated via AJAX -->
            </tbody>
        </table>
        <p><strong>Total:</strong> 0 contacts</p>
    </div>

<?php endif; ?>
