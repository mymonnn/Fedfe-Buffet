<?php
/**
 * @param string $area customer หรือ staff
 * @param array|false $employee บัญชีที่ตรวจสอบแล้ว หรือ false สำหรับลูกค้า
 * @param int $table_number หมายเลขโต๊ะลูกค้า
 * @param bool $has_visit มีรอบบริการที่แสดงได้หรือไม่
 */
function render_header($area, $employee, $table_number, $has_visit) {
?>
<header class="site-header<?= $area === 'customer' && $has_visit ? ' customer-header' : '' ?>">
    <div class="container">
        <?php if ($area === 'customer' && $has_visit): ?>
        <h1>โต๊ะ <?= h($table_number) ?></h1>
        <?php else: ?>
        <a class="brand" href="<?= BASE_URL ?>/<?= $area === 'staff' ? 'staff/tables.php' : '' ?>">เฟ็ดเฟ่ บุฟเฟต์</a>
        <?php endif; ?>
        <?php if ($area === 'staff' && $employee): ?>
        <p class="staff-identity"><?= h($employee['first_name'] . ' ' . $employee['last_name']) ?> · <?= is_manager($employee) ? 'ผู้จัดการร้าน' : 'พนักงาน' ?></p>
        <nav aria-label="เมนูพนักงาน"><ul>
            <li><a href="<?= BASE_URL ?>/staff/tables.php"><?= icon('table') ?> โต๊ะและบริการ</a></li>
            <li><a href="<?= BASE_URL ?>/staff/orders.php"><?= icon('orders') ?> คิวออร์เดอร์</a></li>
            <li><a href="<?= BASE_URL ?>/staff/menu.php"><?= icon('menu') ?> รายการอาหาร</a></li>
            <?php if (is_manager($employee)): ?>
            <li><a href="<?= BASE_URL ?>/staff/employees.php"><?= icon('users') ?> บัญชีพนักงาน</a></li>
            <li><a href="<?= BASE_URL ?>/staff/report.php"><?= icon('chart') ?> รายงานยอดขาย</a></li>
            <?php endif; ?>
            <li><form method="post" action="<?= BASE_URL ?>/logout.php"><?php csrf_field(); ?><button class="button secondary small" type="submit"><?= icon('logout') ?> ออกจากระบบ</button></form></li>
        </ul></nav>
        <?php endif; ?>
    </div>
</header>
<?php
}
