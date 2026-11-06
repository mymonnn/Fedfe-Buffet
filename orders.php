<?php
require_once "connection.php";
require_once "functions.php";
require_once "includes/customer.php";
require_once "includes/icons.php";
require_once "includes/header.php";
require_once "includes/footer.php";

$table_number = filter_var($_GET["table"] ?? "", FILTER_VALIDATE_INT) ?: 0;
$requested_visit = filter_var($_GET["visit"] ?? "", FILTER_VALIDATE_INT) ?: 0;
$visit = load_customer_visit($pdo, $table_number, $requested_visit, isset($_GET["visit"]));
$visit_id = $visit ? (int) $visit["id"] : 0;
$ordering = $visit && !$visit["completed_at"] && $visit["table_open"] && !$visit["request_bill"];
$draft = $_SESSION["carts"][$visit_id] ?? [];
$cart_count = draft_count($visit_id);
$customer_query = "?table=" . (int) $table_number . "&visit=" . $visit_id;
$page_flash = $_SESSION["flash"] ?? null;
unset($_SESSION["flash"]);
$page_title = "รายการที่สั่ง";
$area = "customer";
$customer_page = "orders";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title) ?> | เฟ็ดเฟ่ บุฟเฟต์</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/customer.css">
    <script src="script/customer.js" defer></script>
</head>
<body class="<?= $visit ? 'customer customer-service' : 'customer unavailable' ?>">
<?php if ($visit) render_header($area, false, $table_number, true); ?>
<main class="container">
<?php if (!$visit): ?>
    <section class="unavailable-state">
        <?= icon('unavailable') ?>
        <h1>โต๊ะยังไม่เปิดให้บริการ</h1>
        <p>กรุณาติดต่อพนักงาน</p>
    </section>
<?php else: ?>
<?php if ($page_flash): ?><p role="status" data-flash class="notice <?= h($page_flash['type']) ?>"><?= h($page_flash['message']) ?></p><?php endif; ?>
<div id="customer-app" data-table="<?= h($table_number) ?>" data-visit="<?= h($visit_id) ?>"
     data-active="<?= !$visit['completed_at'] && $visit['table_open'] ? '1' : '0' ?>" data-ordering="<?= $ordering ? '1' : '0' ?>"
     data-cart-count="<?= h($cart_count) ?>" data-cart-url="<?= BASE_URL ?>/actions/customer.php"
     data-status-url="<?= BASE_URL ?>/ajax/customer-status.php<?= h($customer_query) ?>">
    <div class="page-heading"><h2>รายการที่สั่ง</h2><p class="muted">สถานะอัปเดตอัตโนมัติ</p></div>
    <p id="customer-message" class="notice error" role="status" hidden></p>
    <p id="ordering-message" class="notice" <?= $ordering ? 'hidden' : '' ?>><?= $visit['completed_at'] ? 'รอบบริการนี้สิ้นสุดแล้ว ขอบคุณที่มาใช้บริการ' : 'แจ้งเช็คบิลแล้ว กรุณารอพนักงาน' ?></p>
    <?php if (!$visit['completed_at']): ?>
    <section class="service-actions" aria-label="ติดต่อพนักงาน">
        <form method="post" action="<?= BASE_URL ?>/actions/customer.php" class="inline-form" data-customer-action>
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="help">
            <input type="hidden" name="table_number" value="<?= h($table_number) ?>">
            <input type="hidden" name="visit_id" value="<?= h($visit_id) ?>">
            <input type="hidden" name="return_to" value="orders">
            <button type="submit" class="button secondary"><?= icon('help') ?> เรียกพนักงาน</button>
        </form>
        <form method="post" action="<?= BASE_URL ?>/actions/customer.php" class="inline-form" data-customer-action>
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="bill">
            <input type="hidden" name="table_number" value="<?= h($table_number) ?>">
            <input type="hidden" name="visit_id" value="<?= h($visit_id) ?>">
            <input type="hidden" name="return_to" value="orders">
            <input type="hidden" name="discard_cart" value="0">
            <button type="submit" class="button secondary" <?= !$ordering ? 'disabled' : '' ?>><?= icon('bill') ?> เรียกเช็คบิล</button>
        </form>
    </section>
    <?php endif; ?>
    <p id="customer-poll-message" class="small-text muted" role="status"></p>
    <div id="customer-status"><p class="empty-state">กำลังโหลดรายการ…</p></div>
    <noscript><p class="notice">กรุณาเปิด JavaScript เพื่อแสดงรายการที่สั่งและอัปเดตสถานะ</p></noscript>
</div>
<?php endif; ?>
</main>
<?php render_footer($area, $customer_page, $customer_query, $cart_count, (bool) $visit); ?>
</body>
</html>
