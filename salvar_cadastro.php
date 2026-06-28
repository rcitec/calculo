<?php
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coletando e limpando os dados enviados
    $nome = trim($_POST['nome']);
    $telefone = trim($_POST['telefone']);
    $usuario = trim($_POST['usuario']);
    $senha = trim($_POST['senha']);
    
    $veiculo_nome = trim($_POST['veiculo']);
    $media_consumo = floatval($_POST['media_consumo']);
    
    $preco_combustivel = floatval($_POST['preco_combustivel']);
    $lucro_max = floatval($_POST['lucro_max']);
    $lucro_min = floatval($_POST['lucro_min']);

    try {
        // Inicia a transação no banco de dados
        $conn->beginTransaction();

        // 1. Criptografa a senha para segurança
        $senha_cripto = password_hash($senha, PASSWORD_DEFAULT);

        // 2. Insere na tabela 'usuarios'
        $sql_user = "INSERT INTO usuarios (usuario, senha, nome, telefone) VALUES (:usuario, :senha, :nome, :telefone)";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->execute([
            ':usuario' => $usuario,
            ':senha' => $senha_cripto,
            ':nome' => $nome,
            ':telefone' => $telefone
        ]);
        
        // Pega o ID do usuário que acabou de ser criado
        $usuario_id = $conn->lastInsertId();

        // 3. Insere na tabela 'veiculos' vinculando ao usuario_id
        $sql_veiculo = "INSERT INTO veiculos (veiculo, media_consumo, usuario_id) VALUES (:veiculo, :media_consumo, :usuario_id)";
        $stmt_veiculo = $conn->prepare($sql_veiculo);
        $stmt_veiculo->execute([
            ':veiculo' => $veiculo_nome,
            ':media_consumo' => $media_consumo,
            ':usuario_id' => $usuario_id
        ]);

        // Pega o ID o veículo que acabou de ser criado
        $veiculo_id = $conn->lastInsertId();

        // 4. Atualiza a tabela 'usuarios' para colocar o veiculo_id padrão dele
        $sql_update_user = "UPDATE usuarios SET veiculo_id = :veiculo_id WHERE id = :usuario_id";
        $stmt_update_user = $conn->prepare($sql_update_user);
        $stmt_update_user->execute([
            ':veiculo_id' => $veiculo_id,
            ':usuario_id' => $usuario_id
        ]);

        // 5. Insere na tabela 'configuracoes' vinculando usuário e veículo
        $sql_config = "INSERT INTO configuracoes (usuario_id, veiculo_id, preco_combustivel, lucro_max, lucro_min) 
                       VALUES (:usuario_id, :veiculo_id, :preco_combustivel, :lucro_max, :lucro_min)";
        $stmt_config = $conn->prepare($sql_config);
        $stmt_config->execute([
            ':usuario_id' => $usuario_id,
            ':veiculo_id' => $veiculo_id,
            ':preco_combustivel' => $preco_combustivel,
            ':lucro_max' => $lucro_max,
            ':lucro_min' => $lucro_min
        ]);

        // Se chegou até aqui sem erros, confirma todas as gravações no banco
        $conn->commit();

        echo "<link rel='stylesheet' href='estilo.css'>";
        echo "<div class='container'><div class='alert alert-success'>
                <h3>Sucesso! 👏👏👏</h3>
                <p>Usuário, veículo e configurações gravados com sucesso no banco de dados.</p>
                <p style='margin-top: 15px; font-size: 0.85rem; color: #555;'>Banco de dados validado!</p>
              </div></div>";

    } catch (Exception $e) {
        // Se der qualquer erro, desfaz tudo que foi tentado na transação
        $conn->rollBack();
        
        echo "<link rel='stylesheet' href='estilo.css'>";
        echo "<div class='container'><div class='alert alert-danger'>
                <h3>Erro ao cadastrar</h3>
                <p>" . $e->getMessage() . "</p>
                <p style='margin-top:15px;'><a href='cadastro.php'>Tentar Novamente</a></p>
              </div></div>";
    }
}
?>
