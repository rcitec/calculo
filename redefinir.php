<?php
require_once 'conexao.php';
$mensagem_sucesso = ''; $mensagem_erro = ''; $token_valido = false;
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

if (!empty($token)) {
    try {
        // Busca sem usar colunas inexistentes (usado/expira_em)
        $query = "SELECT r.*, u.nome FROM recuperacoes_senha r 
                  JOIN usuarios u ON r.usuario_id = u.id 
                  WHERE r.token = :token LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([':token' => $token]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verifica validade manualmente
        if ($pedido && strtotime($pedido['validade']) > time()) {
            $token_valido = true;

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nova_senha'])) {
                $nova_senha = password_hash(trim($_POST['nova_senha']), PASSWORD_DEFAULT);
                
                // Atualiza senha e DELETA o token para evitar reuso
                $conn->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id")
                     ->execute([':senha' => $nova_senha, ':id' => $pedido['usuario_id']]);
                
                $conn->prepare("DELETE FROM recuperacoes_senha WHERE token = :token")
                     ->execute([':token' => $token]);

                $mensagem_sucesso = "Senha alterada! <a href='login.php'>Login</a>";
                $token_valido = false;
            }
        } else {
            $mensagem_erro = "Link expirado ou inválido.";
        }
    } catch (Exception $e) {
        $mensagem_erro = "Erro: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCI - Criar Nova Senha</title>
    <link rel="stylesheet" href="estilo.css?v=1.3">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #2c3e50; padding: 15px; }
        .container-redefinir { background: #ffffff; padding: 30px 25px; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); width: 100%; max-width: 400px; box-sizing: border-box; }
        .titulo { text-align: center; font-size: 1.5rem; font-weight: bold; color: #2c3e50; margin-bottom: 20px; }
        .alert { padding: 12px; border-radius: 4px; font-size: 0.85rem; text-align: center; margin-bottom: 15px; font-weight: bold; line-height:1.4; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .link-login { display: block; text-align: center; margin-top: 15px; font-size: 0.9rem; color: #2ecc71; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
<div class="container-redefinir">
    <div class="titulo">Nova Senha</div>

    <?php if (!empty($mensagem_sucesso)): ?>
        <div class="alert alert-success"><?php echo $mensagem_sucesso; ?></div>
        <a href="login.php" class="link-login">➔ Ir para a Tela de Login</a>
    <?php endif; ?>

    <?php if (!empty($mensagem_erro)): ?>
        <div class="alert alert-danger"><?php echo $mensagem_erro; ?></div>
    <?php endif; ?>

    <?php if ($token_valido): ?>
        <p style="font-size:0.9rem; color:#555; text-align:center; margin-bottom:15px;">Olá, <strong><?php echo htmlspecialchars($pedido['nome']); ?></strong>! Crie sua nova senha de acesso abaixo:</p>
        
        <form action="redefinir.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="nova_senha">Digite a Nova Senha:</label>
                <input type="password" id="nova_senha" name="nova_senha" required placeholder="Mínimo 4 caracteres" style="width:100%; box-sizing:border-box; padding:10px; height:44px;">
            </div>
            
            <button type="submit" class="btn" style="background-color: #2ecc71; width:100%;">Salvar Nova Senha</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
