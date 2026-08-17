<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/crypt.php';
require_once __DIR__ . '/../includes/hmac.php';

$database = getDB();
$database->beginTransaction();

try {
    $users = $database->query('SELECT userid, username, email, name FROM users')->fetchAll();
    $updateUser = $database->prepare('UPDATE users SET data_hmac = ? WHERE userid = ?');
    foreach ($users as $user) {
        $updateUser->execute([
            HMAC::generateForUser($user['username'], $user['email'], $user['name']),
            $user['userid'],
        ]);
    }

    $jobs = $database->query('SELECT jobid, job_name, opn_number_encrypted FROM jobs')->fetchAll();
    $updateJob = $database->prepare('UPDATE jobs SET data_hmac = ? WHERE jobid = ?');
    foreach ($jobs as $job) {
        $opnNumber = AES::_decrypt($job['opn_number_encrypted']);
        $updateJob->execute([
            HMAC::generateForJob($job['job_name'], $opnNumber),
            $job['jobid'],
        ]);
    }

    $database->commit();
    fwrite(STDOUT, sprintf(
        "Rehashed %d user records and %d job records.%s",
        count($users),
        count($jobs),
        PHP_EOL
    ));
} catch (Throwable $error) {
    $database->rollBack();
    fwrite(STDERR, 'Integrity rehash failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
