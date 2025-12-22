# Security Policy

## Supported Versions

Security updates are provided for the current release branch. Older versions may receive critical security patches on a case-by-case basis.

## Threat Model

This application is designed to protect against:

1. **Authentication Attacks**
   - Brute force attacks (rate limiting)
   - Session fixation (session regeneration)
   - Password attacks (bcrypt hashing)

2. **Authorization Bypass**
   - Role-based access control (RBAC)
   - Privilege escalation prevention
   - Root user protection

3. **Data Protection**
   - SQL injection (prepared statements)
   - XSS attacks (output escaping)
   - CSRF attacks (token validation)
   - Data tampering (HMAC verification)
   - Data exposure (AES-256 encryption)

4. **Network Security**
   - Man-in-the-middle (TLS with certificate verification)
   - Session hijacking (secure cookies, HTTPS)

## Security Practices

### Configuration Requirements

1. **Encryption Keys**
   - Generate unique keys using `bin2hex(random_bytes(32))`
   - Store keys securely (environment variables or secure key management)
   - Never commit keys to version control

2. **TLS/HTTPS**
   - Always use HTTPS in production
   - Set `SSL_VERIFY_PEER => true` in production
   - Use valid SSL certificates from trusted CAs
   - Self-signed certificates only for development

3. **Database Security**
   - Use strong database passwords
   - Limit database user permissions (principle of least privilege)
   - Keep database credentials in `config/settings.php` (gitignored)
   - Use parameterized queries (already implemented)

4. **Session Security**
   - Sessions use HttpOnly, Secure, and SameSite cookies
   - Session IDs regenerated on login
   - Session expiration enforced (default 1 hour)

5. **Input Validation**
   - All user inputs validated before processing
   - Output escaped to prevent XSS
   - File uploads restricted (if applicable)

### Security Features

- **CSRF Protection**: All state-changing operations require valid CSRF tokens
- **Rate Limiting**: Login (5 attempts/10 min) and Registration (3 attempts/10 min) per IP
- **Password Policy**: Minimum 10 characters (configurable in `includes/validation.php`)
- **Activity Logging**: All security-relevant actions logged (logins, role changes, data access)
- **HMAC Integrity**: Data integrity verified using HMAC-SHA256

## Reporting a Vulnerability

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via:

1. **Email**: Contact the maintainers directly with details
2. **Include**:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact
   - Suggested fix (if any)

You will receive a response within 48 hours. If the issue is confirmed, we will:

1. Acknowledge receipt of your report
2. Investigate and validate the vulnerability
3. Develop a fix (typically within 7-14 days)
4. Release a security update
5. Credit the reporter (if desired)

## Security Updates

Security updates are released as soon as fixes are available. For critical vulnerabilities, updates may be released within 24-48 hours.

Subscribe to security announcements to receive notifications of security updates.

## Best Practices for Users

1. **Keep Software Updated**: Regularly update the application and dependencies
2. **Use Strong Passwords**: Enforce strong password policies
3. **Limit Access**: Use principle of least privilege for user roles
4. **Monitor Logs**: Regularly review activity logs for suspicious activity
5. **Backup Data**: Regular backups of database and encrypted data
6. **Secure Configuration**: Review and harden configuration files
7. **Network Security**: Use firewalls, restrict access to necessary ports
8. **Regular Audits**: Conduct security audits and penetration testing

## Known Limitations

- Rate limiting uses IP addresses (may be bypassed with proxy/VPN)
- Session security relies on proper HTTPS configuration
- Encryption keys must be protected (if keys are compromised, data is at risk)
- Root user protection requires ROOT_USERNAME to be configured

## Security Checklist

Before deploying to production:

- [ ] Change all default passwords
- [ ] Generate unique encryption keys
- [ ] Set `APP_ENV` to `'production'`
- [ ] Configure valid SSL certificates
- [ ] Set `SSL_VERIFY_PEER` to `true`
- [ ] Review and harden `.htaccess` rules
- [ ] Configure secure database credentials
- [ ] Set proper file permissions
- [ ] Enable error logging, disable error display
- [ ] Review activity logs configuration
- [ ] Configure ROOT_USERNAME if using root user protection
- [ ] Test CSRF protection
- [ ] Test rate limiting
- [ ] Verify encryption/HMAC functionality
- [ ] Conduct security audit

## Compliance

This application implements security controls that may help with:

- OWASP Top 10 mitigation
- PCI DSS requirements (with additional hardening)
- GDPR data protection (encryption, access controls)
- SOC 2 controls (access logging, encryption)

However, compliance is the responsibility of the deploying organization.
