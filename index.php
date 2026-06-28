<?php
session_start();
if (isset($_SESSION['usuario_id'])) {
    header("Location: calculo.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCI Transportes - Controle seu Lucro Real no Frete</title>
    <style>
        /* Cores baseadas no ecossistema RCI */
        :root {
            --cor-principal: #2c3e50;
            --cor-destaque: #FFC107;
            --cor-botao: #2ecc71;
            --cor-botao-hover: #27ae60;
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
            line-height: 1.6;
        }

        /* Hero Section (Topo da Página) */
        .hero {
            background: linear-gradient(135deg, #1e293b 0%, var(--cor-principal) 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
        }

        .logo {
            font-size: 3rem;
            font-weight: bold;
            color: var(--cor-destaque);
            margin: 0 0 5px 0;
            letter-spacing: 2px;
        }

        .sublogo {
            font-size: 0.9rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 30px;
        }

        .hero h1 {
            font-size: 2.2rem;
            margin-bottom: 15px;
            font-weight: 800;
            line-height: 1.2;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero p {
            font-size: 1.1rem;
            color: #cbd5e1;
            max-width: 600px;
            margin: 0 auto 35px auto;
        }

        /* Botões de Ação Chave */
        .btn-principal {
            background-color: var(--cor-botao);
            color: white;
            padding: 16px 32px;
            font-size: 1.1rem;
            font-weight: bold;
            text-decoration: none;
            border-radius: 8px;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
            transition: transform 0.2s, background 0.2s;
        }

        .btn-principal:hover {
            background-color: var(--cor-botao-hover);
            transform: translateY(-2px);
        }

        .btn-login {
            display: inline-block;
            margin-top: 20px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.95rem;
        }
        .btn-login:hover { color: white; text-decoration: underline; }

        /* Seção de Benefícios/Dores */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 50px 20px;
        }

        .secao-titulo {
            text-align: center;
            font-size: 1.6rem;
            color: var(--cor-principal);
            margin-bottom: 40px;
            font-weight: 700;
        }

        .grid-beneficios {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
        }

        .card-beneficio {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border-top: 4px solid var(--cor-principal);
        }

        .card-beneficio h3 {
            margin-top: 0;
            color: var(--cor-principal);
            font-size: 1.2rem;
        }

        /* Seção de Teste Gratuito (Substituindo a Caixa de Oferta Agressiva) */
        .caixa-teste-gratis {
            background: white;
            border: 1px solid #e2e8f0;
            border-top: 4px solid var(--cor-botao);
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            margin-top: 50px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
        }

        .caixa-teste-gratis h3 {
            font-size: 1.5rem;
            color: var(--cor-principal);
            margin-top: 0;
        }

        .caixa-teste-gratis p {
            color: #64748b;
            max-width: 600px;
            margin: 10px auto 25px auto;
            font-size: 1rem;
        }

        footer {
            background-color: #0f172a;
            color: #64748b;
            text-align: center;
            padding: 30px 20px;
            font-size: 0.85rem;
            margin-top: 60px;
        }

        /* Ajustes Finos para Celular */
        @media (max-width: 600px) {
            .hero h1 { font-size: 1.6rem; }
            .hero p { font-size: 1rem; }
            .caixa-teste-gratis h3 { font-size: 1.3rem; }
        }
    </style>
</head>
<body>

    <section class="hero">
        <div class="logo">RCI</div>
        <div class="sublogo">Cálculo Inteligente de Fretes</div>
        
        <h1>Coloque a matemática do seu frete no piloto automático</h1>
        <p>Calcule o lucro real da sua viagem antes de sair de casa. Uma ferramenta simples para o motorista autônomo organizar combustível, pedágios e gerar relatórios profissionais para os clientes.</p>
        
        <a href="cadastro.php" class="btn-principal">Começar a Usar Agora</a>
        <br>
        <a href="login.php" class="btn-login">Já tem uma conta? Entrar no Sistema</a>
    </section>

    <div class="container">
        <h2 class="secao-titulo">Como a ferramenta ajuda o seu dia a dia?</h2>
        
        <div class="grid-beneficios">
            <div class="card-beneficio">
                <h3>⛽ Consumo de Combustível</h3>
                <p>Insira a distância e a média do seu veículo. O sistema calcula a quantidade de litros necessária e o custo aproximado com base no preço do diesel que você definir.</p>
            </div>
            
            <div class="card-beneficio">
                <h3>💰 Margem de Lucro Real</h3>
                <p>Abata custos com chapas, pedágios adicionais, alimentação ou comissões. Descubra na hora se o valor oferecido pela transportadora realmente vale a pena.</p>
            </div>
            
            <div class="card-beneficio">
                <h3>📋 Histórico e Fechamentos</h3>
                <p>Guarde seus fretes organizados por cliente. Ao final da semana ou mês, gere um resumo limpo e profissional para enviar direto pelo E-Mail.</p>
            </div>
        </div>

        <div class="caixa-teste-gratis">
            <h3>Faça o teste prático na sua próxima viagem</h3>
            <p>Cadastre-se e ganhe <strong>15 dias ou 10 cálculos de frete gratuitos</strong> para conhecer o sistema por dentro. Não pedimos cartão de crédito e você não assume nenhum compromisso.</p>
            
            <a href="cadastro.php" class="btn-principal" style="background-color: #3498db; box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2);">Criar Meu Perfil Sem Compromisso</a>
        </div>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> RCI Transportes - Todos os direitos reservados.</p>
        <p style="font-size: 0.75rem; color: #475569;">Desenvolvido para motoristas que valorizam o seu trabalho.</p>
    </footer>

</body>
</html>
