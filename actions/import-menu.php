<?php
require_once '../connection.php';
require_once '../functions.php';
require_once '../includes/auth.php';
$employee = require_employee($pdo);
require_manager($employee);
check_post();

try {
    $file = $_FILES['menu_file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 1024 * 1024) {
        throw new RuntimeException('กรุณาเลือกไฟล์ JSON ขนาดไม่เกิน 1 MB');
    }
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'json') {
        throw new RuntimeException('กรุณาเลือกไฟล์นามสกุล .json');
    }

    // อ่านไฟล์ชั่วคราวที่ PHP ได้รับ แล้วแปลง JSON เป็น array
    $json = file_get_contents($file['tmp_name']);
    $menu = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($menu) || !array_is_list($menu) || count($menu) === 0) {
        throw new RuntimeException('ข้อมูล JSON ต้องเป็นรายการอาหารในรูปแบบ array และมีอย่างน้อย 1 รายการ');
    }

    $pdo->beginTransaction();
    $ids = [];
    $statement = $pdo->prepare(
        'INSERT INTO `MENU`
         (id, name, category, description, price, is_available, is_deleted)
         VALUES (?, ?, ?, ?, ?, 1, 0)'
    );
    for ($i = 0; $i < count($menu); $i++) {
        $food = $menu[$i];
        $row = $i + 1;
        if (!is_array($food) || !isset($food['id'], $food['name'], $food['category'], $food['description'], $food['price'])) {
            throw new RuntimeException('รายการที่ ' . $row . ' ต้องมี id, name, category, description และ price');
        }
        if (!is_int($food['id']) || $food['id'] < 1 || $food['id'] > 4294967295 || in_array($food['id'], $ids, true)) {
            throw new RuntimeException('รายการที่ ' . $row . ' มี ID ไม่ถูกต้องหรือซ้ำในไฟล์');
        }
        if (!is_string($food['name']) || trim($food['name']) === '' || mb_strlen($food['name']) > 120 ||
            !is_string($food['category']) || trim($food['category']) === '' || mb_strlen($food['category']) > 80 ||
            !is_string($food['description']) || mb_strlen($food['description']) > 500) {
            throw new RuntimeException('กรุณาตรวจสอบชื่อ หมวดหมู่ และรายละเอียดของรายการที่ ' . $row);
        }
        if ((!is_int($food['price']) && !is_float($food['price'])) || !preg_match('/\A\d{1,6}(\.\d{1,2})?\z/', (string) $food['price'])) {
            throw new RuntimeException('ราคาในรายการที่ ' . $row . ' ต้องอยู่ระหว่าง 0 ถึง 999999.99 บาท และมีทศนิยมไม่เกิน 2 ตำแหน่ง');
        }

        $id = $food['id'];
        $name = trim($food['name']);
        $category = trim($food['category']);
        $description = trim($food['description']);
        $price = (string) $food['price'];

        $statement->bindParam(1, $id, PDO::PARAM_INT);
        $statement->bindParam(2, $name);
        $statement->bindParam(3, $category);
        $statement->bindParam(4, $description);
        $statement->bindParam(5, $price);
        $statement->execute();

        $ids[] = $id;
    }
    $pdo->commit();
    flash('นำเข้าเมนูสำเร็จ ' . count($menu) . ' รายการ');
    redirect_to('staff/menu.php');
} catch (PDOException $error) {
    $message = $error->getCode() === '23000' ? 'มี ID อาหารอยู่ในฐานข้อมูลแล้ว กรุณาตรวจสอบไฟล์ก่อนนำเข้า' : 'นำเข้าเมนูไม่สำเร็จ กรุณาลองใหม่';
} catch (RuntimeException $error) {
    $message = $error->getMessage();
}
if ($pdo->inTransaction()) {
    $pdo->rollBack();
}
flash($message . ' ระบบยกเลิกการนำเข้าทั้งไฟล์', 'error');
redirect_to('staff/menu.php?import=1');
