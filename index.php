<?php
// Login page - User authentication entry point

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rate_limit.php';
require_once __DIR__ . '/includes/validation.php';

if (isset($_SESSION['userid']) && isset($_SESSION['session_id']) && isset($_SESSION['token'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Validate CSRF token
    if (!csrf_validate_request()) {
        $error = 'Security token validation failed. Please try again.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        // Validate input
        $username_validation = Validator::validateUsername($username);
        $password_validation = Validator::validatePassword($password);
        
        if (!$username_validation['valid']) {
            $error = $username_validation['error'];
        } elseif (!$password_validation['valid']) {
            $error = $password_validation['error'];
        } else {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://localhost/api/login.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'username' => $username,
            'password' => $password
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-CSRF-Token: ' . csrf_token()
        ]);
        $verifyPeer = defined('SSL_VERIFY_PEER') ? (bool)SSL_VERIFY_PEER : true;
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyPeer);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyPeer ? 2 : 0);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                $_SESSION['userid'] = $data['user']['userid'];
                $_SESSION['username'] = $data['user']['username'];
                $_SESSION['session_id'] = $data['session_id'];
                $_SESSION['token'] = $data['token'];
                $_SESSION['user_name'] = $data['user']['name'];
                $_SESSION['role'] = $data['user']['role'] ?? 'guest';
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = $data['error'] ?? 'Login failed';
            }
        } elseif ($http_code === 429) {
            $error = 'Too many login attempts. Please try again later.';
        } else {
            $error = 'Login failed. Please check your credentials.';
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
    <title>Sign In - Secure Portal</title>
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
        
        .login-container {
            background: var(--surface);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            border: 1px solid var(--border);
            padding: 48px;
            width: 100%;
            max-width: 420px;
        }
        
        .logo-area {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-icon {
            font-size: 32px;
            margin-bottom: 12px;
            display: inline-block;
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
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 15px;
            color: var(--primary);
            transition: all 0.2s ease;
            background-color: #fcfcfc;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
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
            display: flex;
            align-items: center;
            gap: 8px;
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
        
        .security-notice {
            margin-top: 32px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            font-size: 13px;
            color: var(--text-light);
            text-align: center;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .lock-icon {
            color: var(--success);
            font-size: 16px;
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
        
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-area">
            <div class="logo-icon"></div>
            <h1>Secure Portal</h1>
            <p class="subtitle">Enterprise Data Management System</p>
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
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autofocus placeholder="Enter your username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>
            
            <button type="submit" name="login" class="btn">Sign In</button>
        </form>
        
        <div class="login-link">
            New user? <a href="register.php">Create an account</a>
        </div>

        <div class="security-notice">
            <div><span class="lock-icon"></span> <strong>End-to-End Encrypted</strong></div>
            <span>Protected by AES-256 Encryption & TLS 1.3</span>
        </div>
    </div>
</body>
</html>
