<?php
require_once "../connection.php";
require_once "../functions.php";
require_once "../includes/auth.php";
$employee = require_employee($pdo);
require_manager($employee);
check_post();
$action = '';
$employee_id = 0;
$values = [];
$save_failed = false;

try {
    $action = $_POST['action'] ?? '';
    if (!is_string($action) || !in_array($action, ['save', 'delete'], true)) {
        throw new RuntimeException('ไม่พบคำสั่งที่ต้องการ');
    }
    $employee_id = post_integer('employee_id', $action === 'save' ? 0 : 1, PHP_INT_MAX);
    if ($action === 'delete') {
        if ($employee_id === (int) $employee['id']) {
            throw new RuntimeException('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้');
        }
        $statement = $pdo->prepare('SELECT id FROM `EMPLOYEE` WHERE id = ?');
        $statement->execute([$employee_id]);
        if (!$statement->fetch()) {
            throw new RuntimeException('ไม่พบบัญชีพนักงานที่ต้องการ');
        }
        $statement = $pdo->prepare('DELETE FROM `EMPLOYEE` WHERE id = ?');
        $statement->execute([$employee_id]);
        flash('ลบบัญชีพนักงานแล้ว');
    } else {
        $fields = ['username', 'first_name', 'last_name', 'email', 'system_role', 'password'];
        $values = [];
        for ($i = 0; $i < count($fields); $i++) {
            if (!isset($_POST[$fields[$i]]) || !is_string($_POST[$fields[$i]])) {
                throw new RuntimeException('กรุณากรอกข้อมูลพนักงานให้ครบถ้วน');
            }
            $values[$fields[$i]] = $fields[$i] === 'password' ? $_POST[$fields[$i]] : trim($_POST[$fields[$i]]);
        }
        if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/D', $values['username'])) {
            throw new RuntimeException('ชื่อผู้ใช้ต้องเป็นอักษรอังกฤษ ตัวเลข จุด ขีดกลาง หรือขีดล่าง 3-64 ตัว');
        }
        if ($values['first_name'] === '' || $values['last_name'] === '' || mb_strlen($values['first_name']) > 80 || mb_strlen($values['last_name']) > 80) {
            throw new RuntimeException('กรุณาตรวจสอบชื่อและนามสกุล');
        }
        if (strlen($values['email']) > 254 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('กรุณากรอกอีเมลให้ถูกต้อง');
        }
        if (!in_array($values['system_role'], ['STAFF', 'MANAGER'], true)) {
            throw new RuntimeException('กรุณาเลือกสิทธิ์การใช้งานให้ถูกต้อง');
        }
        if ($employee_id === (int) $employee['id'] && $values['system_role'] === 'STAFF') {
            throw new RuntimeException('ไม่สามารถลดสิทธิ์บัญชีที่กำลังใช้งานอยู่ได้');
        }
        $password = $values['password'];
        if (($employee_id === 0 || $password !== '') && (strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0"))) {
            throw new RuntimeException('กรุณาใช้รหัสผ่านอักษรอังกฤษ ตัวเลข หรือสัญลักษณ์ ความยาว 8-72 ตัว');
        }
        if ($employee_id === 0) {
            $statement = $pdo->prepare("INSERT INTO `EMPLOYEE`
                (registration_date, username, password_hash, first_name, last_name, email, telephone_number, address, national_id, position, system_role, hire_date)
                VALUES (CURDATE(), ?, ?, ?, ?, ?, '', '', '', '', ?, CURDATE())");
            $statement->execute([$values['username'], password_hash($password, PASSWORD_DEFAULT), $values['first_name'], $values['last_name'], $values['email'], $values['system_role']]);
            $employee_id = (int) $pdo->lastInsertId();
            flash('เพิ่มบัญชีพนักงานแล้ว');
        } else {
            $statement = $pdo->prepare('SELECT id FROM `EMPLOYEE` WHERE id = ?');
            $statement->execute([$employee_id]);
            if (!$statement->fetch()) {
                throw new RuntimeException('ไม่พบบัญชีพนักงานที่ต้องการแก้ไข');
            }
            if ($password === '') {
                $statement = $pdo->prepare('UPDATE `EMPLOYEE` SET username = ?, first_name = ?, last_name = ?, email = ?, system_role = ? WHERE id = ?');
                $statement->execute([$values['username'], $values['first_name'], $values['last_name'], $values['email'], $values['system_role'], $employee_id]);
            } else {
                $statement = $pdo->prepare('UPDATE `EMPLOYEE` SET username = ?, first_name = ?, last_name = ?, email = ?, system_role = ?, password_hash = ? WHERE id = ?');
                $statement->execute([$values['username'], $values['first_name'], $values['last_name'], $values['email'], $values['system_role'], password_hash($password, PASSWORD_DEFAULT), $employee_id]);
            }
            flash('บันทึกบัญชีพนักงานแล้ว');
        }
    }
} catch (PDOException $error) {
    $save_failed = true;
    flash('บันทึกบัญชีไม่สำเร็จ ชื่อผู้ใช้หรืออีเมลอาจซ้ำกับบัญชีอื่น กรุณาตรวจสอบอีกครั้ง', 'error');
} catch (RuntimeException $error) {
    $save_failed = true;
    flash($error->getMessage(), 'error');
}
if ($save_failed && $action === 'save') {
    $_SESSION['employee_form'] = ['id' => $employee_id];
    $fields = ['username', 'first_name', 'last_name', 'email', 'system_role'];
    for ($i = 0; $i < count($fields); $i++) {
        if (isset($values[$fields[$i]])) {
            $_SESSION['employee_form'][$fields[$i]] = $values[$fields[$i]];
        }
    }
    redirect_to('staff/employees.php' . ($employee_id > 0 ? '?edit=' . $employee_id : '') . '#employee-form');
}
unset($_SESSION['employee_form']);
redirect_to('staff/employees.php#' . ($action === 'delete' ? 'employee-list' : 'employee-' . $employee_id));
