<?php
// Inicia a sessão para controlar o estado de logado do usuário
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Se o usuário já estiver logado, redireciona direto para a tela de cálculo
if (isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] === true) {
    header("Location: calculo.php");
    exit();
}

require_once 'conexao.php';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_form = trim($_POST['usuario'] ?? '');
    $senha_form   = trim($_POST['senha'] ?? '');

    if (!empty($usuario_form) && !empty($senha_form)) {
        try {
            // 🟢 CORRIGIDO: Incluídas as colunas status_assinatura e validade_plano na busca SQL
            $sql = "SELECT id, usuario, senha, nome, status_assinatura, validade_plano FROM usuarios WHERE usuario = :usuario LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':usuario' => $usuario_form]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verifica se o usuário realmente existe
            if ($user) {
                
                $senha_valida = false;
                
                // Primeiro testa o padrão seguro (Hash criptografada)
                if (password_verify($senha_form, $user['senha'])) {
                    $senha_valida = true;
                } 
                // Segundo teste: caso a senha no banco esteja em texto limpo (ex: 123)
                elseif ($senha_form === $user['senha']) {
                    $senha_valida = true;
                }

                if ($senha_valida === true) {
                    // SEGREDO AQUI: Grava as variáveis estritamente necessárias na sessão, incluindo o NOME
                    $_SESSION['usuario_logado']    = true;
                    $_SESSION['usuario_id']        = intval($user['id']);
                    $_SESSION['usuario_nome']      = $user['nome']; 
                    
                    // 🟢 CORRIGIDO: Alterado de $usuario para $user correspondendo à variável correta
                    $_SESSION['status_assinatura'] = $user['status_assinatura'];
                    $_SESSION['validade_plano']    = $user['validade_plano']; 

                    // Redireciona imediatamente
                    header("Location: calculo.php");
                    exit();
                } else {
                    $erro = "Usuário ou senha incorretos.";
                }
            } else {
                $erro = "Usuário ou senha incorretos.";
            }
            
        } catch (Exception $e) {
            $erro = "Erro no sistema ao processar login: " . $e->getMessage();
        }
    } else {
        $erro = "Por favor, preencha todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCI - Login</title>
    <link rel="stylesheet" href="estilo.css?v=1.3">
    
    <script>
    // Força o histórico do navegador a entender que a tela atual é o início de tudo
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    // Se o usuário tentar voltar de alguma forma pelo botão do celular, o navegador joga ele para a própria tela de login limpa
    window.onpopstate = function () {
        window.location.replace("login.php");
    };
    </script>
    
    <style>
        body {
            display: flex;
            flex-direction: column; /* 💡 Empilha o bloco de login e o rodapé verticalmente */
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #2c3e50;
            padding: 15px;
            box-sizing: border-box;
        }
        .container-login {
            background: #ffffff;
            padding: 35px 25px;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 380px;
        }
        .logo-login {
            text-align: center;
            font-size: 2.2rem;
            font-weight: bold;
            color: #FFC107;
            margin-bottom: 2px;
            letter-spacing: 1px;
        }
        .sublogo-login {
            text-align: center;
            font-size: 0.85rem;
            color: #7f8c8d;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }
        .alert-login {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .form-group label { color: #34495e; font-weight: bold; }
        .btn-acesso { background-color: #2ecc71; margin-top: 15px; }
        .btn-acesso:hover { background-color: #27ae60; }

        /* 💡 ESTILIZAÇÃO DO RODAPÉ ADAPTADO AO FUNDO ESCURO */
        .login-footer {
            margin-top: 30px;
            text-align: center;
            width: 100%;
            max-width: 380px;
        }
        .login-footer p {
            margin: 6px 0;
            font-family: sans-serif;
        }
        .login-footer .copyright {
            font-size: 0.9rem;
            color: #94a3b8; /* Cinza claro para o fundo escuro */
            opacity: 0.85;
        }
        .login-footer .frase-efeito {
            font-size: 0.85rem;
            color: #64748b; /* Cinza sutil idêntico ao da imagem */
        }
    </style>
</head>
<body>

<div class="container-login">
    <div class="logo-login">RCI</div>
    <div class="sublogo-login">Cálculo de Fretes</div>

    <?php if (!empty($erro)): ?>
        <div class="alert-login"><?php echo htmlspecialchars($erro); ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="form-group">
            <label for="usuario">Usuário:</label>
            <input type="text" id="usuario" name="usuario" required placeholder="Seu usuário" autocomplete="username">
        </div>

        <div class="form-group">
            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" required placeholder="Sua senha" autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-acesso">Entrar no Sistema</button>
                
        <a href="esqueci_senha.php" style="display:block; text-align:center; margin-top:10px; font-size:0.85rem; color:#7f8c8d; text-decoration:none;">Esqueceu sua senha?</a>
        
        <a href="cadastro.php" style="display: block; text-align: center; margin-top: 15px; font-size: 0.9rem; color: #3498db; text-decoration: none; font-weight: 600;">
            Não tem uma conta? Cadastre-se
        </a>
    </form>
</div>

<footer class="login-footer">
    <p class="copyright">&copy; <?php echo date('Y'); ?> RCI Transportes - Todos os direitos reservados.</p>
    <p class="frase-efeito">Desenvolvido para motoristas que valorizam o seu trabalho.</p>
</footer>

</body>
</html>

