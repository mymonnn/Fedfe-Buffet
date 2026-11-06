<?php
// คืนรอบของโต๊ะ โดยไม่สร้างตัวแปรแฝงให้หน้าที่เรียกใช้
function load_customer_visit(PDO $pdo, int $table_number, int $requested_visit, bool $has_visit): array|false {
    if ($table_number < 1 || $table_number > 9) { return false; }
    $visit = false;
    if ($has_visit) {
        // ประวัติของรอบเดิมอ่านได้เฉพาะเบราว์เซอร์ที่เคยเปิดรอบนั้น
        if ($requested_visit > 0 && ($_SESSION['customer_visits'][$requested_visit] ?? null) === $table_number) {
            $statement = $pdo->prepare('SELECT s.*, t.number AS table_number, t.is_available AS table_open FROM `SESSION` s JOIN `TABLE` t ON t.id = s.table_id WHERE s.id = ? AND t.number = ?');
            $statement->execute([$requested_visit, $table_number]);
            $visit = $statement->fetch(PDO::FETCH_ASSOC);
        } else {
            $current = active_visit($pdo, $table_number);
            if ($current && (int) $current['id'] === $requested_visit) { $visit = $current; }
        }
    } else {
        $visit = active_visit($pdo, $table_number);
    }
    if (!$visit) { return false; }
    $visit_id = (int) $visit['id'];
    $_SESSION['customer_visits'][$visit_id] = $table_number;
    if ($visit['completed_at']) { unset($_SESSION['carts'][$visit_id]); }
    return $visit;
}
