# Php-Security-App — Secure PHP/MySQL Portal (RBAC + Encryption + Audit Logs)

A PHP/MySQL web application hardened with **security-first controls**: **RBAC**, **CSRF protection**, **rate limiting**, **session hardening**, **TLS verification**, and **AES encryption + HMAC integrity** for sensitive fields.

---

## Key Features

### Application
- Secure **Sign In / Register** flow with server-side validation
- **Role-Based Access Control (RBAC)** with `admin`, `user`, and `guest` roles
- Secure dashboard workflow to submit and view records (“jobs”)
- **Admin area**: user management + system audit logs

### Security Controls (Implemented)
- **RBAC enforcement** across UI + API routes (permission checks by role)
- **CSRF protection** for state-changing requests (form + API validation)
- **Rate limiting** for login/registration to slow brute-force attempts
- **Session hardening** (secure session handling + session ID regeneration after login)
- **HTTPS enforcement + security headers** via `.htaccess` (HSTS, clickjacking/XSS hardening, no sniffing)
- **AES encryption for sensitive values at rest** (encrypted storage for protected fields)
- **HMAC integrity verification** to detect database tampering + trigger alert messaging
- **Audit logging** for security events (login, submissions, role changes, integrity failures, etc.)

---

## Tech Stack
- **Backend:** PHP (PDO prepared statements), cURL (frontend → API calls)
- **Database:** MySQL
- **Web Server:** Apache (uses `.htaccess` for rewrite + security headers)
- **Crypto:** OpenSSL (AES encryption), HMAC (SHA-256 style integrity)
- **UI:** HTML/CSS (Tailwind-style classes where applicable)

---

## Project Structure

High-level layout:
- `api/` — JSON API endpoints (login, register, jobs, users, activity logs)
- `config/` — database connection + app configuration
- `includes/` — auth, RBAC, session utilities, validation, crypto helpers, logging, rate limiting
- `database/` — schema/init scripts
- `apache/` — server config (if provided for local SSL)
- `ssl/` — local SSL material (if provided)

Root pages:
- `index.php` — Sign in (calls `api/login.php` via HTTPS)
- `register.php` — Create account (calls `api/register.php` via HTTPS)
- `dashboard.php` — Main portal (job submission + integrity view)
- `manage_users.php` — Admin user management
- `activity_logs.php` — Admin audit log viewer
- `.htaccess` — HTTPS redirect + security headers

---

## How It Works (Architecture)

1. **UI pages** (`index.php`, `register.php`, `dashboard.php`) call backend **API endpoints** over **HTTPS** using `cURL`.
2. API returns a `session_id` + `token`, stored in session and attached to subsequent requests via headers:
   - `X-Session-ID`
   - `X-Token`
   - `X-CSRF-Token`
3. API verifies:
   - session validity + token
   - role permissions (RBAC)
   - CSRF token (for write operations)
4. Sensitive values are:
   - **encrypted before storage**
   - verified for **integrity via HMAC** on retrieval to detect tampering

---

## Getting Started (Local Setup)

### Prerequisites
- PHP (with `openssl` enabled)
- Apache (recommended due to `.htaccess`)
- MySQL
- Ability to run HTTPS locally (self-signed cert is OK for demo)

### 1) Clone
```bash
git clone https://github.com/PranaPragada7/Php-Security-App.git
cd Php-Security-App
