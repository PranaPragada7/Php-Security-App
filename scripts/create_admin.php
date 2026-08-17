<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/hmac.php';
require_once __DIR__ . '/../includes/validation.php';

$username = (string) app_env('ADMIN_USERNAME', ROOT_USERNAME);
$password = (string) app_env('ADMIN_PASSWORD', '');
$email = (string) app_env('ADMIN_EMAIL', 'admin@example.test');
$name = (string) app_env('ADMIN_NAME', 'System Administrator');

foreach ([
    Validator::validateUsername($username),
    Validator::validatePassword($password),
    Validator::validateEmail($email),
    Validator::validateName($name),
] as $validation) {
    if (!$validation['valid']) {
        fwrite(STDERR, $validation['error'] . PHP_EOL);
        exit(1);
    }
}

$database = getDB();
$statement = $database->prepare(
    'INSERT INTO users (username, password, email, name, role, data_hmac)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE password = VALUES(password), email = VALUES(email),
         name = VALUES(name), role = VALUES(role), data_hmac = VALUES(data_hmac)'
);
$statement->execute([
    $username,
    password_hash($password, PASSWORD_DEFAULT),
    $email,
    $name,
    'admin',
    HMAC::generateForUser($username, $email, $name),
]);

fwrite(STDOUT, 'Administrator account is ready.' . PHP_EOL);
