<div class="container">
    <h3>HTTP POST Request - Invoice Data (OOP)</h3>
    
    <?php if ($hasError): ?>
        <div class="error">
            <strong>cURL Error:</strong>
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php else: ?>
        <div class="info-section">
            <strong>HTTP Status Code:</strong>
            <?= $statusCode ?>
            <span class="status-badge <?= $isSuccess ? 'status-success' : 'status-error' ?>">
                <?= $isSuccess ? 'SUCCESS' : 'FAILED' ?>
            </span>
        </div>
        
        <div class="info-section">
            <strong>Request Data:</strong>
            <pre><?= $requestData ?></pre>
        </div>
        
        <div class="info-section">
            <strong>Response:</strong>
            <pre><?= $responseBody ?></pre>
        </div>
    <?php endif; ?>
</div>
