<?php
require_once __DIR__ . '/../auth.php';
unset($_SESSION['admin_id']);
session_regenerate_id(true);
header('Location: login.php');
exit;
