<?php
/**
 * serve_pdf.php — Secure PDF file server endpoint.
 *
 * Only Pro users may access. Serves PDFs from the uploads/ directory
 * with support for HTTP Range requests (required by PDF.js for large files).
 *
 * Usage: api/serve_pdf.php?file=1101_台泥_2023.pdf
 */

require_once dirname(__DIR__) . '/auth_check.php';
require_once dirname(__DIR__) . '/config.php';

// ── Auth: Pro users only ───────────────────────────────────────────
if (!isset($isPro) || !$isPro) {
    http_response_code(403);
    echo json_encode(['error' => '僅限 Pro 方案用戶存取 PDF 檔案。']);
    exit;
}

// ── Validate input ─────────────────────────────────────────────────
$filename = $_GET['file'] ?? '';

if ($filename === '') {
    http_response_code(400);
    echo json_encode(['error' => '缺少 file 參數。']);
    exit;
}

// Security: block path traversal
if (preg_match('/\.\.|[\/\\\\]/', $filename)) {
    http_response_code(403);
    echo json_encode(['error' => '非法的檔案名稱。']);
    exit;
}

// Only allow .pdf extension
if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(400);
    echo json_encode(['error' => '僅支援 PDF 檔案。']);
    exit;
}

// ── Resolve and verify file path ───────────────────────────────────
$uploadsDir = dirname(__DIR__) . '/uploads/';
$filePath   = $uploadsDir . $filename;
$realPath   = realpath($filePath);

// Ensure the resolved path is within uploads/
if ($realPath === false || strpos($realPath, realpath($uploadsDir)) !== 0) {
    http_response_code(404);
    echo json_encode(['error' => '檔案不存在。']);
    exit;
}

$fileSize = filesize($realPath);

// ── Serve the file ─────────────────────────────────────────────────
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . rawurlencode($filename) . '"');
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=3600');

// ── Handle HTTP Range requests (PDF.js partial content) ────────────
if (isset($_SERVER['HTTP_RANGE'])) {
    // Parse Range header, e.g. "bytes=0-1023"
    if (preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $start = (int) $m[1];
        $end   = ($m[2] !== '') ? (int) $m[2] : $fileSize - 1;

        // Clamp end
        if ($end >= $fileSize) {
            $end = $fileSize - 1;
        }

        if ($start > $end || $start >= $fileSize) {
            http_response_code(416); // Range Not Satisfiable
            header("Content-Range: bytes */$fileSize");
            exit;
        }

        $length = $end - $start + 1;

        http_response_code(206); // Partial Content
        header("Content-Range: bytes $start-$end/$fileSize");
        header("Content-Length: $length");

        $fp = fopen($realPath, 'rb');
        fseek($fp, $start);
        $remaining = $length;
        while ($remaining > 0 && !feof($fp)) {
            $chunk = fread($fp, min(8192, $remaining));
            echo $chunk;
            $remaining -= strlen($chunk);
        }
        fclose($fp);
        exit;
    }
}

// Full file response
header("Content-Length: $fileSize");
readfile($realPath);
exit;
