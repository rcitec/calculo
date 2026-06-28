<?php
// 1. INCLUSÃO DA TRAVA DE SEGURANÇA E CONEXÃO
require_once 'trava.php'; 
require_once 'conexao.php';

$mensagem_sucesso = '';
$mensagem_erro = '';
$mensagem_aviso = '';

// Variáveis de controle para o modo de edição de veículo
$veiculo_edicao = null;

// 🌟 CAPTURA O NÍVEL DE ACESSO DO USUÁRIO PARA EXIBIÇÃO DO MENU ADMINISTRATIVO
// Buscamos direto do banco usando o $usuario_id que já vem validado pelo trava.php
try {
    $sql_check_admin = "SELECT nivel_acesso FROM usuarios WHERE id = :id LIMIT 1";
    $stmt_check = $conn->prepare($sql_check_admin);
    $stmt_check->execute([':id' => $usuario_id]);
    $res_check = $stmt_check->fetch(PDO::FETCH_ASSOC);
    $is_admin = ($res_check && $res_check['nivel_acesso'] === 'admin');
} catch (Exception $e) {
    $is_admin = false;
}

// ==========================================
// 💡 LOGICA DE EXCLUSÃO DE VEÍCULO
// ==========================================
if (isset($_GET['excluir_veiculo_id'])) {
    $excluir_v_id = intval($_GET['excluir_veiculo_id']);
    
    try {
        // 1. Busca as configurações para ver qual é o veículo ativo
        $q_cfg = "SELECT veiculo_id FROM configuracoes WHERE usuario_id = :usuario_id LIMIT 1";
        $st_cfg = $conn->prepare($q_cfg);
        $st_cfg->execute([':usuario_id' => $usuario_id]);
        $cfg_at = $st_cfg->fetch(PDO::FETCH_ASSOC);

        // 2. Conta quantos veículos o motorista tem no total
        $q_count = "SELECT COUNT(*) as total FROM veiculos WHERE usuario_id = :usuario_id";
        $st_count = $conn->prepare($q_count);
        $st_count->execute([':usuario_id' => $usuario_id]);
        $total_veiculos = $st_count->fetch(PDO::FETCH_ASSOC)['total'];

        if ($cfg_at && $cfg_at['veiculo_id'] == $excluir_v_id) {
            $mensagem_erro = "⚠️ Você não pode excluir o veículo que está ATIVO no sistema atualmente. Altere o veículo ativo acima antes de excluir.";
        } elseif ($total_veiculos <= 1) {
            $mensagem_erro = "⚠️ Você precisa manter pelo menos UM veículo cadastrado para o funcionamento dos cálculos.";
        } else {
            // Se passou pelas validações e o usuário confirmou a exclusão via URL
            if (isset($_GET['confirmar']) && $_GET['confirmar'] === 'sim') {
                $sql_del = "DELETE FROM veiculos WHERE id = :id AND usuario_id = :usuario_id";
                $stmt_del = $conn->prepare($sql_del);
                $stmt_del->execute([':id' => $excluir_v_id, ':usuario_id' => $usuario_id]);
                $mensagem_sucesso = "Veículo excluído com sucesso!";
            } else {
                // Busca o nome do veículo apenas para mostrar na mensagem de confirmação
                $q_v = "SELECT veiculo FROM veiculos WHERE id = :id AND usuario_id = :usuario_id LIMIT 1";
                $st_v = $conn->prepare($q_v);
                $st_v->execute([':id' => $excluir_v_id, ':usuario_id' => $usuario_id]);
                $nome_v_del = $st_v->fetch(PDO::FETCH_ASSOC)['veiculo'] ?? 'Este veículo';

                // 💡 REMOVIDO O '#secao-veiculos' PARA MANTER A PÁGINA NO TOPO
                $mensagem_aviso = "Você tem certeza que deseja excluir o veículo <strong>" . htmlspecialchars($nome_v_del) . "</strong>?<br><br>"
                                . "<a href='configuracoes.php?excluir_veiculo_id=".$excluir_v_id."&confirmar=sim' class='btn-confirmar-sim'>Sim, Excluir</a> "
                                . "<a href='configuracoes.php' class='btn-confirmar-nao'>Cancelar</a>";
            }
        }
    } catch (Exception $e) {
        $mensagem_erro = "Erro ao tentar excluir veículo: " . $e->getMessage();
    }
}

// DETECTA SE O MOTORISTA CLICOU EM EDITAR UM VEÍCULO ESPECÍFICO
if (isset($_GET['editar_veiculo_id'])) {
    $editar_v_id = intval($_GET['editar_veiculo_id']);
    try {
        $query_ev = "SELECT * FROM veiculos WHERE id = :id AND usuario_id = :usuario_id LIMIT 1";
        $stmt_ev = $conn->prepare($query_ev);
        $stmt_ev->execute([':id' => $editar_v_id, ':usuario_id' => $usuario_id]);
        $veiculo_edicao = $stmt_ev->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $mensagem_erro = "Erro ao buscar dados do veículo para edição.";
    }
}

// 2. PROCESSAR FORMULÁRIO DE CONFIGURAÇÕES GERAIS / SELEÇÃO DE VEÍCULO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_config'])) {
    $preco_combustivel = floatval($_POST['preco_combustivel'] ?? 0);
    $lucro_max         = floatval($_POST['lucro_max'] ?? 3.0);
    $lucro_min         = floatval($_POST['lucro_min'] ?? 2.0);
    $veiculo_ativo_id  = intval($_POST['veiculo_ativo_id'] ?? 0);

    try {
        $sql_update = "UPDATE configuracoes SET 
                        preco_combustivel = :preco_combustivel, 
                        lucro_max = :lucro_max, 
                        lucro_min = :lucro_min,
                        veiculo_id = :veiculo_id
                       WHERE usuario_id = :usuario_id";
        $stmt_up = $conn->prepare($sql_update);
        $stmt_up->execute([
            ':preco_combustivel' => $preco_combustivel,
            ':lucro_max'         => $lucro_max,
            ':lucro_min'         => $lucro_min,
            ':veiculo_id'        => $veiculo_ativo_id,
            ':usuario_id'        => $usuario_id
        ]);
        
        $sql_up_user = "UPDATE usuarios SET veiculo_id = :veiculo_id WHERE id = :usuario_id";
        $stmt_up_user = $conn->prepare($sql_up_user);
        $stmt_up_user->execute([
            ':veiculo_id' => $veiculo_ativo_id,
            ':usuario_id' => $usuario_id
        ]);

        $mensagem_sucesso = "Configurações updated com sucesso!";
    } catch (Exception $e) {
        $mensagem_erro = "Erro ao atualizar configurações: " . $e->getMessage();
    }
}

// 3. PROCESSAR FORMULÁRIO DE CADASTRO OU ALTERAÇÃO DE VEÍCULO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_veiculo'])) {
    $nome_veiculo  = trim($_POST['veiculo_nome'] ?? '');
    $media_consumo = floatval($_POST['media_consumo'] ?? 1);
    $v_id_enviado  = intval($_POST['veiculo_id_edicao'] ?? 0);

    // Capturando os novos inputs operacionais adicionados ao formulário
    $pneu        = floatval($_POST['pneu'] ?? 0);
    $troca_oleo  = floatval($_POST['troca_oleo'] ?? 0);
    $outros      = floatval($_POST['outros'] ?? 0);

    if (!empty($nome_veiculo) && $media_consumo > 0) {
        try {
            if ($v_id_enviado > 0) {
                // UPDATE original adaptado com os novos campos operacionais
                $sql_veiculo = "UPDATE veiculos SET 
                                    veiculo = :veiculo, 
                                    media_consumo = :media_consumo,
                                    pneu = :pneu,
                                    troca_oleo = :troca_oleo,
                                    outros = :outros
                                WHERE id = :id AND usuario_id = :usuario_id";
                $stmt_vei = $conn->prepare($sql_veiculo);
                $stmt_vei->execute([
                    ':veiculo'       => $nome_veiculo,
                    ':media_consumo' => $media_consumo,
                    ':pneu'          => $pneu,
                    ':troca_oleo'    => $troca_oleo,
                    ':outros'        => $outros,
                    ':id'            => $v_id_enviado,
                    ':usuario_id'    => $usuario_id
                ]);
                $mensagem_sucesso = "Veículo atualizado com sucesso!";
                $veiculo_edicao = null;
            } else {
                // INSERT original adaptado com os novos campos operacionais
                $sql_veiculo = "INSERT INTO veiculos (usuario_id, veiculo, media_consumo, pneu, troca_oleo, outros) 
                                VALUES (:usuario_id, :veiculo, :media_consumo, :pneu, :troca_oleo, :outros)";
                $stmt_vei = $conn->prepare($sql_veiculo);
                $stmt_vei->execute([
                    ':usuario_id'    => $usuario_id,
                    ':veiculo'       => $nome_veiculo,
                    ':media_consumo' => $media_consumo,
                    ':pneu'          => $pneu,
                    ':troca_oleo'    => $troca_oleo,
                    ':outros'        => $outros
                ]);
                $mensagem_sucesso = "Novo veículo adicionado com sucesso!";
            }
        } catch (Exception $e) {
            $mensagem_erro = "Erro ao salvar dados do veículo: " . $e->getMessage();
        }
    } else {
        $mensagem_erro = "Preencha o nome do veículo e uma média de consumo válida.";
    }
}

// 4. PROCESSAR ALTERAÇÃO DE PERFIL E SENHA DO UTILIZADOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_perfil'])) {
    $nome_perfil     = trim($_POST['perfil_nome'] ?? '');
    $telefone_perfil = trim($_POST['perfil_telefone'] ?? '');
    $nova_senha      = trim($_POST['perfil_senha'] ?? '');

    if (!empty($nome_perfil) && !empty($telefone_perfil)) {
        try {
            if (!empty($nova_senha)) {
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $sql_perfil = "UPDATE usuarios SET nome = :nome, telefone = :telefone, senha = :senha WHERE id = :id";
                $parametros = [
                    ':nome'     => $nome_perfil,
                    ':telefone' => $telefone_perfil,
                    ':senha'    => $senha_hash,
                    ':id'       => $usuario_id
                ];
            } else {
                $sql_perfil = "UPDATE usuarios SET nome = :nome, telefone = :telefone WHERE id = :id";
                $parametros = [
                    ':nome'     => $nome_perfil,
                    ':telefone' => $telefone_perfil,
                    ':id'       => $usuario_id
                ];
            }

            $stmt_prof = $conn->prepare($sql_perfil);
            $stmt_prof->execute($parametros);

            $_SESSION['usuario_nome'] = $nome_perfil;

            $mensagem_sucesso = "Dados do perfil updated com sucesso!";
        } catch (Exception $e) {
            $mensagem_erro = "Erro ao atualizar perfil: " . $e->getMessage();
        }
    } else {
        $mensagem_erro = "Nome e Telefone são campos obrigatórios.";
    }
}

// 5. CARREGAR DADOS DO BANCO DINAMICAMENTE PARA O UTILIZADOR LOGADO
try {
    $query_user = "SELECT nome, usuario, telefone FROM usuarios WHERE id = :usuario_id LIMIT 1";
    $stmt_user = $conn->prepare($query_user);
    $stmt_user->execute([':usuario_id' => $usuario_id]);
    $dados_usuario = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // 🌟 EXTRAI O PRIMEIRO NOME E DO MOTORISTA PARA O SUPORTE VIA WHATSAPP
    $nome_completo_motorista = $dados_usuario['nome'] ?? 'Motorista';
    $partes_nome             = explode(' ', trim($nome_completo_motorista));
    $primeiro_nome           = $partes_nome[0];

    $query_cfg = "SELECT * FROM configuracoes WHERE usuario_id = :usuario_id LIMIT 1";
    $stmt_cfg = $conn->prepare($query_cfg);
    $stmt_cfg->execute([':usuario_id' => $usuario_id]);
    $config_atual = $stmt_cfg->fetch(PDO::FETCH_ASSOC);

    $query_veiculos = "SELECT * FROM veiculos WHERE usuario_id = :usuario_id ORDER BY veiculo ASC";
    $stmt_vei = $conn->prepare($query_veiculos);
    $stmt_vei->execute([':usuario_id' => $usuario_id]);
    $lista_veiculos = $stmt_vei->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Erro crítico ao carregar dados de configuração: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Fretes - Configurações</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
	 :root { --primary: #2c3e50; --success: #2ecc71; --accent: #3498db; --bg: #f1f5f9; }
        body { background-color: var(--bg); font-family: 'Segoe UI', sans-serif; padding-bottom: 30px; margin-top: 0;}
.container { max-width: 600px; margin: 0 auto; padding: 15px; margin-top:70px}
        .menu-navegacao { display: flex; gap: 8px; margin-bottom: 20px; }
        .btn-nav { background: #94a3b8; color: white; padding: 10px; text-decoration: none; border-radius: 8px; font-size: 0.8rem; text-align: center; flex: 1; }
        .btn-nav.ativo { background: #2c3e50; font-weight: bold; border-bottom: 4px solid #2ecc71; }
        
        /* Estilo especial para o botão do Painel Admin */
        .btn-nav.admin-link { background: #e74c3c; font-weight: bold; }
        .btn-nav.admin-link:hover { background: #c0392b; }

        .row-dupla { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        
        .alert { padding: 12px; border-radius: 4px; margin-bottom: 15px; font-size: 0.9rem; font-weight: bold; text-align: center; line-height: 1.4; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }

        select { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; background-color: #fff; }
        
        .lista-geral-veiculos { margin-top: 12px; background: #f8f9fa; border: 1px solid #e1e8ed; border-radius: 6px; padding: 8px 12px; }
        .item-veiculo { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #e1e8ed; gap: 10px; }
        .item-veiculo:last-child { border-bottom: none; }
        .info-txt-veiculo { font-size: 0.95rem; color: #2c3e50; flex: 1; }
        .info-txt-veiculo strong { color: #2ecc71; }
        
        .botoes-acao { display: flex; gap: 6px; }
        .btn-editar-v { background-color: #3498db; color: white; padding: 6px 12px; font-size: 0.8rem; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .btn-editar-v:hover { background-color: #2980b9; }
        .btn-excluir-v { background-color: #e74c3c; color: white; padding: 6px 12px; font-size: 0.8rem; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .btn-excluir-v:hover { background-color: #c0392b; }
        
        .btn-confirmar-sim { background-color: #e74c3c; color: white; padding: 6px 15px; border-radius: 4px; font-weight: bold; text-decoration: none; display: inline-block; }
        .btn-confirmar-nao { background-color: #7f8c8d; color: white; padding: 6px 15px; border-radius: 4px; font-weight: bold; text-decoration: none; display: inline-block; margin-left: 10px; }

        .btn-cancelar-edicao { background-color: #7f8c8d; color: white; padding: 10px; border-radius: 4px; text-decoration: none; display: block; text-align: center; margin-top: 8px; font-weight: bold; font-size: 0.9rem; }
        .btn-logoff { background-color: #34495e; margin-top: 35px; margin-bottom: 20px; text-align: center; text-decoration: none; font-weight: bold; display: block; color: white; padding: 12px; border-radius: 4px; }
        .btn-logoff:hover { background-color: #2c3e50; }
        
        .link-admin-footer { display: block; text-align: center; color: #e74c3c; font-weight: bold; text-decoration: none; font-size: 0.9rem; margin-top: -10px; margin-bottom: 25px; }
        .link-admin-footer:hover { text-decoration: underline; }

        /* 💡 ESTILIZAÇÃO DO RODAPÉ ADAPTADO PARA A PÁGINA INTERNA DE CONFIGURAÇÕES */
        .config-footer {
            text-align: center;
            margin-top: 30px;
            padding-bottom: 60px; /* Margem sutil de fim de página */
            width: 100%;
        }
        .config-footer p {
            margin: 4px 0;
            font-family: sans-serif;
        }
        .config-footer .copyright {
            font-size: 0.85rem;
            color: #64748b; /* Cinza escuro legível */
            font-weight: 500;
        }
        .config-footer .frase-efeito {
            font-size: 0.8rem;
            color: #94a3b8; /* Cinza claro secundário */
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
<div class="container">
    <div class="topo-fixado-celular">
    <div class="menu-navegacao">
        <a href="calculo.php" class="btn-nav">Cálculo</a>
        <a href="historico.php" class="btn-nav">Histórico</a>
	 <a href="faturamento.php" class="btn-nav">Faturamento</a>
        <a href="configuracoes.php" class="btn-nav ativo">Configurações</a>
    </div>
    </div>

    <h2>Configurações do Sistema</h2>

    <?php if (!empty($mensagem_sucesso)): ?>
        <div id="alerta-sucesso" class="alert alert-success"><?php echo $mensagem_sucesso; ?></div>
        <script>setTimeout(() => { const a = document.getElementById('alerta-sucesso'); if(a) a.style.display='none'; }, 3000);</script>
    <?php endif; ?>
    <?php if (!empty($mensagem_erro)): ?>
        <div class="alert alert-danger"><?php echo $mensagem_erro; ?></div>
    <?php endif; ?>
    <?php if (!empty($mensagem_aviso)): ?>
        <div class="alert alert-warning"><?php echo $mensagem_aviso; ?></div>
    <?php endif; ?>

    <form action="configuracoes.php" method="POST">
        <input type="hidden" name="acao_config" value="1">
        <h3>Parâmetros de Cálculo</h3>
        <div class="form-group">
            <label for="preco_combustivel">Litro Combustível Padrão (R$):</label>
            <input type="number" id="preco_combustivel" name="preco_combustivel" step="0.01" value="<?php echo htmlspecialchars($config_atual['preco_combustivel'] ?? '0.00'); ?>" required>
        </div>
        <div class="row-dupla">
            <div class="form-group">
                <label for="lucro_max">Lucro Máximo (Fator):</label>
                <input type="number" id="lucro_max" name="lucro_max" step="0.01" value="<?php echo htmlspecialchars($config_atual['lucro_max'] ?? '3.00'); ?>" required>
            </div>
            <div class="form-group">
                <label for="lucro_min">Lucro Mínimo (Fator):</label>
                <input type="number" id="lucro_min" name="lucro_min" step="0.01" value="<?php echo htmlspecialchars($config_atual['lucro_min'] ?? '2.00'); ?>" required>
            </div>
        </div>
        <div class="form-group">
            <label for="veiculo_ativo_id">Veículo Ativo no Sistema:</label>
            <select id="veiculo_ativo_id" name="veiculo_ativo_id">
                <?php foreach ($lista_veiculos as $v): ?>
                    <option value="<?php echo $v['id']; ?>" <?php echo (isset($config_atual['veiculo_id']) && $config_atual['veiculo_id'] == $v['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($v['veiculo']); ?> (<?php echo $v['media_consumo']; ?> KM/L)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn" style="background-color: #34495e;">Salvar Parâmetros</button>
    </form>

    <form action="configuracoes.php" method="POST" style="margin-top: 25px;" id="secao-veiculos">
        <input type="hidden" name="acao_veiculo" value="1">
        <input type="hidden" name="veiculo_id_edicao" value="<?php echo $veiculo_edicao ? $veiculo_edicao['id'] : ''; ?>">
        
        <h3><?php echo $veiculo_edicao ? '✏️ Alterar Veículo' : '🚚 Cadastrar Novo Veículo'; ?></h3>
        
        <div class="form-group">
            <label for="veiculo_nome">Identificação do Veículo / Modelo:</label>
            <input type="text" id="veiculo_nome" name="veiculo_nome" placeholder="Ex: Fiorino, Iveco, Volvo, etc..." value="<?php echo $veiculo_edicao ? htmlspecialchars($veiculo_edicao['veiculo']) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="media_consumo">Média de Consumo (KM/L):</label>
            <input type="number" id="media_consumo" name="media_consumo" step="0.1" placeholder="Ex: 10,5" value="<?php echo $veiculo_edicao ? htmlspecialchars($veiculo_edicao['media_consumo']) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="v_pneu">Custo de Pneu (por KM):</label>
            <input type="number" id="v_pneu" name="pneu" step="0.0001" placeholder="Ex: 0,005" value="<?php echo $veiculo_edicao ? htmlspecialchars($veiculo_edicao['pneu']) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="v_troca_oleo">Custo de Troca de Óleo (por KM):</label>
            <input type="number" id="v_troca_oleo" name="troca_oleo" step="0.0001" placeholder="Ex: 0,010" value="<?php echo $veiculo_edicao ? htmlspecialchars($veiculo_edicao['troca_oleo']) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="v_outros">Outros Custos do Veículo (por KM):</label>
            <input type="number" id="v_outros" name="outros" step="0.0001" placeholder="Ex: 0,050" value="<?php echo $veiculo_edicao ? htmlspecialchars($veiculo_edicao['outros']) : ''; ?>" required>
        </div>
        
        <button type="submit" class="btn" style="<?php echo $veiculo_edicao ? 'background-color: #e67e22;' : ''; ?>">
            <?php echo $veiculo_edicao ? 'Salvar Alterações' : 'Adicionar Veículo'; ?>
        </button>

        <?php if ($veiculo_edicao): ?>
            <a href="configuracoes.php" class="btn-cancelar-edicao">Cancelar Edição</a>
        <?php endif; ?>
        
        <h4 style="margin-top: 18px; margin-bottom: 6px; color: #34495e;">Frota Cadastrada:</h4>
        <div class="lista-geral-veiculos">
            <?php foreach ($lista_veiculos as $v): ?>
                <div class="item-veiculo">
                    <div class="info-txt-veiculo">
                        <strong><?php echo htmlspecialchars($v['veiculo']); ?></strong> — <?php echo $v['media_consumo']; ?> KM/L
                        <br>
                        <small style="color: #7f8c8d;">
                            Pneu: R$ <?php echo number_format($v['pneu'], 4, ',', '.'); ?>/KM | 
                            Óleo: R$ <?php echo number_format($v['troca_oleo'], 4, ',', '.'); ?>/KM | 
                            Outros: R$ <?php echo number_format($v['outros'], 4, ',', '.'); ?>/KM
                        </small>
                    </div>
                    <div class="botoes-acao">
                        <a href="configuracoes.php?excluir_veiculo_id=<?php echo $v['id']; ?>" class="btn-excluir-v">Excluir</a>
                        <a href="configuracoes.php?editar_veiculo_id=<?php echo $v['id']; ?>#secao-veiculos" class="btn-editar-v">Editar</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </form>

    <form action="configuracoes.php" method="POST" style="margin-top: 25px;">
        <input type="hidden" name="acao_perfil" value="1">
        <h3>Meus Dados de Acesso</h3>
        
        <div class="form-group">
            <label>Usuário (Login):</label>
            <input type="text" value="<?php echo htmlspecialchars($dados_usuario['usuario'] ?? ''); ?>" disabled style="background-color: #eee; color: #7f8c8d; cursor: not-allowed;">
        </div>

        <div class="form-group">
            <label for="perfil_nome">Nome Completo:</label>
            <input type="text" id="perfil_nome" name="perfil_nome" value="<?php echo htmlspecialchars($dados_usuario['nome'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="perfil_telefone">Telefone / WhatsApp:</label>
            <input type="text" id="perfil_telefone" name="perfil_telefone" value="<?php echo htmlspecialchars($dados_usuario['telefone'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label for="perfil_senha">Nova Senha (Deixe em branco para NÃO alterar):</label>
            <input type="password" id="perfil_senha" name="perfil_senha" placeholder="Digite apenas se quiser mudar de senha" autocomplete="new-password">
        </div>

        <button type="submit" class="btn" style="background-color: #2980b9;">Atualizar Perfil</button>
    </form>

    <?php 
        $texto_suporte = "Olá Rogério, estou com uma dúvida aqui no sistema RCI. Meu nome é {$primeiro_nome} / id: {$usuario_id}.";
        $url_suporte   = "https://wa.me/5515997450446?text=" . urlencode($texto_suporte);
    ?>
    
    <br>
    <a href="<?php echo $url_suporte; ?>" target="_blank" 
       style="display: block; text-align: center; color: #27ae60; font-weight: bold; text-decoration: none; font-size: 0.95rem; margin-top: 25px; margin-bottom: 5px;">
        💬 Precisa de ajuda? Chame o Suporte no WhatsApp
    </a>
    <br>

    <a href="logout.php" class="btn btn-logoff" style="margin-top: 15px;">Logoff / Sair do Sistema</a>
    
    <?php if ($is_admin): ?>
        <a href="painel_rci.php" class="link-admin-footer" style="margin-top: 15px;">⚙️ Acessar Painel de Controle SaaS</a>
    <?php endif; ?>

    <footer class="config-footer">
        <p class="copyright">&copy; <?php echo date('Y'); ?> RCI Transportes - Todos os direitos reservados.</p>
        <p class="frase-efeito">Desenvolvido para motoristas que valorizam o seu trabalho.</p>
    </footer>
</div>
</body>
</html>
