<?php
declare(strict_types=1);
// Registration page - New user account creation

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/api_client.php';

if (isset($_SESSION['userid']) && isset($_SESSION['session_id']) && isset($_SESSION['token'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Validate CSRF token
    if (!csrf_validate_request()) {
        $error = 'Security token validation failed. Please try again.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $name = $_POST['name'] ?? '';

        // Validate input
        $username_validation = Validator::validateUsername($username);
        $password_validation = Validator::validatePassword($password);
        $name_validation = Validator::validateName($name);

        if (!$username_validation['valid']) {
            $error = $username_validation['error'];
        } elseif (!$password_validation['valid']) {
            $error = $password_validation['error'];
        } elseif (!$name_validation['valid']) {
            $error = $name_validation['error'];
        } else {
            $result = api_request('POST', 'register.php', [
                'username' => $username,
                'password' => $password,
                'name' => $name,
            ]);
            $http_code = $result['status'];
            $data = $result['data'];

            if ($http_code === 201) {
                if ($data && isset($data['success']) && $data['success']) {
                    $success = 'Account created successfully. You can now sign in.';
                } else {
                    $error = $data['error'] ?? 'Registration failed';
                }
            } else {
                $error = $data['error'] ?? 'Registration failed. Please try again.';
            }
        }
    }
}
csrf_init(); // Ensure CSRF token is initialized for form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | CipherDesk</title>
    <style>
        :root {
            --primary: #0f172a;
            --primary-hover: #1e293b;
            --accent: #3b82f6;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #334155;
            --text-light: #64748b;
            --border: #e2e8f0;
            --error: #ef4444;
            --success: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .register-container {
            background: var(--surface);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border);
            padding: 40px;
            width: 100%;
            max-width: 480px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        h1 {
            color: var(--primary);
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .subtitle {
            color: var(--text-light);
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary);
            font-size: 14px;
            font-weight: 600;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            color: var(--primary);
            transition: all 0.2s ease;
            background-color: #fcfcfc;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            background-color: #fff;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 10px;
        }
        
        .btn:hover {
            background-color: var(--primary-hover);
        }
        
        .error {
            background: #fef2f2;
            color: var(--error);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            border: 1px solid #fee2e2;
            word-break: break-word;
        }
        
        .success {
            background: #ecfdf5;
            color: var(--success);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            border: 1px solid #d1fae5;
        }
        
        .login-link {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
            color: var(--text-light);
        }
        
        .login-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        
        .role-description {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 6px;
            display: block;
        }
    </style>
    <link rel="stylesheet" href="assets/auth.css">
</head>
<body>
    <div class="register-container">
        <div class="header">
            <div class="brand-mark" aria-hidden="true">CD</div>
            <p class="eyebrow">Standard access</p>
            <h1>Create your account</h1>
            <p class="subtitle">New accounts receive the standard user role.</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error">Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">Success: <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" placeholder="John Doe">
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" placeholder="jdoe">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>
            
            <button type="submit" name="register" class="btn">Create Account</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="index.php">Sign in</a>
        </div>
    </div>
</body>
</html>
