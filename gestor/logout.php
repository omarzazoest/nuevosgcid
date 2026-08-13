<?php
session_start();
require_once __DIR__ . '/../config/db.php';
$_SESSION = [];
session_destroy();
header('Location: ' . app_url('gestor/login.php'));
exit;
