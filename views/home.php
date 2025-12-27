<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEX Bridge</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <?php if ($status === 'success' || $status === 'error'): ?>
        <script>
            // Pass operation status to JavaScript for toast notification
            window.operationStatus = {
                status: '<?php echo $status; ?>',
                message: '<?php echo $status === 'error' ? addslashes($_SESSION['error'] ?? 'An error occurred') : 'Operation completed successfully'; ?>'
            };
        </script>
    <?php endif; ?>
    
    <div class="container">
        <div class="header">
            <h1>LEX Bridge - Management System</h1>
        </div>
        
        <!-- Tab Navigation with Actions -->
        <div class="tab-header">
            <div class="tab-navigation">
                <button class="tab" data-tab="contacts">Contacts</button>
                <button class="tab" data-tab="invoices">Invoices</button>
            </div>
            <div class="tab-actions">
                <!-- Action buttons shown/hidden based on active tab -->
                <form action="?action=get-contacts" method="get" class="tab-action" data-for="contacts">
                    <input type="hidden" name="action" value="get-contacts">
                    <input type="hidden" name="page" value="0">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">↻</span> Sync Contacts
                    </button>
                </form>
                
                <form action="post-invoices.php" method="post" class="tab-action" data-for="invoices" style="display: none;">
                    <button type="submit" class="btn btn-primary">
                        <span class="btn-icon">✓</span> Post Invoices
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Contacts Tab -->
        <div id="contacts" class="tab-content">
            <?php include __DIR__ . '/contacts/contact-list-view.php'; ?>
        </div>
        
        <!-- Invoices Tab -->
        <div id="invoices" class="tab-content">
            <div class="contacts-container">
                <h2>Invoices</h2>
                <p>Invoice management coming soon...</p>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification Container -->
    <div id="toast-container"></div>
    
    <!-- Toast Message Template -->
    <template id="toast-msg-template">
        <div class="toast">
            <span class="toast-icon"></span>
            <div class="toast-content">
                <div class="toast-title"></div>
                <div class="toast-message"></div>
            </div>
            <button class="toast-close" aria-label="Close">×</button>
        </div>
    </template>
    
    <!-- JavaScript Components -->
    <script src="js/components/tab-manager.js"></script>
    <script src="js/lex-bridge.js"></script>
    <script src="js/app.js"></script>
</body>
</html>
