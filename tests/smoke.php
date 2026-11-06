<?php
// Run with: C:\xampp\php\php.exe tests\smoke.php
// Run from the project folder. Uses a closed operating table; all changes are rolled back.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once 'functions.php';
require_once 'connection.php';

function smoke_check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$failure = '';
$visitId = 0;
$oldPrice = '';
$savedCarts = $_SESSION['carts'] ?? [];
try {
    smoke_check(decimal_cents('20.50') === 2050, 'Decimal conversion failed');
    smoke_check(cents_decimal(63800) === '638.00', 'Money formatting failed');

    // A navigation draft belongs to one browser visit, not to another table/group.
    $_SESSION['carts'] = [
        101 => [1 => ['quantity' => 1, 'note' => ''], 34 => ['quantity' => 2, 'note' => 'separate plate']],
        102 => [38 => ['quantity' => 1, 'note' => '']]
    ];
    smoke_check(draft_count(101) === 3, 'The draft badge must count portions, not distinct foods');
    smoke_check(draft_count(102) === 1 && draft_count(103) === 0, 'Drafts must remain isolated by visit');
    $_SESSION['carts'] = $savedCarts;

    $pdo->beginTransaction();
    $statement = $pdo->query('SELECT * FROM `TABLE` WHERE number BETWEEN 1 AND 9 AND is_available = 0 ORDER BY number LIMIT 1 FOR UPDATE');
    $table = $statement->fetch(PDO::FETCH_ASSOC);
    smoke_check(is_array($table), 'A table from 1 to 9 must be closed before running this check');
    $tableNumber = (int) $table['number'];
    $statement = $pdo->prepare('SELECT COUNT(*) FROM `SESSION` WHERE table_id = ? AND completed_at IS NULL');
    $statement->execute([$table['id']]);
    smoke_check((int) $statement->fetchColumn() === 0, 'The selected closed table has an unfinished visit');

    $statement = $pdo->query('SELECT id, price FROM `MENU` WHERE id IN (34, 38) ORDER BY id FOR UPDATE');
    $foods = $statement->fetchAll();
    smoke_check(count($foods) === 2, 'Import the project menu before running this check');
    $oldPrice = $foods[0]['price'];
    $pdo->exec('UPDATE `MENU` SET price = 20.00 WHERE id = 34');

    $prefix = 'SMOKE-' . bin2hex(random_bytes(8));
    $statement = $pdo->prepare('INSERT INTO `SESSION` (session_number, table_id, people_amount, buffet_price, created_at) VALUES (?, ?, 2, 299.00, NOW())');
    $statement->execute([$prefix, $table['id']]);
    $visitId = (int) $pdo->lastInsertId();
    $statement = $pdo->prepare('UPDATE `TABLE` SET is_available = 1 WHERE id = ?');
    $statement->execute([$table['id']]);
    $visit = lock_visit($pdo, $tableNumber, $visitId);
    smoke_check((int) $visit['people_amount'] === 2, 'Current visit lookup failed');

    $states = ['SERVED', 'CANCELLED', 'PREPARING'];
    $orders = [];
    $statement = $pdo->prepare('INSERT INTO `ORDER` (number, session_id, created_at, completed_at, status) VALUES (?, ?, NOW(), ?, ?)');
    for ($i = 0; $i < count($states); $i++) {
        $statement->execute([$prefix . '-' . $i, $visitId, $states[$i] === 'PREPARING' ? null : date('Y-m-d H:i:s'), $states[$i]]);
        $orders[] = (int) $pdo->lastInsertId();
    }
    $statement = $pdo->prepare('INSERT INTO `ITEM` (order_id, food_id, quantity, price) VALUES (?, ?, ?, ?)');
    $statement->execute([$orders[0], 34, 2, '40.00']);
    $statement->execute([$orders[1], 38, 1, '89.00']);
    $statement->execute([$orders[2], 38, 1, '89.00']);

    $bill = bill_details($pdo, $visitId);
    smoke_check($bill['buffet_cents'] === 59800, 'Two buffet guests must cost 598 baht');
    smoke_check($bill['extras_cents'] === 4000, 'Only the served ice creams must count');
    smoke_check($bill['total_cents'] === 63800, 'The bill must total 638 baht');
    smoke_check(count($bill['lines']) === 1, 'Cancelled and preparing lines must not be billed');
    smoke_check($bill['pending'] === 1, 'The pending order must block checkout');

    $pdo->exec('UPDATE `MENU` SET price = 99.00 WHERE id = 34');
    $bill = bill_details($pdo, $visitId);
    smoke_check($bill['total_cents'] === 63800, 'A later menu price must not change a captured charge');

    $statement = $pdo->prepare("UPDATE `ORDER` SET status = 'CANCELLED', completed_at = NOW() WHERE id = ?");
    $statement->execute([$orders[2]]);
    $bill = bill_details($pdo, $visitId);
    smoke_check($bill['pending'] === 0 && $bill['total_cents'] === 63800, 'Cancelling the pending order must preserve the bill');

    $staleRejected = false;
    try {
        lock_visit($pdo, $tableNumber, $visitId + 1);
    } catch (RuntimeException $e) {
        $staleRejected = true;
    }
    smoke_check($staleRejected, 'A stale visit ID must be rejected');

    $statement = $pdo->prepare('UPDATE `SESSION` SET total_price = ?, completed_at = NOW() WHERE id = ?');
    $statement->execute([cents_decimal($bill['total_cents']), $visitId]);
    $statement = $pdo->prepare('UPDATE `TABLE` SET is_available = 0 WHERE id = ?');
    $statement->execute([$table['id']]);
    $saved = bill_details($pdo, $visitId);
    smoke_check($saved['visit']['total_price'] === '638.00', 'The finalized amount must be saved exactly');

    $closedRejected = false;
    try {
        lock_visit($pdo, $tableNumber, $visitId);
    } catch (RuntimeException $e) {
        $closedRejected = true;
    }
    smoke_check($closedRejected, 'A closed table must reject further changes');
} catch (Throwable $e) {
    $failure = $e->getMessage();
} finally {
    $_SESSION['carts'] = $savedCarts;
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

if ($failure !== '') {
    fwrite(STDERR, 'FAIL: ' . $failure . PHP_EOL);
    exit(1);
}

$statement = $pdo->prepare('SELECT COUNT(*) FROM `SESSION` WHERE id = ?');
$statement->execute([$visitId]);
$price = $pdo->query('SELECT price FROM `MENU` WHERE id = 34')->fetchColumn();
if ((int) $statement->fetchColumn() !== 0 || $price !== $oldPrice) {
    fwrite(STDERR, 'FAIL: Test changes were not rolled back' . PHP_EOL);
    exit(1);
}
echo 'PASS: Visit-keyed draft counts, 638-baht bill, exclusions, captured price, visit guards.' . PHP_EOL;
echo 'PASS: All test rows and menu edits rolled back.' . PHP_EOL;
