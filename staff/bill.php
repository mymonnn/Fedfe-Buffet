<?php
require_once "../connection.php";
require_once "../functions.php";
require_once "../includes/auth.php";
require_once "../includes/icons.php";
require_once "../includes/header.php";
require_once "../includes/footer.php";
$employee = require_employee($pdo);

$visit_id = filter_input(INPUT_GET, 'visit', FILTER_VALIDATE_INT);
if (!$visit_id || $visit_id < 1) {
    flash('ไม่พบรอบการให้บริการที่ต้องการ', 'error');
    redirect_to('staff/tables.php');
}
try {
    $bill = bill_details($pdo, $visit_id);
} catch (RuntimeException $error) {
    flash('ไม่พบรอบการให้บริการที่ต้องการ', 'error');
    redirect_to('staff/tables.php');
}
$visit = $bill['visit'];
$is_closed = $visit['completed_at'] !== null;
$page_title = "บิลโต๊ะ " . $visit['table_number'];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title) ?> | เฟ็ดเฟ่ บุฟเฟต์</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/staff.css">
    <script src="../script/staff.js" defer></script>
</head>
<body class="staff">
<?php render_header('staff', $employee, 0, false); ?>
<main class="container">
<?php if ($flash): ?>
    <p role="status" data-flash class="notice <?= h($flash['type']) ?>"><?= h($flash['message']) ?></p>
<?php endif; ?>
<header class="page-heading">
    <h1>บิลโต๊ะ <?= h($visit['table_number']) ?></h1>
    <p>รอบ <?= h($visit['session_number']) ?> · เปิดเมื่อ <?= h($visit['created_at']) ?></p>
</header>
<section class="panel" aria-labelledby="bill-heading">
    <h2 id="bill-heading">ค่าใช้จ่าย</h2>
    <?php if ($is_closed): ?>
        <p class="notice success">ชำระเงินและปิดโต๊ะแล้วเมื่อ <?= h($visit['completed_at']) ?></p>
    <?php elseif ($visit['request_bill']): ?>
        <p class="notice warning">ลูกค้าขอเช็คบิลแล้ว ระงับการสั่งอาหารเพิ่ม</p>
        <form action="<?= h(BASE_URL) ?>/actions/table.php" method="post" data-confirm="ยกเลิกการขอเช็คบิลและให้โต๊ะนี้สั่งอาหารต่อหรือไม่?">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="resume">
            <input type="hidden" name="table_number" value="<?= h($visit['table_number']) ?>">
            <input type="hidden" name="visit_id" value="<?= h($visit['id']) ?>">
            <button type="submit" class="button secondary small">ให้ลูกค้าสั่งอาหารต่อ</button>
        </form>
    <?php endif; ?>
    <div class="table-wrap">
        <table class="summary-table">
            <thead><tr><th scope="col">รายการ</th><th scope="col" class="text-right">จำนวน</th><th scope="col" class="text-right">รวม (บาท)</th></tr></thead>
            <tbody>
                <tr>
                    <td>บุฟเฟ่ต์ คนละ <?= h(money($visit['buffet_price'])) ?> บาท</td>
                    <td class="text-right"><?= h($visit['people_amount']) ?> คน</td>
                    <td class="text-right"><?= h(money(cents_decimal($bill['buffet_cents']))) ?></td>
                </tr>
                <?php for ($i = 0; $i < count($bill['lines']); $i++): ?>
                    <?php $line = $bill['lines'][$i]; ?>
                    <tr>
                        <td><?= h($line['name']) ?></td>
                        <td class="text-right"><?= h($line['quantity']) ?></td>
                        <td class="text-right"><?= h(money($line['price'])) ?></td>
                    </tr>
                <?php endfor; ?>
                <tr class="total-row">
                    <th scope="row" colspan="2">ยอดรวม<?= $is_closed ? 'ที่บันทึกไว้' : '' ?></th>
                    <td class="text-right"><?= h(money($is_closed ? $visit['total_price'] : cents_decimal($bill['total_cents']))) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
    <p class="muted">คิดเฉพาะอาหารนอกบุฟเฟ่ต์ที่เสิร์ฟแล้ว ออร์เดอร์ที่ยกเลิกไม่นำมาคิดเงิน</p>
    <?php if (!$is_closed && $bill['pending'] > 0): ?>
        <p class="notice warning">มี <?= h($bill['pending']) ?> ออร์เดอร์รอจัดเตรียม กรุณาเสิร์ฟหรือยกเลิกให้เรียบร้อยก่อนชำระเงิน</p>
        <p><a class="button secondary" href="<?= h(BASE_URL) ?>/staff/orders.php">ไปจัดการออร์เดอร์</a></p>
    <?php elseif (!$is_closed): ?>
        <form action="<?= h(BASE_URL) ?>/actions/table.php" method="post" data-confirm="ยืนยันว่าได้รับเงินครบแล้วและต้องการปิดโต๊ะนี้หรือไม่?" data-submit-once>
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="table_number" value="<?= h($visit['table_number']) ?>">
            <input type="hidden" name="visit_id" value="<?= h($visit['id']) ?>">
            <input type="hidden" name="expected_total_cents" value="<?= h($bill['total_cents']) ?>">
            <button type="submit" class="button">รับชำระเงินและปิดโต๊ะ</button>
        </form>
    <?php endif; ?>
    <p><a href="<?= h(BASE_URL) ?>/staff/tables.php">กลับไปจัดการโต๊ะ</a></p>
</section>
</main>
<?php render_footer('staff', '', '', 0, false); ?>
</body>
</html>
