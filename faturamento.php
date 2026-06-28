<?php
// 1. INCLUSÃO DA TRAVA DE SEGURANÇA E CONEXÃO (PADRÃO PDO MULTIUSUÁRIO)
require_once 'trava.php'; 
require_once 'conexao.php';

$cliente_selecionado = $_POST['cliente'] ?? '';
$vencimento = $_POST['vencimento'] ?? '';
$codigo_barras = $_POST['codigo_barras'] ?? '';
$html_gerado = "";
$mensagem_erro = "";

// Função para formatar a linha digitável do boleto
function formatarCodigoBarrasPHP($codigo) {
    $num = preg_replace('/\D/', '', $codigo);
    if (strlen($num) !== 47) {
        return $codigo;
    }
    return substr($num, 0, 5) . '.' . substr($num, 5, 5) . ' ' .
           substr($num, 10, 5) . '.' . substr($num, 15, 6) . ' ' .
           substr($num, 21, 5) . '.' . substr($num, 26, 6) . ' ' .
           substr($num, 32, 1) . ' ' . substr($num, 33);
}

// 2. BUSCA OS DADOS CADASTRAIS DO EMISSOR (USUÁRIO LOGADO) PARA A ASSINATURA E PERMISSÕES
try {
    // 💡 AJUSTE DE MESTRE: Buscamos também o nível de acesso do usuário em vez de travar no ID 1 fixo
    $query_u = "SELECT nome, telefone, nivel_acesso FROM usuarios WHERE id = :usuario_id LIMIT 1";
    $stmt_u = $conn->prepare($query_u);
    $stmt_u->execute([':usuario_id' => $usuario_id]);
    $emissor = $stmt_u->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Erro ao carregar dados do usuário: " . $e->getMessage());
}

// 3. PROCESSA E GERA A ESTRUTURA VISUAL BASEADO NO faturar DO USUÁRIO LOGADO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Busca apenas as viagens do usuário logado que estão marcadas no faturamento (faturar = 1)
        $sql_viagens = "SELECT * FROM viagens WHERE faturar = 1 AND usuario_id = :usuario_id ORDER BY data_viagem ASC";
        $stmt_viagens = $conn->prepare($sql_viagens);
        $stmt_viagens->execute([':usuario_id' => $usuario_id]);
        $viagens = $stmt_viagens->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($viagens) > 0) {
            
            $html_gerado .= '<div style="font-family: sans-serif; max-width: 500px; margin: 0 auto; color: #666666; line-height: 1.5;">';
            $html_gerado .= '  <div style="font-size: 14px; margin-top: 0; margin-bottom: 15px; color: #666666;">Prezados, anexo Documentação referente a:</div>';
            
            $total_receber = 0;
            $cor_alternada = false;

            foreach ($viagens as $v) {
                $total_receber += $v['total_frete'];
                $data_formatada = date('d/m', strtotime($v['data_viagem']));
                $valor_formatada = number_format($v['total_frete'], 2, ',', '.');
                
                $bg_bloco = $cor_alternada ? '#f8f9fa' : '#fdfbe7'; 
                $cor_alternada = !$cor_alternada;

                $html_gerado .= '  <div style="background-color: '.$bg_bloco.'; padding: 10px 10px; margin-bottom: 2px;">';
                $html_gerado .= '    <table width="100%" cellspacing="0" cellpadding="0" style="font-size: 14px; border-collapse: collapse;">';
                $html_gerado .= '      <tr>';
                $html_gerado .= '        <td style="color: #444444; font-weight: bold; width: 65px; padding-right: 10px; vertical-align: top; padding-top: 2px;">'.$data_formatada.'</td>';
                $html_gerado .= '        <td style="color: #666666; padding-right: 10px; vertical-align: top;">';
                $html_gerado .= '          <span style="color: #555555; font-weight: 500;">'.htmlspecialchars($v['origem']).' &rarr; '.htmlspecialchars($v['destino']).'</span>';
                if (!empty($v['solicitante'])) {
                    $html_gerado .= '          <br><span style="font-size: 12px; color: #888888; font-style: italic;">Autorizado: <strong style="color: #555555;">'.htmlspecialchars($v['solicitante']).'</strong></span>';
                }
                $html_gerado .= '        </td>';
                $html_gerado .= '        <td align="right" style="color: #7070fa; font-weight: bold; vertical-align: middle; width: 110px; white-space: nowrap;">R$ '.$valor_formatada.'</td>';
                $html_gerado .= '      </tr>';
                $html_gerado .= '    </table>';
                $html_gerado .= '  </div>';
            }
            
            $total_final_formatado = number_format($total_receber, 2, ',', '.');
            $data_venc_formatada = !empty($vencimento) ? date('d/m/Y', strtotime($vencimento)) : 'A combinar';

            $html_gerado .= '  <div style="border-top: 1px solid #e0e0e0; margin-top: 15px; padding-top: 12px; text-align: right; margin-bottom: 12px;">';
            $html_gerado .= '    <span style="font-size: 16px; font-weight: bold; color: #555555;">Total a Receber: R$ '.$total_final_formatado.'</span>';
            $html_gerado .= '  </div>';
            
            $html_gerado .= '  <div style="border-top: 1px solid #e0e0e0; padding-top: 12px; font-size: 14px; color: #666666;">';
            $nome_pagador = !empty($cliente_selecionado) ? $cliente_selecionado : 'A combinar';
            $html_gerado .= '    <div style="margin: 5px 0;">Pagador: <strong style="color: #444444; text-transform: uppercase;">'.htmlspecialchars($nome_pagador).'</strong></div>';
            $html_gerado .= '    <div style="margin: 5px 0;">Vencimento: <strong style="color: #444444;">'.$data_venc_formatada.'</strong></div>';
            
            if (!empty($codigo_barras)) {
                $codigo_formatado = formatarCodigoBarrasPHP($codigo_barras);
                $html_gerado .= '    <div style="margin: 5px 0; font-size: 13px; line-height: 1.4;">Código de Barras: <span style="color: #444444; font-weight: bold; font-family: sans-serif; letter-spacing: 0.3px;">'.htmlspecialchars($codigo_formatado).'</span></div>';
            }
            $html_gerado .= '  </div>';
            
            // ASSINATURA DINÂMICA
            $html_gerado .= '  <div style="border-top: 1px solid #e0e0e0; margin-top: 20px; padding-top: 15px; font-size: 14px; color: #666666;">';
            $html_gerado .= '    <div style="margin: 0 0 15px 0;">Atenciosamente;</div>';
            $html_gerado .= '    <div style="margin: 0; color: #444444; font-weight: bold;">'.htmlspecialchars($emissor['nome'] ?? 'Profissional').'</div>';
            if (!empty($emissor['telefone'])) {
                $html_gerado .= '    <div style="margin: 3px 0 0 0; color: #666666;"><strong style="color: #444444;">'.htmlspecialchars($emissor['telefone']).'</strong></div>';
            }
            $html_gerado .= '  </div>';
            $html_gerado .= '</div>';
        } else {
            $mensagem_erro = "Nenhum frete ativo encontrado no seu Faturamento. Marque os fretes no Histórico primeiro.";
        }
    } catch (Exception $e) {
        $mensagem_erro = "Erro ao processar faturamento: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCI - Faturamento</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
	 :root { --primary: #2c3e50; --success: #2ecc71; --accent: #3498db; --bg: #f1f5f9; }
        body { background-color: var(--bg); font-family: 'Segoe UI', sans-serif; padding-bottom: 30px; margin-top: 0;}
        .container { max-width: 600px; margin: 0 auto; padding: 15px; margin-top:70px}
        .menu-navegacao { display: flex; gap: 8px; margin-bottom: 20px; }
        .btn-nav { background: #94a3b8; color: white; padding: 10px; text-decoration: none; border-radius: 8px; font-size: 0.8rem; text-align: center; flex: 1; }
        .btn-nav.ativo { background: #2c3e50; font-weight: bold; border-bottom: 4px solid #2ecc71; }

        input, input[type="date"] { 
            width: 100%; 
            padding: 12px; 
            font-size: 14px; 
            font-family: sans-serif;
            border: 1px solid #ccc; 
            border-radius: 6px; 
            background: #fafafa; 
            box-sizing: border-box;
        }
        
        .caixa-resultado { background: #fff; border: 1px solid #bdc3c7; padding: 15px; border-radius: 6px; margin-top: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .btn-copiar { background-color: #3498db; color: white; margin-bottom: 15px; }
        .btn-copiar:hover { background-color: #2980b9; }
        .preview-box { border: 1px dashed #7f8c8d; padding: 15px; background: #fff; margin-top: 15px; border-radius: 4px; }
        .alerta-erro { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 12px; border-radius: 4px; font-size: 0.85rem; text-align: center; margin-top: 15px; font-weight: bold; }

        #barra-progresso {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: #e0e0e0;
            overflow: hidden;
            z-index: 9999;
        }
        #barra-progresso::after {
            content: '';
            display: block;
            width: 40%;
            height: 100%;
            background: #2ecc71;
            animation: carregando 1.2s infinite linear;
        }
        @keyframes carregando {
            0% { margin-left: -40%; }
            100% { margin-left: 100%; }
        }

        body.TelaCopiaEmail #painel-sistema .menu-navegacao, 
        body.TelaCopiaEmail #painel-sistema form, 
        body.TelaCopiaEmail #painel-sistema h2, 
        body.TelaCopiaEmail #painel-sistema .txt-instrucao,
        body.TelaCopiaEmail #painel-sistema .titulo-resultado,
        body.TelaCopiaEmail #painel-sistema .label-preview,
        body.TelaCopiaEmail #painel-sistema #CopiarMailApp { 
            display: none !important; 
        }
        
        body.TelaCopiaEmail .container, 
        body.TelaCopiaEmail .caixa-resultado, 
        body.TelaCopiaEmail #conteudo-previa { 
            background: none !important; 
            border: none !important; 
            box-shadow: none !important; 
            padding: 0 !important; 
            margin: 0 !important; 
            max-width: 100% !important;
        }
        /* Painel flutuante fixado no topo da tela */
        .topo-fixado-celular {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            margin-bottom: 0;
            z-index: 1000;
            background-color: #ffffff;
            padding: 10px 15px 0px 15px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.12);
            box-sizing: border-box;
	    max-width: 600px;
	    margin: 0 auto;
        }
    </style>
</head>

<body>
<div id="barra-progresso"></div>

<div class="container" id="painel-sistema">
    
    <div class="topo-fixado-celular">
        <div class="menu-navegacao">
            <a href="calculo.php" class="btn-nav">Cálculo</a>
            <a href="historico.php" class="btn-nav">Histórico</a>
            <a href="faturamento.php" class="btn-nav ativo">Faturamento</a>
            <a href="configuracoes.php" class="btn-nav">Configurações</a>
        </div>
    </div>

    <h2 style="margin-top: 15px;">Gerador de Fechamento</h2>

    <form method="POST" action="faturamento.php" onsubmit="ativarCarregando(this)">
        <div class="form-group">
            <label for="cliente">Nome do Pagador / Empresa (Opcional):</label>
            <input type="text" id="cliente" name="cliente" placeholder="Ex: RCI" value="<?php echo htmlspecialchars($cliente_selecionado); ?>">
        </div>

        <div class="form-group">
            <label for="vencimento">Data de Vencimento:</label>
            <input type="date" id="vencimento" name="vencimento" value="<?php echo $vencimento; ?>">
        </div>

        <div class="form-group">
            <label for="txtCodigoBarras">Linha Digitável do Boleto (Opcional):</label>
            <input type="text" id="txtCodigoBarras" name="codigo_barras" placeholder="Apenas números (Ex: 4039000007...)" maxlength="54" oninput="FormataCodigoBarras()" value="<?php echo htmlspecialchars($codigo_barras); ?>">
        </div>

        <button type="submit" id="btnEnviarForm" class="btn">Gerar Estrutura de Cobrança ⚡</button>
    </form>

    <?php if (!empty($mensagem_erro)): ?>
        <div class="alerta-erro"><?php echo $mensagem_erro; ?></div>
    <?php endif; ?>

    <?php if (!empty($html_gerado)): ?>
        <div class="caixa-resultado">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; border-bottom: 1px solid #ccc; padding-bottom: 8px; margin-bottom: 15px;">
    
                <h2 style="margin: 0; display: flex; align-items: center; font-size: 1.3rem; color: #2c3e50;">
                    🎉 Fechamento Gerado!
                </h2>

                <?php 
                // 🛠️ CORREÇÃO DE SEGURANÇA: Agora liberado para você (ID 1) ou para quem tiver nível administrativo ('admin')
                if (isset($_SESSION['usuario_id']) && (intval($_SESSION['usuario_id']) === 1 || ($emissor['nivel_acesso'] ?? '') === 'admin')): 
                ?>
                    <a href="cte.php" target="_blank" class="btn-cte" style="
                        background-color: #3498db; 
                        color: white; 
                        text-decoration: none; 
                        padding: 5px 12px; 
                        border-radius: 4px; 
                        font-size: 0.8rem; 
                        font-weight: bold;
                        transition: background 0.2s;
                    " onmouseover="this.style.backgroundColor='#2980b9'" onmouseout="this.style.backgroundColor='#3498db'">
                        CTE
                    </a>
                <?php endif; ?>

            </div>
            <p class="txt-instrucao" style="font-size: 0.8rem; color: #7f8c8d; margin-bottom:12px;">Clique no botão abaixo para copiar a tabela limpa e isolada. Depois, basta abrir o Gmail e dar Ctrl+V (ou colar).</p>
            
            <button id="CopiarMailApp" class="btn btn-copiar" onclick="CopiarMail()">Copiar Documento para o Gmail 📋</button>
            
            <label class="label-preview" style="color:#2c3e50; font-weight:bold; font-size:0.85rem;">Visualização Prévia:</label>
            <div id="conteudo-previa" class="preview-box">
                <?php echo $html_gerado; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function FormataCodigoBarras() {
    var input = document.getElementById('txtCodigoBarras');
    var value = input.value;
    value = value.replace(/\D/g, '');
    var formattedValue = '';
    for (var i = 0; i < value.length; i++) {
        switch (i) {
            case 5: formattedValue += '.'; break;
            case 10: formattedValue += ' '; break;
            case 15: formattedValue += '.'; break;
            case 21: formattedValue += ' '; break;
            case 26: formattedValue += '.'; break;
            case 32: formattedValue += ' '; break;
            case 33: formattedValue += ' '; break;        
        }
        formattedValue += value[i];
    }       
    input.value = formattedValue;
}

function activarCarregando(form) {
    const btn = document.getElementById("btnEnviarForm");
    const barra = document.getElementById("barra-progresso");
    
    barra.style.display = "block";
    btn.innerHTML = "Carregando Fechamento... ⏳";
    
    setTimeout(() => { btn.disabled = true; }, 10);
    return true;
}

function CopiarMail() {
    const botao = document.getElementById("CopiarMailApp");
    const corpoTela = document.body;
    const corOriginalBotao = botao.style.backgroundColor || "#3498db";

    corpoTela.classList.add("TelaCopiaEmail");
    window.scrollTo(0, 0);

    botao.style.backgroundColor = "lightgoldenrodyellow";
    botao.style.color = "#333";
    botao.textContent = "> Documento Copiado com Sucesso!";

    document.execCommand('selectAll', false, null);

    setTimeout(function(){
        document.execCommand('copy');
    }, 150);

    setTimeout(function(){
        window.getSelection().removeAllRanges();
    }, 1000);

    setTimeout(function(){
        corpoTela.classList.remove("TelaCopiaEmail");
    }, 1100);

    setTimeout(function(){
        botao.style.backgroundColor = corOriginalBotao;
        botao.style.color = "white";
        botao.textContent = "Copiar Documento para o Gmail 📋";
    }, 4000);
}
</script> 
</body>
</html>
