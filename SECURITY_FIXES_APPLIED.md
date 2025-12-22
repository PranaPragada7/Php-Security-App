# Security Fixes Applied

This document summarizes the security fixes that have been applied to address critical issues identified in the security audit.

## Fixes Applied

### Fix #1: Auth Constructor & Database Connection Standardization

**Problem:** Auth constructor didn't accept parameters, but all API endpoints were passing `$db`, causing redundant database connections.

**Changes:**
1. **includes/auth.php** - Updated constructor to accept optional `$db` parameter:
   ```php
   public function __construct($db = null) {
       $this->db = $db ?? getDB();
   }
   ```

2. **All API endpoints** - Updated to use `getDB()` singleton instead of creating new PDO connections:
   - `api/login.php`
   - `api/register.php`
   - `api/jobs.php`
   - `api/users.php`
   - `api/activity_logs.php`
   - `api/integrity_check.php`

3. **includes/logger.php** - Updated to use `getDB()` instead of creating its own PDO connection.

**Files Changed:**
- `includes/auth.php`
- `api/login.php`
- `api/register.php`
- `api/jobs.php`
- `api/users.php`
- `api/activity_logs.php`
- `api/integrity_check.php`
- `includes/logger.php`

---

### Fix #2: SSL Certificate Verification Enabled

**Problem:** All cURL requests disabled SSL certificate verification, making the application vulnerable to MITM attacks.

**Changes:**
1. Updated all cURL calls to use configurable SSL verification:
   ```php
   $verifyPeer = defined('SSL_VERIFY_PEER') ? (bool)SSL_VERIFY_PEER : true;
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyPeer);
   curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyPeer ? 2 : 0);
   ```

2. **config/settings.example.php** - Set `SSL_VERIFY_PEER` to `true` by default.

**Files Changed:**
- `index.php`
- `register.php`
- `dashboard.php` (2 locations)
- `activity_logs.php`
- `config/settings.example.php`

---

### Fix #3: Removed Hardcoded Encryption Keys

**Problem:** Example configuration file contained predictable hardcoded encryption keys.

**Changes:**
1. **config/settings.example.php** - Replaced hardcoded keys with placeholders:
   ```php
   // Before:
   define('AES_KEY', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
   define('HMAC_SECRET_KEY', 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210');
   
   // After:
   define('AES_KEY', 'CHANGE_THIS_TO_RANDOM_64_HEX_CHARS');
   define('HMAC_SECRET_KEY', 'CHANGE_THIS_TO_RANDOM_64_HEX_CHARS');
   ```

2. Added clear instructions on how to generate secure keys using `bin2hex(random_bytes(32))`.

**Files Changed:**
- `config/settings.example.php`

---

### Fix #6: Removed Plaintext Storage Alongside Encrypted Data

**Problem:** Jobs table stored both encrypted and plaintext versions of sensitive OPN data, defeating the purpose of encryption.

**Changes:**
1. **api/jobs.php** - Removed `opn_number_plaintext` from INSERT statement:
   ```php
   // Before:
   $stmt = $db->prepare("INSERT INTO jobs (userid, job_name, opn_number_encrypted, opn_number_plaintext, clear_text_data, data_hmac) VALUES (?, ?, ?, ?, ?, ?)");
   $stmt->execute([$userid, $job_name, $encrypted_opn, $opn_number, $clear_text_data, $data_hmac]);
   
   // After:
   $stmt = $db->prepare("INSERT INTO jobs (userid, job_name, opn_number_encrypted, clear_text_data, data_hmac) VALUES (?, ?, ?, ?, ?)");
   $stmt->execute([$userid, $job_name, $encrypted_opn, $clear_text_data, $data_hmac]);
   ```

2. **database/schema.sql** - Removed `opn_number_plaintext` column definition.

3. **database/migrations/001_drop_opn_plaintext.sql** - Created migration file to safely drop the column from existing databases.

**Files Changed:**
- `api/jobs.php`
- `database/schema.sql`
- `database/migrations/001_drop_opn_plaintext.sql` (new file)

---

## Verification Steps

### 1. Database Migration
If you have an existing database with the `opn_number_plaintext` column, run the migration:
```bash
mysql -u root -p encryption_demo_server < database/migrations/001_drop_opn_plaintext.sql
```

Or manually:
```sql
ALTER TABLE jobs DROP COLUMN opn_number_plaintext;
```

### 2. Configuration
Ensure `config/settings.php` exists and is properly configured:
- Copy `config/settings.example.php` to `config/settings.php` if needed
- Generate secure encryption keys using:
  ```bash
  php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
  ```
- Set `SSL_VERIFY_PEER` to `true` for production

### 3. Test Login/Registration
- Test user registration
- Test user login
- Verify sessions are created correctly

### 4. Test Job Creation
- Create a new job via the dashboard
- Verify job is stored with only encrypted data (check database)
- Verify job retrieval works correctly

### 5. Test SSL Verification
- If using valid SSL certificates, verify HTTPS requests work
- If using self-signed certificates in development, you may need to:
  - Set `SSL_VERIFY_PEER` to `false` temporarily (NOT recommended for production)
  - Or properly configure certificate authorities

---

## Summary of Files Changed

**Total: 13 files modified, 1 new file created**

### Modified Files:
1. `includes/auth.php`
2. `api/login.php`
3. `api/register.php`
4. `api/jobs.php`
5. `api/users.php`
6. `api/activity_logs.php`
7. `api/integrity_check.php`
8. `includes/logger.php`
9. `index.php`
10. `register.php`
11. `dashboard.php`
12. `activity_logs.php`
13. `config/settings.example.php`
14. `database/schema.sql`

### New Files:
1. `database/migrations/001_drop_opn_plaintext.sql`

---

## Notes

- All changes maintain backward compatibility where possible
- Database migration is safe to run on existing databases (checks for column existence)
- SSL verification defaults to `true` for security, but can be configured via settings
- No breaking changes to existing functionality
- All security fixes follow minimal patch approach

---

**Next Steps:**
- Apply database migration if needed
- Generate and configure secure encryption keys
- Test all functionality thoroughly
- Review remaining issues from security audit (CSRF protection, input validation, etc.)
