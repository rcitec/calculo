<?php
require_once 'conexao.php';
$mensagem_erro = '';
$link_whatsapp = '';
$usuario_digitado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_digitado = trim($_POST['usuario'] ?? '');

    if (!empty($usuario_digitado)) {
        try {
            // 1. Busca o usuário
            $stmt = $conn->prepare("SELECT id, nome, usuario FROM usuarios WHERE usuario = :usuario LIMIT 1");
            $stmt->execute([':usuario' => $usuario_digitado]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $expira = date('Y-m-d H:i:s', strtotime('+2 hours'));

                // 2. Insere usando a coluna 'validade'
                $sql_ins = "INSERT INTO recuperacoes_senha (usuario_id, token, validade) VALUES (:usuario_id, :token, :validade)";
                $stmt_ins = $conn->prepare($sql_ins);
                $stmt_ins->execute([':usuario_id' => $user['id'], ':token' => $token, ':validade' => $expira]);

                $url_redefinir = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/redefinir.php?token=" . $token;
                $mensagem_texto = "Olá Suporte RCI! Solicito redefinição de senha para: " . $user['usuario'] . "\n\nLink: " . $url_redefinir;
                $link_whatsapp = "https://api.whatsapp.com/send?phone=5515997450446&text=" . urlencode($mensagem_texto);
            } else {
                $mensagem_erro = "Nome de usuário não encontrado.";
            }
        } catch (Exception $e) {
            $mensagem_erro = "Erro: " . $e->getMessage();
        }
    }
}
?>

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCI - Recuperar Senha</title>
    <link rel="stylesheet" href="estilo.css?v=1.3">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #2c3e50; padding: 15px; }
        .container-recuperar { background: #ffffff; padding: 30px 25px; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); width: 100%; max-width: 400px; box-sizing: border-box; }
        .titulo { text-align: center; font-size: 1.5rem; font-weight: bold; color: #2c3e50; margin-bottom: 15px; }
        .descricao { font-size: 0.9rem; color: #7f8c8d; text-align: center; margin-bottom: 25px; line-height: 1.4; }
        .alert { padding: 12px; border-radius: 4px; font-size: 0.85rem; text-align: center; margin-bottom: 15px; font-weight: bold; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn-whats { background-color: #25D366; color: white; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; font-weight: bold; text-align: center; padding: 12px; border-radius: 4px; margin-top: 15px; font-size: 1rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-whats:hover { background-color: #128C7E; }
        .link-voltar { display: block; text-align: center; margin-top: 20px; font-size: 0.9rem; color: #3498db; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="container-recuperar">
    <div class="titulo">Recuperação de Senha</div>
    
    <?php if (empty($link_whatsapp)): ?>
        <div class="descricao">Digite abaixo o nome de usuário que você usa para entrar no sistema. Nós geraremos um link de liberação direta via WhatsApp do suporte.</div>
        
        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert alert-danger"><?php echo $mensagem_erro; ?></div>
        <?php endif; ?>

        <form action="esqueci_senha.php" method="POST">
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="usuario">Seu Usuário de Acesso:</label>
                <input type="text" id="usuario" name="usuario" value="<?php echo htmlspecialchars($usuario_digitado); ?>" required placeholder="Ex: joao.silva" style="width:100%; box-sizing:border-box; padding:10px; height:44px;">
            </div>
            <button type="submit" class="btn" style="background-color: #34495e; width: 100%;">Gerar Link de Recuperação</button>
        </form>
    <?php else: ?>
        <!-- Se o link foi gerado com sucesso, esconde o formulário e mostra o botão do Zap -->
        <div style="text-align: center; padding: 10px 0;">
            <span style="font-size: 3rem;">📱</span>
            <h3 style="color: #2c3e50; margin-top: 10px;">Link Criado com Sucesso!</h3>
            <p style="font-size: 0.9rem; color: #555; margin-bottom: 20px; line-height: 1.4;">
                Clique no botão abaixo para abrir o seu WhatsApp e enviar a mensagem de liberação para o nosso suporte.
            </p>
            <a href="<?php echo $link_whatsapp; ?>" target="_blank" class="btn-whats">
                Enviar para o WhatsApp
            </a>
        </div>
    <?php endif; ?>

    <a href="login.php" class="link-voltar">➔ Voltar para o Login</a>
</div>
</body>
</html>
