<?php
use Moni\Repositories\ExpensesRepository;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(404);
    echo 'Gasto no encontrado.';
    exit;
}

$expense = ExpensesRepository::find($id);
if (!$expense || empty($expense['pdf_path'])) {
    http_response_code(404);
    echo 'PDF no disponible.';
    exit;
}

$fullPath = dirname(__DIR__) . '/' . ltrim((string)$expense['pdf_path'], '/');
if (!is_file($fullPath)) {
    http_response_code(404);
    echo 'El archivo PDF no existe en el servidor.';
    exit;
}

header('Content-Type: application/pdf');
header('Content-Length: ' . (string)filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;
