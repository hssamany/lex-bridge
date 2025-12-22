<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEX Bridge - Contacts</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 15px 32px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            margin: 10px 0;
            cursor: pointer;
            border: none;
            border-radius: 4px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-secondary {
            background-color: #008CBA;
        }
        .btn-secondary:hover {
            background-color: #007399;
        }
        .alert {
            margin-bottom: 15px;
            padding: 12px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #e8f5e9;
            color: #388e3c;
            border: 1px solid #c8e6c9;
        }
        .alert-danger {
            background-color: #ffebee;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
        }
        .contacts-container {
            margin-top: 20px;
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .status {
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 4px;
        }
        .status.success {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        .status.error {
            background-color: #ffebee;
            color: #d32f2f;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background-color: #fff;
        }
        table th {
            background-color: #4CAF50;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
        }
        table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        table tr:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <h1>LEX Bridge - Contact Management</h1>
    
    <?php if ($status === 'success'): ?>
        <div class="alert alert-success">✓ Contacts loaded successfully!</div>
    <?php elseif ($status === 'error'): ?>
        <div class="alert alert-danger">✗ <?= htmlspecialchars($_SESSION['error'] ?? 'An error occurred') ?></div>
    <?php endif; ?>
    
    <form action="?action=get-contacts" method="get" style="display: inline;">
        <input type="hidden" name="action" value="get-contacts">
        <input type="hidden" name="page" value="0">
        <button type="submit" class="btn btn-secondary">Refresh Contacts</button>
    </form>
    
    <form action="post-invoices.php" method="post" style="display: inline;">
        <button type="submit" class="btn">Post Invoices</button>
    </form>
    
    <?php include __DIR__ . '/contacts/contact-list-view.php'; ?>
</body>
</html>
