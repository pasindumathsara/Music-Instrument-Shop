<?php
// ──────────────────────────────────────────────
// Start session (safe to call multiple times)
// ──────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ──────────────────────────────────────────────
// Application Constants
// ──────────────────────────────────────────────
define('BASE_URL',          '/Music-Instrument-Shop');
define('UPLOAD_URL',        BASE_URL . '/uploads/');
define('UPLOAD_DIR',        dirname(__DIR__) . '/uploads/');
define('SHIPPING_THRESHOLD', 100.00);   // free shipping above this
define('SHIPPING_COST',      9.99);     // flat rate below threshold

// ──────────────────────────────────────────────
// Database Connection
// ──────────────────────────────────────────────
$host     = "localhost";
$user     = "root";
$password = "";
$database = "music_store";

$conn = new mysqli($host, $user, $password, $database);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("<div style='font-family:sans-serif;padding:40px;color:#dc2626;'>
         <h2>Database Connection Failed</h2>
         <p>" . htmlspecialchars($conn->connect_error) . "</p>
         <p>Make sure XAMPP MySQL service is running and the <code>music_store</code> database exists.</p>
         </div>");
}