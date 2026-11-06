<?php
require_once "../connection.php";
require_once "../functions.php";
require_once "../includes/auth.php";
require_once "../includes/icons.php";
require_once "../includes/header.php";
require_once "../includes/footer.php";
$employee = require_employee($pdo);
require_manager($employee);

$statement = $pdo->query('SELECT DATE(completed_at) AS sale_date, COUNT(*) AS visit_count,
    SUM(people_amount) AS guest_count, SUM(total_price) AS revenue
    FROM `SESSION` WHERE completed_at IS NOT NULL
    GROUP BY DATE(completed_at) ORDER BY sale_date DESC');
$page_title = 'สรุปยอดขายรายวัน';
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
    <h1>สรุปยอดขายรายวัน</h1>
    <p>รวมเฉพาะรอบที่รับชำระเงินและปิดโต๊ะแล้ว นับแต่ละรอบเพียงครั้งเดียว</p>
</header>
<section class="panel" aria-labelledby="report-heading">
    <h2 id="report-heading">ยอดขายตามวันที่ปิดโต๊ะ</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th scope="col">วันที่</th><th scope="col" class="text-right">จำนวนรอบ</th><th scope="col" class="text-right">จำนวนลูกค้า</th><th scope="col" class="text-right">ยอดขาย (บาท)</th></tr></thead>
            <tbody>
                <?php $row_count = 0; ?>
                <?php while ($day = $statement->fetch()): ?>
                    <?php $row_count++; ?>
                    <tr>
                        <td><?= h($day['sale_date']) ?></td>
                        <td class="text-right"><?= h($day['visit_count']) ?></td>
                        <td class="text-right"><?= h($day['guest_count']) ?></td>
                        <td class="text-right"><?= h(money($day['revenue'])) ?></td>
                    </tr>
                <?php endwhile; ?>
                <?php if ($row_count === 0): ?><tr><td colspan="4" class="empty-state">ยังไม่มีรอบที่ชำระเงินแล้ว</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</main>
<?php render_footer('staff', '', '', 0, false); ?>
</body>
</html>
