<?php
date_default_timezone_set('Asia/Bangkok');
define('BASE_URL', '/fedfebuffet');

// PHP session เก็บผู้เข้าสู่ระบบและ token ของฟอร์ม ไม่ใช่รอบรับประทานในฐานข้อมูล
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => BASE_URL . '/']);
    session_start();
}
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(24));
}

function h(mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_field(): void {
    echo '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '">';
}

function check_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: text/plain; charset=UTF-8');
        exit('กรุณาส่งข้อมูลผ่านแบบฟอร์ม');
    }
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('แบบฟอร์มหมดอายุ กรุณากลับไปโหลดหน้าใหม่');
    }
}

function redirect_to(string $path): never {
    header('Location: ' . BASE_URL . '/' . $path, true, 303);
    exit;
}

function flash(string $message, string $type = 'success'): void {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function post_integer(string $key, int $min, int $max): int {
    $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value === null || $value < $min || $value > $max) {
        throw new RuntimeException('ข้อมูลจำนวนหรือหมายเลขไม่ถูกต้อง');
    }
    return $value;
}

function is_manager(array|false $employee): bool {
    return $employee && $employee['system_role'] === 'MANAGER';
}

function require_manager(array $employee): void {
    if (!is_manager($employee)) {
        http_response_code(403);
        exit('หน้านี้สำหรับผู้จัดการร้านเท่านั้น');
    }
}

function categories(PDO $pdo): array {
    $statement = $pdo->query("SELECT category FROM `MENU` WHERE is_deleted = 0 GROUP BY category
        ORDER BY CASE category
            WHEN 'เนื้อ' THEN 1
            WHEN 'หมู' THEN 2
            WHEN 'ไก่' THEN 3
            WHEN 'ทะเล' THEN 4
            WHEN 'ผักและเห็ด' THEN 5
            WHEN 'เส้นและข้าว' THEN 6
            WHEN 'น้ำซุปและน้ำจิ้ม' THEN 7
            WHEN 'ของหวาน' THEN 8
            WHEN 'เครื่องดื่ม' THEN 9
            ELSE 10 END, category");
    $result = [];
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $result[] = $row['category'];
    }
    return $result;
}

function menu_categories(PDO $pdo): array {
    $standard = ['เนื้อ', 'หมู', 'ไก่', 'ทะเล', 'ผักและเห็ด', 'เส้นและข้าว', 'น้ำซุปและน้ำจิ้ม', 'ของหวาน', 'เครื่องดื่ม'];
    return array_values(array_unique(array_merge($standard, categories($pdo))));
}

function menu_image_url(int $food_id): string {
    $path = 'images/menu/' . $food_id . '.jpg';
    $file = dirname(__FILE__) . '/' . $path;
    if (!is_file($file)) {
        return BASE_URL . '/images/placeholder.svg';
    }
    return BASE_URL . '/' . $path . '?v=' . filemtime($file) . '-' . filesize($file);
}

function active_visit(PDO $pdo, int $tableNumber): array|false {
    if ($tableNumber < 1 || $tableNumber > 9) { return false; }
    $statement = $pdo->prepare('SELECT s.*, t.number AS table_number, t.is_available AS table_open FROM `SESSION` s JOIN `TABLE` t ON t.id = s.table_id WHERE t.number = ? AND t.is_available = 1 AND s.completed_at IS NULL LIMIT 1');
    $statement->execute([$tableNumber]);
    return $statement->fetch(PDO::FETCH_ASSOC);
}

// เรียกหลัง beginTransaction(): ทุกการเปลี่ยนรอบบริการล็อกโต๊ะก่อนเสมอ
function lock_visit(PDO $pdo, int $tableNumber, int $visitId): array {
    if ($tableNumber < 1 || $tableNumber > 9) {
        throw new RuntimeException('หมายเลขโต๊ะต้องเป็น 1-9');
    }
    $statement = $pdo->prepare('SELECT * FROM `TABLE` WHERE number = ? FOR UPDATE');
    $statement->execute([$tableNumber]);
    $table = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$table || !$table['is_available']) {
        throw new RuntimeException('โต๊ะนี้ปิดให้บริการแล้ว กรุณาติดต่อพนักงาน');
    }
    $statement = $pdo->prepare('SELECT * FROM `SESSION` WHERE table_id = ? AND completed_at IS NULL FOR UPDATE');
    $statement->execute([$table['id']]);
    $visit = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$visit || (int) $visit['id'] !== $visitId) {
        throw new RuntimeException('รอบบริการนี้สิ้นสุดแล้ว กรุณาเปิดลิงก์โต๊ะอีกครั้ง');
    }
    return $visit;
}

// คิดเงินเป็นสตางค์จำนวนเต็ม ป้องกันการปัดเศษของเลขทศนิยม
function decimal_cents(string|int $amount): int {
    $amount = (string) $amount;
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amount)) {
        throw new RuntimeException('ราคาไม่ถูกต้อง');
    }
    $parts = explode('.', $amount);
    return ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
}

function cents_decimal(int $amount): string {
    return intdiv($amount, 100) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
}

function money(string|int $amount): string {
    return number_format(decimal_cents($amount) / 100, 2);
}

function bill_details(PDO $pdo, int $visitId): array {
    $statement = $pdo->prepare('SELECT s.*, t.number AS table_number FROM `SESSION` s JOIN `TABLE` t ON t.id = s.table_id WHERE s.id = ?');
    $statement->execute([$visitId]);
    $visit = $statement->fetch(PDO::FETCH_ASSOC);
    if (!$visit) {
        throw new RuntimeException('ไม่พบรอบบริการ');
    }
    $statement = $pdo->prepare("SELECT i.*, m.name, o.number AS order_number FROM `ITEM` i JOIN `MENU` m ON m.id = i.food_id JOIN `ORDER` o ON o.id = i.order_id WHERE o.session_id = ? AND o.status = 'SERVED' AND i.price > 0 ORDER BY o.id, i.id");
    $statement->execute([$visitId]);
    $lines = [];
    $extras = 0;
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $extras += decimal_cents($row['price']); // price เป็นราคารวมรายการแล้ว
        $lines[] = $row;
    }
    $statement = $pdo->prepare("SELECT COUNT(*) FROM `ORDER` WHERE session_id = ? AND status = 'PREPARING'");
    $statement->execute([$visitId]);
    $buffet = (int) $visit['people_amount'] * decimal_cents($visit['buffet_price']);
    return ['visit' => $visit, 'lines' => $lines, 'pending' => (int) $statement->fetchColumn(), 'buffet_cents' => $buffet, 'extras_cents' => $extras, 'total_cents' => $buffet + $extras];
}

function status_label(string $status): string {
    if ($status === 'SERVED') { return 'เสิร์ฟแล้ว'; }
    if ($status === 'CANCELLED') { return 'ยกเลิก'; }
    return 'รอจัดเตรียม';
}

// ตะกร้าของเบราว์เซอร์นี้แยกตามรอบบริการ ไม่ปะปนกับลูกค้ากลุ่มใหม่
function draft_count(int $visitId): int {
    $draft = $_SESSION['carts'][$visitId] ?? [];
    $ids = array_keys($draft);
    $total = 0;
    for ($i = 0; $i < count($ids); $i++) {
        $total += $draft[$ids[$i]]['quantity'];
    }
    return $total;
}

function cart_rows(PDO $pdo, int $visitId): array {
    $draft = $_SESSION['carts'][$visitId] ?? [];
    $ids = array_keys($draft);
    $rows = [];
    $statement = $pdo->prepare('SELECT * FROM `MENU` WHERE id = ?');
    for ($i = 0; $i < count($ids); $i++) {
        $statement->execute([$ids[$i]]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['quantity'] = $draft[$ids[$i]]['quantity'];
            $row['note'] = $draft[$ids[$i]]['note'];
            $rows[] = $row;
        }
    }
    return $rows;
}

function table_row(PDO $pdo, int $tableNumber): array|false {
    if ($tableNumber < 1 || $tableNumber > 9) { return false; }
    $statement = $pdo->prepare('SELECT t.number AS table_number, t.is_available AS table_open, s.id AS visit_id, s.session_number, s.people_amount, s.created_at, s.need_help, s.request_bill FROM `TABLE` t LEFT JOIN `SESSION` s ON s.table_id = t.id AND s.completed_at IS NULL WHERE t.number = ?');
    $statement->execute([$tableNumber]);
    return $statement->fetch(PDO::FETCH_ASSOC);
}
