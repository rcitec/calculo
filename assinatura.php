<?php
require_once 'trava.php'; // Garante que o motorista está logado
require_once 'conexao.php';

// Verifica se ele caiu aqui porque atingiu o limite de 10 fretes
$motivo_limite = (isset($_GET['motivo']) && $_GET['motivo'] === 'limite');

// Verifica se ele caiu aqui porque passou o prazo de 15 dias
$motivo_expirado = (isset($_GET['motivo']) && $_GET['motivo'] === 'expirado');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>RCI Fretes - Assinatura Digital</title>
    <style>
        :root {
            --cor-principal: #2c3e50;
            --cor-destaque: #FFC107;
            --cor-erro: #e74c3c;
            --cor-sucesso: #2ecc71;
            --cor-sucesso-hover: #27ae60;
            --texto-escuro: #334155;
            --fundo-claro: #f8fafc;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            color: var(--texto-escuro);
            background-color: var(--fundo-claro);
            line-height: 1.5;
        }

        .topo-sistema {
            background: var(--cor-principal);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: bold;
            font-size: 1.2rem;
            letter-spacing: 1px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .container-assinatura {
            max-width: 500px;
            margin: 20px auto;
            padding: 0 15px;
            box-sizing: border-box;
        }

        /* Card Principal de Alerta / Boas-vindas */
        .card-status {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border-top: 4px solid var(--cor-destaque);
            margin-bottom: 20px;
        }

        /* Estilo dinâmico caso esteja bloqueado por tempo ou status */
        .card-status.card-bloqueado {
            border-top: 4px solid var(--cor-erro);
        }

        .card-status h2 {
            margin-top: 0;
            color: var(--cor-principal);
            font-size: 1.4rem;
        }

        .card-status p {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        /* Detalhes do Plano */
        .card-plano {
            background: white;
            border-radius: 8px;
            padding: 25px 20px;
            text-align: center;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
            border: 2px solid var(--cor-sucesso);
            position: relative;
        }

        .badge-plano {
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--cor-sucesso);
            color: white;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
            padding: 4px 12px;
            border-radius: 20px;
            letter-spacing: 1px;
        }

        .preco-grande {
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--cor-principal);
            margin: 15px 0 5px 0;
        }

        .preco-grande span {
            font-size: 1.1rem;
            font-weight: normal;
            color: #64748b;
        }

        /* Lista de Benefícios Sem Burocracia */
        .lista-beneficios {
            text-align: left;
            margin: 25px 0;
            padding: 0;
            list-style: none;
        }

        .lista-beneficios li {
            margin-bottom: 12px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #475569;
        }

        /* Botões */
        .btn-pagamento {
            display: block;
            background-color: var(--cor-sucesso);
            color: white;
            text-align: center;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: bold;
            text-decoration: none;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(46, 204, 113, 0.3);
            transition: background 0.2s;
            border: none;
            width: 100%;
            box-sizing: border-box;
            cursor: pointer;
        }

        .btn-pagamento:hover {
            background-color: var(--cor-sucesso-hover);
        }

        .btn-voltar {
            display: inline-block;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        .btn-voltar:hover { 
            color: var(--cor-principal); 
            text-decoration: underline; 
        }
        
        .lista-planos { margin: 20px 0; display: flex; flex-direction: column; gap: 10px; }
.item-plano { 
    display: flex; align-items: center; border: 2px solid #e2e8f0; 
    padding: 15px; border-radius: 8px; cursor: pointer; transition: 0.3s;
}
.item-plano:hover { border-color: #2ecc71; }
.item-plano input { margin-right: 15px; }
.item-plano span { font-weight: 600; }

/* Visual quando o usuário seleciona */
.item-plano:has(input:checked) {
    border-color: #2ecc71;
    background: #f0fff4;
}

        footer {
            text-align: center;
            margin-top: 50px;
            padding: 10px 0;
            font-size: 0.8rem;
            color: #94a3b8;
        }
        .login-footer p {
            margin: 1px 0;
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
    <div class="topo-sistema">RCI FRETES</div>

    <div class="container-assinatura">

        <?php 
            // Ativa o visual de alerta se houver algum tipo de restrição na URL
            $bloqueado = ($motivo_expirado || $motivo_limite || isset($_GET['motivo']));
        ?>
        <div class="card-status <?= $bloqueado ? 'card-bloqueado' : '' ?>">
            <?php if ($motivo_limite): ?>
                <h2>Parabéns pelo trabalho! 📈</h2>
                <p>Você atingiu o limite de 10 cálculos gratuitos do seu teste. Ficamos muito felizes em ver o RCI ajudando a controlar o seu lucro real na estrada.</p>
            <?php elseif ($motivo_expirado): ?>
                <h2>Seu período de teste expirou! ⏳</h2>
                <p>Os seus 15 dias de avaliação gratuita chegaram ao fim. Ative o plano premium para continuar liberando novos cálculos e organizando os seus fretes.</p>
            <?php elseif (isset($_GET['motivo']) && $_GET['motivo'] === 'inativo'): ?>
                <h2>Conta Inativa 🚫</h2>
                <p>O seu acesso ao Sistema RCI encontra-se inativo no momento. Regularize sua assinatura abaixo para restabelecer o seu painel.</p>
            <?php elseif (isset($_GET['motivo']) && $_GET['motivo'] === 'vencido'): ?>
                <h2>Assinatura Vencida 💳</h2>
                <p>Não identificamos o pagamento da sua última mensalidade. Ative o acesso via PIX para liberar o seu aplicativo imediatamente.</p>
            <?php else: ?>
                <h2>Garante seu acesso total! 🛠️</h2>
                <p>Você pode ativar sua assinatura ilimitada a qualquer momento para garantir que nunca vai ficar na mão na hora de fechar com o cliente.</p>
            <?php endif; ?>
        </div>

<form action="processar_assinatura.php" method="POST">
    <div class="card-plano">
        <div class="badge-plano">Escolha seu Plano</div>

        <!-- Opções de Planos -->
        <div class="lista-planos">
            <label class="item-plano">
                <input type="radio" name="plano" value="teste_7" required>
                <span>7 Dias - R$ 6,90</span>
            </label>
            <label class="item-plano">
                <input type="radio" name="plano" value="teste_15">
                <span>15 Dias - R$ 10,90</span>
            </label>
            <label class="item-plano selecionado">
                <input type="radio" name="plano" value="assinante_30" checked>
                <span>30 Dias - R$ 17,90</span>
            </label>
            <label class="item-plano">
                <input type="radio" name="plano" value="assinante_90">
                <span>3 Meses - R$ 44,90</span>
            </label>
        </div>

        <button type="submit" class="btn-pagamento">🔒 Pagar via PIX</button>
        
        <br>
        <a href="logout.php" class="btn-voltar">Voltar para o Login</a>
    </div>
</form>

<footer class="login-footer">
    <p class="copyright">&copy; <?php echo date('Y'); ?> RCI Transportes - Todos os direitos reservados.</p>
    <p class="frase-efeito">Desenvolvido para motoristas que valorizam o seu trabalho.</p>
</footer>

</body>
</html>
