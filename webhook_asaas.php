<?php
require_once 'conexao.php';
header('Content-Type: application/json');

$json_recebido = file_get_contents('php://input');
$data = json_decode($json_recebido, true);

if (!$data || !isset($data['event'])) {
    echo json_encode(['erro' => 'Dados ausentes']);
    exit;
}

if ($data['event'] === 'PAYMENT_RECEIVED' || $data['event'] === 'PAYMENT_CONFIRMED') {
    
    $usuario_id = $data['payment']['externalReference'] ?? null;
    $descricao = $data['payment']['description'] ?? '';

    // 1. Identifica o nome do plano para o banco de dados
$nome_plano = 'mensal'; // Valor padrão
$dias_adicionais = 30;

if (strpos($descricao, '7 Dias') !== false) {
    $dias_adicionais = 7;
    $nome_plano = '7 dias';
} elseif (strpos($descricao, '15 Dias') !== false) {
    $dias_adicionais = 15;
    $nome_plano = '15 dias';
} elseif (strpos($descricao, '3 Meses') !== false) {
    $dias_adicionais = 90;
    $nome_plano = '3 meses';
} elseif (strpos($descricao, '30 Dias') !== false) {
    $dias_adicionais = 30;
    $nome_plano = '30 dias';
}

    if ($usuario_id) {
        try {
            $stmt_user = $conn->prepare("SELECT validade_plano FROM usuarios WHERE id = :id LIMIT 1");
            $stmt_user->execute([':id' => $usuario_id]);
            $usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                // 2. Calcula a nova validade
                $hoje = new DateTime();
                $validade_antiga = !empty($usuario['validade_plano']) ? new DateTime($usuario['validade_plano']) : null;

                // Se o plano ainda é válido, soma a partir da validade antiga. Se venceu, soma a partir de hoje.
                if ($validade_antiga && $validade_antiga > $hoje) {
                    $validade_antiga->modify("+{$dias_adicionais} days");
                    $nova_validade = $validade_antiga->format('Y-m-d');
                } else {
                    $hoje->modify("+{$dias_adicionais} days");
                    $nova_validade = $hoje->format('Y-m-d');
                }

                // 3. Atualiza o banco
                $sql_update = "UPDATE usuarios SET 
                                plano = :plano, 
                                status_assinatura = 'ativo', 
                                validade_plano = :nova_validade 
                               WHERE id = :id";
                
                $stmt_up = $conn->prepare($sql_update);
                $stmt_up->execute([
                    ':plano' => $nome_plano,
                    ':nova_validade' => $nova_validade,
                    ':id' => $usuario_id
                ]);

                http_response_code(200);
                echo json_encode(['sucesso' => true]);
                exit;
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['erro' => $e->getMessage()]);
            exit;
        }
    }
}

http_response_code(200);
echo json_encode(['status' => 'Evento ignorado']);
exit;
