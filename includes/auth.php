<?php
function require_employee(PDO $pdo, bool $ajax_request = false): array {
    if (isset($_SESSION['employee_id'])) {
        $statement = $pdo->prepare('SELECT * FROM `EMPLOYEE` WHERE id = ? AND is_deactivated = 0');
        $statement->execute([$_SESSION['employee_id']]);
        $employee = $statement->fetch(PDO::FETCH_ASSOC);
        if ($employee) { return $employee; }
    }
    unset($_SESSION['employee_id']);
    if ($ajax_request) {
        http_response_code(401);
        exit('กรุณาเข้าสู่ระบบอีกครั้ง');
    }
    redirect_to('login.php');
}
