<?php
// User management page (Admin only) - List users, change roles (root-only), view HMAC integrity
require_once __DIR__ . '/config/settings.php'; // Include settings to get ROOT_USERNAME
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/rbac.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['userid']) || !isset($_SESSION['role']) || !RBAC::canManageUsers($_SESSION['role'])) {
    header('Location: dashboard.php');
    exit;
}

// Pass session data to JS safely
$session_id = $_SESSION['session_id'] ?? '';
$token = $_SESSION['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Secure Web App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-slate-50 min-h-screen">
    <nav class="bg-navy-900 text-white shadow-lg" style="background-color: #0f172a;">
        <div class="container mx-auto px-6 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <i class="fas fa-shield-alt text-2xl text-emerald-400"></i>
                    <span class="text-xl font-bold tracking-wider">SecureSys Admin</span>
                </div>
                <div class="flex items-center space-x-6">
                    <span class="text-slate-300">
                        <i class="fas fa-user-shield mr-2"></i><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                    </span>
                    <a href="dashboard.php" class="hover:text-emerald-400 transition-colors">
                        <i class="fas fa-arrow-left mr-1"></i> Dashboard
                    </a>
                    <button onclick="logout()" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded-lg transition-colors font-medium text-sm">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mx-auto px-6 py-8">
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-slate-800">User Management</h1>
                <p class="text-slate-600 mt-2">Manage registered users and their roles.</p>
            </div>
            <a href="register.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-6 py-3 rounded-lg shadow-md transition-all flex items-center">
                <i class="fas fa-user-plus mr-2"></i> Register New User
            </a>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200">
                            <th class="p-4 font-semibold text-slate-700">ID</th>
                            <th class="p-4 font-semibold text-slate-700">Username</th>
                            <th class="p-4 font-semibold text-slate-700">Name</th>
                            <th class="p-4 font-semibold text-slate-700">Email</th>
                            <th class="p-4 font-semibold text-slate-700">Role</th>
                            <th class="p-4 font-semibold text-slate-700">HMAC Integrity</th>
                            <th class="p-4 font-semibold text-slate-700">Registered At</th>
                            <th class="p-4 font-semibold text-slate-700 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody" class="text-slate-600">
                        <tr>
                            <td colspan="8" class="p-8 text-center">
                                <i class="fas fa-spinner fa-spin text-3xl text-emerald-500 mb-3"></i>
                                <p>Loading users...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const API_URL = 'https://localhost/api';
        const SESSION_ID = "<?php echo htmlspecialchars($session_id, ENT_QUOTES, 'UTF-8'); ?>";
        const SESSION_TOKEN = "<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>";
        const CURRENT_USER_ID = <?php echo intval($_SESSION['userid']); ?>;
        const ROOT_USERNAME = "<?php echo defined('ROOT_USERNAME') ? htmlspecialchars(ROOT_USERNAME, ENT_QUOTES, 'UTF-8') : ''; ?>";
        const CURRENT_USERNAME = "<?php echo htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>";
        const IS_ROOT_USER = CURRENT_USERNAME === ROOT_USERNAME;
        

        // Fetch users on load
        document.addEventListener('DOMContentLoaded', function() {
            // Set up event delegation once (works for all future table re-renders)
            setupRoleChangeDelegation();
            fetchUsers();
        });
        
        function setupRoleChangeDelegation() {
            // Use event delegation on the table container for all role dropdown changes
            // This works even after the tbody innerHTML is replaced
            const tableContainer = document.querySelector('.bg-white.rounded-xl.shadow-sm');
            if (tableContainer) {
                tableContainer.addEventListener('change', function(event) {
                    if (event.target && event.target.classList.contains('role-select-dropdown')) {
                        const select = event.target;
                        const userid = select.getAttribute('data-userid');
                        const username = select.getAttribute('data-username');
                        const oldRole = select.getAttribute('data-old-role');
                        const newRole = select.value;
                        
                        changeUserRole(userid, username, newRole, oldRole, select);
                    }
                });
            }
        }

        async function fetchUsers() {
            if (!SESSION_ID || !SESSION_TOKEN) {
                window.location.href = 'index.php';
                return;
            }

            try {
                const response = await fetch(`${API_URL}/users.php`, {
                    headers: {
                        'X-Session-ID': SESSION_ID,
                        'X-Token': SESSION_TOKEN
                    }
                });

                if (response.status === 401 || response.status === 403) {
                    alert('Session expired or unauthorized');
                    window.location.href = 'index.php';
                    return;
                }

                const data = await response.json();
                renderUsers(data.users);
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('usersTableBody').innerHTML = `
                    <tr><td colspan="8" class="p-8 text-center text-red-500">Failed to load users. Please ensure the server is running.</td></tr>
                `;
            }
        }

        function renderUsers(users) {
            const tbody = document.getElementById('usersTableBody');
            if (!users || users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="p-8 text-center">No users found.</td></tr>';
                return;
            }
            
            // Track HMAC verification status
            let verifiedCount = 0;
            let failedCount = 0;
            let notAvailableCount = 0;

            tbody.innerHTML = users.map(user => {
                const roleColors = {
                    'admin': 'bg-purple-100 text-purple-700',
                    'user': 'bg-blue-100 text-blue-700',
                    'guest': 'bg-gray-100 text-gray-700'
                };
                // Role display - editable for root, read-only for others
                let roleDisplay = '';
                const isTargetRoot = user.username.trim() === ROOT_USERNAME;
                const isCurrentUser = user.userid == CURRENT_USER_ID;
                const canEditRole = IS_ROOT_USER && !isTargetRoot && !isCurrentUser;
                
                if (canEditRole) {
                    // Root user can change this user's role (dropdown)
                    // Use data attributes for safe event handling (no inline JS with user data)
                    const roleSelectId = `role-select-${user.userid}`;
                    const safeUsername = user.username.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                    roleDisplay = `
                        <select id="${roleSelectId}" 
                            data-userid="${user.userid}"
                            data-username="${safeUsername}"
                            data-old-role="${user.role}"
                            class="role-select-dropdown px-3 py-1 rounded-full text-xs font-medium uppercase tracking-wide border-2 border-transparent hover:border-blue-300 focus:border-blue-500 focus:outline-none cursor-pointer ${roleColors[user.role] || 'bg-gray-100'}">
                            <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>admin</option>
                            <option value="user" ${user.role === 'user' ? 'selected' : ''}>user</option>
                            <option value="guest" ${user.role === 'guest' ? 'selected' : ''}>guest</option>
                        </select>
                    `;
                } else {
                    // Read-only role badge
                    roleDisplay = `<span class="px-3 py-1 rounded-full text-xs font-medium uppercase tracking-wide ${roleColors[user.role] || 'bg-gray-100'}">${user.role}</span>`;
                }
                
                // HMAC verification status
                let hmacBadge = '';
                const hmacStatus = user.hmac_status || 'not_checked';
                
                if (hmacStatus === 'verified') {
                    verifiedCount++;
                    hmacBadge = `<span class="px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 flex items-center gap-1">
                        <i class="fas fa-check-circle"></i> Verified
                    </span>`;
                } else if (hmacStatus === 'failed') {
                    failedCount++;
                    hmacBadge = `<span class="px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700 flex items-center gap-1">
                        <i class="fas fa-exclamation-triangle"></i> Failed
                    </span>`;
                } else if (hmacStatus === 'not_available') {
                    notAvailableCount++;
                    hmacBadge = `<span class="px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 flex items-center gap-1">
                        <i class="fas fa-info-circle"></i> N/A
                    </span>`;
                } else {
                    hmacBadge = `<span class="px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700 flex items-center gap-1">
                        <i class="fas fa-question-circle"></i> Unknown
                    </span>`;
                }
                
                // Don't show delete button for the current user
                let actionButton = '';
                const username = user.username.trim();

                if (username === ROOT_USERNAME) {
                     actionButton = `<span class="px-2 py-1 rounded text-xs font-bold bg-amber-100 text-amber-800">ROOT</span>`;
                } else if (user.userid != CURRENT_USER_ID) {
                    actionButton = `
                        <button onclick="deleteUser(${user.userid}, '${user.username}')" 
                            class="text-red-500 hover:text-red-700 hover:bg-red-50 px-3 py-2 rounded transition-all"
                            title="Delete User">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    `;
                } else {
                     actionButton = `<span class="text-gray-400 italic text-sm">Current User</span>`;
                }
                
                return `
                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                        <td class="p-4 font-mono text-sm text-slate-500">#${user.userid}</td>
                        <td class="p-4 font-medium text-slate-800">${user.username}</td>
                        <td class="p-4">${user.name}</td>
                        <td class="p-4 text-slate-500">${user.email}</td>
                        <td class="p-4">${roleDisplay}</td>
                        <td class="p-4">${hmacBadge}</td>
                        <td class="p-4 text-sm text-slate-500">${new Date(user.created_at).toLocaleString()}</td>
                        <td class="p-4 text-right">
                            ${actionButton}
                        </td>
                    </tr>
                `;
            }).join('');
            
            // Display HMAC verification summary message
            displayHMACSummary(verifiedCount, failedCount, notAvailableCount, users.length);
            // Note: Event delegation is already set up, no need to re-setup after render
        }
        
        function displayHMACSummary(verified, failed, notAvailable, total) {
            // Remove existing summary if any
            const existingSummary = document.getElementById('hmac-summary');
            if (existingSummary) {
                existingSummary.remove();
            }
            
            // Create summary message
            const summaryDiv = document.createElement('div');
            summaryDiv.id = 'hmac-summary';
            summaryDiv.className = 'mb-4 p-4 rounded-lg border';
            
            let summaryHTML = '<div class="flex items-center gap-2 mb-2">';
            summaryHTML += '<i class="fas fa-shield-alt text-emerald-500"></i>';
            summaryHTML += '<h3 class="font-semibold text-slate-800">HMAC Data Integrity Check</h3>';
            summaryHTML += '</div>';
            
            if (failed > 0) {
                summaryDiv.className += ' bg-red-50 border-red-200';
                summaryHTML += `<p class="text-red-700"><i class="fas fa-exclamation-triangle mr-2"></i><strong>Warning:</strong> ${failed} user(s) failed HMAC verification. Data integrity may be compromised.</p>`;
            } else if (verified === total) {
                summaryDiv.className += ' bg-green-50 border-green-200';
                summaryHTML += `<p class="text-green-700"><i class="fas fa-check-circle mr-2"></i><strong>Success:</strong> All ${verified} user(s) passed HMAC verification. Data integrity verified.</p>`;
            } else {
                summaryDiv.className += ' bg-blue-50 border-blue-200';
                summaryHTML += `<p class="text-blue-700">`;
                summaryHTML += `<i class="fas fa-info-circle mr-2"></i>`;
                summaryHTML += `<strong>Status:</strong> ${verified} verified, ${notAvailable} not available (legacy data), ${failed} failed out of ${total} total users.`;
                summaryHTML += `</p>`;
            }
            
            summaryDiv.innerHTML = summaryHTML;
            
            // Insert summary before the table
            const tableContainer = document.querySelector('.bg-white.rounded-xl.shadow-sm');
            if (tableContainer && tableContainer.parentNode) {
                tableContainer.parentNode.insertBefore(summaryDiv, tableContainer);
            }
        }

        async function deleteUser(userid, username) {
            if (!confirm(`Are you sure you want to delete user "${username}"? This will also delete all their jobs and activity logs.`)) {
                return;
            }

            try {
                const response = await fetch(`${API_URL}/users.php?id=${userid}`, {
                    method: 'DELETE',
                    headers: {
                        'X-Session-ID': SESSION_ID,
                        'X-Token': SESSION_TOKEN
                    }
                });

                const data = await response.json();

                if (response.ok) {
                    alert('User deleted successfully');
                    fetchUsers(); // Refresh list
                } else {
                    alert(data.error || 'Failed to delete user');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while deleting the user');
            }
        }

        async function changeUserRole(userid, username, newRole, oldRole, selectElement) {
            if (!confirm(`Change role of user "${username}" from "${oldRole}" to "${newRole}"?`)) {
                // Reset dropdown to old value
                selectElement.value = oldRole;
                return;
            }

            try {
                const response = await fetch(`${API_URL}/users.php`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Session-ID': SESSION_ID,
                        'X-Token': SESSION_TOKEN
                    },
                    body: JSON.stringify({
                        userid: userid,
                        role: newRole
                    })
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    // Show success message
                    showMessage('success', data.message || 'Role changed successfully');
                    // Update data-old-role attribute for future changes
                    selectElement.setAttribute('data-old-role', newRole);
                    // Refresh user list to show updated role
                    fetchUsers();
                } else {
                    // Show error message
                    showMessage('error', data.error || 'Failed to change role');
                    // Reset dropdown to old value
                    selectElement.value = oldRole;
                }
            } catch (error) {
                console.error('Error:', error);
                showMessage('error', 'An error occurred while changing the role');
                // Reset dropdown to old value
                selectElement.value = oldRole;
            }
        }

        function showMessage(type, message) {
            // Remove existing messages
            const existingMsg = document.getElementById('role-change-message');
            if (existingMsg) {
                existingMsg.remove();
            }

            // Create message element
            const msgDiv = document.createElement('div');
            msgDiv.id = 'role-change-message';
            msgDiv.className = `mb-4 p-4 rounded-lg border ${
                type === 'success' 
                    ? 'bg-green-50 border-green-200 text-green-700' 
                    : 'bg-red-50 border-red-200 text-red-700'
            }`;
            msgDiv.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-auto text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;

            // Insert before the table
            const tableContainer = document.querySelector('.bg-white.rounded-xl.shadow-sm');
            if (tableContainer && tableContainer.parentNode) {
                tableContainer.parentNode.insertBefore(msgDiv, tableContainer);
            }

            // Auto-hide after 5 seconds
            setTimeout(() => {
                if (msgDiv.parentNode) {
                    msgDiv.remove();
                }
            }, 5000);
        }

        function logout() {
            // Note: In PHP session based auth, we should ideally call a logout endpoint.
            // But since this is a simple implementation, we just redirect with a flag that dashboard.php handles (or create a logout.php)
            // dashboard.php has a check for ?logout=1
            window.location.href = 'dashboard.php?logout=1';
        }
    </script>
</body>
</html>
