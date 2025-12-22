<?php if (!empty($contactsData['contacts'])): ?>
    <div class="contacts-container">
        <h2>Contacts</h2>
        <div class="status <?php echo $contactsData['isSuccess'] ? 'success' : 'error'; ?>">
            <strong>Status Code:</strong> <?php echo $contactsData['statusCode']; ?>
            <?php if ($contactsData['error']): ?>
                <br><strong>Error:</strong> <?php echo htmlspecialchars($contactsData['error']); ?>
            <?php endif; ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Company Name</th>
                    <th>Customer Number</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contactsData['contacts'] as $contact): ?>
                    <tr>
                        <td><?= htmlspecialchars($contact['id']) ?></td>
                        <td><?= htmlspecialchars($contact['companyName']) ?></td>
                        <td><?= htmlspecialchars($contact['customerNumber']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><strong>Total:</strong> <?= count($contactsData['contacts']) ?> contacts</p>
    </div>
<?php elseif (isset($contactsData)): ?>
    <div class="contacts-container">
        <p>No contacts found. Click "Refresh Contacts" to load data.</p>
    </div>
<?php endif; ?>
