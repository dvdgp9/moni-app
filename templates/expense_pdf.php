<?php
use Moni\Repositories\ExpensesRepository;
use Moni\Services\ExpenseDocumentService;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    echo 'Gasto no encontrado.';
    exit;
}

$expense = ExpensesRepository::find($id);
if (!$expense || empty($expense['pdf_path'])) {
    http_response_code(404);
    echo 'Documento no disponible.';
    exit;
}

$fullPath = dirname(__DIR__) . '/' . ltrim((string)$expense['pdf_path'], '/');
if (!is_file($fullPath)) {
    http_response_code(404);
    echo 'El archivo no existe en el servidor.';
    exit;
}

$mimeType = ExpenseDocumentService::mimeTypeForPath($fullPath);
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string)filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;
