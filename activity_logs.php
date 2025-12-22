<?php
// Activity logs viewer (Admin only) - System audit trail with filtering

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['session_id']) || !isset($_SESSION['token'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/rbac.php';

$role = $_SESSION['role'] ?? 'guest';

if (!RBAC::canAccessActivityLogs($role)) {
    header('Location: dashboard.php');
    exit;
}

$filter_userid = isset($_GET['userid']) ? intval($_GET['userid']) : null;
$filter_activity_type = isset($_GET['activity_type']) ? trim($_GET['activity_type']) : null;

// Whitelist activity_type for security
$allowedTypes = [
    'LOGIN',
    'JOB_SUBMIT',
    'JOB_VIEW',
    'DATA_TRANSFER',
    'HMAC_VERIFY',
    'ROLE_CHANGE',
    'ROLE_CHANGE_DENIED',
    'USER_DELETE',
    'REGISTER',
    'LOG_ACCESS'
];

if (!in_array($filter_activity_type, $allowedTypes, true)) {
    $filter_activity_type = null;
}

$filter_userid_str = $filter_userid ? (string)$filter_userid : '';
$filter_activity_type_str = $filter_activity_type ? htmlspecialchars($filter_activity_type) : '';

$logs = [];
$pagination = ['total' => 0, 'limit' => 100, 'offset' => 0, 'has_more' => false];
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;

$ch = curl_init();
$url = 'https://localhost/api/activity_logs.php';
if ($filter_userid || $filter_activity_type || $offset || $limit != 100) {
    $params = [];
    if ($filter_userid) $params[] = 'userid=' . urlencode($filter_userid);
    if ($filter_activity_type) $params[] = 'activity_type=' . urlencode($filter_activity_type);
    if ($offset) $params[] = 'offset=' . urlencode($offset);
    if ($limit != 100) $params[] = 'limit=' . urlencode($limit);
    if (!empty($params)) {
        $url .= '?' . implode('&', $params);
    }
}

curl_setopt($ch, CURLOPT_URL, $url);
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
        $logs = $data['logs'] ?? [];
        $pagination = $data['pagination'] ?? $pagination;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Audit Logs - Secure Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
    </style>
</head>
<body>
    <nav class="bg-slate-900 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 font-bold text-xl">
                        Secure Portal
                    </div>
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-baseline space-x-4">
                            <a href="dashboard.php" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                            <a href="activity_logs.php" class="bg-slate-800 text-white px-3 py-2 rounded-md text-sm font-medium">Activity Logs</a>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">ADMIN AREA</span>
                    <a href="dashboard.php?logout=1" class="text-sm text-gray-400 hover:text-white transition-colors">Sign out</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6 flex justify-between items-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">System Audit Trail</h3>
                <span class="text-xs text-gray-500">Displaying recent security events</span>
            </div>
            
            <div class="p-4 bg-gray-50 border-b border-gray-200">
                <form method="GET" action="" class="flex gap-4 flex-wrap items-end">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">User ID</label>
                        <input type="number" name="userid" value="<?php echo htmlspecialchars($filter_userid_str); ?>" placeholder="ID" class="border rounded-md p-2 text-sm w-24">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Event Type</label>
                        <select name="activity_type" class="border rounded-md p-2 text-sm w-48">
                            <option value="">All Events</option>
                            <option value="LOGIN" <?php echo $filter_activity_type === 'LOGIN' ? 'selected' : ''; ?>>Login</option>
                            <option value="JOB_SUBMIT" <?php echo $filter_activity_type === 'JOB_SUBMIT' ? 'selected' : ''; ?>>Job Submission</option>
                            <option value="JOB_VIEW" <?php echo $filter_activity_type === 'JOB_VIEW' ? 'selected' : ''; ?>>Data Access</option>
                            <option value="DATA_TRANSFER" <?php echo $filter_activity_type === 'DATA_TRANSFER' ? 'selected' : ''; ?>>Data Transfer</option>
                            <option value="HMAC_VERIFY" <?php echo $filter_activity_type === 'HMAC_VERIFY' ? 'selected' : ''; ?>>HMAC Verification</option>
                            <option value="ROLE_CHANGE" <?php echo $filter_activity_type === 'ROLE_CHANGE' ? 'selected' : ''; ?>>Role Change</option>
                            <option value="ROLE_CHANGE_DENIED" <?php echo $filter_activity_type === 'ROLE_CHANGE_DENIED' ? 'selected' : ''; ?>>Role Change Denied</option>
                            <option value="USER_DELETE" <?php echo $filter_activity_type === 'USER_DELETE' ? 'selected' : ''; ?>>User Deletion</option>
                            <option value="REGISTER" <?php echo $filter_activity_type === 'REGISTER' ? 'selected' : ''; ?>>Registration</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm hover:bg-blue-700">Filter</button>
                        <a href="activity_logs.php" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-md text-sm hover:bg-gray-300">Reset</a>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Event</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($logs as $log): 
                            // Badge colors for different activity types (PHP 7 compatible switch)
                            switch ($log['activity_type'] ?? '') {
                                case 'LOGIN':
                                    $badgeColor = 'bg-green-100 text-green-800';
                                    break;
                                case 'JOB_SUBMIT':
                                    $badgeColor = 'bg-blue-100 text-blue-800';
                                    break;
                                case 'JOB_VIEW':
                                    $badgeColor = 'bg-indigo-100 text-indigo-800';
                                    break;
                                case 'DATA_TRANSFER':
                                    $badgeColor = 'bg-cyan-100 text-cyan-800';
                                    break;
                                case 'HMAC_VERIFY':
                                    $badgeColor = 'bg-purple-100 text-purple-800';
                                    break;
                                case 'ROLE_CHANGE':
                                    $badgeColor = 'bg-emerald-100 text-emerald-800';
                                    break;
                                case 'ROLE_CHANGE_DENIED':
                                    $badgeColor = 'bg-red-100 text-red-800';
                                    break;
                                case 'USER_DELETE':
                                    $badgeColor = 'bg-orange-100 text-orange-800';
                                    break;
                                case 'REGISTER':
                                    $badgeColor = 'bg-teal-100 text-teal-800';
                                    break;
                                case 'LOG_ACCESS':
                                    $badgeColor = 'bg-slate-100 text-slate-800';
                                    break;
                                default:
                                    $badgeColor = 'bg-gray-100 text-gray-800';
                                    break;
                            }
                            
                            // Parse description for role changes to extract target user and role info
                            $description = $log['description'] ?? '';
                            $enhancedDescription = htmlspecialchars($description);
                            
                            if (($log['activity_type'] ?? '') === 'ROLE_CHANGE' || ($log['activity_type'] ?? '') === 'ROLE_CHANGE_DENIED') {
                                // Extract target username, old role, and new role from description
                                // Format: "Changed role of user 'username' (ID: X) from 'old_role' to 'new_role'"
                                // or: "Attempted to change role of user 'username' (ID: X) from 'old_role' to 'new_role' - Access denied..."
                                if (preg_match("/user '([^']+)' \(ID: (\d+)\) from '([^']+)' to '([^']+)'/", $description, $matches)) {
                                    $targetUsername = htmlspecialchars($matches[1]);
                                    $targetUserId = htmlspecialchars($matches[2]);
                                    $oldRole = htmlspecialchars($matches[3]);
                                    $newRole = htmlspecialchars($matches[4]);
                                    
                                    $enhancedDescription = sprintf(
                                        '<div class="space-y-1"><div class="font-medium">Target: <span class="text-gray-900">%s</span> (ID: %s)</div><div class="text-xs flex items-center gap-2">Role: <span class="px-1.5 py-0.5 rounded bg-gray-200 font-medium">%s</span> <span class="text-gray-400">→</span> <span class="px-1.5 py-0.5 rounded bg-gray-200 font-medium">%s</span></div>%s</div>',
                                        $targetUsername,
                                        $targetUserId,
                                        $oldRole,
                                        $newRole,
                                        ($log['activity_type'] ?? '') === 'ROLE_CHANGE_DENIED' ? '<div class="text-red-600 text-xs font-medium mt-1"><i class="fas fa-ban mr-1"></i>Access Denied</div>' : '<div class="text-emerald-600 text-xs font-medium mt-1"><i class="fas fa-check-circle mr-1"></i>Success</div>'
                                    );
                                } else {
                                    // Fallback: just show the description with proper escaping
                                    $enhancedDescription = htmlspecialchars($description);
                                }
                            }
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $badgeColor; ?>">
                                    <?php echo htmlspecialchars($log['activity_type'] ?? 'UNKNOWN'); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <div class="flex flex-col">
                                    <span class="font-medium"><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></span>
                                    <span class="text-gray-400 text-xs">ID: <?php echo $log['userid'] ?? 'N/A'; ?></span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 max-w-md">
                                <?php echo $enhancedDescription; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono text-xs">
                                <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php echo htmlspecialchars($log['created_at'] ?? ''); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6 flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Showing <?php echo count($logs); ?> records
                </div>
                <div class="flex gap-2">
                    <?php if ($offset > 0): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['offset' => max(0, $offset - $limit)])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Previous
                        </a>
                    <?php endif; ?>
                    <?php if ($pagination['has_more']): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['offset' => $offset + $limit])); ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
