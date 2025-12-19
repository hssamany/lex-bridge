<!-- filepath: /C:/xampp/htdocs/TestApi/index.php -->
<?php
session_start();
$contactsData = isset($_SESSION['contactsData']) ? $_SESSION['contactsData'] : null;
unset($_SESSION['contactsData']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hello World</title>
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
        pre {
            background-color: #fff;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
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
    <h1><?php echo "Hello, World!"; ?></h1>
    <form action="post-invoices.php" method="post">
        <button type="submit" class="btn">Execute LEX Client</button>
    </form>
    <form action="get-contacts.php" method="get">
        <button type="submit" class="btn btn-secondary">Get Contacts</button>
    </form>
    
    <?php if ($contactsData): ?>
        <div class="contacts-container">
            <h2>Contacts</h2>
            <div class="status <?php echo $contactsData['isSuccess'] ? 'success' : 'error'; ?>">
                <strong>Status Code:</strong> <?php echo $contactsData['statusCode']; ?>
                <?php if ($contactsData['error']): ?>
                    <br><strong>Error:</strong> <?php echo htmlspecialchars($contactsData['error']); ?>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($contactsData['contacts'])): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Company Name</th>
                            <th>Customer Number</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contactsData['contacts'] as $contact): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($contact['id']); ?></td>
                                <td><?php echo htmlspecialchars($contact['companyName']); ?></td>
                                <td><?php echo htmlspecialchars($contact['customerNumber']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No contacts found.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</body>
</html>