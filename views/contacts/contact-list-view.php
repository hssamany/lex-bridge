<?php if (!empty($contactsData['contacts'])): ?>
    <script>
        // Pass contact sync data to JavaScript
        window.contactsData = <?php echo json_encode($contactsData); ?>;
    </script>
    <div class="contacts-container">
        <h2>Contacts</h2>
        
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
