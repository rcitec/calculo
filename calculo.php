<?php
require_once 'trava.php'; 
require_once 'conexao.php';

// DEBUGR: Se isso imprimir "ID Vazio!", o problema está no trava.php ou na sessão
if (empty($usuario_id)) {
    die("Erro: O ID do usuário não foi carregado. Verifique o arquivo trava.php ou sua sessão.");
}

// --- CHAVE MESTRA: Garante que o Admin não sofra bloqueios ---
$stmt_admin = $conn->prepare("SELECT nivel_acesso FROM usuarios WHERE id = :id");
$stmt_admin->execute([':id' => $usuario_id]);
$dados_user = $stmt_admin->fetch(PDO::FETCH_ASSOC);
$is_admin = ($dados_user && $dados_user['nivel_acesso'] === 'admin');

// --- LÓGICA DE INICIALIZAÇÃO DE VEÍCULOS E CONFIGURAÇÕES ---
try {
    // Traz os dados de consumo e as novas taxas por KM do veículo ativo salvando em $usuario_config
    $query = "SELECT c.*, v.media_consumo, v.veiculo, v.pneu, v.troca_oleo, v.outros 
              FROM configuracoes c 
              JOIN veiculos v ON c.veiculo_id = v.id 
              WHERE c.usuario_id = :usuario_id LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute([':usuario_id' => $usuario_id]);
    $usuario_config = $stmt->fetch(PDO::FETCH_ASSOC); // Modificado de $config para $usuario_config

    // Se não tiver configurações montadas no banco ainda, cria os padrões de fábrica
    if (!$usuario_config) {
        $stmt_v = $conn->prepare("SELECT id FROM veiculos WHERE usuario_id = :usuario_id LIMIT 1");
        $stmt_v->execute([':usuario_id' => $usuario_id]);
        $veiculo_existente = $stmt_v->fetch(PDO::FETCH_ASSOC);

        if (!$veiculo_existente) {
            $sql_ins_v = "INSERT INTO veiculos (usuario_id, veiculo, media_consumo, pneu, troca_oleo, outros) 
                          VALUES (:usuario_id, 'Veículo Padrão', 10.0, 0.0000, 0.0000, 0.0000)";
            $stmt_ins_v = $conn->prepare($sql_ins_v);
            $stmt_ins_v->execute([':usuario_id' => $usuario_id]);
            $veiculo_id_criado = $conn->lastInsertId();
        } else {
            $veiculo_id_criado = $veiculo_existente['id'];
        }

        $sql_ins_c = "INSERT INTO configuracoes (usuario_id, veiculo_id, preco_combustivel, lucro_max, lucro_min) VALUES (:usuario_id, :veiculo_id, 0.00, 3.00, 2.00)";
        $stmt_ins_c_exec = $conn->prepare($sql_ins_c);
        $stmt_ins_c_exec->execute([':usuario_id' => $usuario_id, ':veiculo_id' => $veiculo_id_criado]);
        
        $stmt->execute([':usuario_id' => $usuario_id]);
        $usuario_config = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    die("Erro crítico ao processar configurações: " . $e->getMessage());
}

// --- BUSCA VIAGEM PARA EDIÇÃO ---
$viagem_edicao = null;
if (isset($_GET['editar_id'])) {
    $editar_id = intval($_GET['editar_id']);
    $stmt_v = $conn->prepare("SELECT * FROM viagens WHERE id = :id AND usuario_id = :usuario_id LIMIT 1");
    $stmt_v->execute([':id' => $editar_id, ':usuario_id' => $usuario_id]);
    $viagem_edicao = $stmt_v->fetch(PDO::FETCH_ASSOC);
    
    // Se estiver editando, sobrescreve o combustível padrão pelo valor gravado na viagem
    if ($viagem_edicao) {
        $config['preco_combustivel'] = $viagem_edicao['preco_combustivel'];
    }
}

// =========================================================================
// 📊 UNIFICADO: CONTROLE DO LIMITE DE TESTES GRATUITOS & BANNER INTELIGENTE
// =========================================================================
$mensagem_trava_premium = "";
$is_premium = ($is_admin === true); // Se for admin, passa direto

$plano_atual = 'gratis';
$validade = null;

// 1. Busca os dados do plano direto na tabela de usuários
try {
    $sql_u_premium = "SELECT plano, validade_plano FROM usuarios WHERE id = :id LIMIT 1";
    $stmt_u_p = $conn->prepare($sql_u_premium);
    $stmt_u_p->execute([':id' => $usuario_id]);
    $res_u_p = $stmt_u_p->fetch(PDO::FETCH_ASSOC);
    
    if ($res_u_p) {
        $plano_atual = $res_u_p['plano'] ?? 'gratis';
        $validade = $res_u_p['validade_plano'] ?? null;
    }
} catch (Exception $e) {
    // Mantém os padrões para não quebrar a página
}

// 2. Se o plano for 'mensal', ele é considerado premium absoluto
if ($plano_atual === 'mensal') {
    $is_premium = true;
}

// 3. Se NÃO for mensal, avaliamos o período de testes (Fretes restantes + Data limite)
if (!$is_premium) {
    try {
        // Conta as viagens atuais
        $sql_count = "SELECT COUNT(*) as total FROM viagens WHERE usuario_id = :usuario_id";
        $stmt_c = $conn->prepare($sql_count);
        $stmt_c->execute([':usuario_id' => $usuario_id]);
        $total_calculos = intval($stmt_c->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        
        $limite_gratis = 10;
        $restantes_fretes = $limite_gratis - $total_calculos;
        
        // 🟢 CÁLCULO DE DATA DE VALIDADE CORRIGIDO COM DATETIME
        $hoje = new DateTime(date('Y-m-d'));
        $data_validade_banco = !empty($validade) ? new DateTime($validade) : null;
        
        // Se não tiver data no banco, assume que já venceu
        $data_valida = false;
        $dias_restantes = 0;

        if ($data_validade_banco) {
            // Se a data de validade for maior ou igual a hoje, ela é válida
            if ($data_validade_banco >= $hoje) {
                $data_valida = true;
                // Calcula a diferença exata de dias entre hoje e a validade
                $diferenca = $hoje->diff($data_validade_banco);
                $dias_restantes = intval($diferenca->format('%r%a')); 
            }
        }

        // CÁLCULO DA EXPIRAÇÃO:
        // O motorista Bloqueia se: Estourar os 10 fretes OU se a data de validade já passou (venceu)
        if ($restantes_fretes <= 0 || !$data_valida) {
            // Só bloqueia se for frete novo. Se for edição, deixa abrir.
            if (!isset($viagem_edicao) || !$viagem_edicao) {
                $motivo = ($restantes_fretes <= 0) ? 'limite' : 'expirado';
                header("Location: assinatura.php?motivo=" . $motivo);
                exit;
            }
        }
        
        // 🚀 BANNER INTELIGENTE ATUALIZADO: Foco nos dias restantes de teste
        if ($plano_atual === 'gratis' && $data_valida) {
            
            // Ativa o banner se faltarem 3 dias ou menos OU se restarem 3 fretes ou menos
            if (($dias_restantes >= 0 && $dias_restantes <= 3) || ($restantes_fretes > 0 && $restantes_fretes <= 3)) {
                
                // Prioridade 1: Se o tempo estiver acabando (3 dias ou menos), dá o alerta de tempo
                if ($dias_restantes >= 0 && $dias_restantes <= 3) {
                    
                    if ($dias_restantes == 0) {
                        $texto_tempo = "<strong>expira hoje!</strong>";
                    } elseif ($dias_restantes == 1) {
                        $texto_tempo = "expira em <strong>1 dia</strong> (amanhã).";
                    } else {
                        $texto_tempo = "expira em <strong>{$dias_restantes} dias</strong>.";
                    }
                    
                    $mensagem_trava_premium = "⏳ <strong>Aviso de Validade:</strong> Seu período de avaliação gratuita {$texto_tempo} <a href='assinatura.php?motivo=expirado' style='color:#FFC107; font-weight:bold; text-decoration:underline;'>Ativar Plano Premium via PIX</a>";
                
                } else {
                    // Prioridade 2: Se o tempo estiver OK, mas os fretes estiverem acabando
                    $mensagem_trava_premium = "💡 <strong>Seu período de testes está terminando:</strong> Você ainda tem mais <strong>{$restantes_fretes}</strong> cálculos gratuitos. <a href='assinatura.php?motivo=limite' style='color:#FFC107; font-weight:bold; text-decoration:underline;'>Garantir acesso ilimitado</a>";
                }
                
            }
//        }
        }
        
    } catch (Exception $e) {
        // Deixa passar em caso de erro no bloco para não travar a calculadora
    }
}

// =========================================================================

try {
    $query = "SELECT c.*, v.media_consumo, v.veiculo 
              FROM configuracoes c 
              JOIN veiculos v ON c.veiculo_id = v.id 
              WHERE c.usuario_id = :usuario_id LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute([':usuario_id' => $usuario_id]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        $query_v_check = "SELECT id FROM veiculos WHERE usuario_id = :usuario_id LIMIT 1";
        $stmt_v_check = $conn->prepare($query_v_check);
        $stmt_v_check->execute([':usuario_id' => $usuario_id]);
        $veiculo_existente = $stmt_v_check->fetch(PDO::FETCH_ASSOC);

        if (!$veiculo_existente) {
            $sql_ins_v = "INSERT INTO veiculos (usuario_id, veiculo, media_consumo) VALUES (:usuario_id, 'Veículo Padrão', 10.0)";
            $stmt_ins_v = $conn->prepare($sql_ins_v);
            $stmt_ins_v->execute([':usuario_id' => $usuario_id]);
            $veiculo_id_criado = $conn->lastInsertId();
        } else {
            $veiculo_id_criado = $veiculo_existente['id'];
        }

        $sql_ins_c = "INSERT INTO configuracoes (usuario_id, veiculo_id, preco_combustivel, lucro_max, lucro_min) 
                      VALUES (:usuario_id, :veiculo_id, 0.00, 3.00, 2.00)";
        $stmt_ins_c_exec = $conn->prepare($sql_ins_c);
        $stmt_ins_c_exec->execute([':usuario_id' => $usuario_id, ':veiculo_id' => $veiculo_id_criado]);

        $stmt->execute([':usuario_id' => $usuario_id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($viagem_edicao) {
        $config['preco_combustivel'] = $viagem_edicao['preco_combustivel'];
    }

} catch (Exception $e) {
    die("Erro crítico ao processar configurações: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, interactive-widget=resizes-content">
    <title>Cálculo de Fretes - RCI</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        /* 💡 AJUSTADO dinamicamente via PHP caso o banner do período de teste apareça */
	body {
	    padding-top: <?php echo !empty($mensagem_trava_premium) ? '230px' : '185px'; ?> !important;
	    box-sizing: border-box;
	    margin: 0;
	}

	 .menu-navegacao { display: flex; gap: 8px; margin-bottom: 8px; width: 100%;}
        .btn-nav { background: #94a3b8; color: white; padding: 10px; text-decoration: none; border-radius: 8px; font-size: 0.8rem; text-align: center; flex: 1; }
        .btn-nav.ativo { background: #2c3e50; font-weight: bold; border-bottom: 4px solid #2ecc71; ; }
        
        /* Painel flutuante fixado no topo da tela */
        .topo-fixado-celular {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background-color: #ffffff;
            padding: 10px 15px 12px 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.12);
            box-sizing: border-box;
	    max-width: 600px;
	    margin: 0 auto;
        }

/* 🟢 ADICIONADO: Estilo reforçado para o banner fixo saltar na tela */
.banner-aviso-premium {
    background-color: #e74c3c !important; /* Vermelho Alerta */
    color: #ffffff !important;
    padding: 10px 15px !important;
    text-align: center !important;
    font-size: 0.85rem !important;
    font-weight: 500 !important;
    line-height: 1.4 !important;
    border-radius: 6px !important;
    margin: 5px 15px 12px 15px !important; /* Afasta das bordas do app */
    box-shadow: 0 4px 10px rgba(231, 76, 60, 0.3) !important;
    display: block !important;
    z-index: 1002 !important; /* Fica acima de tudo */
}

/* Garante que os links dentro do banner fiquem visíveis e destacados */
.banner-aviso-premium a {
    color: #ffc107 !important;
    font-weight: bold !important;
    text-decoration: underline !important;
    margin-left: 5px;
}

        .painel-topo {
            background: #2c3e50;
            color: #fff;
            padding: 8px 12px;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            width: 100%;
            box-sizing: border-box;
        }
        .painel-linha-principal { display: flex; justify-content: space-between; align-items: center; }
        .painel-topo h1 { font-size: 1.4rem; color: #2ecc71; margin: 0; }
        .painel-topo small { font-size: 0.7rem; color: #bdc3c7; font-weight: bold; }
        
        .resumo-cards {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-top: 4px;
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 4px;
        }
        .card-info { font-size: 0.75rem; color: #ecf0f1; }
        .card-info strong { color: #ffffff; }
        
        .row-dupla { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .btn-whats { background-color: #25D366; color: white; }
        .btn-whats:hover { background-color: #128C7E; }
        .btn-limpar { background-color: #e74c3c; color: white; }
        .btn-limpar:hover { background-color: #c0392b; }
        .btn-clonar { background-color: #9b59b6; color: white; }
        .btn-clonar:hover { background-color: #8e44ad; }
        
        .checkbox-faturamento {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #e8f4f8;
            padding: 10px;
            border-radius: 4px;
            margin-top: 24px;
            height: 44px;
            box-sizing: border-box;
            transition: background 0.3s;
        }
        .checkbox-faturamento input { transform: scale(1.3); }

        input[type="text"], input[type="number"], input[type="tel"], input[type="date"], input[type="datetime-local"] {
            width: 100% !important;
            box-sizing: border-box !important;
            padding: 10px !important;
            border: 1px solid #ccc !important;
            border-radius: 4px !important;
            font-size: 15px !important;
            height: 44px !important;
            /* 💡 AJUSTADO dinamicamente para manter o scroll de foco alinhado com o tamanho do cabeçalho */
            scroll-margin-top: <?php echo !empty($mensagem_trava_premium) ? '235px' : '195px'; ?> !important; 
        }

        .titulo-calculo-container {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-top: 5px;
            margin-bottom: 10px;
        }
        .titulo-calculo-container h2 { margin: 0; }
        .titulo-calculo-container .registro-id {
            font-size: 0.95rem;
            color: #7f8c8d;
            font-weight: bold;
            background: #e1e8ed;
            padding: 2px 8px;
            border-radius: 4px;
        }

        #form-calculo { padding-bottom: 40px; }

        .calculo-footer {
            text-align: center;
            margin-top: 10px;
            padding-bottom: 210px; 
            width: 100%;
        }
        .calculo-footer p { margin: 4px 0; font-family: sans-serif; }
        .calculo-footer .copyright { font-size: 0.85rem; color: #64748b; font-weight: 500; }
        .calculo-footer .frase-efeito { font-size: 0.8rem; color: #94a3b8; }
        
        .btn-logoff {background-color: #34495e; margin-top: 35px; margin-bottom: 20px; text-align: center; text-decoration: none; font-weight: bold; display: block; color: white; padding: 12px; border-radius: 4px;}
    </style>
</head>

<body>
<div class="topo-fixado-celular">

    <!-- 🟢 ENCAIXADO: O banner agora fica no topo de tudo, antes do menu -->
    <?php if (!empty($mensagem_trava_premium)): ?>
        <div class="banner-aviso-premium">
            <?php echo $mensagem_trava_premium; ?>
        </div>
    <?php endif; ?>

    <div class="menu-navegacao">
        <a href="calculo.php" class="btn-nav ativo">Cálculo</a>
        <a href="historico.php" class="btn-nav">Histórico</a>
        <a href="faturamento.php" class="btn-nav">Faturamento</a>
        <a href="configuracoes.php" class="btn-nav">Configurações</a>
    </div>

    <div class="painel-topo">
        <?php 
            $nome_completo = $_SESSION['usuario_nome'] ?? 'Motorista';
            $partes_nome = explode(' ', trim($nome_completo));
            $primeiro_nome = $partes_nome[0];
        ?>
        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #2ecc71; font-weight: bold; margin-bottom: 2px;">
            <span>Olá, <?php echo htmlspecialchars($primeiro_nome); ?>! 👋</span>
            <span style="color: #bdc3c7; font-weight: normal;">RCI Transportes</span>
        </div>

        <div class="painel-linha-principal">
            <small>TOTAL DO FRETE</small>
            <h1 id="txt-total-frete">R$ 0,00</h1>
        </div>
        <div class="resumo-cards">
            <div class="card-info">Gasto: <strong id="txt-gasto-comb">R$ 0,00</strong></div>
            <div class="card-info" style="display: flex; justify-content: flex-end; align-items: center; white-space: nowrap; text-align: right;">
                <span>Lucro Líquido:&nbsp;</span>
                <strong id="txt-lucro-liq">R$ 0,00</strong> 
                <span id="txt-pct-lucro" style="color: #2ecc71; font-weight: bold; margin-left: 5px;">(0%)</span>
            </div>
        </div>
        <div style="font-size: 0.7rem; margin-top: 10px; color: #bdc3c7; text-align: center;">
            Fator: <span id="txt-fator-atual">0.0</span> | Média: <?php echo $config['media_consumo']; ?> KM/L | <strong id="txt-val-km" style="color: #2ecc71;">R$ 0,00</strong>/KM | Veículo: <strong style="color: #2ecc71; font-weight: bold;"><?php echo htmlspecialchars($config['veiculo'] ?? 'Padrão'); ?></strong>
        </div>
    </div>
</div>

<div class="container">
    
    <?php if (isset($_GET['sucesso'])): ?>
        <div id="alerta-sucesso" style="background-color: #2ecc71; color: white; text-align: center; padding: 10px; font-weight: bold; font-size: 0.9rem; border-radius: 4px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%; box-sizing: border-box;">
            ✔️ Lançamento gravado com sucesso!
        </div>
        <script>
            setTimeout(() => {
                const alerta = document.getElementById('alerta-sucesso');
                if (alerta) alerta.style.display = 'none';
            }, 3000);
        </script>
    <?php endif; ?>

    <div class="titulo-calculo-container">
        <h2>Cálculo de Frete</h2>
        <?php if ($viagem_edicao): ?>
            <span class="registro-id" id="exibicao-id">[#<?php echo $viagem_edicao['id']; ?>]</span>
        <?php endif; ?>
    </div>
    
    <form action="salvar_viagem.php" method="POST" id="form-calculo">
        <input type="hidden" name="usuario_id" value="<?php echo $usuario_id; ?>">
        <input type="hidden" name="veiculo_id" value="<?php echo $config['veiculo_id']; ?>">
        <input type="hidden" id="lucro_max" value="<?php echo $config['lucro_max']; ?>">
        <input type="hidden" id="lucro_min" value="<?php echo $config['lucro_min']; ?>">
        <input type="hidden" id="media_consumo" value="<?php echo $config['media_consumo']; ?>">
        
        <input type="hidden" name="total_frete" id="input-total-frete" value="<?php echo $viagem_edicao ? $viagem_edicao['total_frete'] : '0.00'; ?>">
        <input type="hidden" name="gasto_combustivel" id="input-gasto-comb" value="<?php echo $viagem_edicao ? $viagem_edicao['gasto_combustivel'] : '0.00'; ?>">
        <input type="hidden" name="lucro_liquido" id="input-lucro-liq" value="<?php echo $viagem_edicao ? $viagem_edicao['lucro_liquido'] : '0.00'; ?>">

        <input type="hidden" name="viagem_id" id="viagem_id" value="<?php echo $viagem_edicao ? $viagem_edicao['id'] : ''; ?>">
        
        <div class="row-dupla">
            <div class="form-group">
                <label>Preço Combustível (R$):</label>
                <input type="number" id="preco_combustivel" name="preco_combustivel" step="0.01" value="<?php echo $config['preco_combustivel']; ?>" required>
            </div>
            <div class="form-group">
                <label>Distância (Km):</label>
                <input type="number" id="distancia_km" name="distancia_km" step="0.1" value="<?php echo $viagem_edicao ? $viagem_edicao['distancia_km'] : ''; ?>" required placeholder="Ex: 150">
            </div>
        </div>

        <div class="row-dupla">
            <div class="form-group">
                <label>Pedágio (R$):</label>
                <input type="number" id="pedagio" name="pedagio" step="0.01" value="<?php echo $viagem_edicao ? $viagem_edicao['pedagio'] : '0.00'; ?>">
            </div>
            <div class="form-group">
                <label>Ajudante (R$):</label>
                <input type="number" id="ajudante" name="ajudante" step="0.01" value="<?php echo $viagem_edicao ? $viagem_edicao['ajudante'] : '0.00'; ?>">
            </div>
        </div>

        <div class="row-dupla">
            <div class="form-group">
                <label>Outros (+) (R$):</label>
                <input type="number" id="outros" name="outros" step="0.01" value="<?php echo $viagem_edicao ? $viagem_edicao['outros'] : '0.00'; ?>">
            </div>
            <div class="form-group">
                <label>Ajuste (-) (R$):</label>
                <input type="number" id="ajuste" name="ajuste" step="0.01" value="<?php echo $viagem_edicao ? $viagem_edicao['ajuste'] : '0.00'; ?>">
            </div>
        </div>

        <h3 style="margin-top: 20px;">Rota e Contato</h3>
        
        <div class="form-group">
            <label for="origem">Origem:</label>
            <input type="text" id="origem" name="origem" value="<?php echo $viagem_edicao ? htmlspecialchars($viagem_edicao['origem']) : ''; ?>" required placeholder="Cidade ou Endereço de saída">
        </div>

        <div class="form-group">
            <label for="destino">Destino:</label>
            <input type="text" id="destino" name="destino" value="<?php echo $viagem_edicao ? htmlspecialchars($viagem_edicao['destino']) : ''; ?>" required placeholder="Cidade ou Endereço de chegada">
        </div>

        <div class="row-dupla">
            <div class="form-group">
                <label for="solicitante">Solicitante:</label>
                <input type="text" id="solicitante" name="solicitante" value="<?php echo $viagem_edicao ? htmlspecialchars($viagem_edicao['solicitante']) : ''; ?>" placeholder="Nome do cliente">
            </div>
            <div class="form-group">
                <label for="telefone_cliente">Telefone:</label>
                <input type="tel" id="telefone_cliente" name="telefone_cliente" value="<?php echo $viagem_edicao ? htmlspecialchars($viagem_edicao['telefone_cliente']) : ''; ?>" placeholder="Ex: (11) 99999-9999" maxlength="15">
            </div>
        </div>
                    
<div class="row-dupla">
    <!-- Campo de Data -->
    <div class="form-group" style="flex: 1;">
        <label for="data_frete">Data do Frete:</label>
        <?php 
            if ($viagem_edicao && !empty($viagem_edicao['data_viagem'])) {
                $valor_data = date('Y-m-d\TH:i', strtotime($viagem_edicao['data_viagem']));
            } else {
                $valor_data = date('Y-m-d\TH:i');
            }
        ?>
        <input type="datetime-local" id="data_frete" name="data_frete" value="<?php echo $valor_data; ?>" required>
    </div>
                
    <!-- Grupo de Opções (Faturar e Agendar lado a lado) -->
    <div style="display: flex; gap: 6px; align-items: flex-end; padding-bottom: 12px;">
        
        <!-- Checkbox Faturar -->
        <div class="checkbox-faturamento" id="box-faturamento">
            <input type="checkbox" id="faturar" name="faturar" value="1" <?php echo ($viagem_edicao && $viagem_edicao['faturar'] == 1) ? 'checked' : ''; ?>>
            <label for="faturar" style="margin-bottom:0; cursor:pointer; font-weight: normal;">Faturar</label>
        </div>

	<!-- Checkbox Agendar (Viagem) com Texto Dinâmico -->
	<div class="checkbox-faturamento" id="box-agendar">
	    <input type="checkbox" id="agendar" name="agendar" value="1" <?php echo ($viagem_edicao && isset($viagem_edicao['viagem']) && $viagem_edicao['viagem'] == 1) ? 'checked' : ''; ?>>
	    <label for="agendar" style="margin-bottom:0; cursor:pointer; font-weight: normal;">
       	 <span id="txt-status-agenda">Agendar</span>
	    </label>
	</div>

    </div>
</div>

        <div style="margin-top: 20px; margin-bottom: 10px; display: flex; flex-direction: column; gap: 8px;">
            <button type="submit" class="btn" id="btn-principal-salvar">
                <?php echo $viagem_edicao ? 'Atualizar Viagem' : 'Salvar Viagem'; ?>
            </button>
            
            <div style="display: flex; gap: 8px;">
                <?php if ($viagem_edicao): ?>
                    <button type="button" class="btn btn-whats" id="btn-whatsapp" style="flex: 1;">Copiar Whats</button>
                    <button type="button" class="btn btn-clonar" id="btn-clonar" style="flex: 1;">Clonar</button>
                <?php endif; ?>
                <button type="button" class="btn btn-limpar" id="btn-limpar" style="flex: 1;">Limpar</button>
            </div>
        </div>

	<!-- INPUTS OCULTOS COM OS VALORES DO VEÍCULO ATIVO CARREGADOS DO BANCO -->
	<input type="hidden" id="v_pneu" value="<?php echo isset($usuario_config['pneu']) ? floatval($usuario_config['pneu']) : '0.0000'; ?>">
	<input type="hidden" id="v_troca_oleo" value="<?php echo isset($usuario_config['troca_oleo']) ? floatval($usuario_config['troca_oleo']) : '0.0000'; ?>">
	<input type="hidden" id="v_outros" value="<?php echo isset($usuario_config['outros']) ? floatval($usuario_config['outros']) : '0.0000'; ?>">
    </form>
    
    <a href="logout.php" class="btn btn-logoff" style="margin-top: 15px;">Logoff / Sair do Sistema</a>

    <footer class="calculo-footer">
        <p class="copyright">&copy; <?php echo date('Y'); ?> RCI Transportes - Todos os direitos reservados.</p>
        <p class="frase-efeito">Desenvolvido para motoristas que valorizam o seu trabalho.</p>
    </footer>

</div>

<script>
const inputs = ['preco_combustivel', 'distancia_km', 'pedagio', 'ajudante', 'outros', 'ajuste'];

inputs.forEach(id => {
    document.getElementById(id).addEventListener('input', calcularSistemaFrete);
});

document.querySelectorAll('#form-calculo input:not([type="hidden"]):not([type="checkbox"])').forEach(input => {
    input.addEventListener('click', function() {
        if (!this.classList.contains('focado')) {
            setTimeout(() => {
                if (typeof this.select === 'function') {
                    this.select();
                }
                if (this.setSelectionRange && this.type !== 'number') {
                    this.setSelectionRange(0, this.value.length);
                }
                this.classList.add('focado');
            }, 80);
        }
    });

    input.addEventListener('blur', function() {
        this.classList.remove('focado');
    });
});

document.getElementById('telefone_cliente').addEventListener('input', function(e) {
    let x = e.target.value.replace(/\D/g, '').match(/(\d{0,2})(\d{0,5})(\d{0,4})/);
    e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
});

document.getElementById('faturar').addEventListener('change', function() {
    // ============================================================
    // AUTOMAÇÃO DO CHECKBOX FATURAR
    // ============================================================

    const viagemId = document.getElementById('viagem_id').value;
    
    if (viagemId && viagemId !== '') {
        const boxFaturamento = document.getElementById('box-faturamento');
        const originalBg = boxFaturamento.style.background;
        
        boxFaturamento.style.background = '#d0eaf3';

        const formData = new FormData();
        formData.append('id', viagemId);
        formData.append('faturar', this.checked ? 1 : 0);
        fetch('atualizar_status_faturamento.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json()) // Alterado para .json()
.then(data => {
    if (data.sucesso) {
        // Sucesso! O box fica verde
        boxFaturamento.style.background = '#2ecc71';
        setTimeout(() => { boxFaturamento.style.background = originalBg; }, 600);
    } else {
        alert('Erro: ' + (data.erro || 'Erro no servidor'));
        this.checked = !this.checked;
    }
})
.catch(error => {
    alert('Erro de conexão com o servidor.');
    checkbox.checked = !checkbox.checked;
});
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // ============================================================
    // AUTOMAÇÃO DO CHECKBOX AGENDAR
    // ============================================================
    const inputAgendarAuto = document.getElementById('agendar');
    const divBoxAgendaAuto = document.getElementById('box-agendar');

    if (inputAgendarAuto && divBoxAgendaAuto) {
        inputAgendarAuto.addEventListener('change', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const viagemId = urlParams.get('editar_id');

            if (!viagemId) return; // Se for nova viagem, salva no botão final

            // Guarda o fundo original e põe uma cor de carregamento (azul claro)
            const bgOriginalAgenda = divBoxAgendaAuto.style.background || '';
            divBoxAgendaAuto.style.background = '#d0eaf3';

            const formData = new FormData();
            formData.append('viagem_id', viagemId);
            formData.append('viagem', this.checked ? 1 : 0);

            fetch('atualizar_status_agenda.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    // Sucesso! O box fica verde igual ao faturamento por 600ms
                    divBoxAgendaAuto.style.background = '#2ecc71';
                    setTimeout(() => {
                        divBoxAgendaAuto.style.background = bgOriginalAgenda;
                    }, 600);
                    // Roda a função para mudar o título para (Agendado) ou (Realizado)
                    if (typeof atualizarTextoAgenda === 'function') {
                        atualizarTextoAgenda();
                    }
                } else {
                    alert('Erro ao atualizar agendamento automático.');
                    this.checked = !this.checked;
                    divBoxAgendaAuto.style.background = bgOriginalAgenda;
                    if (typeof atualizarTextoAgenda === 'function') {
                        atualizarTextoAgenda();
                    }
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                this.checked = !this.checked;
                divBoxAgendaAuto.style.background = bgOriginalAgenda;
                if (typeof atualizarTextoAgenda === 'function') {
                    atualizarTextoAgenda();
                }
            });
        });
    }

    // Executa a leitura da data uma vez ao abrir a página (essencial no modo edição)
    if (typeof atualizarTextoAgenda === 'function') {
        atualizarTextoAgenda();
    }
});

function calcularFatorLucro(distancia, lucroMax, lucroMin) {
    if (distancia <= 100) return lucroMax;
    if (distancia >= 1000) return lucroMin;
    let intervaloDistancia = 1000 - 100; 
    let distanciaPercorridaNoIntervalo = distancia - 100; 
    let diferencaLucro = lucroMax - lucroMin;
    let decrescimo = (distanciaPercorridaNoIntervalo / intervaloDistancia) * diferencaLucro;
    return parseFloat((lucroMax - decrescimo).toFixed(4));
}

function calcularSistemaFrete() {
    const precoComb = parseFloat(document.getElementById('preco_combustivel').value) || 0;
    const distancia = parseFloat(document.getElementById('distancia_km').value) || 0;
    const pedagio = parseFloat(document.getElementById('pedagio').value) || 0;
    const ajudante = parseFloat(document.getElementById('ajudante').value) || 0;
    
    // Mantido o nome original 'outros' para não quebrar o resto da função abaixo
    const outros = parseFloat(document.getElementById('outros').value) || 0;
    const ajuste = parseFloat(document.getElementById('ajuste').value) || 0;

    // Captura dos novos inputs ocultos vindos da tabela veiculos
    const custoPneuPorKm = parseFloat(document.getElementById('v_pneu').value) || 0;
    const custoOleoPorKm = parseFloat(document.getElementById('v_troca_oleo').value) || 0;
    const custoOutrosPorKm = parseFloat(document.getElementById('v_outros').value) || 0;
    
    const mediaConsumo = parseFloat(document.getElementById('media_consumo').value) || 1;
    const lucroMax = parseFloat(document.getElementById('lucro_max').value) || 3.0;
    const lucroMin = parseFloat(document.getElementById('lucro_min').value) || 2.0;

    if(distancia <= 0 || mediaConsumo <= 0) {
        zerarPainel();
        return;
    }

    const FatorLucro = calcularFatorLucro(distancia, lucroMax, lucroMin);
    document.getElementById('txt-fator-atual').innerText = FatorLucro;

    // Totais acumulados com base na distância multiplicada pelas taxas do veículo
    const totalPneu = custoPneuPorKm * distancia;
    const totalOleo = custoOleoPorKm * distancia;
    const totalOutrosVeiculo = custoOutrosPorKm * distancia;

    const gastoCombustivel = (distancia / mediaConsumo) * precoComb;

    // Soma de todos os valores incluindo os novos custos por KM do veículo
    const totalFrete = (gastoCombustivel * FatorLucro) + pedagio + ajudante + outros + totalPneu + totalOleo + totalOutrosVeiculo - ajuste;

    // Lucro líquido real descontando também a manutenção do veículo
    const lucroLiquido = totalFrete - gastoCombustivel - pedagio - ajudante - totalPneu - totalOleo - totalOutrosVeiculo;
    const valorPorKm = totalFrete / distancia;

    // ==============================================
    // CÁLCULO DO PERCENTUAL DE LUCRO (CORRIGIDO)
    // ==============================================
    const gastosTotaisOperacionais = gastoCombustivel + totalPneu + totalOleo + totalOutrosVeiculo;

    let percentualLucroGasto = 0;
    
    // Validamos se o gasto real totalizado é maior que zero para evitar erros matemáticos
    if (gastosTotaisOperacionais > 0) {
        percentualLucroGasto = (lucroLiquido / gastosTotaisOperacionais) * 100;
    }

    // ==============================================
    // EXIBIÇÃO DOS RESULTADOS ATUALIZADOS NA TELA
    // ==============================================
    document.getElementById('txt-total-frete').innerText = totalFrete.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    document.getElementById('txt-gasto-comb').innerText = gastosTotaisOperacionais.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    document.getElementById('txt-lucro-liq').innerText = lucroLiquido.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    
    document.getElementById('txt-val-km').innerText = valorPorKm.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    
    // Mostra o percentual correto com duas casas decimais (ex: 2.43%)
    document.getElementById('txt-pct-lucro').innerText = `(${percentualLucroGasto.toFixed(2)}%)`;

    // Atualiza os inputs ocultos de envio do formulário
    document.getElementById('input-total-frete').value = totalFrete.toFixed(2);
    document.getElementById('input-gasto-comb').value = gastosTotaisOperacionais.toFixed(2);
    document.getElementById('input-lucro-liq').value = lucroLiquido.toFixed(2);
}

function zerarPainel() {
    document.getElementById('txt-total-frete').innerText = "R$ 0,00";
    document.getElementById('txt-gasto-comb').innerText = "R$ 0,00";
    document.getElementById('txt-lucro-liq').innerText = "R$ 0,00";
    document.getElementById('txt-fator-atual').innerText = "0.0";
    document.getElementById('txt-val-km').innerText = "R$ 0,00";
    document.getElementById('txt-pct-lucro').innerText = "(0%)";
    
    document.getElementById('input-total-frete').value = "0.00";
    document.getElementById('input-gasto-comb').value = "0.00";
    document.getElementById('input-lucro-liq').value = "0.00";
}

document.getElementById('btn-limpar').addEventListener('click', () => {
    document.getElementById('distancia_km').value = '';
    document.getElementById('pedagio').value = '0.00';
    document.getElementById('ajudante').value = '0.00';
    document.getElementById('outros').value = '0.00';
    document.getElementById('ajuste').value = '0.00';
    document.getElementById('origem').value = '';
    document.getElementById('destino').value = '';
    document.getElementById('solicitante').value = '';
    document.getElementById('telefone_cliente').value = '';
    document.getElementById('faturar').checked = false;
    document.getElementById('viagem_id').value = '';
    
    const agora = new Date();
    const fAno = agora.getFullYear();
    const fMes = String(agora.getMonth() + 1).padStart(2, '0');
    const fDia = String(agora.getDate()).padStart(2, '0');
    const fHora = String(agora.getHours()).padStart(2, '0');
    const fMin = String(agora.getMinutes()).padStart(2, '0');
    document.getElementById('data_frete').value = `${fAno}-${fMes}-${fDia}T${fHora}:${fMin}`;

    zerarPainel();

    document.getElementById('btn-principal-salvar').innerText = 'Salvar Viagem';

    const badgeId = document.getElementById('exibicao-id');
    if(badgeId) badgeId.style.display = 'none';
    if(window.location.search.includes('editar_id')) {
        window.history.replaceState({}, document.title, "calculo.php");
        const btnWhatsapp = document.getElementById('btn-whatsapp');
        if(btnWhatsapp) btnWhatsapp.style.display = 'none';
        const btnClonar = document.getElementById('btn-clonar');
        if(btnClonar) btnClonar.style.display = 'none';
    }
    window.scrollTo({ top: 0, left: 0, behavior: "smooth" });
});

const btnClonarElement = document.getElementById('btn-clonar');
if (btnClonarElement) {
    btnClonarElement.addEventListener('click', () => {
        document.getElementById('viagem_id').value = '';
        document.getElementById('btn-principal-salvar').innerText = 'Salvar Viagem (Cópia Clonada)';
        window.history.replaceState({}, document.title, "calculo.php");
        btnClonarElement.style.display = 'none';
        
        const badgeId = document.getElementById('exibicao-id');
        if(badgeId) badgeId.style.display = 'none';

        alert("Viagem clonada com sucesso! Ajuste os dados que desejar e clique em Salvar para criar o novo registro.");
    });
}

document.getElementById('btn-whatsapp').addEventListener('click', () => {
    const origen = document.getElementById('origem').value;
    const destino = document.getElementById('destino').value;
    
    if(!origen || !destino) {
        alert("Preencha ao menos Origem e Destino para copiar o texto!");
        return;
    }

    const totalFreteVal = parseFloat(document.getElementById('input-total-frete').value) || 0;
    const ajudanteVal = parseFloat(document.getElementById('ajudante').value) || 0;

    let textoWhats = `📦 _Orçamento de Frete:_\n\n📍 ${origen}\n 🏁 ${destino}\n\n`;

    if (ajudanteVal > 0) {
        const fretePuro = totalFreteVal - ajudanteVal;
        
        const formattedFrete = fretePuro.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        const formattedAjudante = ajudanteVal.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        const formattedTotal = totalFreteVal.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        textoWhats += `📋 Frete ${formattedFrete}\n`;
        textoWhats += `* 👤 Ajudante +${formattedAjudante}\n\n`;
        textoWhats += `🚚 Total do Frete:  *${formattedTotal}*`;
    } else {
        const formattedTotal = totalFreteVal.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        textoWhats += `🚚 Total do Frete:  *${formattedTotal}*`;
    }
    
    textoWhats += `\n\n\n_⏳ Fico à disposição se tiver alguma dúvida. Se estiver tudo certo, pode me dar o seu "Ok" por aqui._`;

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(textoWhats).then(() => {
            alert("Texto formatado copiado! É só colar no WhatsApp.");
        }).catch(() => {
            executarCopiaManual(textoWhats);
        });
    } else {
        executarCopiaManual(textoWhats);
    }
});

function executarCopiaManual(texto) {
    const tempTextArea = document.createElement('textarea');
    tempTextArea.value = texto;
    tempTextArea.style.position = 'fixed'; 
    document.body.appendChild(tempTextArea);
    tempTextArea.focus();
    tempTextArea.select();
    try {
        document.execCommand('copy');
        alert("Texto formatado copiado! É só colar no WhatsApp.");
    } catch (err) {
        alert("Erro ao copiar. Por favor, tente copiar manualmente.");
    }
    document.body.removeChild(tempTextArea);
}

function atualizarTextoAgenda() {
    const inputAgendar = document.getElementById('agendar');
    const inputData = document.getElementById('data_frete');
    const txtStatus = document.getElementById('txt-status-agenda');

    // Se o input não existir na tela, interrompe a função
    if (!inputAgendar) return;

    // Se a caixinha não estiver marcada de verdade, força o texto padrão
    if (!inputAgendar.checked) {
        txtStatus.innerText = 'Agendar';
        return;
    }

    // Se tiver data preenchida, faz o cálculo do tempo
    if (inputData && inputData.value) {
        const dataSelecionada = new Date(inputData.value);
        const agora = new Date();

        // Altera o texto visual baseado na data escolhida
        if (dataSelecionada > agora) {
            txtStatus.innerText = 'Agendado';
        } else {
            txtStatus.innerText = 'Realizado';
        }
    }
}

// Vincula a função aos eventos de mudança na tela
document.addEventListener('DOMContentLoaded', function() {
    const inputAgendar = document.getElementById('agendar');
    const inputData = document.getElementById('data_frete');

    if (inputAgendar) {
        inputAgendar.addEventListener('change', atualizarTextoAgenda);
    }
    if (inputData) {
        inputData.addEventListener('change', atualizarTextoAgenda);
    }

    // Executa uma vez ao carregar a página (importante para o modo edição)
    atualizarTextoAgenda();
});

// Vincula a função aos eventos da página para rodar na hora
document.getElementById('agendar').addEventListener('change', atualizarTextoAgenda);
document.getElementById('data_frete').addEventListener('change', atualizarTextoAgenda);

// Roda uma vez assim que a página carregar (caso seja uma edição de viagem antiga)
document.addEventListener('DOMContentLoaded', atualizarTextoAgenda);

window.onload = calcularSistemaFrete;
</script>
</body>
</html>
