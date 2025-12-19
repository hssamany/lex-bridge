<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice API Response</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h3 {
            color: #333;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .error {
            color: #d32f2f;
            background-color: #ffebee;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #d32f2f;
        }
        .success {
            color: #388e3c;
            background-color: #e8f5e9;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #388e3c;
        }
        .info-section {
            margin: 20px 0;
        }
        .info-section strong {
            color: #555;
            display: block;
            margin-bottom: 5px;
        }
        pre {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            overflow-x: auto;
            font-size: 14px;
            line-height: 1.5;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            margin-left: 10px;
        }
        .status-success {
            background-color: #4CAF50;
            color: white;
        }
        .status-error {
            background-color: #f44336;
            color: white;
        }
    </style>
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
