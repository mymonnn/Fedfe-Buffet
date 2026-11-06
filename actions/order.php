<?php
require_once '../connection.php';
require_once '../functions.php';
require_once '../includes/auth.php';
$employee = require_employee($pdo);
check_post();
try {
    $orderId = post_integer('order_id', 1, PHP_INT_MAX);
    $action = $_POST['action'] ?? '';
    if ($action !== 'serve' && $action !== 'cancel' && $action !== 'reset') { throw new RuntimeException('คำสั่งไม่ถูกต้อง'); }
    if ($action === 'reset') { require_manager($employee); }
    $statement = $pdo->prepare('SELECT o.session_id, t.number FROM `ORDER` o JOIN `SESSION` s ON s.id = o.session_id JOIN `TABLE` t ON t.id = s.table_id WHERE o.id = ?');
    $statement->execute([$orderId]);
    $order = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$order) { throw new RuntimeException('ไม่พบออร์เดอร์'); }
    $pdo->beginTransaction();
    lock_visit($pdo, (int) $order['number'], (int) $order['session_id']);
    if ($action === 'reset') {
        $statement = $pdo->prepare("UPDATE `ORDER` SET status = 'PREPARING', completed_at = NULL WHERE id = ? AND status IN ('SERVED','CANCELLED')");
        $statement->execute([$orderId]);
    } else {
        $statement = $pdo->prepare("UPDATE `ORDER` SET status = ?, completed_at = NOW() WHERE id = ? AND status = 'PREPARING'");
        $statement->execute([$action === 'serve' ? 'SERVED' : 'CANCELLED', $orderId]);
    }
    if ($statement->rowCount() !== 1) { throw new RuntimeException('ออร์เดอร์นี้ดำเนินการแล้ว'); }
    $pdo->commit();
    flash('อัปเดตสถานะออร์เดอร์แล้ว');
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    flash($e instanceof PDOException ? 'บันทึกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง' : $e->getMessage(), 'error');
}
redirect_to('staff/orders.php#order-' . ($orderId ?? 0));
