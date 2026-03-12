<?php
$baseHref = lexbridge_base_path();
$basePath = $baseHref === '/' ? '/' : rtrim($baseHref, '/');
$statusTranslations = require dirname(__DIR__, 2) . '/Config/invoice-status.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?= htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo $homeView->getPageTitle(); ?></title>
    <link rel="stylesheet" href="public/css/styles.css">
    <link rel="icon" href="public/favicon.ico" type="image/x-icon">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $homeView->getPageHeading(); ?></h1>
        </div>
        
        <!-- Tab Manager will be inserted here -->
        <div id="tab-manager-container" 
            <?php echo $homeView->renderOperationStatusAttribute(); ?>
        >
        </div>
        
        <!-- Hidden tab content templates -->
        <template id="contacts-tab-content">
            <?php $homeView->renderContactsTabContent(); ?>
        </template>
                
        <template id="orders-tab-content">
            <?php $homeView->renderOrdersTabContent(); ?>
        </template>

        <template id="line-items-tab-content">
            <?php $homeView->renderLineItemsTabContent(); ?>
        </template>
        
        <template id="line-items-filter-template">
            <?php $homeView->renderLineItemsActions(); ?>
        </template>

        <template id="invoices-tab-content">
            <?php $homeView->renderInvoicesTabContent(); ?>
        </template>

        <template id="orders-filter-template">
            <?php $homeView->renderOrdersToolbar(); ?>
        </template>

        <template id="contacts-filter-template">
            <?php $homeView->renderContactsToolbar(); ?>
        </template>
        
        <template id="invoices-filter-template">
            <?php $homeView->renderInvoicesFilter(); ?>
        </template>
    </div>
    
    <!-- Toast Notification Container -->
    <div id="toast-container"></div>
    
    <!-- Toast template is now loaded by toast-notifier.js -->
    
    <!-- JavaScript Components -->
    <script>
        window.lexBridgeConfig = Object.freeze(<?= json_encode([
            'baseHref' => $baseHref,
            'basePath' => $basePath,
        ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
        window.invoiceStatusTranslations = <?= json_encode($statusTranslations, JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <!-- <script src="public/js/utils/form-interceptor.js"></script> -->
    <script src="public/js/components/toast-notifier/toast-notifier.js"></script>
    <script src="public/js/components/tab-manager/tab-manager.js"></script>
    <script src="public/js/components/paginator/paginator.js"></script>
    <script src="public/js/components/filter-form.js"></script>
    <script src="public/js/pages/contacts.js"></script>
    <script src="public/js/lex-bridge.js"></script>
    <script src="public/js/components/line-item-editor-dialog.js"></script>
    <script src="public/js/utils/ui-helpers.js"></script>
    <script src="public/js/utils/customer-search.js"></script>
    <script src="public/js/modules/customer-search-controller.js"></script>
    <script src="public/js/modules/line-item-persistence.js"></script>
    <script src="public/js/modules/article-search-controller.js"></script>
    <script src="public/js/pages/home.js"></script>
    <script src="public/js/pages/invoices.js"></script>
    <script src="public/js/pages/line-items.js"></script>
    <script src="public/js/pages/orders.js"></script>
    <script src="public/js/app.js"></script>
</body>
</html>
