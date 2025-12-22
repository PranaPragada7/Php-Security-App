<?php
// User dashboard - Main application interface with job submission and role-based data viewing

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['session_id']) || !isset($_SESSION['token'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/rbac.php';

$error = '';
$success = '';
$role = $_SESSION['role'] ?? 'guest';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_job'])) {
    // Validate CSRF token
    if (!csrf_validate_request()) {
        $error = 'Security token validation failed. Please try again.';
    } elseif (!RBAC::canSubmitJobs($role)) {
        $error = 'You do not have permission to submit jobs';
    } else {
        $job_name = $_POST['job_name'] ?? '';
        $opn_number = $_POST['opn_number'] ?? '';
        $clear_text_data = $_POST['clear_text_data'] ?? '';
        
        if (empty($job_name) || empty($opn_number)) {
            $error = 'Please enter both job name and OPN number';
        } else {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://localhost/api/jobs.php');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'job_name' => $job_name,
                'opn_number' => $opn_number,
                'clear_text_data' => $clear_text_data
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Session-ID: ' . $_SESSION['session_id'],
                'X-Token: ' . $_SESSION['token'],
                'X-CSRF-Token: ' . csrf_token()
            ]);
            $verifyPeer = defined('SSL_VERIFY_PEER') ? (bool)SSL_VERIFY_PEER : true;
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyPeer);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyPeer ? 2 : 0);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 201) {
                $data = json_decode($response, true);
                if ($data && isset($data['success']) && $data['success']) {
                    $success = 'Job submitted successfully! HMAC generated.';
                    $_POST['job_name'] = '';
                    $_POST['opn_number'] = '';
                    $_POST['clear_text_data'] = '';
                } else {
                    $error = $data['error'] ?? 'Failed to submit job';
                }
            } else {
                $data = json_decode($response, true);
                $error = $data['error'] ?? 'Failed to submit job. Please try again.';
            }
        }
    }
}

$jobs = [];
if (RBAC::canViewPlaintext($role)) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://localhost/api/jobs.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Session-ID: ' . $_SESSION['session_id'],
        'X-Token: ' . $_SESSION['token'],
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
            $jobs = $data['jobs'] ?? [];
        }
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$roleBadgeColors = [
    'admin' => 'bg-red-100 text-red-800',
    'user' => 'bg-green-100 text-green-800',
    'guest' => 'bg-gray-100 text-gray-800'
];
$roleBadgeClass = $roleBadgeColors[$role] ?? 'bg-gray-100 text-gray-800';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Secure Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
        .nav-link {
            transition: all 0.2s;
        }
        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="bg-slate-900 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 font-bold text-xl">
                        Secure Portal
                    </div>
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <a href="dashboard.php" class="bg-slate-800 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                            <?php if (RBAC::canAccessActivityLogs($role)): ?>
                                <a href="activity_logs.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:text-white">Activity Logs</a>
                            <?php endif; ?>
                            <?php if (RBAC::canManageUsers($role)): ?>
                                <a href="manage_users.php" class="nav-link px-3 py-2 rounded-md text-sm font-medium text-gray-300 hover:text-white">Manage Users</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-300">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['username']); ?></span>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $roleBadgeClass; ?>">
                            <?php echo strtoupper(htmlspecialchars($role)); ?>
                        </span>
                    </div>
                    <a href="?logout=1" class="text-sm text-gray-400 hover:text-white transition-colors">Sign out</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        
        <!-- HMAC Verification Summary (Always visible for admins) -->
        <?php if (RBAC::canViewEncrypted($role)): ?>
        <div id="hmac-summary-box" class="mb-6 bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r shadow-sm relative">
            <button type="button" onclick="var box=document.getElementById('hmac-summary-box');if(box){box.style.display='none';}" class="absolute top-2 right-2 text-blue-400 hover:text-blue-600 p-1 rounded" style="cursor: pointer !important; z-index: 9999 !important; pointer-events: auto !important; background: transparent; border: none;">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" style="pointer-events: none;">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
            <div class="flex items-center justify-between pr-10">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-bold text-blue-800 uppercase tracking-wide">
                            HMAC Data Integrity Check
                        </h3>
                        <div class="mt-1 text-sm text-blue-700">
                            <p id="hmac-summary-text">Verifying data integrity... (HMAC verification in progress)</p>
                        </div>
                    </div>
                </div>
                <div id="hmac-status-icon" class="ml-4" style="pointer-events: none;">
                    <svg class="h-6 w-6 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Integrity Alert Container (Shows when failures detected) -->
        <div id="integrity-alert" class="hidden mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r shadow-sm relative">
            <button type="button" onclick="document.getElementById('integrity-alert').style.display='none'; document.getElementById('integrity-alert').classList.add('hidden');" class="absolute top-2 right-2 text-red-400 hover:text-red-600 p-1 rounded" style="cursor: pointer; z-index: 10;">
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
            <div class="flex pr-8">
                <div class="flex-shrink-0">
                    <svg class="h-6 w-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="ml-3 flex-1">
                    <h3 class="text-sm font-bold text-red-800 uppercase tracking-wide">
                        SECURITY ALERT: Data Integrity Compromised
                    </h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p><span id="integrity-count" class="font-bold text-lg">0</span> job record(s) have been modified directly in the database (HMAC verification failed).</p>
                        <p class="mt-1 text-xs">The HMAC values do not match - data may have been tampered with.</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r shadow-sm">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700"><?php echo htmlspecialchars($success); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (RBAC::canSubmitJobs($role)): ?>
        <div class="bg-white overflow-hidden shadow rounded-lg mb-8 border border-gray-200">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Submit Secure Data</h3>
                <p class="mt-1 max-w-2xl text-sm text-gray-500">Data will be encrypted using AES-256 before storage.</p>
            </div>
            <div class="px-4 py-5 sm:p-6">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <label for="job_name" class="block text-sm font-medium text-gray-700">Job Name</label>
                            <div class="mt-1">
                                <input type="text" name="job_name" id="job_name" required 
                                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border" 
                                    placeholder="e.g., Q4 Financial Report">
                            </div>
                        </div>

                        <div class="sm:col-span-3">
                            <label for="clear_text_data" class="block text-sm font-medium text-gray-700">Description (Public)</label>
                            <div class="mt-1">
                                <input type="text" name="clear_text_data" id="clear_text_data" 
                                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border" 
                                    placeholder="Non-sensitive description">
                            </div>
                        </div>

                        <div class="sm:col-span-6">
                            <label for="opn_number" class="block text-sm font-medium text-gray-700 flex items-center gap-2">
                                Sensitive OPN Data 
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                    <svg class="mr-1.5 h-2 w-2 text-blue-400" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>
                                    Encrypted Field
                                </span>
                            </label>
                            <div class="mt-1">
                                <input type="text" name="opn_number" id="opn_number" required 
                                    class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md p-2 border" 
                                    placeholder="Enter sensitive data to encrypt">
                            </div>
                        </div>
                    </div>
                    <div class="mt-6">
                        <button type="submit" name="submit_job" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Encrypt & Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
            <div class="bg-white overflow-hidden shadow rounded-lg mb-8 border border-red-200">
                <div class="px-4 py-5 sm:p-6 text-center">
                    <svg class="mx-auto h-12 w-12 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Access Restricted</h3>
                    <p class="mt-1 text-sm text-gray-500">Your current role (<?php echo htmlspecialchars($role); ?>) does not have permission to submit new jobs.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="flex flex-col">
            <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                    <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg bg-white">
                        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50 flex justify-between items-center">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Secure Data Repository</h3>
                            <?php if ($role === RBAC::ROLE_GUEST): ?>
                                <span class="text-xs text-red-600 font-semibold">View Restricted</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($role === RBAC::ROLE_GUEST): ?>
                            <div class="p-8 text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.05 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                                <p class="mt-2">You do not have permission to view data records.</p>
                            </div>
                        <?php elseif (empty($jobs)): ?>
                            <div class="p-8 text-center text-gray-500">No records found.</div>
                        <?php else: ?>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Job Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">OPN Data</th>
                                        <?php if (RBAC::canViewEncrypted($role)): ?>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Encrypted Value</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HMAC Integrity</th>
                                        <?php endif; ?>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($jobs as $job): ?>
                                        <?php 
                                        $hmacStatus = $job['hmac_status'] ?? 'unknown';
                                        $hmacFailed = ($hmacStatus === 'failed' || ($job['hmac_verified'] ?? false) === false);
                                        $rowClass = $hmacFailed && RBAC::canViewEncrypted($role) ? 'hover:bg-gray-50 bg-red-50 border-l-4 border-red-500' : 'hover:bg-gray-50';
                                        ?>
                                        <tr class="<?php echo $rowClass; ?>">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                #<?php echo htmlspecialchars($job['jobid'] ?? ''); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($job['job_name'] ?? ''); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($job['clear_text_data'] ?? '-'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <?php if (RBAC::canViewPlaintext($role) && RBAC::canViewSensitiveData($role)): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        <?php echo htmlspecialchars($job['opn_number'] ?? ''); ?>
                                                    </span>
                                                <?php elseif (RBAC::canViewPlaintext($role)): ?>
                                                    <span class="text-gray-400 italic">[Hidden for User]</span>
                                                <?php else: ?>
                                                    <span class="text-red-400">[Access Denied]</span>
                                                <?php endif; ?>
                                            </td>
                                            <?php if (RBAC::canViewEncrypted($role)): ?>
                                                <td class="px-6 py-4 text-sm text-gray-500 font-mono text-xs max-w-xs truncate">
                                                    <div class="truncate w-48" title="<?php echo htmlspecialchars($job['opn_number_encrypted'] ?? ''); ?>">
                                                        <?php echo htmlspecialchars($job['opn_number_encrypted'] ?? ''); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-sm font-mono text-xs">
                                                    <?php 
                                                    $hmacStatus = $job['hmac_status'] ?? 'unknown';
                                                    $hmacVerified = $job['hmac_verified'] ?? false;
                                                    
                                                    if ($hmacStatus === 'failed' || !$hmacVerified): ?>
                                                        <span class="px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-700 flex items-center gap-1 w-fit">
                                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                                            </svg>
                                                            Failed
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700 flex items-center gap-1 w-fit">
                                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                                            </svg>
                                                            Verified
                                                        </span>
                                                    <?php endif; ?>
                                                    <div class="truncate w-24 text-gray-400 mt-1" title="<?php echo htmlspecialchars($job['data_hmac'] ?? ''); ?>">
                                                        <?php echo htmlspecialchars(substr($job['data_hmac'] ?? '', 0, 10)); ?>...
                                                    </div>
                                                </td>
                                            <?php endif; ?>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($job['created_at'] ?? ''); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script>
        // HMAC Verification Check on load (Admin Only)
        <?php if (RBAC::canViewSensitiveData($role)): ?>
        document.addEventListener('DOMContentLoaded', async function() {
            const summaryBox = document.getElementById('hmac-summary-box');
            const summaryText = document.getElementById('hmac-summary-text');
            const statusIcon = document.getElementById('hmac-status-icon');
            const alertBox = document.getElementById('integrity-alert');
            const countSpan = document.getElementById('integrity-count');
            
            try {
                // Check integrity
                const response = await fetch('https://localhost/api/integrity_check.php', {
                    headers: {
                        'X-Session-ID': '<?php echo $_SESSION['session_id']; ?>',
                        'X-Token': '<?php echo $_SESSION['token']; ?>'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    
                    // Count verified vs failed jobs from the table
                    const jobRows = document.querySelectorAll('tbody tr');
                    let verifiedCount = 0;
                    let failedCount = 0;
                    
                    jobRows.forEach(row => {
                        const hmacCell = row.querySelector('td:nth-child(6)'); // HMAC Integrity column
                        if (hmacCell) {
                            const badge = hmacCell.querySelector('span');
                            if (badge) {
                                if (badge.textContent.includes('Failed')) {
                                    failedCount++;
                                } else if (badge.textContent.includes('Verified')) {
                                    verifiedCount++;
                                }
                            }
                        }
                    });
                    
                    const totalJobs = jobRows.length;
                    
                    // Update summary box
                    if (data.compromised_count > 0 || failedCount > 0) {
                        // Show failure alert
                        summaryBox.className = 'mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r shadow-sm';
                        summaryText.innerHTML = `<strong class="text-red-800">WARNING:</strong> <span class="font-bold">${data.compromised_count || failedCount}</span> job(s) failed HMAC verification. Data integrity may be compromised.`;
                        statusIcon.innerHTML = '<svg class="h-6 w-6 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>';
                        
                        // Show detailed alert
                        countSpan.textContent = data.compromised_count || failedCount;
                        alertBox.classList.remove('hidden');
                        alertBox.style.display = 'block';
                        
                        // Log compromised jobs
                        data.compromised_jobs.forEach(job => {
                            console.warn(`Tampered Job Detected: ID ${job.jobid} - ${job.job_name} (${job.reason})`);
                        });
                    } else if (totalJobs > 0) {
                        // All verified
                        summaryBox.className = 'mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r shadow-sm';
                        summaryText.innerHTML = `<strong class="text-green-800">SUCCESS:</strong> All <span class="font-bold">${verifiedCount}</span> job(s) passed HMAC verification. Data integrity verified using SHA-256.`;
                        statusIcon.innerHTML = '<svg class="h-6 w-6 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>';
                    } else {
                        // No jobs
                        summaryBox.className = 'mb-6 bg-gray-50 border-l-4 border-gray-400 p-4 rounded-r shadow-sm';
                        summaryText.innerHTML = '<span class="text-gray-700">No jobs found. HMAC verification will run when jobs are submitted.</span>';
                        statusIcon.innerHTML = '<svg class="h-6 w-6 text-gray-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>';
                    }
                } else {
                    summaryBox.className = 'mb-6 bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-r shadow-sm';
                    summaryText.innerHTML = '<span class="text-yellow-800">Could not verify HMAC integrity. Please refresh the page.</span>';
                    statusIcon.innerHTML = '<svg class="h-6 w-6 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>';
                }
            } catch (e) {
                console.error('HMAC verification check failed:', e);
                summaryBox.className = 'mb-6 bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-r shadow-sm';
                summaryText.innerHTML = '<span class="text-yellow-800">Error checking HMAC integrity. Please try again.</span>';
                statusIcon.innerHTML = '<svg class="h-6 w-6 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>';
            }
        });
        <?php endif; ?>
        
        // Dismiss integrity alert button handler (using event delegation)
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('#dismiss-integrity-alert');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                const alertBox = document.getElementById('integrity-alert');
                if (alertBox) {
                    alertBox.style.display = 'none';
                    alertBox.classList.add('hidden');
                }
            }
        });
        
        // Also add direct handler when alert is shown
        function setupDismissButton() {
            const dismissBtn = document.getElementById('dismiss-integrity-alert');
            if (dismissBtn) {
                dismissBtn.onclick = function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const alertBox = document.getElementById('integrity-alert');
                    if (alertBox) {
                        alertBox.style.display = 'none';
                        alertBox.classList.add('hidden');
                    }
                    return false;
                };
            }
        }
        
        // Setup button when alert is shown
        const originalRemoveHidden = Element.prototype.remove || function() {};
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    const alertBox = document.getElementById('integrity-alert');
                    if (alertBox && !alertBox.classList.contains('hidden')) {
                        setupDismissButton();
                    }
                }
            });
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            setupDismissButton();
            const alertBox = document.getElementById('integrity-alert');
            if (alertBox) {
                observer.observe(alertBox, { attributes: true, attributeFilter: ['class'] });
            }
        });
    </script>
</body>
</html>
