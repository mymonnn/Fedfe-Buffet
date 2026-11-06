<?php
require_once '../connection.php';
require_once '../functions.php';
require_once '../includes/auth.php';

$ajax_request = isset($_POST['ajax']) && $_POST['ajax'] === '1';
$employee = require_employee($pdo, $ajax_request);
check_post();
$action = '';
$menu_id = 0;
$values = [];
$save_failed = false;
$staged_photo = '';
$photo_target = '';
$photo_backup = '';
$photo_installed = false;

try {
    $action = $_POST['action'] ?? '';
    if (!is_string($action) || !in_array($action, ['save', 'availability', 'delete'], true)) {
        throw new RuntimeException('ไม่พบคำสั่งที่ต้องการ');
    }
    if ($action !== 'availability') {
        require_manager($employee);
    }
    $menu_id = post_integer('menu_id', $action === 'save' ? 0 : 1, PHP_INT_MAX);

    if ($action === 'save') {
        $fields = ['name', 'category', 'price', 'description'];
        $values = [];
        for ($i = 0; $i < count($fields); $i++) {
            if (!isset($_POST[$fields[$i]]) || !is_string($_POST[$fields[$i]])) {
                throw new RuntimeException('กรุณากรอกข้อมูลอาหารให้ครบถ้วน');
            }
            $values[$fields[$i]] = trim($_POST[$fields[$i]]);
        }
        if ($values['name'] === '' || mb_strlen($values['name']) > 120 || $values['category'] === '' || mb_strlen($values['category']) > 80) {
            throw new RuntimeException('กรุณาตรวจสอบชื่ออาหารและหมวดหมู่');
        }
        if (mb_strlen($values['description']) > 500) {
            throw new RuntimeException('รายละเอียดอาหารต้องไม่เกิน 500 ตัวอักษร');
        }
        if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/D', $values['price'])) {
            throw new RuntimeException('ราคาเพิ่มต้องเป็นจำนวนเงินตั้งแต่ 0 ถึง 999999.99 บาท');
        }
        $price = cents_decimal(decimal_cents($values['price']));
        if (!in_array($values['category'], menu_categories($pdo), true)) {
            throw new RuntimeException('กรุณาเลือกหมวดหมู่อาหาร');
        }

        $file = $_FILES['food_image'] ?? null;
        $has_upload = ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($has_upload) {
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) {
                throw new RuntimeException('อัปโหลดรูปไม่สำเร็จ กรุณาเลือกไฟล์ขนาดไม่เกิน 2 MB');
            }
            $image_info = @getimagesize($file['tmp_name']);
            if (($image_info[2] ?? 0) !== IMAGETYPE_JPEG || @file_get_contents($file['tmp_name'], false, null, -2) !== "\xFF\xD9") {
                throw new RuntimeException('กรุณาเลือกไฟล์รูป JPEG (.jpg หรือ .jpeg) ที่สมบูรณ์');
            }
        }

        $available = isset($_POST['is_available']) && $_POST['is_available'] === '1' ? 1 : 0;
        $pdo->beginTransaction();
        $saved_id = $menu_id;
        if ($menu_id === 0) {
            $statement = $pdo->prepare('INSERT INTO `MENU` (name, category, price, description, is_available) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$values['name'], $values['category'], $price, $values['description'], $available]);
            $saved_id = (int) $pdo->lastInsertId();
        } else {
            $statement = $pdo->prepare('SELECT id FROM `MENU` WHERE id = ? AND is_deleted = FALSE FOR UPDATE');
            $statement->execute([$menu_id]);
            if (!$statement->fetchColumn()) {
                throw new RuntimeException('ไม่พบอาหารที่ต้องการแก้ไข');
            }
            $statement = $pdo->prepare('UPDATE `MENU` SET name = ?, category = ?, price = ?, description = ?, is_available = ? WHERE id = ?');
            $statement->execute([$values['name'], $values['category'], $price, $values['description'], $available, $menu_id]);
        }

        if ($has_upload) {
            $photo_target = '../images/menu/' . $saved_id . '.jpg';
            $staged_photo = '../images/menu/.upload-' . bin2hex(random_bytes(12)) . '.tmp';
            if (!@move_uploaded_file($file['tmp_name'], $staged_photo)) {
                throw new RuntimeException('บันทึกไฟล์รูปไม่สำเร็จ กรุณาตรวจสอบโฟลเดอร์ images/menu');
            }
            // เก็บรูปเดิมไว้จนกว่าการบันทึกฐานข้อมูลจะเสร็จ
            if (is_file($photo_target)) {
                $photo_backup = $staged_photo . '.bak';
                if (!@copy($photo_target, $photo_backup)) {
                    throw new RuntimeException('ไม่สามารถสำรองรูปเดิมได้');
                }
                // ให้ URL ของรูปเปลี่ยนแม้บันทึกซ้ำภายในวินาทีเดียวกัน
                if (!@touch($staged_photo, max(time(), filemtime($photo_target) + 1))) {
                    throw new RuntimeException('ไม่สามารถเตรียมไฟล์รูปใหม่ได้');
                }
            }
            if (!@rename($staged_photo, $photo_target)) {
                throw new RuntimeException('ไม่สามารถแทนที่ไฟล์รูปได้');
            }
            $photo_installed = true;
        }

        $pdo->commit();
        flash($menu_id === 0 ? 'เพิ่มรายการอาหารแล้ว' : 'บันทึกรายการอาหารแล้ว');
        $menu_id = $saved_id;
    } else {
        $statement = $pdo->prepare('SELECT id FROM `MENU` WHERE id = ? AND is_deleted = FALSE');
        $statement->execute([$menu_id]);
        if (!$statement->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException('ไม่พบรายการอาหารที่ต้องการ');
        }
        if ($action === 'availability') {
            $available = post_integer('is_available', 0, 1);
            $statement = $pdo->prepare('UPDATE `MENU` SET is_available = ? WHERE id = ? AND is_deleted = FALSE');
            $statement->execute([$available, $menu_id]);
            if (!$ajax_request) flash($available ? 'เปิดให้สั่งอาหารรายการนี้แล้ว' : 'ทำเครื่องหมายว่าอาหารหมดแล้ว');
        } else {
            $statement = $pdo->prepare('UPDATE `MENU` SET is_deleted = TRUE, is_available = FALSE WHERE id = ?');
            $statement->execute([$menu_id]);
            flash('ลบรายการอาหารออกจากเมนูแล้ว ประวัติออร์เดอร์ยังคงอยู่');
        }
    }
} catch (PDOException $error) {
    $save_failed = true;
    flash('บันทึกข้อมูลอาหารไม่สำเร็จ กรุณาตรวจสอบข้อมูลและลองอีกครั้ง', 'error');
} catch (RuntimeException $error) {
    $save_failed = true;
    flash($error->getMessage(), 'error');
}
if ($save_failed && $photo_installed) {
    if ($photo_backup !== '') {
        if (!@rename($photo_backup, $photo_target)) {
            error_log('Could not restore menu photo backup: ' . $photo_backup);
            $photo_backup = '';
        }
    } else {
        @unlink($photo_target);
    }
}
if ($staged_photo !== '' && is_file($staged_photo)) {
    @unlink($staged_photo);
}
if ($photo_backup !== '' && is_file($photo_backup)) {
    @unlink($photo_backup);
}
if ($save_failed && $pdo->inTransaction()) {
    $pdo->rollBack();
}
if ($ajax_request) {
    header('Content-Type: text/plain; charset=UTF-8');
    if ($save_failed) {
        http_response_code(400);
        echo $_SESSION['flash']['message'] ?? 'บันทึกไม่สำเร็จ';
        unset($_SESSION['flash']);
    }
    exit;
}
if ($save_failed && $action === 'save') {
    $_SESSION['menu_form'] = ['id' => $menu_id, 'is_available' => isset($_POST['is_available']) && $_POST['is_available'] === '1' ? 1 : 0];
    $fields = ['name', 'category', 'price', 'description'];
    for ($i = 0; $i < count($fields); $i++) {
        if (isset($values[$fields[$i]])) {
            $_SESSION['menu_form'][$fields[$i]] = $values[$fields[$i]];
        }
    }
    redirect_to('staff/menu.php' . ($menu_id > 0 ? '?edit=' . $menu_id : '') . '#menu-form');
}
unset($_SESSION['menu_form']);
redirect_to('staff/menu.php#' . ($action === 'delete' ? 'menu-list' : 'food-' . $menu_id));
