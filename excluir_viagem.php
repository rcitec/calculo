<?php
require_once 'trava.php'; 
require_once 'conexao.php';

// Verifica se recebeu o ID da viagem a ser excluída
if (isset($_GET['id'])) {
    $viagem_id = trim($_GET['id']);

    try {
        // SEGURANÇA: O DELETE exige o ID da viagem AND o ID do utilizador logado
        $sql = "DELETE FROM viagens WHERE id = :id AND usuario_id = :usuario_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $viagem_id,
            ':usuario_id' => $usuario_id
        ]);

        // Verificação para AJAX
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
        
        if ($isAjax) {
            http_response_code(200);
            exit;
        } else {
            header("Location: historico.php");
            exit;
        }

    } catch (Exception $e) {
        // Se der erro no banco, retorna erro 500 para o navegador
        http_response_code(500);
        echo "Erro ao excluir: " . $e->getMessage();
        exit;
    }
} else {
    // Se não veio ID, volta para o histórico
    header("Location: historico.php");
    exit;
}
?>
