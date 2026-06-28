<?php
require_once 'trava.php';
require_once 'conexao.php';

header('Content-Type: application/json');

if (empty($usuario_id)) {
    echo json_encode(['sucesso' => false, 'erro' => 'Usuário não logado']);
    exit;
}

try {
    // Atualiza todos os registros do usuário para faturar = 0
    $stmt = $conn->prepare("UPDATE viagens SET faturar = 0 WHERE usuario_id = :usuario_id");
    $stmt->execute([':usuario_id' => $usuario_id]);
    
    echo json_encode(['sucesso' => true]);
} catch (Exception $e) {
    echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
}
?>
