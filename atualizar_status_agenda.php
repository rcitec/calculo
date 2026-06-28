<?php
require_once 'trava.php';
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $viagem_id = isset($_POST['viagem_id']) ? intval($_POST['viagem_id']) : 0;
    $viagem    = isset($_POST['viagem']) ? intval($_POST['viagem']) : 0;

    if ($viagem_id > 0) {
        try {
            // Atualiza apenas a coluna viagem de forma direta e rápida
            $sql = "UPDATE viagens SET viagem = :viagem WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':viagem' => $viagem,
                ':id'     => $viagem_id
            ]);

            echo json_encode(['sucesso' => true]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
            exit;
        }
    }
}
echo json_encode(['sucesso' => false, 'erro' => 'Requisição inválida']);

