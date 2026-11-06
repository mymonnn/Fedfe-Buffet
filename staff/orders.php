<?php
require_once "../connection.php";
require_once "../functions.php";
require_once "../includes/auth.php";
require_once "../includes/icons.php";
require_once "../includes/header.php";
require_once "../includes/footer.php";
$employee = require_employee($pdo);

$page_title = "คิวออร์เดอร์";
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
    <h1>คิวออร์เดอร์</h1>
    <p>ออร์เดอร์เรียงตามเวลาที่สั่ง พนักงานครัวและพนักงานเสิร์ฟใช้หน้านี้ร่วมกัน</p>
</header>
<section class="panel" aria-labelledby="queue-heading">
    <h2 id="queue-heading">ออร์เดอร์ของโต๊ะที่กำลังให้บริการ</h2>
    <div data-staff-poll="<?= h(BASE_URL) ?>/ajax/staff-status.php?view=orders" aria-live="polite">
        <p class="muted">กำลังโหลดออร์เดอร์…</p>
    </div>
    <p class="muted" data-poll-message></p>
    <noscript><p class="notice warning">กรุณาเปิด JavaScript เพื่อแสดงคิวออร์เดอร์และอัปเดตสถานะ</p></noscript>
</section>
</main>
<?php render_footer('staff', '', '', 0, false); ?>
</body>
</html>
