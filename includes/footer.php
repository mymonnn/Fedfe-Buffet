<?php
/**
 * @param string $area customer หรือ staff
 * @param string $customer_page menu, cart หรือ orders
 * @param string $customer_query หมายเลขโต๊ะและรอบบริการในลิงก์
 * @param int $cart_count จำนวนอาหารที่ยังไม่ส่ง
 * @param bool $has_visit มีรอบบริการที่แสดงได้หรือไม่
 */
function render_footer($area, $customer_page, $customer_query, $cart_count, $has_visit) {
    if ($area === 'customer' && $has_visit) {
?>
<nav class="customer-nav" aria-label="เมนูลูกค้า">
    <div class="customer-nav-inner">
        <a href="<?= BASE_URL ?>/<?= h($customer_query) ?>" data-customer-link <?= $customer_page === 'menu' ? 'aria-current="page"' : '' ?>><?= icon('menu') ?><span>เมนู</span></a>
        <a href="<?= BASE_URL ?>/cart.php<?= h($customer_query) ?>" data-customer-link <?= $customer_page === 'cart' ? 'aria-current="page"' : '' ?>><?= icon('cart') ?><span>ตะกร้า <span class="nav-count cart-count"><?= h($cart_count) ?></span></span></a>
        <a href="<?= BASE_URL ?>/orders.php<?= h($customer_query) ?>" data-customer-link <?= $customer_page === 'orders' ? 'aria-current="page"' : '' ?>><?= icon('orders') ?><span>รายการที่สั่ง</span></a>
    </div>
</nav>
<?php
    } elseif ($area === 'staff') {
?>
<footer class="site-footer"><p>เฟ็ดเฟ่ บุฟเฟต์</p></footer>
<?php
    }
}
