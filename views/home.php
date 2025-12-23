<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEX Bridge</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <h1>LEX Bridge - Management System</h1>
    
    <?php if ($status === 'success'): ?>
        <div class="alert alert-success">✓ Operation completed successfully!</div>
    <?php elseif ($status === 'error'): ?>
        <div class="alert alert-danger">✗ <?= htmlspecialchars($_SESSION['error'] ?? 'An error occurred') ?></div>
    <?php endif; ?>
    
    <!-- Tab Navigation -->
    <div class="tabs">
        <button class="tab" data-tab="contacts">Contacts</button>
        <button class="tab" data-tab="invoices">Invoices</button>
    </div>
    
    <!-- Contacts Tab -->
    <div id="contacts" class="tab-content">
        <form action="?action=get-contacts" method="get" style="display: inline;">
            <input type="hidden" name="action" value="get-contacts">
            <input type="hidden" name="page" value="0">
            <button type="submit" class="btn btn-secondary">Synchronize Contacts</button>
        </form>
        
        <?php include __DIR__ . '/contacts/contact-list-view.php'; ?>
    </div>
    
    <!-- Invoices Tab -->
    <div id="invoices" class="tab-content">
        <form action="post-invoices.php" method="post" style="display: inline;">
            <button type="submit" class="btn">Post Invoices</button>
        </form>
        
        <div class="contacts-container">
            <h2>Invoices</h2>
            <p>Invoice management coming soon...</p>
        </div>
    </div>
    
    <!-- JavaScript Components -->
    <script src="js/components/tab-manager.js"></script>
    <script src="js/lex-bridge.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
