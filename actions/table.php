<?php
require_once '../connection.php';
require_once '../functions.php';
require_once '../includes/auth.php';
require_once '../includes/table-card.php';
$ajax = ($_POST['ajax'] ?? '') === '1' || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$employee = require_employee($pdo, $ajax);
check_post();
$visitId = filter_var($_POST['visit_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$tableNumber = 0;
$action = $_POST['action'] ?? '';
try {
    $tableNumber = post_integer('table_number', 1, 9);
    $pdo->beginTransaction();
    if ($action === 'open') {
        $people = post_integer('people_amount', 1, 99);
        $statement = $pdo->prepare('SELECT * FROM `TABLE` WHERE number = ? FOR UPDATE');
        $statement->execute([$tableNumber]);
        $table = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$table || $table['is_available']) { throw new RuntimeException('โต๊ะนี้เปิดอยู่แล้วหรือไม่พบโต๊ะ'); }
        $statement = $pdo->prepare('SELECT id FROM `SESSION` WHERE table_id = ? AND completed_at IS NULL');
        $statement->execute([$table['id']]);
        if ($statement->fetch(PDO::FETCH_ASSOC)) { throw new RuntimeException('โต๊ะนี้มีรอบบริการค้างอยู่'); }
        $statement = $pdo->prepare('SELECT COUNT(*) FROM `SESSION` WHERE table_id = ?');
        $statement->execute([$table['id']]);
        $number = 'T' . $tableNumber . '-' . ((int) $statement->fetchColumn() + 1);
        $free_guests = $people >= 6 ? 1 : 0;
        $statement = $pdo->prepare('INSERT INTO `SESSION` (session_number, table_id, people_amount, free_guests, buffet_price, created_at) VALUES (?, ?, ?, ?, 299.00, NOW())');
        $statement->execute([$number, $table['id'], $people, $free_guests]);
        $statement = $pdo->prepare('UPDATE `TABLE` SET is_available = 1 WHERE id = ?');
        $statement->execute([$table['id']]);
        $message = 'เปิดโต๊ะ ' . $tableNumber . ' แล้ว';
    } else {
        $visitId = post_integer('visit_id', 1, PHP_INT_MAX);
        $visit = lock_visit($pdo, $tableNumber, $visitId);
        if ($action === 'guests') {
            $people = post_integer('people_amount', 1, 99);
            $free_guests = $people >= 6 ? 1 : 0;
            $statement = $pdo->prepare('UPDATE `SESSION` SET people_amount = ?, free_guests = ? WHERE id = ?');
            $statement->execute([$people, $free_guests, $visitId]);
            $message = 'แก้ไขจำนวนลูกค้าแล้ว';
        } elseif ($action === 'ack') {
            $statement = $pdo->prepare('UPDATE `SESSION` SET need_help = 0 WHERE id = ?');
            $statement->execute([$visitId]);
            $message = 'รับทราบการเรียกพนักงานแล้ว';
        } elseif ($action === 'resume') {
            $statement = $pdo->prepare('UPDATE `SESSION` SET request_bill = 0 WHERE id = ?');
            $statement->execute([$visitId]);
            $message = 'ยกเลิกการขอเช็คบิลแล้ว ลูกค้าสั่งอาหารต่อได้';
        } elseif ($action === 'checkout') {
            $bill = bill_details($pdo, $visitId);
            if ($bill['pending'] > 0) { throw new RuntimeException('ยังมีออร์เดอร์รอจัดเตรียม กรุณาเสิร์ฟหรือยกเลิกก่อนปิดโต๊ะ'); }
            $expected = post_integer('expected_total_cents', 0, PHP_INT_MAX);
            if ($expected !== $bill['total_cents']) { throw new RuntimeException('ยอดบิลมีการเปลี่ยนแปลง กรุณาตรวจสอบยอดล่าสุดและรับชำระให้ครบก่อนยืนยันอีกครั้ง'); }
            $statement = $pdo->prepare('UPDATE `SESSION` SET total_price = ?, completed_at = NOW(), need_help = 0, request_bill = 0 WHERE id = ?');
            $statement->execute([cents_decimal($bill['total_cents']), $visitId]);
            $statement = $pdo->prepare('UPDATE `TABLE` SET is_available = 0 WHERE id = ?');
            $statement->execute([$visit['table_id']]);
            $message = 'รับชำระเงินและปิดโต๊ะแล้ว';
        } else { throw new RuntimeException('คำสั่งไม่ถูกต้อง'); }
    }
    $pdo->commit();
    if ($ajax) {
        $table = table_row($pdo, $tableNumber);
        if (!$table) { throw new RuntimeException('ไม่พบโต๊ะที่ต้องการ'); }
        render_table_card($table);
        exit;
    }
    flash($message);
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $message = $e instanceof PDOException ? 'บันทึกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง' : $e->getMessage();
    if ($ajax) { http_response_code(409); echo h($message); exit; }
    flash($message, 'error');
}
redirect_to(($action === 'checkout' || $action === 'resume') && $visitId > 0 ? 'staff/bill.php?visit=' . $visitId : 'staff/tables.php#table-' . $tableNumber);
