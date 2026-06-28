<?php
// Inicia a sessão se ainda não tiver sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Requer a conexão com o banco de dados
require_once 'conexao.php';

// VERIFICAÇÃO DE SEGURANÇA: Só o admin entra aqui
$usuario_id = $_SESSION['usuario_id'] ?? 0;
try {
    $sql_check = "SELECT nivel_acesso FROM usuarios WHERE id = :id LIMIT 1";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([':id' => $usuario_id]);
    $res_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$res_check || $res_check['nivel_acesso'] !== 'admin') {
        // Se não for admin, chuta de volta para o cálculo ou login
        header("Location: calculo.php");
        exit();
    }
} catch (Exception $e) {
    die("Erro de permissão.");
}

$mensagem_sucesso = '';
$mensagem_erro = '';

// PROCESSAR ATUALIZAÇÃO DO MOTORISTA VIA POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_usuario'])) {
    $id_motorista = intval($_POST['id_motorista']);
    $plano        = $_POST['plano'];
    $status       = $_POST['status_assinatura'];
    $validade     = $_POST['validade_plano'];

    // Se a validade for enviada vazia, gravamos como NULL no banco
    $validade_banco = !empty($validade) ? $validade . " 23:59:59" : null;

    try {
        $sql_update = "UPDATE usuarios SET 
                        plano = :plano, 
                        status_assinatura = :status, 
                        validade_plano = :validade
                       WHERE id = :id";
        $stmt_up = $conn->prepare($sql_update);
        $stmt_up->execute([
            ':plano'    => $plano,
            ':status'   => $status,
            ':validade' => $validade_banco,
            ':id'       => $id_motorista
        ]);
        $mensagem_sucesso = "Usuário ID $id_motorista atualizado com sucesso!";
    } catch (Exception $e) {
        $mensagem_erro = "Erro ao atualizar: " . $e->getMessage();
    }
}

// CARREGA TODOS OS MOTORISTAS DO BANCO (EXCETO O ADMIN)
try {
    $sql_motoristas = "SELECT id, nome, usuario, telefone, plano, status_assinatura, validade_plano 
                       FROM usuarios 
                       WHERE nivel_acesso != 'admin' OR nivel_acesso IS NULL 
                       ORDER BY id DESC";
    $stmt_m = $conn->query($sql_motoristas);
    $motoristas = $stmt_m->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erro ao carregar motoristas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - RCI</title>
    <style>
        html, body { 
            margin: 0; 
            padding: 0; 
            width: 100%; 
            background-color: #f4f6f9; 
            font-family: sans-serif;
            display: block; 
        }
        
        .container-admin { 
            max-width: 1200px; 
            margin: 30px auto; 
            background: #fff; 
            padding: 25px; 
            border-radius: 8px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            box-sizing: border-box;
        }
        
        h2 { color: #2c3e50; margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #34495e; padding-bottom: 10px; }
        
        .alert { padding: 12px; border-radius: 4px; margin-bottom: 15px; font-weight: bold; text-align: center; }
        .alert-success { background:#d4edda; color:#155724; }
        .alert-danger { background:#f8d7da; color:#721c24; }
        
        /* Estilo da Barra de Busca */
        .busca-container { margin-bottom: 25px; max-width: 450px; }
        .busca-container label { font-weight: bold; color: #2c3e50; display: block; margin-bottom: 8px; font-size: 0.95rem; }
        .busca-container input { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; box-sizing: border-box; }
        
        /* Tabela Responsiva */
        .tabela-wrapper { overflow-x: auto; margin-top: 15px; width: 100%; }
        table { width: 100%; border-collapse: collapse; min-width: 950px; }
        th, td { padding: 12px; border: 1px solid #e2e8f0; text-align: left; font-size: 0.9rem; }
        th { background-color: #2c3e50; color: white; }
        tr:nth-child(even) { background-color: #f8fafc; }
        
        select, input[type="date"] { padding: 6px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.85rem; background-color: #fff; }
        
        .btn-salvar { background-color: #2ecc71; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 0.85rem; }
        .btn-salvar:hover { background-color: #27ae60; }
        
        .link-zap { color: #27ae60; text-decoration: none; font-weight: bold; }
        .link-zap:hover { text-decoration: underline; }

        .btn-voltar { display: inline-block; text-decoration: none; background-color: #34495e; color: white; padding: 10px 20px; border-radius: 4px; font-weight: bold; font-size: 0.9rem; margin-top: 25px; transition: background 0.2s; }
        .btn-voltar:hover { background-color: #2c3e50; }
    </style>
</head>
<body>

<div class="container-admin">

    <h2>🛠️ Painel Controle de Assinaturas - RCI</h2>

    <?php if (!empty($mensagem_sucesso)): ?>
        <div class="alert alert-success"><?php echo $mensagem_sucesso; ?></div>
    <?php endif; ?>
    <?php if (!empty($mensagem_erro)): ?>
        <div class="alert alert-danger"><?php echo $mensagem_erro; ?></div>
    <?php endif; ?>

    <div class="busca-container">
        <label for="busca_motorista">🔍 Buscar Motorista por Nome ou ID:</label>
        <input type="text" id="busca_motorista" placeholder="Digite o nome ou ID do motorista...">
    </div>

    <div class="tabela-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>WhatsApp</th>
                    <th>Plano Atual</th>
                    <th>Status</th>
                    <th>Validade do Plano</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($motoristas) === 0): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: #7f8c8d;">Nenhum motorista cadastrado até o momento.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($motoristas as $m): 
                        // Formata a data para exibir no input tipo date (YYYY-MM-DD)
                        $data_formato_input = '';
                        if (!empty($m['validade_plano'])) {
                            $data_formato_input = date('Y-m-d', strtotime($m['validade_plano']));
                        }
                    ?>
                        <tr>
                            <td><strong>#<?php echo $m['id']; ?></strong></td>
                            
                            <td><?php echo htmlspecialchars($m['nome']); ?> (<span style="color: #7f8c8d;"><?php echo htmlspecialchars($m['usuario']); ?></span>)</td>
                            
                            <td>
                                <a href="https://wa.me/55<?php echo preg_replace('/\D/', '', $m['telefone']); ?>" target="_blank" class="link-zap">
                                    💬 <?php echo htmlspecialchars($m['telefone']); ?>
                                </a>
                            </td>
                            
                            <form action="painel_rci.php" method="POST">
                                <input type="hidden" name="id_motorista" value="<?php echo $m['id']; ?>">
                                
                                <td>
                                    <select name="plano">
                                        <option value="gratis" <?php echo ($m['plano'] === 'gratis') ? 'selected' : ''; ?>>Grátis (10 cálculos)</option>
                                        <option value="mensal" <?php echo ($m['plano'] === 'mensal') ? 'selected' : ''; ?>>Mensal (Premium)</option>
                                    </select>
                                </td>
                                
                                <td>
                                    <select name="status_assinatura">
                                        <option value="ativo" <?php echo ($m['status_assinatura'] === 'ativo') ? 'selected' : ''; ?>>Ativo</option>
                                        <option value="vencido" <?php echo ($m['status_assinatura'] === 'vencido') ? 'selected' : ''; ?>>Vencido</option>
                                        <option value="inativo" <?php echo ($m['status_assinatura'] === 'inativo') ? 'selected' : ''; ?>>Inativo (Bloqueado)</option>
                                    </select>
                                </td>
                                
                                <td>
                                    <input type="date" name="validade_plano" value="<?php echo $data_formato_input; ?>">
                                </td>
                                
                                <td>
                                    <button type="submit" name="salvar_usuario" class="btn-salvar">💾 Salvar</button>
                                </td>
                            </form>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <a href="configuracoes.php" class="btn-voltar">➔ Voltar para o Sistema</a>

</div>

<script>
document.getElementById('busca_motorista').addEventListener('keyup', function() {
    let busca = this.value.toLowerCase().trim();
    let linhas = document.querySelectorAll('table tbody tr');
    
    linhas.forEach(function(linha) {
        // Ignora a linha caso seja a de "Nenhum motorista cadastrado"
        if(linha.cells.length < 2) return;

        let idUser = linha.cells[0] ? linha.cells[0].textContent.toLowerCase() : '';
        let nomeUser = linha.cells[1] ? linha.cells[1].textContent.toLowerCase() : '';
        
        if (idUser.includes(busca) || nomeUser.includes(busca)) {
            linha.style.display = ''; 
        } else {
            linha.style.display = 'none'; 
        }
    });
});
</script>

</body>
</html>