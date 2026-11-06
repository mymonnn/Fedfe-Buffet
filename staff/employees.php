<?php
require_once "../connection.php";
require_once "../functions.php";
require_once "../includes/auth.php";
require_once "../includes/icons.php";
require_once "../includes/header.php";
require_once "../includes/footer.php";
$employee = require_employee($pdo);
require_manager($employee);

$editing = ['id' => 0, 'username' => '', 'first_name' => '', 'last_name' => '', 'email' => '', 'system_role' => 'STAFF'];
if (isset($_GET['edit'])) {
    $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    $statement = $pdo->prepare('SELECT id, username, first_name, last_name, email, system_role FROM `EMPLOYEE` WHERE id = ?');
    $statement->execute([$edit_id ?: 0]);
    $editing = $statement->fetch();
    if (!$editing) {
        flash('ไม่พบบัญชีพนักงานที่ต้องการแก้ไข', 'error');
        redirect_to('staff/employees.php');
    }
}
$form_data = $_SESSION['employee_form'] ?? null;
unset($_SESSION['employee_form']);
$form_error = is_array($form_data) && (int) $form_data['id'] === (int) $editing['id'];
if ($form_error) {
    $fields = ['username', 'first_name', 'last_name', 'email', 'system_role'];
    for ($i = 0; $i < count($fields); $i++) {
        if (array_key_exists($fields[$i], $form_data)) {
            $editing[$fields[$i]] = $form_data[$fields[$i]];
        }
    }
}
$statement = $pdo->query('SELECT id, username, first_name, last_name, email, system_role FROM `EMPLOYEE` ORDER BY id');
$roles = ['STAFF', 'MANAGER'];
$role_names = ['STAFF' => 'พนักงาน', 'MANAGER' => 'ผู้จัดการ'];
$page_title = 'บัญชีพนักงาน';
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
    <h1>บัญชีพนักงาน</h1>
    <p>เพิ่ม แก้ไข และลบบัญชีพนักงาน</p>
</header>
<details class="panel management-form" id="employee-form" <?= $editing['id'] || $form_error ? 'open' : '' ?>>
    <summary><?= icon($editing['id'] ? 'edit' : 'plus') ?> <?= $editing['id'] ? 'แก้ไขบัญชี: ' . h($editing['username']) : 'เพิ่มบัญชีพนักงาน' ?></summary>
    <form action="<?= h(BASE_URL) ?>/actions/employee.php" method="post" data-submit-once>
        <?php csrf_field(); ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="employee_id" value="<?= h($editing['id']) ?>">
        <div class="form-row">
            <div class="field">
                <label for="employee-username">ชื่อผู้ใช้</label>
                <input id="employee-username" name="username" value="<?= h($editing['username']) ?>" minlength="3" maxlength="64" pattern="[A-Za-z0-9_.\-]{3,64}" title="อักษรอังกฤษ ตัวเลข จุด ขีดกลาง หรือขีดล่าง 3-64 ตัว" autocomplete="off" required>
            </div>
            <div class="field">
                <label for="employee-password">รหัสผ่าน<?= $editing['id'] ? ' (เว้นว่างเพื่อใช้รหัสเดิม)' : '' ?></label>
                <input id="employee-password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" <?= $editing['id'] ? '' : 'required' ?>>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="employee-first-name">ชื่อ</label>
                <input id="employee-first-name" name="first_name" value="<?= h($editing['first_name']) ?>" maxlength="80" required>
            </div>
            <div class="field">
                <label for="employee-last-name">นามสกุล</label>
                <input id="employee-last-name" name="last_name" value="<?= h($editing['last_name']) ?>" maxlength="80" required>
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="employee-email">อีเมล</label>
                <input id="employee-email" type="email" name="email" value="<?= h($editing['email']) ?>" maxlength="254" required>
            </div>
            <div class="field">
                <label for="employee-role">สิทธิ์การใช้งาน</label>
                <select id="employee-role" name="system_role" required>
                    <?php for ($i = 0; $i < count($roles); $i++): ?>
                        <option value="<?= h($roles[$i]) ?>" <?= $editing['system_role'] === $roles[$i] ? 'selected' : '' ?>><?= h($role_names[$roles[$i]]) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="button"><?= $editing['id'] ? 'บันทึกการแก้ไข' : 'เพิ่มพนักงาน' ?></button>
        <?php if ($editing['id']): ?><a class="button secondary" href="<?= h(BASE_URL) ?>/staff/employees.php">ยกเลิกการแก้ไข</a><?php endif; ?>
    </form>
</details>
<section class="panel" id="employee-list" aria-labelledby="employee-list-heading">
    <h2 id="employee-list-heading">รายชื่อพนักงาน</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th scope="col">ชื่อผู้ใช้</th><th scope="col">ชื่อ-นามสกุล</th><th scope="col">อีเมล</th><th scope="col">สิทธิ์</th><th scope="col">จัดการ</th></tr></thead>
            <tbody>
                <?php while ($account = $statement->fetch()): ?>
                    <tr id="employee-<?= h($account['id']) ?>">
                        <td><?= h($account['username']) ?><?= (int) $account['id'] === (int) $employee['id'] ? ' (คุณ)' : '' ?></td>
                        <td><?= h($account['first_name'] . ' ' . $account['last_name']) ?></td>
                        <td><?= h($account['email']) ?></td>
                        <td><?= h($role_names[$account['system_role']] ?? $account['system_role']) ?></td>
                        <td class="row-actions">
                            <a class="button secondary small" href="<?= h(BASE_URL) ?>/staff/employees.php?edit=<?= h($account['id']) ?>#employee-form">แก้ไข</a>
                            <?php if ((int) $account['id'] !== (int) $employee['id']): ?>
                                <form action="<?= h(BASE_URL) ?>/actions/employee.php" method="post" class="inline-form" data-confirm="<?= h('ลบบัญชี ' . $account['username'] . ' หรือไม่? การลบไม่สามารถย้อนกลับได้') ?>">
                                    <?php csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="employee_id" value="<?= h($account['id']) ?>">
                                    <button type="submit" class="button danger small">ลบ</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</section>
</main>
<?php render_footer('staff', '', '', 0, false); ?>
</body>
</html>
