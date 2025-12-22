<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice API Response</title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
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
</body>
</html>
