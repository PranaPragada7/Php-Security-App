# Contributing

Thank you for your interest in contributing to this project! This document provides guidelines and instructions for contributing.

## Development Setup

### Prerequisites

- PHP 7.0+ (7.3+ recommended)
- MySQL 5.7+ / MariaDB 10.2+
- Apache with mod_rewrite and mod_ssl
- Git

### Local Setup

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd secure-web-project
   ```

2. **Set up database:**
   ```bash
   mysql -u root -p < database/schema.sql
   mysql -u root -p encryption_demo_server < database/sample_data.sql
   ```

3. **Configure application:**
   ```bash
   cp config/settings.example.php config/settings.php
   # Edit config/settings.php with your database credentials
   # Generate encryption keys: php -r "echo bin2hex(random_bytes(32));"
   ```

4. **Configure SSL (for local HTTPS):**
   ```bash
   cd ssl
   ./generate_certificates.sh  # or .bat on Windows
   ```

5. **Configure Apache:**
   - Use `apache/httpd-vhosts.conf` as a reference
   - Point virtual host to project root
   - Ensure `.htaccess` is enabled

6. **Access application:**
   - Navigate to `https://localhost/`
   - Login with sample credentials (see README.md)

## Making Changes

### Code Style

- Follow PSR-12 coding standards (where applicable)
- Use meaningful variable and function names
- Add comments for complex logic
- Keep functions focused and single-purpose

### Security Considerations

- **Always use prepared statements** for database queries
- **Validate and sanitize all user input**
- **Escape output** to prevent XSS
- **Use CSRF tokens** for state-changing operations
- **Never commit sensitive data** (keys, passwords, credentials)
- **Log security-relevant events** via ActivityLogger

### Testing

Before submitting a pull request:

1. **Test your changes locally:**
   - Verify functionality works as expected
   - Test with different user roles (admin, user, guest)
   - Check for PHP errors/warnings

2. **Security testing:**
   - Verify CSRF protection works
   - Test input validation
   - Check SQL injection prevention
   - Verify XSS protection

3. **Check for regressions:**
   - Ensure existing features still work
   - Test edge cases and error handling

## Pull Request Process

### Before Submitting

1. **Update documentation:**
   - Update README.md if adding features
   - Update SECURITY.md if changing security features
   - Add comments to code if logic is complex

2. **Check your code:**
   - No syntax errors
   - Follows security best practices
   - Includes error handling
   - No hardcoded credentials or keys

3. **Commit messages:**
   - Use clear, descriptive commit messages
   - Reference issue numbers if applicable
   - Example: "Add CSRF protection to login form (#123)"

### Submitting a PR

1. **Create a branch:**
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/bug-description
   ```

2. **Make your changes** and commit:
   ```bash
   git add .
   git commit -m "Description of changes"
   ```

3. **Push to your fork:**
   ```bash
   git push origin feature/your-feature-name
   ```

4. **Open a Pull Request:**
   - Provide a clear description of changes
   - Reference any related issues
   - Include testing steps if applicable
   - List any breaking changes

### PR Review Process

1. Maintainers will review your PR
2. Address any feedback or requested changes
3. Once approved, your PR will be merged

## Areas for Contribution

We welcome contributions in these areas:

- **Security improvements**: Additional security features, vulnerability fixes
- **Documentation**: Improving README, adding examples, clarifying instructions
- **Code quality**: Refactoring, optimization, bug fixes
- **Testing**: Adding automated tests, improving test coverage
- **Features**: New functionality (discuss in issues first)
- **Performance**: Database optimization, caching improvements

## Reporting Issues

### Bug Reports

Include:
- Description of the bug
- Steps to reproduce
- Expected vs actual behavior
- PHP version, MySQL version
- Relevant error messages/logs
- Screenshots if applicable

### Feature Requests

Include:
- Use case / problem being solved
- Proposed solution
- Alternatives considered
- Impact on existing features

## Code of Conduct

- Be respectful and professional
- Focus on constructive feedback
- Help others learn and improve
- Follow security best practices

## Questions?

Open an issue for questions, or contact the maintainers directly.

Thank you for contributing!
