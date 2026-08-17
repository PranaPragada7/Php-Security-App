# CipherDesk

[![CI](https://github.com/PranaPragada7/Php-Security-App/actions/workflows/ci.yml/badge.svg)](https://github.com/PranaPragada7/Php-Security-App/actions/workflows/ci.yml)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL 8](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)

CipherDesk is a PHP and MySQL portal for demonstrating secure handling of
sensitive operational records. It combines role-based authorization, encrypted
database fields, integrity verification, session management, and an audit trail
in a small application that can be run locally with Docker.

> This is an educational security project, not an independently audited
> production system.

## What it demonstrates

- Password hashing with PHP's current `PASSWORD_DEFAULT` algorithm
- Server-side sessions backed by hashed application tokens
- `admin`, `user`, and `guest` permissions enforced in the UI and API
- AES-256-CBC encryption for protected record values
- SHA-256 HMAC verification for record and user-data integrity
- CSRF validation on every state-changing browser request
- Persistent rate limiting for login and registration
- Parameterized PDO queries and server-side input validation
- Immediate session revocation on logout
- Administrative user management and security-event auditing

Public registration always creates a standard `user` account. Administrative
access cannot be selected from the registration form or supplied through the
registration API.

## Architecture

```text
Browser
  └── Apache + PHP pages
        ├── same-origin JSON API
        ├── authentication / RBAC / CSRF services
        └── PDO
              └── MySQL
                    ├── hashed passwords and session tokens
                    ├── encrypted record values
                    └── audit events
```

Application credentials remain in the server-side PHP session. Browser
JavaScript uses the `HttpOnly` session cookie and a CSRF token; database session
tokens are not embedded in rendered pages.

## Run with Docker

Requirements: Docker Desktop with Docker Compose.

```powershell
git clone https://github.com/PranaPragada7/Php-Security-App.git
cd Php-Security-App
docker compose up --detach --build
```

Create the first administrator without committing a password:

```powershell
docker compose exec `
  -e ADMIN_PASSWORD="choose-a-long-unique-password" `
  -e ADMIN_EMAIL="admin@example.test" `
  app php scripts/create_admin.php
```

Open [http://localhost:8080](http://localhost:8080). New accounts created from
the registration page receive the standard user role.

Stop the environment and remove its local database volume with:

```powershell
docker compose down --volumes
```

## Configuration

The application reads configuration from environment variables. A local
`config/settings.php` may override the environment-backed example, but it is
ignored by Git.

| Variable | Purpose | Development default |
|---|---|---|
| `APP_ENV` | `development`, `test`, or `production` | `development` |
| `BASE_URL` | Public application origin | `http://127.0.0.1:8080` |
| `API_BASE_URL` | Internal same-host API origin | `${BASE_URL}/api` |
| `DB_HOST` | MySQL hostname | `127.0.0.1` |
| `DB_DATABASE` | Database name | `cipherdesk` |
| `DB_USERNAME` | Database user | `cipherdesk` |
| `DB_PASSWORD` | Database password | empty |
| `AES_KEY` | 64-character hexadecimal encryption key | development-only key |
| `HMAC_SECRET_KEY` | 64-character hexadecimal integrity key | development-only key |
| `ROOT_USERNAME` | Administrator allowed to change roles | `admin` |
| `HTTPS_ENABLED` | Require HTTPS redirects | enabled in production |
| `TRUSTED_PROXIES` | Comma-separated proxy IP addresses | empty |

Production mode refuses to start without valid AES and HMAC secrets. Generate
each secret independently:

```powershell
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

## Database setup without Docker

1. Create the schema with `database/schema.sql`.
2. Configure the database environment variables.
3. Create an administrator with `php scripts/create_admin.php` while supplying
   `ADMIN_PASSWORD` and `ADMIN_EMAIL`.
4. Serve the repository through Apache or another PHP-capable web server.

No default credentials or reusable passwords are stored in the repository.

## Validation

Run the offline unit and security checks:

```powershell
php tests/run.php
```

Check PHP syntax:

```powershell
Get-ChildItem -Recurse -Filter *.php |
  ForEach-Object { php -l $_.FullName }
```

GitHub Actions additionally builds the complete Docker environment and tests
database health, registration, login, protected routing, security headers, and
web-server access restrictions.

## Repository layout

```text
api/          JSON endpoints and health check
apache/       Apache configuration examples
assets/       Shared interface styling
config/       Environment and PDO configuration
database/     Schema and forward migrations
includes/     Security and application helpers
scripts/      Administrator provisioning
ssl/          Local certificate generation scripts
tests/        Offline checks and HTTP smoke test
```

## Security notes

- The development secrets in `compose.yaml` are for disposable local data only.
- Use TLS from a trusted certificate authority in production.
- Run `database/migrations/002_hash_session_tokens.sql` when upgrading an
  existing installation; it intentionally revokes existing sessions. Then run
  `php scripts/rehash_integrity.php` with the existing encryption and HMAC keys
  to upgrade stored integrity signatures.
- Have a qualified security professional review the system before handling real
  confidential data.
