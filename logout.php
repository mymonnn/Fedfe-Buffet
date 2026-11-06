<?php

    require_once "functions.php";

    check_post();
    if (session_id() !== '') {
        unset($_SESSION['employee_id']);
        $_SESSION = [];
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => BASE_URL . '/', 'httponly' => true, 'samesite' => 'Lax']);
        session_destroy();
    }
    redirect_to('login.php');
