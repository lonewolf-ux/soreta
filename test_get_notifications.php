<?php
require_once '../includes/config.php';
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['csrf_token'] = CSRFProtection::generateToken();
include 'get_notifications.php';
?>
