<?php
// ─── Auth Guard ───────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user'])) {
    header('Location: /eco_sys/landing.php');
    exit;
}

// Expose helpers
$currentUser = $_SESSION['user'];
$currentRole = $_SESSION['role'] ?? 'user';
$isAdmin     = ($currentRole === 'admin');

// Tier logic based on role
$isPro  = ($currentRole === 'pro' || $currentRole === 'admin');
$isPlus = ($currentRole === 'plus' || $isPro); // Plus includes Pro features except bot
$isFree = (!$isPlus && !$isPro); // Free tier

function requireAdmin(): void {
    global $isAdmin;
    if (!$isAdmin) {
        http_response_code(403);
        die(json_encode(['error' => '權限不足']));
    }
}
