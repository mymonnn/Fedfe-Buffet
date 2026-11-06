<?php
    require_once 'connection.php';
    require_once 'functions.php';
    require_once 'includes/icons.php';
    require_once 'includes/header.php';
    require_once 'includes/footer.php';
    $error = '';
    $username = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_post();
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $statement = $pdo->prepare('SELECT * FROM `EMPLOYEE` WHERE username = ? AND is_deactivated = 0');
        $statement->execute([$username]);
        $account = $statement->fetch();
        if ($account && password_verify($password, $account['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['employee_id'] = $account['id'];
            redirect_to('staff/tables.php');
        }
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
    $area = 'customer';
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบพนักงาน | เฟ็ดเฟ่ บุฟเฟต์</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/customer.css">
    <script src="script/customer.js" defer></script>
</head>

<body class="customer">
    <?php render_header($area, false, 0, false); ?>
    <main class="container">
        <section class="panel login-panel">
            <h1>เข้าสู่ระบบพนักงาน</h1>
            <p>สำหรับพนักงานและผู้จัดการร้าน</p>
            <?php if ($error): ?><p class="notice error" role="alert"><?= h($error) ?></p><?php endif; ?>
            <form method="post">
                <?php csrf_field(); ?>
                <label for="username">ชื่อผู้ใช้</label>
                <input id="username" name="username" value="<?= h($username) ?>" required maxlength="64" autocomplete="username">
                <label for="password">รหัสผ่าน</label>
                <input id="password" name="password" type="password" required maxlength="200" autocomplete="current-password">
                <button class="button" type="submit">เข้าสู่ระบบ</button>
            </form>
        </section>
    </main>
    <?php render_footer($area, '', '', 0, false); ?>
</body>

</html>