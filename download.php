<?php
/**
 * download.php — Secure digital product download handler
 *
 * Only allows download if:
 *  - User is logged in
 *  - User has a PAID order containing this product
 *  - (or admin with ?admin=1)
 *
 * Files are served from uploads/digital/ and never exposed directly via URL.
 */
require_once 'includes/db.php';
require_once 'includes/functions.php';
requireLogin();

$uid  = (int)$_SESSION['user_id'];
$file = basename($_GET['file'] ?? '');   // sanitize: strip path separators
$pid  = (int)($_GET['product_id'] ?? 0); // product id (optional, for access check)

if (!$file) {
    setFlash('danger', 'No file specified.');
    header("Location: " . BASE_URL . "/orders.php"); exit();
}

$filePath = UPLOAD_DIR . 'digital/' . $file;

if (!file_exists($filePath)) {
    setFlash('danger', 'File not found.');
    header("Location: " . BASE_URL . "/orders.php"); exit();
}

// ── Admin preview bypass ────────────────────────────────────────
if (isset($_GET['admin']) && isAdmin()) {
    serveFile($filePath, $file);
}

// ── Find which product this digital file belongs to ─────────────
$pStmt = $conn->prepare("SELECT id FROM products WHERE digital_file=? AND product_type='digital' LIMIT 1");
$pStmt->bind_param("s", $file);
$pStmt->execute();
$product = $pStmt->get_result()->fetch_assoc();

if (!$product) {
    setFlash('danger', 'Digital product not found.');
    header("Location: " . BASE_URL . "/orders.php"); exit();
}
$productId = (int)$product['id'];

// ── Check user has a PAID order containing this product ─────────
$aStmt = $conn->prepare(
    "SELECT oi.id
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.user_id = ?
       AND oi.product_id = ?
       AND o.status IN ('paid','processing','shipped','delivered')
     LIMIT 1"
);
$aStmt->bind_param("ii", $uid, $productId);
$aStmt->execute();
if ($aStmt->get_result()->num_rows === 0) {
    setFlash('danger', 'You must purchase this product before downloading it.');
    header("Location: " . BASE_URL . "/product.php?id=$productId"); exit();
}

// ── Serve file ──────────────────────────────────────────────────
// Fetch display name and log download
$dpRow = null;
$st = $conn->prepare("SELECT original_name, id FROM digital_products WHERE file_name=? LIMIT 1");
$st->bind_param("s", $file);
$st->execute();
$dpRow = $st->get_result()->fetch_assoc();
$displayName = ($dpRow && $dpRow['original_name']) ? $dpRow['original_name'] : $file;

// Log download
$ip   = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$logP = $conn->prepare("INSERT INTO download_logs (user_id, product_id, file_name, ip_address) VALUES (?,?,?,?)");
$logP->bind_param("iiss", $uid, $productId, $file, $ip);
$logP->execute();

// Increment download count in digital_products table
$incr = $conn->prepare("UPDATE digital_products SET download_count = download_count + 1 WHERE file_name=?");
$incr->bind_param("s", $file);
$incr->execute();

serveFile($filePath, $displayName);



function serveFile(string $path, string $name): void {
    $mime = mime_content_type($path) ?: 'application/octet-stream';
    header('Content-Type: '       . $mime);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: '     . filesize($path));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    ob_clean(); flush();
    readfile($path);
    exit();
}
