<?php

declare(strict_types=1);

require_once __DIR__ . '/admin_auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido.');
}

$csrfToken = (string) ($_POST['csrf_token'] ?? '');
if (!hash_equals(adminCsrfToken(), $csrfToken)) {
    http_response_code(403);
    exit('Pedido inválido. Atualize a página e tente novamente.');
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$estado = (string) ($_POST['estado'] ?? '');
$estadosPermitidos = ['Pendente', 'Aprovada', 'Rejeitada', 'Concluída'];

if (!$id || !in_array($estado, $estadosPermitidos, true)) {
    http_response_code(422);
    exit('Estado ou inscrição inválidos.');
}

require_once __DIR__ . '/conexao.php';

$stmt = $conn->prepare('UPDATE inscricoes SET estado = ? WHERE id = ?');
$stmt->bind_param('si', $estado, $id);
$stmt->execute();
$stmt->close();
$conn->close();

header('Location: listar_inscricoes.php');
exit;