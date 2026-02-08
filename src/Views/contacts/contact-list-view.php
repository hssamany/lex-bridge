<?php $contacts = $contactsData['contacts'] ?? []; ?>

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
            <?php foreach ($contacts as $index => $contact): ?>
                <?php
                    $customerId = isset($contact['customerId']) && $contact['customerId'] !== null
                        ? (int) $contact['customerId']
                        : null;
                    $articleId = isset($contact['articleId']) && $contact['articleId'] !== null
                        ? (int) $contact['articleId']
                        : null;
                    $articleLabel = $contact['articleLabel'] ?? '';
                    $rowKey = $customerId !== null && $customerId > 0 ? 'c' . $customerId : 'idx' . $index;
                    $datalistId = 'contact-article-options-' . $rowKey;
                ?>
                <tr
                    data-customer-id="<?= $customerId !== null ? $customerId : '' ?>"
                    data-current-article-id="<?= $articleId !== null ? $articleId : '' ?>"
                    data-current-article-label="<?= htmlspecialchars($articleLabel, ENT_QUOTES, 'UTF-8'); ?>"
                >
                    <td><?= htmlspecialchars($contact['companyName'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($contact['customerNumber'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($contact['lexCustomerNumber'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <div
                            class="contact-article-editor"
                            data-customer-id="<?= $customerId !== null ? $customerId : '' ?>"
                            data-current-article-id="<?= $articleId !== null ? $articleId : '' ?>"
                            data-current-article-label="<?= htmlspecialchars($articleLabel, ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <input
                                type="text"
                                class="contact-article-input"
                                list="<?= $datalistId; ?>"
                                value="<?= htmlspecialchars($articleLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                placeholder="Artikel wählen..."
                                autocomplete="off"
                            >
                            <input type="hidden" class="contact-article-id-field" value="<?= $articleId !== null ? $articleId : ''; ?>">
                            <datalist id="<?= $datalistId; ?>">
                                <?php if ($articleLabel !== '' && $articleId !== null): ?>
                                    <option value="<?= htmlspecialchars($articleLabel, ENT_QUOTES, 'UTF-8'); ?>" data-article-id="<?= $articleId; ?>"></option>
                                <?php endif; ?>
                            </datalist>
                            <button type="button" class="contact-article-clear" title="Zuordnung entfernen" aria-label="Artikelzuordnung entfernen">&times;</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="table-paginator contacts-paginator" data-paginator="contacts"></div>
    <!-- Removed legacy total label; now handled by paginator -->
</div>
