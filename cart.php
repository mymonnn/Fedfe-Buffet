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
$page_title = "ตะกร้าอาหาร";
$area = "customer";
$customer_page = "cart";
$rows = $visit ? cart_rows($pdo, $visit_id) : [];
$order_token = '';
if ($visit) {
    $order_token = bin2hex(random_bytes(16));
    $_SESSION['order_tokens'][$order_token] = $visit_id;
    $tokens = array_keys($_SESSION['order_tokens']);
    for ($i = 0; $i < count($tokens) - 50; $i++) unset($_SESSION['order_tokens'][$tokens[$i]]);
}
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
    <div class="page-heading"><h2>ตะกร้าอาหาร</h2><p class="muted">ตรวจสอบรายการก่อนส่งให้ครัว</p></div>
    <p id="customer-message" class="notice error" role="status" hidden></p>
    <p id="ordering-message" class="notice" <?= $ordering ? 'hidden' : '' ?>><?= $visit['completed_at'] ? 'รอบบริการนี้สิ้นสุดแล้ว ขอบคุณที่มาใช้บริการ' : 'แจ้งเช็คบิลแล้ว ไม่สามารถสั่งอาหารเพิ่มได้' ?></p>
    <section id="cart-empty" class="empty-state" <?= $cart_count ? 'hidden' : '' ?>>
        <?= icon('cart') ?><h3>ยังไม่มีอาหารในตะกร้า</h3><p>เลือกอาหารที่ต้องการ แล้วกลับมายืนยันได้ที่นี่</p>
        <a class="button" href="<?= BASE_URL ?>/<?= h($customer_query) ?>" data-customer-link>เลือกอาหาร</a>
    </section>
    <div class="cart-list">
        <?php for ($i = 0; $i < count($rows); $i++): ?>
        <?php
            $food = $rows[$i];
            $image = menu_image_url((int) $food['id']);
            $available = $food['is_available'] && !$food['is_deleted'];
        ?>
        <article class="cart-row" data-cart-item>
            <img src="<?= h($image) ?>" alt="<?= h($food['name']) ?>" width="88" height="88" data-fallback="<?= BASE_URL ?>/images/placeholder.svg">
            <div class="cart-row-content">
                <h3><?= h($food['name']) ?></h3>
                <?php if ((float) $food['price'] > 0): ?><p class="menu-price">+ <?= h(money($food['price'])) ?> บาท / ที่</p><?php endif; ?>
                <?php if (!$available): ?><p class="text-danger small-text">อาหารนี้ไม่พร้อมสั่ง กรุณานำออกจากตะกร้า</p><?php endif; ?>
                <form method="post" action="<?= BASE_URL ?>/actions/customer.php" class="quantity-form" data-cart-form data-available="<?= $available ? '1' : '0' ?>">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="cart">
                    <input type="hidden" name="table_number" value="<?= h($table_number) ?>">
                    <input type="hidden" name="visit_id" value="<?= h($visit_id) ?>">
                    <input type="hidden" name="food_id" value="<?= h($food['id']) ?>">
                    <input type="hidden" name="return_to" value="cart">
                    <div class="quantity-control">
                        <button type="button" class="quantity-button" data-quantity-change="-1" aria-label="ลด <?= h($food['name']) ?>" <?= !$ordering ? 'disabled' : '' ?>><?= icon('minus') ?></button>
                        <input class="quantity-input" type="number" name="quantity" value="<?= h($food['quantity']) ?>" min="0" max="99" step="1" required aria-label="จำนวน <?= h($food['name']) ?>" <?= !$ordering || !$available ? 'disabled' : '' ?>>
                        <button type="button" class="quantity-button" data-quantity-change="1" aria-label="เพิ่ม <?= h($food['name']) ?>" <?= !$ordering || !$available || $food['quantity'] >= 99 ? 'disabled' : '' ?>><?= icon('plus') ?></button>
                    </div>
                    <button type="button" class="icon-button remove-food" data-remove-cart aria-label="นำ <?= h($food['name']) ?> ออก" <?= !$ordering ? 'disabled' : '' ?>><?= icon('remove') ?></button>
                    <details class="cart-note" <?= $food['note'] !== '' ? 'open' : '' ?>>
                        <summary>หมายเหตุ<?= $food['note'] !== '' ? ' (มีข้อความ)' : ' (ถ้ามี)' ?></summary>
                        <label class="visually-hidden" for="note-<?= h($food['id']) ?>">หมายเหตุ <?= h($food['name']) ?></label>
                        <textarea name="note" id="note-<?= h($food['id']) ?>" maxlength="200" rows="2" placeholder="เช่น แยกจาน" <?= !$ordering ? 'disabled' : '' ?>><?= h($food['note']) ?></textarea>
                        <span id="note-count-<?= h($food['id']) ?>" class="note-count small-text"><?= mb_strlen($food['note']) ?>/200 ตัวอักษร</span>
                    </details>
                    <button type="submit" class="button small quantity-save" <?= !$ordering ? 'disabled' : '' ?>>บันทึก</button>
                    <span class="save-status" data-save-status role="status"></span>
                </form>
            </div>
        </article>
        <?php endfor; ?>
    </div>
    <form id="order-form" method="post" action="<?= BASE_URL ?>/actions/customer.php" class="order-submit-panel" data-customer-action <?= !$cart_count ? 'hidden' : '' ?>>
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="order">
        <input type="hidden" name="table_number" value="<?= h($table_number) ?>">
        <input type="hidden" name="visit_id" value="<?= h($visit_id) ?>">
        <input type="hidden" name="order_token" value="<?= h($order_token) ?>">
        <?php for ($i = 0; $i < count($rows); $i++): ?>
        <input type="hidden" name="expected_prices[<?= h($rows[$i]['id']) ?>]" value="<?= h($rows[$i]['price']) ?>" data-expected-food="<?= h($rows[$i]['id']) ?>">
        <input type="hidden" name="expected_quantities[<?= h($rows[$i]['id']) ?>]" value="<?= h($rows[$i]['quantity']) ?>" data-expected-food="<?= h($rows[$i]['id']) ?>">
        <input type="hidden" name="expected_notes[<?= h($rows[$i]['id']) ?>]" value="<?= h($rows[$i]['note']) ?>" data-expected-food="<?= h($rows[$i]['id']) ?>">
        <?php endfor; ?>
        <p class="small-text muted">รายการในตะกร้ายังไม่ส่งให้ครัว จนกว่าจะกดยืนยัน</p>
        <button type="submit" class="button submit-order" <?= !$ordering ? 'disabled' : '' ?>><?= icon('check') ?> ยืนยันสั่งอาหาร <span class="cart-count"><?= h($cart_count) ?></span> ที่</button>
    </form>
</div>
<?php endif; ?>
</main>
<?php render_footer($area, $customer_page, $customer_query, $cart_count, (bool) $visit); ?>
</body>
</html>
