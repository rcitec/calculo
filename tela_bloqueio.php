<?php
// Como esta página é um destino de bloqueio, NÃO incluímos o trava.php aqui para não gerar um loop infinito de redirecionamentos.
// Mas iniciamos a sessão para saber o nome do motorista, se ele estiver logado.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nome_completo  = $_SESSION['usuario_nome'] ?? 'Motorista';
$usuario_id     = $_SESSION['usuario_id'] ?? '0';

// Extrai apenas o primeiro nome do motorista
$partes_nome   = explode(' ', trim($nome_completo));
$primeiro_nome = $partes_nome[0];

// Captura o motivo do bloqueio vindo da URL
$motivo = $_GET['motivo'] ?? 'padrao';
$vencimento_br = '';

if (isset($_GET['vencimento'])) {
    $vencimento_br = date('d/m/Y', strtotime($_GET['vencimento']));
}

// Configura as mensagens personalizadas para cada cenário de bloqueio
$titulo_bloco = "Acesso Restrito";
$mensagem_principal = "Identificamos uma pendência na sua assinatura. Para continuar a usar o sistema RCI, por favor, regularize o seu acesso.";
$icone = "🔒";

if ($motivo === 'conta_inativa') {
    $titulo_bloco = "Conta Inativa";
    $mensagem_principal = "Olá, <strong>$nome_completo</strong>. O seu utilizador foi desativado ou bloqueado pela administração do sistema. Se isto for um erro, entre em contacto com o suporte.";
    $icone = "🚫";
} elseif ($motivo === 'plano_vencido') {
    $titulo_bloco = "Assinatura Vencida";
    $mensagem_principal = "Olá, <strong>$nome_completo</strong>. O seu plano Premium expirou a <strong>$vencimento_br</strong>. Para não perder o histórico das suas viagens e continuar a calcular fretes com lucro real, renove a sua assinatura.";
    $icone = "⏳";
} elseif ($motivo === 'limite_atingido') {
    $titulo_bloco = "Limite do Plano Grátis";
    $mensagem_principal = "Parabéns pelo volume de trabalho, <strong>$nome_completo</strong>! Você atingiu o limite máximo de 10 fretes salvos no <strong>Plano Gratuito</strong>.<br><br>Para continuar a registar novos fretes, ter acesso ao gerador de faturamento e usar o sistema sem restrições, faça o upgrade para o Plano Premium.";
    $icone = "🚀";
}

// =========================================================================
// MENSAGENS PADRONIZADAS DO WHATSAPP (Com Primeiro Nome e ID)
// =========================================================================

// Mensagem 1: Liberação Premium
$texto_premium = "Olá aqui é {$primeiro_nome} / id: {$usuario_id}, gostaria de liberar o meu acesso Premium no sistema RCI Fretes.";
$url_premium   = "https://wa.me/5515997450446?text=" . urlencode($texto_premium);

// Mensagem 2: Conta Inativa (Agora atualizada também!)
$texto_inativo = "Olá aqui é {$primeiro_nome} / id: {$usuario_id}, o meu utilizador no RCI está constando como inativo.";
$url_inativo   = "https://wa.me/5515997450446?text=" . urlencode($texto_inativo);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCI - Central de Assinaturas</title>
    <link rel="stylesheet" href="estilo.css?v=1.3">
    <style>
        body { background-color: #f4f6f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; font-family: sans-serif; }
        
        .card-bloqueio { background: #ffffff; border-radius: 12px; max-width: 450px; width: 90%; padding: 30px 20px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-top: 5px solid #e74c3c; }
        .card-bloqueio.premium-sugestao { border-top-color: #2ecc71; }
        
        .icone-bloqueio { font-size: 3.5rem; margin-bottom: 15px; display: inline-block; }
        
        h2 { color: #2c3e50; margin-top: 0; font-size: 1.6rem; margin-bottom: 15px; }
        
        .texto-alerta { color: #555; font-size: 1rem; line-height: 1.6; margin-bottom: 25px; }
        
        .caixa-vantagens { background-color: #f8f9fa; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 12px; margin-bottom: 25px; text-align: left; }
        .caixa-vantagens h4 { margin: 0 0 8px 0; color: #2c3e50; font-size: 0.9rem; }
        .caixa-vantagens ul { margin: 0; padding-left: 20px; font-size: 0.85rem; color: #555; }
        .caixa-vantagens li { margin-bottom: 4px; }

        /* Botão principal de ação (WhatsApp / PIX) */
        .btn-liberar { background-color: #2ecc71; color: white; border: none; padding: 14px 20px; font-size: 1rem; font-weight: bold; border-radius: 6px; cursor: pointer; text-decoration: none; display: block; transition: background 0.2s; box-shadow: 0 2px 5px rgba(46,204,113,0.3); }
        .btn-liberar:hover { background-color: #27ae60; }
        
        .btn-voltar-login { display: inline-block; margin-top: 20px; color: #7f8c8d; font-size: 0.85rem; text-decoration: none; font-weight: 500; }
        .btn-voltar-login:hover { color: #34495e; text-decoration: underline; }
    </style>
</head>
<body>

<div class="card-bloqueio <?php echo ($motivo !== 'conta_inativa') ? 'premium-sugestao' : ''; ?>">
    
    <div class="icone-bloqueio"><?php echo $icone; ?></div>
    
    <h2><?php echo $titulo_bloco; ?></h2>
    
    <div class="texto-alerta">
        <?php echo $mensagem_principal; ?>
    </div>

    <?php if ($motivo === 'limite_atingido' || $motivo === 'plano_vencido'): ?>
        <div class="caixa-vantagens">
            <h4>👑 Vantagens do Plano Premium:</h4>
            <ul>
                <li>Cálculos de frete ilimitados.</li>
                <li>Histórico completo de viagens sem expiração.</li>
                <li>Gerador de Fechamento/Faturamento automático para o Gmail.</li>
                <li>Suporte prioritário via WhatsApp.</li>
            </ul>
        </div>
        
        <a href="<?php echo $url_premium; ?>" 
           target="_blank" 
           class="btn-liberar">
            🚀 Liberar Meu Acesso Premium Agora
        </a>
    <?php else: ?>
        <a href="<?php echo $url_inativo; ?>" 
           target="_blank" 
           class="btn-liberar" style="background-color: #34495e;">
            💬 Falar com o Suporte
        </a>
    <?php endif; ?>

    <a href="logout.php" class="btn-voltar-login">Sair / Fazer Login com outra conta</a>

</div>

</body>
</html>