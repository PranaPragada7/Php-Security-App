# Secure Web Application

A secure PHP/MySQL web application demonstrating enterprise-grade security practices including AES-256 encryption, HMAC data integrity verification, role-based access control (RBAC), and comprehensive audit logging.

## Features

- **Authentication & Authorization**
  - Secure password hashing with `password_hash()`
  - Session-based authentication with token validation
  - Role-Based Access Control (RBAC): admin, user, guest roles
  - CSRF protection on all forms and API endpoints
  - Rate limiting for login and registration (brute force protection)
  
- **Data Security**
  - AES-256-CBC encryption for sensitive data at rest
  - HMAC-SHA256 integrity verification for data tampering detection
  - TLS/HTTPS enforcement
  - Secure session management (HttpOnly, Secure, SameSite cookies)
  
- **Security Features**
  - Input validation and sanitization
  - SQL injection prevention (prepared statements)
  - XSS protection (output escaping)
  - Activity logging and audit trails
  - Session regeneration on login (session fixation prevention)

## Requirements

- PHP 7.0+ (PHP 7.3+ recommended for SameSite cookie support)
- MySQL 5.7+ / MariaDB 10.2+
- Apache with mod_rewrite and mod_ssl enabled
- OpenSSL extension for PHP
- PDO MySQL extension

## Installation

### 1. Clone Repository

```bash
git clone <repository-url>
cd secure-web-project
```

### 2. Configure Database

Import the database schema:

```bash
mysql -u root -p < database/schema.sql
```

Optionally import sample data:

```bash
mysql -u root -p encryption_demo_server < database/sample_data.sql
```

### 3. Configure Application

Copy the example configuration file:

```bash
cp config/settings.example.php config/settings.php
```

Edit `config/settings.php` and configure:

1. **Database credentials:**
   ```php
   $db_config = [
       'host' => 'localhost',
       'database' => 'encryption_demo_server',
       'username' => 'your_db_user',
       'password' => 'your_db_password',
   ];
   ```

2. **Generate encryption keys:**
   ```bash
   php -r "echo 'AES_KEY: ', bin2hex(random_bytes(32)), PHP_EOL;"
   php -r "echo 'HMAC_SECRET_KEY: ', bin2hex(random_bytes(32)), PHP_EOL;"
   ```
   
   Update in `config/settings.php`:
   ```php
   define('AES_KEY', '<generated-64-hex-chars>');
   define('HMAC_SECRET_KEY', '<generated-64-hex-chars>');
   ```

3. **Set environment:**
   ```php
   define('APP_ENV', 'production'); // or 'development'
   ```

### 4. Configure Apache

Copy and customize `apache/httpd-vhosts.conf` to your Apache configuration, or configure your virtual host to point to the project root.

Ensure `.htaccess` is enabled and HTTPS is configured.

### 5. SSL/TLS Setup

For local development, you can use self-signed certificates (see `ssl/` directory scripts), but **production must use valid SSL certificates from a trusted CA**.

**Important:** The application enforces SSL verification by default. If using self-signed certificates:
- Set `SSL_VERIFY_PEER => false` in `config/settings.php` (development only!)
- Or properly configure your local CA bundle

For production, ensure `SSL_VERIFY_PEER => true` (default).

### 6. Apply Migrations

If you have existing data, apply migrations:

```bash
mysql -u root -p encryption_demo_server < database/migrations/001_drop_opn_plaintext.sql
```

## Usage

### Accessing the Application

- Login page: `https://localhost/`
- Registration: `https://localhost/register.php`
- Dashboard: `https://localhost/dashboard.php` (requires login)

### Default Credentials

If you imported `sample_data.sql`:
- Username: `admin` / Password: `password123`
- Username: `user1` / Password: `password123`
- Username: `guest1` / Password: `password123`

**WARNING: Change these passwords immediately in production!**

## Security Configuration

### Production Checklist

- [ ] Change all default passwords
- [ ] Generate unique encryption keys (AES_KEY, HMAC_SECRET_KEY)
- [ ] Set `APP_ENV` to `'production'`
- [ ] Set `SSL_VERIFY_PEER` to `true`
- [ ] Use valid SSL certificates from trusted CA
- [ ] Configure secure database credentials
- [ ] Review `.htaccess` security headers
- [ ] Set proper file permissions (config/settings.php should be 600)
- [ ] Enable error logging, disable display_errors in production
- [ ] Configure ROOT_USERNAME in settings.php if using root user protection

### Security Features

- **CSRF Protection**: All forms and state-changing API endpoints require CSRF tokens
- **Rate Limiting**: Login (5 attempts/10 min), Registration (3 attempts/10 min) per IP
- **Session Security**: HttpOnly, Secure (HTTPS), SameSite=Lax cookies
- **Input Validation**: Username, password, job names, and other inputs validated
- **Error Handling**: Production mode hides detailed errors from users

## Architecture

```
├── api/              # REST API endpoints
├── config/           # Configuration files
├── database/         # Schema, migrations, sample data
├── includes/         # Core classes and helpers
│   ├── auth.php      # Authentication
│   ├── csrf.php      # CSRF protection
│   ├── crypt.php     # AES encryption
│   ├── hmac.php      # HMAC integrity
│   ├── logger.php    # Activity logging
│   ├── rate_limit.php # Rate limiting
│   ├── rbac.php      # Role-based access control
│   ├── session.php   # Secure session handling
│   └── validation.php # Input validation
├── index.php         # Login page
├── register.php      # Registration page
├── dashboard.php     # Main application interface
└── manage_users.php  # User management (admin)
```

## API Endpoints

All API endpoints require authentication via `X-Session-ID` and `X-Token` headers (except login/register which create sessions).

- `POST /api/login.php` - Authenticate user
- `POST /api/register.php` - Register new user
- `GET /api/jobs.php` - List jobs (role-based filtering)
- `POST /api/jobs.php` - Create new job
- `GET /api/users.php` - List users (admin only)
- `PUT /api/users.php` - Update user role (root only)
- `DELETE /api/users.php` - Delete user (admin, not self/root)
- `GET /api/activity_logs.php` - View audit logs (admin only)
- `GET /api/integrity_check.php` - HMAC integrity check (admin only)

## Development

### Running Locally

1. Start MySQL and Apache
2. Configure database and settings.php
3. Generate self-signed certificate (if needed):
   ```bash
   cd ssl
   ./generate_certificates.sh  # or .bat on Windows
   ```
4. Access via `https://localhost/`

### Testing

- Test login/registration functionality
- Verify CSRF tokens are required
- Test rate limiting by attempting multiple failed logins
- Verify encryption/HMAC on job data
- Check activity logs for audit trail

## Troubleshooting

**SSL Verification Errors:**
- Ensure valid certificates or set `SSL_VERIFY_PEER => false` (dev only)
- Check certificate paths and permissions

**Database Connection Errors:**
- Verify credentials in `config/settings.php`
- Ensure database exists and user has proper permissions
- Check MySQL is running

**Session Issues:**
- Verify `session.save_path` is writable
- Check cookie settings in `includes/session.php`
- Ensure HTTPS is properly configured for Secure flag

## License

MIT License - see LICENSE file for details

## Security

For security vulnerabilities, see SECURITY.md or report via email to the maintainers.
