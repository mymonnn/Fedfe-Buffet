<?php
require_once '../connection.php';
require_once '../functions.php';
check_post();
$tableNumber = filter_var($_POST['table_number'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$visitId = filter_var($_POST['visit_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
$action = $_POST['action'] ?? '';
$ajax = ($_POST['ajax'] ?? '') === '1' || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
$returnPage = $action === 'order' || $action === 'cart' ? 'cart.php' : 'orders.php';
try {
    $tableNumber = post_integer('table_number', 1, 9);
    $visitId = post_integer('visit_id', 1, PHP_INT_MAX);
    $pdo->beginTransaction();
    $visit = lock_visit($pdo, $tableNumber, $visitId);
    $_SESSION['customer_visits'][$visitId] = $tableNumber;
    if ($action === 'cart') {
        $foodId = post_integer('food_id', 1, PHP_INT_MAX);
        $quantity = post_integer('quantity', 0, 99);
        $note = $_POST['note'] ?? ($_SESSION['carts'][$visitId][$foodId]['note'] ?? '');
        if (!is_string($note) || mb_strlen($note) > 200) { throw new RuntimeException('หมายเหตุต้องไม่เกิน 200 ตัวอักษร'); }
        if ($visit['request_bill'] && $quantity > 0) { throw new RuntimeException('ขอเช็คบิลแล้ว กรุณาแจ้งพนักงานหากต้องการสั่งต่อ'); }
        $statement = $pdo->prepare('SELECT * FROM `MENU` WHERE id = ?');
        $statement->execute([$foodId]);
        $food = $statement->fetch(PDO::FETCH_ASSOC);
        $oldQuantity = $_SESSION['carts'][$visitId][$foodId]['quantity'] ?? 0;
        if ($quantity > 0 && (!$food || (($food['is_deleted'] || !$food['is_available']) && $quantity > $oldQuantity))) {
            throw new RuntimeException(($food ? $food['name'] : 'อาหารรายการนี้') . ' หมดชั่วคราว กรุณาเลือกรายการอื่น');
        }
        $pdo->commit();
        if ($quantity === 0) { unset($_SESSION['carts'][$visitId][$foodId]); }
        else { $_SESSION['carts'][$visitId][$foodId] = ['quantity' => $quantity, 'note' => trim($note)]; }
        if ($ajax) {
            echo '<span data-cart-count="' . draft_count($visitId) . '" data-quantity="' . $quantity . '">บันทึกแล้ว</span>';
            exit;
        }
        flash('บันทึกรายการที่เลือกแล้ว');
    } elseif ($action === 'help') {
        $statement = $pdo->prepare('UPDATE `SESSION` SET need_help = 1 WHERE id = ?');
        $statement->execute([$visitId]);
        $pdo->commit();
        $message = 'เรียกพนักงานแล้ว กรุณารอสักครู่';
        if ($ajax) { echo h($message); exit; }
        flash($message);
    } elseif ($action === 'bill') {
        if (draft_count($visitId) > 0 && ($_POST['discard_cart'] ?? '') !== '1') {
            throw new RuntimeException('ยังมีอาหารในตะกร้าที่ยังไม่ส่ง กรุณาส่งรายการหรือนำออกก่อนเช็คบิล');
        }
        $statement = $pdo->prepare('UPDATE `SESSION` SET request_bill = 1 WHERE id = ?');
        $statement->execute([$visitId]);
        $pdo->commit();
        unset($_SESSION['carts'][$visitId]);
        flash('แจ้งเช็คบิลแล้ว กรุณารอพนักงาน');
    } elseif ($action === 'order') {
        if ($visit['request_bill']) { throw new RuntimeException('โต๊ะนี้ขอเช็คบิลแล้ว กรุณาแจ้งพนักงานหากต้องการสั่งต่อ'); }
        $token = $_POST['order_token'] ?? '';
        if (!is_string($token) || ($_SESSION['order_tokens'][$token] ?? null) !== $visitId) {
            throw new RuntimeException('รายการนี้ส่งแล้วหรือแบบฟอร์มหมดอายุ กรุณาตรวจสอบรายการที่สั่ง');
        }
        $draft = $_SESSION['carts'][$visitId] ?? [];
        if (count($draft) < 1) { throw new RuntimeException('กรุณาเลือกอาหารก่อนยืนยันออร์เดอร์'); }
        $prices = $_POST['expected_prices'] ?? [];
        $quantities = $_POST['expected_quantities'] ?? [];
        $notes = $_POST['expected_notes'] ?? [];
        if (!is_array($prices) || !is_array($quantities) || !is_array($notes) || count($prices) !== count($draft)) {
            throw new RuntimeException('รายการในตะกร้ามีการเปลี่ยนแปลง กรุณาตรวจสอบและยืนยันอีกครั้ง');
        }
        $ids = array_keys($draft);
        sort($ids); // ล็อกอาหารตามลำดับเดียวกันเมื่อมีหลายโต๊ะสั่งพร้อมกัน
        $lines = [];
        for ($i = 0; $i < count($ids); $i++) {
            $statement = $pdo->prepare('SELECT * FROM `MENU` WHERE id = ? LOCK IN SHARE MODE');
            $statement->execute([$ids[$i]]);
            $food = $statement->fetch(PDO::FETCH_ASSOC);
            if (!$food || $food['is_deleted'] || !$food['is_available']) {
                throw new RuntimeException(($food ? $food['name'] : 'อาหารบางรายการ') . ' หมดชั่วคราว กรุณานำออกจากตะกร้าก่อนส่ง');
            }
            if (!is_string($prices[$ids[$i]] ?? null) || decimal_cents($prices[$ids[$i]]) !== decimal_cents($food['price'])) {
                throw new RuntimeException('ราคา ' . $food['name'] . ' เปลี่ยนแล้ว กรุณาตรวจสอบราคาในตะกร้าและยืนยันอีกครั้ง');
            }
            if (filter_var($quantities[$ids[$i]] ?? null, FILTER_VALIDATE_INT) !== $draft[$ids[$i]]['quantity'] || !is_string($notes[$ids[$i]] ?? null) || trim($notes[$ids[$i]]) !== $draft[$ids[$i]]['note']) {
                throw new RuntimeException('รายการในตะกร้ามีการเปลี่ยนแปลง กรุณาตรวจสอบและยืนยันอีกครั้ง');
            }
            $lines[] = ['food_id' => $ids[$i], 'quantity' => $draft[$ids[$i]]['quantity'], 'note' => $draft[$ids[$i]]['note'], 'price' => cents_decimal(decimal_cents($food['price']) * $draft[$ids[$i]]['quantity'])];
        }
        $statement = $pdo->prepare('SELECT COUNT(*) FROM `ORDER` WHERE session_id = ?');
        $statement->execute([$visitId]);
        $number = 'V' . $visitId . '-' . ((int) $statement->fetchColumn() + 1);
        $statement = $pdo->prepare("INSERT INTO `ORDER` (number, session_id, created_at, status) VALUES (?, ?, NOW(), 'PREPARING')");
        $statement->execute([$number, $visitId]);
        $orderId = $pdo->lastInsertId();
        $statement = $pdo->prepare('INSERT INTO `ITEM` (order_id, food_id, quantity, note, price) VALUES (?, ?, ?, ?, ?)');
        for ($i = 0; $i < count($lines); $i++) {
            $statement->execute([$orderId, $lines[$i]['food_id'], $lines[$i]['quantity'], $lines[$i]['note'], $lines[$i]['price']]);
        }
        $pdo->commit();
        unset($_SESSION['order_tokens'][$token], $_SESSION['carts'][$visitId]);
        flash('ส่งรายการเข้าครัวแล้ว');
        $returnPage = 'orders.php';
    } else { throw new RuntimeException('คำสั่งไม่ถูกต้อง'); }
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    $database_error = $e instanceof PDOException;
    $message = $database_error ? 'ระบบบันทึกรายการขัดข้อง กรุณาลองใหม่อีกครั้ง' : $e->getMessage();
    if ($database_error) error_log('Fedfe customer action failed: ' . $e->getMessage());
    if ($ajax) {
        http_response_code($database_error ? 500 : 409);
        header('Content-Type: text/plain; charset=UTF-8');
        exit($message);
    }
    flash($message, 'error');
}
redirect_to($returnPage . '?table=' . $tableNumber . '&visit=' . $visitId);
