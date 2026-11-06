<?php
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=fedfebuffet;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->exec("SET time_zone = '+07:00'");
    } 
    catch (PDOException $e) {
        http_response_code(503);
        exit('เชื่อมต่อฐานข้อมูลไม่ได้ กรุณาเปิด MySQL ใน XAMPP และตรวจสอบการตั้งค่า connection.php');
    }
