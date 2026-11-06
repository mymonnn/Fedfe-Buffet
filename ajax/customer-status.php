<?php
require_once '../connection.php';
require_once '../functions.php';
require_once '../includes/customer.php';
$table_number = (int) (filter_var($_GET['table'] ?? null, FILTER_VALIDATE_INT) ?: 0);
$requested_visit = (int) (filter_var($_GET['visit'] ?? null, FILTER_VALIDATE_INT) ?: 0);
$visit = load_customer_visit($pdo, $table_number, $requested_visit, isset($_GET['visit']));
header('Cache-Control: no-store');
if (!$visit) {
    echo '<section data-active="0" data-ordering="0" data-cart-count="0"><p class="notice">รอบบริการนี้สิ้นสุดแล้ว กรุณาติดต่อพนักงาน</p></section>';
    exit;
}
$active = !$visit['completed_at'] && $visit['table_open'];
$visit_id = (int) $visit['id'];
$ordering = $active && !$visit['request_bill'];
$cart_count = draft_count($visit_id);
$statement = $pdo->prepare('SELECT * FROM `ORDER` WHERE session_id = ? ORDER BY created_at DESC, id DESC');
$statement->execute([$visit_id]);
?>
<section data-active="<?= $active ? '1' : '0' ?>" data-ordering="<?= $ordering ? '1' : '0' ?>" data-cart-count="<?= $cart_count ?>">
<?php if (!$active): ?><p class="notice success">ชำระเงินเรียบร้อยแล้ว ขอบคุณที่มาใช้บริการ</p>
<?php elseif ($visit['request_bill']): ?><p class="notice">แจ้งเช็คบิลแล้ว กรุณารอพนักงาน</p><?php endif; ?>
<?php if ($active && $visit['need_help']): ?><p class="notice">เรียกพนักงานแล้ว กรุณารอสักครู่</p><?php endif; ?>
<?php $hasOrders = false; while ($order = $statement->fetch(PDO::FETCH_ASSOC)): $hasOrders = true; ?>
<article class="order-card">
    <h2>รายการ <?= h($order['number']) ?> <span class="status <?= h($order['status']) ?>"><?= status_label($order['status']) ?></span></h2>
    <p class="muted"><?= h(date('H:i', strtotime($order['created_at']))) ?></p>
    <ul>
    <?php
    $items = $pdo->prepare('SELECT i.*, m.name FROM `ITEM` i JOIN `MENU` m ON m.id = i.food_id WHERE order_id = ? ORDER BY i.id');
    $items->execute([$order['id']]);
    while ($item = $items->fetch(PDO::FETCH_ASSOC)): ?>
        <li><?= h($item['name']) ?> <strong>× <?= h($item['quantity']) ?></strong><?php if ($item['note']): ?><p class="muted"><?= h($item['note']) ?></p><?php endif; ?></li>
    <?php endwhile; ?>
    </ul>
</article>
<?php endwhile; ?>
<?php if (!$hasOrders): ?><p class="empty-state">ยังไม่มีรายการที่สั่ง</p><?php endif; ?>
</section>
