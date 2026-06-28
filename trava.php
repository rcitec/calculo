<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Se não estiver logado, chuta imediatamente para a tela de login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$pagina_atual = basename($_SERVER['PHP_SELF']);

// ============================================================
// 🔒 BLOQUEIO INTELIGENTE POR STATUS DA ASSINATURA (DO BANCO)
// ============================================================
if (isset($_SESSION['status_assinatura'])) {
    $status = $_SESSION['status_assinatura'];

    // CASO A: Conta totalmente INATIVA
    if ($status === 'inativo') {
        // 🟢 LIBERADO: Permite que o inativo acesse a página de processar o pagamento
        $paginas_inativo = ['assinatura.php', 'logout.php', 'processar_assinatura.php'];
        
        if (!in_array($pagina_atual, $paginas_inativo)) {
            header("Location: assinatura.php?motivo=inativo");
            exit;
        }
    }

    // CASO B: Conta VENCIDA
    if ($status === 'vencido') {
        // 🟢 LIBERADO: Incluído o 'processar_assinatura.php' na lista do vencido
        $paginas_liberadas = [
            'assinatura.php', 
            'logout.php', 
            'historico.php', 
            'atualizar_status_agenda.php', 
            'atualizar_status_faturamento.php',
            'processar_assinatura.php' // 👈 Libera a geração do PIX
        ];
        
        if (!in_array($pagina_atual, $paginas_liberadas)) {
            header("Location: assinatura.php?motivo=vencido");
            exit;
        }
    }
}

// ============================================================
// 🔒 VERIFICAÇÃO AUTOMÁTICA DE VALIDADE DOS 10 DIAS GRATUITOS
// ============================================================
if (isset($_SESSION['validade_plano']) && isset($_SESSION['plano']) && $_SESSION['plano'] === 'gratis') {
    $data_validade = $_SESSION['validade_plano'];
    $hoje          = date('Y-m-d');

    // Se o dia atual passou do prazo limite de testes
    if ($hoje > $data_validade) {
        
        // 🟢 LIBERADO: Incluído também para quem está com o teste grátis expirado e quer pagar
        $paginas_liberadas_gratis = [
            'assinatura.php', 
            'logout.php', 
            'historico.php', 
            'atualizar_status_agenda.php', 
            'atualizar_status_faturamento.php',
            'processar_assinatura.php' // 👈 Libera a geração do PIX
        ];
        
        if (!in_array($pagina_atual, $paginas_liberadas_gratis)) {
            header("Location: assinatura.php?motivo=expirado");
            exit;
        }
    }
}
?>

