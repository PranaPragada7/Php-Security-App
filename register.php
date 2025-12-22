<?php
// Registration page - New user account creation

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Validate CSRF token
    if (!csrf_validate_request()) {
        $error = 'Security token validation failed. Please try again.';
    } else {
        // Check rate limit
        $rateLimiter = new RateLimiter();
        $rateLimit = $rateLimiter->checkLimit('register', 3, 600); // 3 attempts per 10 minutes
        
        if (!$rateLimit['allowed']) {
            $error = 'Too many registration attempts. Please try again later.';
        } else {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $name = $_POST['name'] ?? '';
            $role = $_POST['role'] ?? 'guest';
            
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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://localhost/api/register.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'username' => $username,
            'password' => $password,
            'name' => $name,
            'role' => $role
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Capture headers to check for 500 errors
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-CSRF-Token: ' . csrf_token()
        ]);
        $verifyPeer = defined('SSL_VERIFY_PEER') ? (bool)SSL_VERIFY_PEER : true;
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyPeer);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyPeer ? 2 : 0);
        
        $response_raw = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        // Split header and body
        $header = substr($response_raw, 0, $header_size);
        $body = substr($response_raw, $header_size);
        
        if ($http_code === 201) {
            $data = json_decode($body, true);
            if ($data && isset($data['success']) && $data['success']) {
                $success = 'Account created successfully. Redirecting...';
                header("refresh:2;url=index.php");
            } else {
                $error = $data['error'] ?? 'Registration failed';
            }
        } elseif ($http_code === 429) {
            $error = 'Too many registration attempts. Please try again later.';
        } else {
            $data = json_decode($body, true);
            // Force display the raw error if JSON decode fails
            $error = $data['error'] ?? "Server Error ($http_code): " . htmlspecialchars(substr($body, 0, 200));
        }
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
    <title>Create Account - Secure Portal</title>
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
</head>
<body>
    <div class="register-container">
        <div class="header">
            <h1>Create Account</h1>
            <p class="subtitle">Join the secure enterprise platform</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error">Error: <?php echo $error; ?></div>
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
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    <option value="guest">Guest (Limited View)</option>
                    <option value="user">User (Standard Access)</option>
                    <option value="admin">Admin (Full Access)</option>
                </select>
                <span class="role-description">Select your permission level for the demo.</span>
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
