# Repository Security Audit Report
**Date:** 2024  
**Project:** Secure Web Application (PHP)  
**Auditor:** Security Review

---

## A) Project Overview

### Stack & Runtime
- **Language:** PHP (appears to be PHP 7.0+)
- **Database:** MySQL/MariaDB (PDO)
- **Architecture:** Traditional PHP MVC-style with API endpoints
- **Security Features:** AES-256 encryption, HMAC integrity, RBAC, session-based auth

### Entry Points
- `index.php` - Login page (entry point)
- `register.php` - User registration
- `dashboard.php` - Main application interface
- `api/login.php` - Authentication API
- `api/register.php` - Registration API
- `api/jobs.php` - Job management API
- `api/users.php` - User management API (admin)
- `api/activity_logs.php` - Audit logs API (admin)
- `api/integrity_check.php` - HMAC verification API (admin)

### How to Run
1. Copy `config/settings.example.php` to `config/settings.php`
2. Configure database credentials and encryption keys in `settings.php`
3. Import database schema from `database/schema.sql`
4. Configure Apache virtual host (see `apache/httpd-vhosts.conf`)
5. Set up SSL certificates (see `ssl/` directory)
6. Point web server to project root
7. Access via `https://localhost/`

**Note:** No README file found. Documentation would improve onboarding.

---

## B) Top 10 Critical Issues (Ranked)

### 1. CRITICAL: Auth Constructor Parameter Ignored / Database Connection Bug
**File(s):** `api/login.php:21`, `api/register.php:51`, `api/jobs.php:15`, `api/users.php:27`, `api/activity_logs.php:11`, `api/integrity_check.php:26`

**What's wrong:**
All API endpoints instantiate `Auth` with a `$db` parameter: `new Auth($db)`, but the `Auth` class constructor doesn't accept parameters. It uses `getDB()` internally, ignoring the passed database connection.

```php
// Current (BROKEN):
$db = new PDO(...);
$auth = new Auth($db);  // $db is ignored!

// Auth class constructor:
public function __construct() {
    $this->db = getDB();  // Ignores any passed parameter
}
```

**Why it matters:**
- Creates unnecessary duplicate database connections (memory waste)
- Bypasses the singleton Database class, preventing connection pooling
- The explicit `$db` connections created in APIs are unused by Auth class
- Confusing code that suggests one behavior but does another
- Potential connection leak if connections aren't properly closed

**Fix steps:**
1. **Option A (Recommended):** Remove `$db` parameter from all `new Auth()` calls and let Auth use singleton:
   ```php
   // In all API files, change:
   $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
   $auth = new Auth($db);
   // To:
   $auth = new Auth();
   ```

2. **Option B:** Modify Auth constructor to accept optional DB parameter:
   ```php
   public function __construct($db = null) {
       $this->db = $db ?? getDB();
   }
   ```
   But still remove the redundant `new PDO()` calls in API files.

**Code diff (Option A - api/login.php):**
```diff
     try {
-        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
-        $auth = new Auth($db);
+        $auth = new Auth();
         $logger = new ActivityLogger();
```

---

### 2. CRITICAL: SSL Certificate Verification Disabled (MITM Vulnerability)
**File(s):** `index.php:32-33`, `register.php:38-39`, `dashboard.php:42-43,76-77`, `activity_logs.php:68-69`

**What's wrong:**
All cURL requests disable SSL certificate verification:
```php
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
```

**Why it matters:**
- Vulnerable to Man-in-the-Middle (MITM) attacks
- Attackers can intercept and modify HTTPS traffic
- Completely defeats the purpose of using HTTPS
- Production security risk

**Fix steps:**
1. Enable SSL verification:
   ```php
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
   curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
   ```

2. For development, use proper self-signed certificates and add to CA bundle, OR use environment-based flag:
   ```php
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, defined('SSL_VERIFY_PEER') ? SSL_VERIFY_PEER : true);
   curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, defined('SSL_VERIFY_PEER') && SSL_VERIFY_PEER ? 2 : 0);
   ```

3. Update `config/settings.example.php` to have `SSL_VERIFY_PEER => true` by default.

**Code diff (index.php):**
```diff
-        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
-        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
+        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, defined('SSL_VERIFY_PEER') ? SSL_VERIFY_PEER : true);
+        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, defined('SSL_VERIFY_PEER') && SSL_VERIFY_PEER ? 2 : 0);
```

---

### 3. CRITICAL: Hardcoded Encryption Keys in Example File
**File(s):** `config/settings.example.php:31,37`

**What's wrong:**
Example configuration file contains hardcoded encryption keys that are predictable:
```php
define('AES_KEY', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
define('HMAC_SECRET_KEY', 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210');
```

**Why it matters:**
- If users copy example file without changing keys, data is encrypted with known keys
- Anyone with access to example file knows the default keys
- Weak keys compromise all encrypted data
- Should use cryptographically secure random keys

**Fix steps:**
1. Generate placeholder keys with comment showing generation method:
   ```php
   // Generate using: echo bin2hex(random_bytes(32));
   define('AES_KEY', 'CHANGE_THIS_TO_RANDOM_64_HEX_CHARACTERS');
   // Generate using: echo bin2hex(random_bytes(32));
   define('HMAC_SECRET_KEY', 'CHANGE_THIS_TO_RANDOM_64_HEX_CHARACTERS');
   ```

2. Add validation in settings.php to ensure keys are changed:
   ```php
   if (AES_KEY === '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef') {
       throw new Exception("AES_KEY must be changed from default value!");
   }
   ```

**Code diff:**
```diff
-define('AES_KEY', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
+// Generate using: echo bin2hex(random_bytes(32));
+// IMPORTANT: Change this to a secure random 64-character hex string
+define('AES_KEY', 'CHANGE_THIS_TO_RANDOM_64_HEX_CHARACTERS');
```

---

### 4. HIGH: Missing CSRF Protection
**File(s):** `index.php`, `register.php`, `dashboard.php`, `manage_users.php`

**What's wrong:**
No CSRF tokens on any forms or API endpoints. Forms submit without token validation.

**Why it matters:**
- Vulnerable to Cross-Site Request Forgery (CSRF) attacks
- Attackers can trick logged-in users into performing unintended actions
- Can lead to unauthorized job submissions, role changes, user deletions

**Fix steps:**
1. Generate CSRF token on session start:
   ```php
   if (!isset($_SESSION['csrf_token'])) {
       $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
   }
   ```

2. Add hidden field to all forms:
   ```html
   <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
   ```

3. Validate token on form submission:
   ```php
   if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
       die('CSRF token validation failed');
   }
   ```

4. For API endpoints using headers, validate `X-CSRF-Token` header.

---

### 5. HIGH: Inconsistent Database Connection Pattern
**File(s):** All API files create new PDO connections instead of using Database singleton

**What's wrong:**
- `includes/auth.php` uses `getDB()` (singleton pattern)
- All API files (`api/login.php`, `api/register.php`, `api/jobs.php`, etc.) create new PDO instances
- `includes/logger.php` creates its own PDO connection
- No connection reuse, potential connection exhaustion

**Why it matters:**
- Waste of database connections
- Can hit MySQL connection limit under load
- Inconsistent codebase
- Harder to manage connection settings centrally

**Fix steps:**
1. Replace all `new PDO(...)` in API files with `getDB()`
2. Update `ActivityLogger` to use `getDB()` instead of creating its own connection
3. Remove redundant connection code

**Code diff (api/login.php):**
```diff
     try {
-        $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
         $auth = new Auth();
         $logger = new ActivityLogger();
```

**Code diff (includes/logger.php):**
```diff
     private function connect() {
         try {
-            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
-            $this->db = new PDO($dsn, DB_USER, DB_PASS);
+            require_once __DIR__ . '/database.php';
+            $this->db = getDB();
             $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

---

### 6. HIGH: Plaintext Data Stored Alongside Encrypted Data
**File(s):** `api/jobs.php:64`, `database/schema.sql:38`

**What's wrong:**
Jobs table stores both `opn_number_encrypted` (encrypted) AND `opn_number_plaintext` (unencrypted):
```sql
opn_number_plaintext VARCHAR(255) NULL, -- Added for demonstration purposes
```

And the code stores both:
```php
$stmt->execute([$userid, $job_name, $encrypted_opn, $opn_number, $clear_text_data, $data_hmac]);
//                                                              ^^^^^^^^^^^^^^^^^ plaintext stored!
```

**Why it matters:**
- Defeats the purpose of encryption
- Sensitive data is readable in database
- Anyone with database access can read plaintext
- Security theater - encryption appears to work but doesn't protect data

**Fix steps:**
1. Remove `opn_number_plaintext` column from database schema
2. Remove plaintext insertion from `api/jobs.php`
3. Only store encrypted version
4. Decrypt only when displaying (and only for authorized users)

**Code diff (api/jobs.php):**
```diff
-        $stmt = $db->prepare("INSERT INTO jobs (userid, job_name, opn_number_encrypted, opn_number_plaintext, clear_text_data, data_hmac) VALUES (?, ?, ?, ?, ?, ?)");
-        $stmt->execute([$userid, $job_name, $encrypted_opn, $opn_number, $clear_text_data, $data_hmac]);
+        $stmt = $db->prepare("INSERT INTO jobs (userid, job_name, opn_number_encrypted, clear_text_data, data_hmac) VALUES (?, ?, ?, ?, ?)");
+        $stmt->execute([$userid, $job_name, $encrypted_opn, $clear_text_data, $data_hmac]);
```

**Database migration:**
```sql
ALTER TABLE jobs DROP COLUMN opn_number_plaintext;
```

---

### 7. MEDIUM: Registration Allows Users to Select Their Own Role
**File(s):** `register.php:18,250-257`, `api/register.php:35`

**What's wrong:**
Registration form allows users to select admin/user/guest role:
```html
<select id="role" name="role" required>
    <option value="guest">Guest (Limited View)</option>
    <option value="user">User (Standard Access)</option>
    <option value="admin">Admin (Full Access)</option>
</select>
```

While the API validates the role, the UI shouldn't allow this selection.

**Why it matters:**
- Users can register as admin, gaining full system access
- No role assignment control
- Security risk - any attacker can become admin

**Fix steps:**
1. Remove role selection from registration form
2. Always assign default role (guest) on registration
3. Only admins/root can change roles via user management interface

**Code diff (register.php):**
```diff
-            <div class="form-group">
-                <label for="role">Role</label>
-                <select id="role" name="role" required>
-                    <option value="guest">Guest (Limited View)</option>
-                    <option value="user">User (Standard Access)</option>
-                    <option value="admin">Admin (Full Access)</option>
-                </select>
-                <span class="role-description">Select your permission level for the demo.</span>
-            </div>
+            <!-- Role is automatically set to 'guest' by default -->
```

**Code diff (register.php POST handler):**
```diff
-    $role = $_POST['role'] ?? 'guest';
+    // Role is always set to default (guest) for new registrations
+    $role = 'guest';
```

---

### 8. MEDIUM: Missing Input Validation & Sanitization
**File(s):** Multiple API endpoints

**What's wrong:**
Limited validation on user inputs:
- Username length/format not validated
- Email format not validated (though email auto-generated)
- Job names, OPN numbers not validated for length/content
- No SQL injection protection beyond prepared statements (good, but input validation adds defense in depth)

**Why it matters:**
- Potential for invalid data in database
- Could cause issues with display/processing
- Defense in depth principle
- Better user experience with clear error messages

**Fix steps:**
1. Add username validation (length, allowed characters):
   ```php
   if (strlen($username) < 3 || strlen($username) > 50 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
       http_response_code(400);
       echo json_encode(['error' => 'Username must be 3-50 alphanumeric characters']);
       exit;
   }
   ```

2. Add email validation when email is provided
3. Add length limits for all text fields
4. Validate OPN number format if there's a known pattern

---

### 9. MEDIUM: Session Security Issues
**File(s):** `includes/auth.php:52-76`, `index.php:4`

**What's wrong:**
- No session regeneration on login (session fixation risk)
- Session cookie security settings not explicitly set
- No secure flag on session cookies (though HTTPS should be used)

**Why it matters:**
- Session fixation attacks possible
- If HTTP is accessible, session cookies could be intercepted
- Should regenerate session ID after authentication

**Fix steps:**
1. Regenerate session ID after successful login:
   ```php
   session_regenerate_id(true); // Delete old session
   ```

2. Set secure session cookie settings:
   ```php
   ini_set('session.cookie_httponly', 1);
   ini_set('session.cookie_secure', 1); // Only if HTTPS
   ini_set('session.use_strict_mode', 1);
   ```

3. Add session_regenerate_id after successful authentication in `index.php`

---

### 10. MEDIUM: Dead Code - Unused Login Table
**File(s):** `database/schema.sql:22-30`, `database/sample_data.sql:14-17`

**What's wrong:**
Database schema includes a `login` table that appears to be legacy/unused:
```sql
CREATE TABLE IF NOT EXISTS login (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

No code references this table except sample data insertion.

**Why it matters:**
- Confusing for developers
- Maintenance burden
- Potential security risk if accidentally used
- Database clutter

**Fix steps:**
1. Remove `login` table creation from schema.sql
2. Remove sample data insertion for login table
3. Or document its purpose if it's intentionally kept for migration/compatibility

---

## C) Security Checklist Results

| Category | Status | Notes |
|----------|--------|-------|
| **Authentication** | PARTIAL | Password hashing good (password_hash). Session tokens random. BUT: No session regeneration on login, no CSRF protection. |
| **Authorization** | PASS | RBAC implemented. Role-based permissions enforced. Root user protection exists. |
| **Data Encryption** | PARTIAL | AES-256 encryption used. BUT: Plaintext stored alongside encrypted data. Hardcoded keys in example. |
| **SQL Injection** | PASS | Prepared statements used throughout. No SQL injection vulnerabilities found. |
| **XSS Protection** | PASS | `htmlspecialchars()` used for output escaping. |
| **CSRF Protection** | FAIL | No CSRF tokens on forms or API endpoints. |
| **SSL/TLS** | PARTIAL | HTTPS enforced in .htaccess. BUT: SSL verification disabled in all cURL requests. |
| **Secrets Management** | PARTIAL | Settings file gitignored. BUT: Hardcoded keys in example file. |
| **Input Validation** | PARTIAL | Basic validation exists. BUT: Missing length/format validation on many fields. |
| **Error Handling** | PARTIAL | Exceptions caught. BUT: Error messages may leak information in some cases. |
| **Session Security** | PARTIAL | Random session IDs/tokens. BUT: No regeneration on login, cookie security flags not set. |
| **Rate Limiting** | FAIL | No rate limiting on login/registration endpoints. Vulnerable to brute force. |
| **Audit Logging** | PASS | Activity logging implemented and working. |
| **HMAC Integrity** | PASS | HMAC verification for data integrity implemented. |

---

## D) Performance Opportunities

### Quick Wins

1. **Database Connection Pooling**
   - **Impact:** High
   - **Effort:** Low
   - **Fix:** Use Database singleton consistently (already exists, just needs to be used everywhere)

2. **Remove Redundant Plaintext Storage**
   - **Impact:** Medium (reduces storage, improves security)
   - **Effort:** Low
   - **Fix:** Remove `opn_number_plaintext` column

3. **Add Database Indexes** (if missing)
   - **Impact:** Medium-High for queries
   - **Effort:** Low
   - **Note:** Schema already has good indexes on users.username, sessions.session_id, etc.

4. **N+1 Query Prevention in Jobs API**
   - **Impact:** Low (jobs API already fetches in single query)
   - **Status:** Already optimized

5. **Remove Dead Code**
   - **Impact:** Low (maintenance)
   - **Effort:** Low
   - **Fix:** Remove unused `login` table

### Future Optimizations

- Add query result caching for user roles (if frequently accessed)
- Implement pagination limits on all list endpoints (some already have)
- Consider prepared statement caching if using persistent connections
- Add database query logging in development to identify slow queries

---

## E) Next Actions Checklist

### Critical (Fix Immediately)
- [ ] Fix Auth constructor parameter issue (Issue #1)
- [ ] Enable SSL certificate verification (Issue #2)
- [ ] Remove hardcoded encryption keys from example (Issue #3)
- [ ] Remove plaintext storage from jobs table (Issue #6)

### High Priority (Fix Soon)
- [ ] Implement CSRF protection (Issue #4)
- [ ] Standardize database connections to use singleton (Issue #5)
- [ ] Remove role selection from registration form (Issue #7)

### Medium Priority (Fix When Possible)
- [ ] Add comprehensive input validation (Issue #8)
- [ ] Fix session security (regeneration, cookie flags) (Issue #9)
- [ ] Remove dead code (login table) (Issue #10)
- [ ] Add rate limiting to authentication endpoints
- [ ] Create README.md with setup instructions

### Code Quality
- [ ] Add PHPDoc comments where missing
- [ ] Consider adding PHPUnit tests
- [ ] Add .editorconfig or coding standards file
- [ ] Consider using Composer for dependency management (if adding libraries)

### Documentation
- [ ] Create README.md with setup and run instructions
- [ ] Document API endpoints
- [ ] Document environment variables/settings
- [ ] Add architecture diagram

---

## Assumptions Made

1. **PHP Version:** Assumed PHP 7.0+ based on syntax (random_bytes, null coalescing)
2. **Database:** Assumed MySQL/MariaDB based on syntax and ENGINE=InnoDB
3. **Web Server:** Assumed Apache based on .htaccess and apache/ directory
4. **Development vs Production:** Treated codebase as production-ready, identified dev-only issues (SSL verification disabled, hardcoded keys)
5. **Settings File:** Assumed `config/settings.php` exists (gitignored) and is properly configured in production

---

## Summary

**Security Assessment: Moderate Risk**

The application has a solid security foundation with:
- Proper password hashing
- Prepared statements (SQL injection protected)
- Output escaping (XSS protected)
- RBAC authorization
- HMAC integrity checks
- Activity logging

However, critical issues need immediate attention:
- SSL verification disabled (MITM risk)
- CSRF protection missing
- Plaintext data stored alongside encrypted
- Hardcoded encryption keys in example
- Code inconsistencies (database connections, Auth constructor)

---

*End of Audit Report*
