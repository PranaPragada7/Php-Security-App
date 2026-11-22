<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/rate_limit.php';

/**
 * Offline transaction-contract tests. A fake PDO verifies the locking protocol
 * and rollback paths; MySQL integration is still needed to test real contention.
 */
final class RateLimitTestPDO extends PDO
{
    public ?array $record = null;
    public array $events = [];
    public ?string $failAt = null;
    private ?array $snapshot = null;
    private bool $transaction = false;
    public bool $rowLocked = false;

    public function __construct() {}

    public function exec(string $statement): int|false { return 0; }

    public function beginTransaction(): bool
    {
        if ($this->transaction) {
            throw new PDOException('Transaction already active');
        }
        $this->snapshot = $this->record;
        $this->transaction = true;
        $this->events[] = 'begin';
        return true;
    }

    public function inTransaction(): bool { return $this->transaction; }

    public function commit(): bool
    {
        $this->failIf('commit');
        check($this->transaction, 'rate limit commits an active transaction');
        $this->events[] = 'commit';
        $this->transaction = false;
        $this->rowLocked = false;
        return true;
    }

    public function rollBack(): bool
    {
        $this->record = $this->snapshot;
        $this->events[] = 'rollback';
        $this->transaction = false;
        $this->rowLocked = false;
        return true;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new RateLimitTestStatement($this, preg_replace('/\s+/', ' ', trim($query)));
    }

    public function failIf(string $stage): void
    {
        if ($this->failAt === $stage) {
            throw new PDOException('Simulated ' . $stage . ' failure');
        }
    }
}

final class RateLimitTestStatement extends PDOStatement
{
    private array|false $result = false;

    public function __construct(private RateLimitTestPDO $db, private string $sql) {}

    public function execute(?array $params = null): bool
    {
        if (str_starts_with($this->sql, 'DELETE')) {
            $this->db->events[] = 'delete';
            $this->db->record = null;
            return true;
        }
        check($this->db->inTransaction(), 'rate limit database decisions occur in a transaction');
        if (str_starts_with($this->sql, 'INSERT')) {
            $this->db->failIf('insert');
            check(str_contains($this->sql, 'ON DUPLICATE KEY UPDATE'), 'first-attempt materialization handles an existing row');
            check(str_contains($this->sql, 'VALUES (?, ?, 0,'), 'materialization does not consume an extra attempt');
            $this->db->rowLocked = true;
            $this->db->events[] = 'materialize';
            $this->db->record ??= ['attempts' => 0, 'first_attempt_at' => date('Y-m-d H:i:s')];
        } elseif (str_starts_with($this->sql, 'SELECT')) {
            $this->db->failIf('select');
            check($this->db->rowLocked, 'row is materialized before reading its counter');
            check(str_contains($this->sql, 'FOR UPDATE'), 'counter read requests an exclusive row lock');
            $this->db->events[] = 'read-locked';
            $this->result = $this->db->failAt === 'missing' ? false : $this->db->record;
        } elseif (str_starts_with($this->sql, 'UPDATE')) {
            $this->db->failIf('update');
            check($this->db->rowLocked, 'counter updates retain the row lock');
            $this->db->events[] = 'update';
            if (str_contains($this->sql, 'SET attempts = 1,')) {
                $this->db->record = ['attempts' => 1, 'first_attempt_at' => date('Y-m-d H:i:s')];
            } else {
                $this->db->record['attempts']++;
            }
        } else {
            throw new RuntimeException('Unexpected rate limit SQL');
        }
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->result;
    }
}

$rateLimitDb = new RateLimitTestPDO();
$rateLimiter = new RateLimiter($rateLimitDb);
for ($attempt = 1; $attempt <= 5; $attempt++) {
    $result = $rateLimiter->checkLimit('login', 5, 600);
    check($result['allowed'] && $result['remaining'] === 5 - $attempt, 'allowed attempts consume exactly one slot');
    check(!$rateLimitDb->inTransaction(), 'allowed attempt releases its transaction');
}
$rateLimitDb->events = [];
$result = $rateLimiter->checkLimit('login', 5, 600);
check(!$result['allowed'] && $result['remaining'] === 0, 'sixth attempt is denied');
check($rateLimitDb->record['attempts'] === 5, 'denied attempt leaves the counter at its maximum');
check($rateLimitDb->events === ['begin', 'materialize', 'read-locked', 'commit'], 'denied attempt also releases its lock');

$rateLimitDb->record['first_attempt_at'] = date('Y-m-d H:i:s', time() - 601);
$rateLimitDb->events = [];
$result = $rateLimiter->checkLimit('login', 5, 600);
check($result['allowed'] && $result['remaining'] === 4, 'expired window starts with exactly one attempt');
check($rateLimitDb->events === ['begin', 'materialize', 'read-locked', 'update', 'commit'], 'expiry resets in place under the row lock');
$rateLimiter->resetLimit('login');
check($rateLimitDb->record === null, 'explicit reset deletes the rate limit row');

foreach (['insert', 'select', 'missing', 'update', 'commit'] as $failureStage) {
    $rateLimitDb = new RateLimitTestPDO();
    $rateLimitDb->failAt = $failureStage;
    $result = (new RateLimiter($rateLimitDb))->checkLimit('login', 5, 600);
    check(!$result['allowed'] && $result['available'] === false, 'database failure denies authentication');
    check(!$rateLimitDb->inTransaction(), 'database failure releases the transaction');
    check($rateLimitDb->record === null, 'rollback removes an uncommitted first-attempt row');
    check(in_array('rollback', $rateLimitDb->events, true), 'database failure executes rollback');
}
