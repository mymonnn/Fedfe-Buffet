<?php
require_once "../connection.php";
require_once "../functions.php";
require_once "../includes/auth.php";
require_once "../includes/icons.php";
require_once "../includes/header.php";
require_once "../includes/footer.php";
require_once "../includes/table-card.php";
$employee = require_employee($pdo);

$statement = $pdo->query("SELECT t.number AS table_number, t.is_available AS table_open,
    s.id AS visit_id, s.session_number, s.people_amount, s.created_at, s.need_help, s.request_bill
    FROM `TABLE` t LEFT JOIN `SESSION` s ON s.table_id = t.id AND s.completed_at IS NULL
    WHERE t.number BETWEEN 1 AND 9
    ORDER BY t.number");
$tables = $statement->fetchAll();
$page_title = "จัดการโต๊ะ";
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
    <h1>จัดการโต๊ะ</h1>
    <p>เปิดโต๊ะ บันทึกจำนวนลูกค้า และรับการเรียกจากโต๊ะ</p>
</header>

<section class="panel" aria-labelledby="alerts-heading">
    <h2 id="alerts-heading">การเรียกพนักงานและขอเช็คบิล</h2>
    <div data-staff-poll="<?= h(BASE_URL) ?>/ajax/staff-status.php?view=alerts" aria-live="polite">
        <?php $alert_count = 0; ?>
        <?php for ($i = 0; $i < count($tables); $i++): ?>
            <?php $table = $tables[$i]; ?>
            <?php if ($table['visit_id'] && ($table['need_help'] || $table['request_bill'])): ?>
                <?php $alert_count++; ?>
                <p class="notice warning">
                    โต๊ะ <?= h($table['table_number']) ?>:
                    <?= $table['need_help'] ? 'เรียกพนักงาน' : '' ?>
                    <?= $table['need_help'] && $table['request_bill'] ? ' / ' : '' ?>
                    <?= $table['request_bill'] ? 'ขอเช็คบิล' : '' ?>
                </p>
            <?php endif; ?>
        <?php endfor; ?>
        <?php if ($alert_count === 0): ?>
            <p class="muted">ยังไม่มีการเรียกพนักงานหรือขอเช็คบิล</p>
        <?php endif; ?>
    </div>
    <p class="muted" data-poll-message></p>
</section>

<section aria-labelledby="tables-heading">
    <h2 id="tables-heading">โต๊ะทั้งหมด</h2>
    <p class="notice warning table-refresh" data-table-refresh hidden>สถานะโต๊ะเปลี่ยนไป <a href="<?= h(BASE_URL) ?>/staff/tables.php">อัปเดตโต๊ะ</a> · ข้อมูลที่ยังไม่ได้บันทึกจะถูกล้างเมื่ออัปเดต</p>
    <div class="table-list" data-table-list data-table-state-url="<?= h(BASE_URL) ?>/ajax/staff-status.php?view=tables">
        <?php for ($i = 0; $i < count($tables); $i++): ?>
            <?php render_table_card($tables[$i]); ?>
        <?php endfor; ?>
    </div>
</section>
</main>
<?php render_footer('staff', '', '', 0, false); ?>
</body>
</html>
