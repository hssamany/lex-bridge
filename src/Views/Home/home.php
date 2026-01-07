<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/lex-bridge/">
    <title><?php echo $homeView->getPageTitle(); ?></title>
    <link rel="stylesheet" href="public/css/styles.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $homeView->getPageHeading(); ?></h1>
        </div>
        
        <!-- Tab Manager will be inserted here -->
        <div 
            id="tab-manager-container" 
            <?php echo $homeView->renderOperationStatusAttribute(); ?>
        >
        </div>
        
        <!-- Hidden tab content templates -->
        <template id="contacts-tab-content">
            <?php $homeView->renderContactsTabContent(); ?>
        </template>
        
        <template id="invoices-tab-content">
            <?php $homeView->renderInvoicesTabContent(); ?>
        </template>
        <template id="line-items-tab-content">
            <?php $homeView->renderLineItemsTabContent(); ?>
        </template>
        <template id="line-items-filter-template">
            <?php $homeView->renderLineItemsFilter(); ?>
        </template>
    </div>
    
    <!-- Toast Notification Container -->
    <div id="toast-container"></div>
    
    <!-- Toast template is now loaded by toast-notifier.js -->
    
    <!-- JavaScript Components -->
    <script src="public/js/components/toast-notifier/toast-notifier.js"></script>
    <script src="public/js/components/tab-manager/tab-manager.js"></script>
    <script src="public/js/lex-bridge.js"></script>
    <script src="public/js/pages/home.js"></script>
    <script src="public/js/pages/invoices.js"></script>
    <script src="public/js/pages/line-items.js"></script>
    <script src="public/js/app.js"></script>
</body>
</html>
