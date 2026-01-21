<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Error') ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
        }
        .error-container {
            background: white;
            padding: 3rem;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
        }
        h1 {
            color: #667eea;
            margin: 0 0 1rem 0;
            font-size: 2.5rem;
        }
        p {
            color: #666;
            margin: 1rem 0 2rem 0;
            line-height: 1.6;
        }
        a {
            display: inline-block;
            padding: 0.75rem 2rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
        }
        a:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1><?= htmlspecialchars($heading ?? 'Error') ?></h1>
        <p><?= htmlspecialchars($message ?? 'An error occurred.') ?></p>
        <a href="<?= htmlspecialchars(lexbridge_base_path(), ENT_QUOTES, 'UTF-8'); ?>">Go Home</a>
    </div>
</body>
</html>
