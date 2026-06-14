<?php
/**
 * log_error.php — Logs client-side JavaScript errors.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    $logFile = __DIR__ . '/browser_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['user'] ?? 'anonymous';
    $role = $_SESSION['role'] ?? 'none';
    
    $logMsg = sprintf(
        "[%s] [User: %s, Role: %s] %s\n",
        $timestamp,
        $user,
        $role,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    
    file_put_contents($logFile, $logMsg, FILE_APPEND);
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true]);
exit;
