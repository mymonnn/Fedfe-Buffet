<?php
require_once '../connection.php';
require_once '../functions.php';
require_once '../includes/auth.php';
require_once '../includes/table-card.php';
$employee = require_employee($pdo, true);
header('Cache-Control: no-store');
$view = $_GET['view'] ?? 'orders';
if ($view === 'tables') {
    $statement = $pdo->query('SELECT t.number AS table_number, t.is_available AS table_open, s.id AS visit_id, s.session_number, s.people_amount, s.created_at, s.need_help, s.request_bill FROM `TABLE` t LEFT JOIN `SESSION` s ON s.table_id = t.id AND s.completed_at IS NULL WHERE t.number BETWEEN 1 AND 9 ORDER BY t.number');
    while ($table = $statement->fetch(PDO::FETCH_ASSOC)) { render_table_card($table); }
    exit;
}
if ($view === 'alerts') {
    $statement = $pdo->query('SELECT s.*, t.number AS table_number FROM `SESSION` s JOIN `TABLE` t ON t.id = s.table_id WHERE t.number BETWEEN 1 AND 9 AND s.completed_at IS NULL AND (s.need_help = 1 OR s.request_bill = 1) ORDER BY t.number');
    $hasAlerts = false;
    while ($visit = $statement->fetch(PDO::FETCH_ASSOC)): $hasAlerts = true; ?>
        <article class="notice">
            <strong>โต๊ะ <?= h($visit['table_number']) ?></strong>
            <?php if ($visit['need_help']): ?>
                เรียกพนักงาน
                <form class="inline-form" method="post" action="<?= BASE_URL ?>/actions/table.php">
                    <?php csrf_field(); ?><input type="hidden" name="action" value="ack"><input type="hidden" name="table_number" value="<?= h($visit['table_number']) ?>"><input type="hidden" name="visit_id" value="<?= h($visit['id']) ?>">
                    <button class="button secondary" type="submit">รับทราบ</button>
                </form>
            <?php endif; ?>
            <?php if ($visit['request_bill']): ?><a class="button" href="<?= BASE_URL ?>/staff/bill.php?visit=<?= h($visit['id']) ?>">ขอเช็คบิล</a><?php endif; ?>
        </article>
    <?php endwhile;
    if (!$hasAlerts) { echo '<p class="muted">ไม่มีการเรียกพนักงานหรือขอเช็คบิล</p>'; }
    exit;
}
if ($view !== 'orders') { http_response_code(400); exit('คำขอไม่ถูกต้อง'); }
$statement = $pdo->query("SELECT o.*, t.number AS table_number FROM `ORDER` o JOIN `SESSION` s ON s.id = o.session_id JOIN `TABLE` t ON t.id = s.table_id WHERE t.number BETWEEN 1 AND 9 AND s.completed_at IS NULL ORDER BY (o.status = 'PREPARING') DESC, o.created_at, o.id");
$hasOrders = false;
while ($order = $statement->fetch(PDO::FETCH_ASSOC)): $hasOrders = true;
?>
<article class="panel order-card" id="order-<?= h($order['id']) ?>">
    <h2>โต๊ะ <?= h($order['table_number']) ?> · <?= h($order['number']) ?> <span class="status <?= h($order['status']) ?>"><?= status_label($order['status']) ?></span></h2>
    <p class="muted">สั่งเมื่อ <?= h(date('d/m/Y H:i', strtotime($order['created_at']))) ?></p>
    <ul>
    <?php
    $items = $pdo->prepare('SELECT i.*, m.name FROM `ITEM` i JOIN `MENU` m ON m.id = i.food_id WHERE order_id = ? ORDER BY i.id');
    $items->execute([$order['id']]);
    while ($item = $items->fetch(PDO::FETCH_ASSOC)): ?>
        <li><strong><?= h($item['name']) ?> × <?= h($item['quantity']) ?></strong><?php if ($item['note']): ?><br><span>หมายเหตุ: <?= h($item['note']) ?></span><?php endif; ?></li>
    <?php endwhile; ?>
    </ul>
    <?php if ($order['status'] === 'PREPARING'): ?>
    <form method="post" action="<?= BASE_URL ?>/actions/order.php" class="inline-form">
        <?php csrf_field(); ?><input type="hidden" name="order_id" value="<?= h($order['id']) ?>">
        <button class="button" type="submit" name="action" value="serve">เสิร์ฟแล้ว</button>
        <button class="button danger" type="submit" name="action" value="cancel" data-confirm="ยกเลิกออร์เดอร์นี้ทั้งหมดหรือไม่?">ยกเลิกออร์เดอร์</button>
    </form>
    <?php elseif (is_manager($employee)): ?>
    <form method="post" action="<?= BASE_URL ?>/actions/order.php" class="inline-form" data-confirm="คืนออร์เดอร์นี้เป็นรอจัดเตรียมเพื่อแก้ไขสถานะหรือไม่?">
        <?php csrf_field(); ?><input type="hidden" name="order_id" value="<?= h($order['id']) ?>"><input type="hidden" name="action" value="reset">
        <button class="button secondary small" type="submit">แก้ไขสถานะ</button>
    </form>
    <?php endif; ?>
</article>
<?php endwhile; ?>
<?php if (!$hasOrders): ?><p class="empty-state">ยังไม่มีออร์เดอร์ในโต๊ะที่เปิดให้บริการ</p><?php endif; ?>
