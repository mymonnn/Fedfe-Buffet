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

$area = "customer";
$customer_page = "menu";
$foods = [];
$category_list = [];
if ($visit) {
    $statement = $pdo->prepare("SELECT * FROM `MENU` WHERE is_deleted = 0 ORDER BY id");
    $statement->execute();
    $foods = $statement->fetchAll();
    $category_list = categories($pdo);
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เมนูอาหาร | เฟ็ดเฟ่ บุฟเฟต์</title>
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
                <p id="customer-message" class="notice error" role="status" hidden></p>
                <p id="ordering-message" class="notice" <?= $ordering ? 'hidden' : '' ?>><?= $visit['completed_at'] ? 'รอบบริการนี้สิ้นสุดแล้ว ขอบคุณที่มาใช้บริการ' : 'แจ้งเช็คบิลแล้ว ไม่สามารถสั่งอาหารเพิ่มได้' ?></p>
                <div class="menu-toolbar">
                    <h2>เมนูอาหาร</h2>
                    <label class="visually-hidden" for="category-filter">หมวดหมู่อาหาร</label>
                    <select id="category-filter">
                        <option value="">ทั้งหมด</option>
                        <?php for ($i = 0; $i < count($category_list); $i++): ?>
                            <option value="<?= h($category_list[$i]) ?>"><?= h($category_list[$i]) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php for ($category_index = 0; $category_index < count($category_list); $category_index++): ?>
                    <section class="menu-category-section" data-category="<?= h($category_list[$category_index]) ?>" aria-labelledby="category-<?= $category_index ?>">
                        <h2 id="category-<?= $category_index ?>" class="category-heading"><?= h($category_list[$category_index]) ?></h2>
                        <div class="menu-list">
                            <?php for ($i = 0; $i < count($foods); $i++): ?>
                                <?php
                                $food = $foods[$i];
                                if ($food["category"] !== $category_list[$category_index]) continue;
                                $quantity = (int) ($draft[$food['id']]['quantity'] ?? 0);
                                $image = menu_image_url((int) $food['id']);
                                ?>
                                <article class="menu-card <?= !$food['is_available'] ? 'sold-out' : '' ?>" data-cart-item>
                                    <img src="<?= h($image) ?>" alt="<?= h($food['name']) ?>" width="320" height="220" loading="lazy" data-fallback="<?= BASE_URL ?>/images/placeholder.svg">
                                    <div class="menu-card-body">
                                        <h3><?= h($food['name']) ?></h3>
                                        <?php if (!empty($food['description'])): ?><p class="food-description"><?= h($food['description']) ?></p><?php endif; ?>
                                        <?php if (!$food['is_available']): ?><span class="sold-out-label">หมด</span><?php endif; ?>
                                        <div class="menu-card-footer">
                                            <p class="menu-price"><?php if ((float) $food['price'] > 0): ?>+ <?= h(money($food['price'])) ?> บาท / ที่<?php endif; ?></p>
                                            <form method="post" action="<?= BASE_URL ?>/actions/customer.php" class="quantity-form" data-cart-form data-available="<?= $food['is_available'] ? '1' : '0' ?>">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="cart">
                                                <input type="hidden" name="table_number" value="<?= h($table_number) ?>">
                                                <input type="hidden" name="visit_id" value="<?= h($visit_id) ?>">
                                                <input type="hidden" name="food_id" value="<?= h($food['id']) ?>">
                                                <input type="hidden" name="return_to" value="menu">
                                                <div class="quantity-control">
                                                    <button type="button" class="quantity-button" data-quantity-change="-1" aria-label="ลด <?= h($food['name']) ?>" <?= !$ordering || !$quantity ? 'disabled' : '' ?>><?= icon('minus') ?></button>
                                                    <input class="quantity-input" type="number" name="quantity" value="<?= h($quantity) ?>" min="0" max="99" step="1" required aria-label="จำนวน <?= h($food['name']) ?>" <?= !$ordering || !$food['is_available'] ? 'disabled' : '' ?>>
                                                    <button type="button" class="quantity-button" data-quantity-change="1" aria-label="เพิ่ม <?= h($food['name']) ?>" <?= !$ordering || !$food['is_available'] || $quantity >= 99 ? 'disabled' : '' ?>><?= icon('plus') ?></button>
                                                </div>
                                                <button type="submit" class="button small quantity-save" <?= !$ordering ? 'disabled' : '' ?>>บันทึก</button>
                                                <span class="save-status" data-save-status role="status"></span>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endfor; ?>
                        </div>
                    </section>
                <?php endfor; ?>
                <?php if (!$foods): ?><p class="empty-state">ยังไม่มีรายการอาหาร กรุณาติดต่อพนักงาน</p><?php endif; ?>
                <noscript>
                    <p class="notice">กรอกจำนวนแล้วกดบันทึก เพื่อเพิ่มอาหารลงตะกร้า</p>
                </noscript>
            </div>
        <?php endif; ?>
    </main>
    <?php render_footer($area, $customer_page, $customer_query, $cart_count, (bool) $visit); ?>
</body>

</html>
