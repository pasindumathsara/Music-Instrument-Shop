<?php
// ============================================================
// includes/functions.php — Global helper functions
// ============================================================

// ── Auth Helpers ────────────────────────────────────────────

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isStaff(): bool {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'staff';
}

function requireLogin(string $redirect = ''): void {
    if (!isLoggedIn()) {
        $target = $redirect ?: BASE_URL . '/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']);
        header("Location: $target");
        exit();
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header("Location: " . BASE_URL . "/home.php");
        exit();
    }
}

function requireManagement(): void {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin' && $_SESSION['user_role'] !== 'staff') {
        header("Location: " . BASE_URL . "/home.php");
        exit();
    }
}

// ── Sanitization ─────────────────────────────────────────────

function sanitize(string $data): string {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// ── Formatting ───────────────────────────────────────────────

function formatPrice(float $price): string {
    return '£' . number_format($price, 2);
}

function formatDate(string $date): string {
    return date('M j, Y', strtotime($date));
}

// ── Shipping Calculation ─────────────────────────────────────

function calculateShipping(float $subtotal, ?mysqli $conn = null): float {
    if ($subtotal >= (defined('SHIPPING_THRESHOLD') ? SHIPPING_THRESHOLD : 100)) return 0.00;
    
    $shipping = 0.0;
    if (!empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $id => $item) {
            $cost = (float)($item['shipping_cost'] ?? 0);
            
            // If DB connection is provided, get the LATEST cost from the products table
            if ($conn) {
                $st = $conn->prepare("SELECT shipping_cost FROM products WHERE id = ?");
                $st->bind_param("i", $id);
                $st->execute();
                $res = $st->get_result()->fetch_assoc();
                if ($res) {
                    $cost = (float)$res['shipping_cost'];
                    // Update session so UI stays in sync
                    $_SESSION['cart'][$id]['shipping_cost'] = $cost;
                }
            }
            $shipping += $cost * (int)$item['quantity'];
        }
    }
    
    // Fallback to legacy global logic ONLY if no per-product costs were found
    if ($shipping == 0 && $subtotal < (defined('SHIPPING_THRESHOLD') ? SHIPPING_THRESHOLD : 100)) {
        return defined('SHIPPING_COST') ? SHIPPING_COST : 0.00;
    }
    
    return $shipping;
}

function isFreeShipping(float $subtotal): bool {
    return $subtotal >= SHIPPING_THRESHOLD;
}

/**
 * Returns true if every product in the cart is a digital product (no shipping needed).
 * Requires DB connection $conn to be in scope.
 */
function isCartAllDigital(mysqli $conn): bool {
    if (empty($_SESSION['cart'])) return false;
    $ids = array_keys($_SESSION['cart']);
    if (empty($ids)) return false;
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $conn->prepare("SELECT COUNT(*) FROM products WHERE id IN ($ph) AND product_type != 'digital'");
    $st->bind_param(str_repeat('i', count($ids)), ...$ids);
    $st->execute();
    return $st->get_result()->fetch_row()[0] === 0;
}


// ── Cart Helpers ─────────────────────────────────────────────

function getCartCount(): int {
    if (empty($_SESSION['cart'])) return 0;
    return array_sum(array_column($_SESSION['cart'], 'quantity'));
}

function getCartSubtotal(): float {
    if (empty($_SESSION['cart'])) return 0.0;
    $total = 0.0;
    foreach ($_SESSION['cart'] as $item) {
        $total += (float)$item['price'] * (int)$item['quantity'];
    }
    return $total;
}

// ── Product / Review Helpers ──────────────────────────────────

function hasPurchasedProduct(mysqli $conn, int $userId, int $productId): bool {
    $stmt = $conn->prepare("
        SELECT oi.id FROM order_items oi
        INNER JOIN orders o ON oi.order_id = o.id
        WHERE o.user_id = ? AND oi.product_id = ?
          AND o.status IN ('paid','processing','shipped','delivered')
        LIMIT 1
    ");
    $stmt->bind_param("ii", $userId, $productId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function hasReviewedProduct(mysqli $conn, int $userId, int $productId): bool {
    $stmt = $conn->prepare(
        "SELECT id FROM reviews WHERE user_id = ? AND product_id = ? LIMIT 1"
    );
    $stmt->bind_param("ii", $userId, $productId);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function getProductRating(mysqli $conn, int $productId): array {
    $stmt = $conn->prepare(
        "SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total
         FROM reviews WHERE product_id = ?"
    );
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return [
        'avg'   => (float)($row['avg_rating'] ?? 0),
        'total' => (int)($row['total'] ?? 0),
    ];
}

// ── Star Renderer ─────────────────────────────────────────────

function renderStars(float $rating, bool $interactive = false): string {
    $html = '<span class="stars">';
    for ($i = 1; $i <= 5; $i++) {
        $cls = ($i <= round($rating)) ? 'star filled' : 'star empty';
        $html .= "<i class=\"$cls\">★</i>";
    }
    $html .= '</span>';
    return $html;
}

// ── Flash Messages ────────────────────────────────────────────

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function showFlash(): string {
    $flash = getFlash();
    if (!$flash) return '';
    $type = htmlspecialchars($flash['type']);
    $msg  = htmlspecialchars($flash['message']);
    return "<div class=\"alert alert-{$type}\">$msg</div>";
}

// ── Image Upload ──────────────────────────────────────────────

function uploadProductImage(array $file): array {
    $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $maxBytes = 5 * 1024 * 1024; // 5 MB

    if (!in_array($file['type'], $allowed)) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, WEBP or GIF images are allowed.'];
    }
    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'Image must be under 5 MB.'];
    }

    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $name = 'product_' . uniqid() . '.' . $ext;
    $dest = UPLOAD_DIR . $name;

    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => true, 'filename' => $name];
    }
    return ['ok' => false, 'error' => 'File upload failed.'];
}

// ── Product Image Renderer ────────────────────────────────────

function productImageTag(string|null $image, string $alt = '', string $class = ''): string {
    if ($image && file_exists(UPLOAD_DIR . $image)) {
        $src = UPLOAD_URL . htmlspecialchars($image);
        return "<img src=\"$src\" alt=\"" . htmlspecialchars($alt) . "\" class=\"$class\">";
    }
    return "<div class=\"no-image\">🎵</div>";
}

// ── Order Status Badge ────────────────────────────────────────

function statusBadge(string $status): string {
    $map = [
        'pending'    => 'badge-pending',
        'paid'       => 'badge-paid',
        'processing' => 'badge-processing',
        'shipped'    => 'badge-shipped',
        'delivered'  => 'badge-delivered',
        'cancelled'  => 'badge-cancelled',
    ];
    $class = $map[$status] ?? 'badge-pending';
    return "<span class=\"badge $class\">" . ucfirst(htmlspecialchars($status)) . "</span>";
}
