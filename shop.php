<?php
// shop.php → home.php redirect stub
// Preserves any existing query string (search, cat, sort, page)
require_once 'includes/db.php';
require_once 'includes/functions.php';

$qs = $_SERVER['QUERY_STRING'] ?? '';
header("Location: " . BASE_URL . "/home.php" . ($qs ? "?$qs" : ''));
exit();
