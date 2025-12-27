<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEX Bridge</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LEX Bridge - Management System</h1>
        </div>
        
        <!-- Tab Manager will be inserted here -->
        <div 
            id="tab-manager-container" 
            <?php if ($status === 'success' || $status === 'error'): ?>
            data-operation-status='<?php echo htmlspecialchars(json_encode([
                'status' => $status,
                'message' => $status === 'error' ? ($_SESSION['error'] ?? 'An error occurred') : 'Operation completed successfully'
            ]), ENT_QUOTES, 'UTF-8'); ?>'
            <?php endif; ?>
        ></div>
        
        <!-- Hidden tab content templates -->
        <template id="contacts-tab-content">
            <?php include __DIR__ . '/contacts/contact-list-view.php'; ?>
        </template>
        
        <template id="invoices-tab-content">
            <div class="contacts-container">
                <h2>Invoices</h2>
                <p>Invoice management coming soon...</p>
            </div>
        </template>
    </div>
    
    <!-- Toast Notification Container -->
    <div id="toast-container"></div>
    
    <!-- Toast template is now loaded by toast-notifier.js -->
    
    <!-- JavaScript Components -->
    <script src="js/components/toast-notifier/toast-notifier.js"></script>
    <script src="js/components/tab-manager/tab-manager.js"></script>
    <script src="js/lex-bridge.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
