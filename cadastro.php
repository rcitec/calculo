<?php
// 1. As declarações de 'use' DEVEM vir primeiro de tudo no arquivo
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ative a exibição de erros temporariamente para testes se necessário
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Agora incluímos os arquivos necessários
require_once 'conexao.php';
require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

$mensagem_sucesso = '';
$mensagem_erro = '';

// Inicializamos todas as variáveis vazias para reter os dados na tela em caso de erro
$nome              = '';
$usuario           = '';
$email             = ''; 
$telefone          = '';
$cidade            = ''; 
$estado            = ''; 
$nome_veiculo      = '';
$media_consumo     = '';
$preco_combustivel = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome              = trim($_POST['nome'] ?? '');
    $usuario           = trim($_POST['usuario'] ?? '');
    $email             = trim($_POST['email'] ?? ''); 
    $telefone          = trim($_POST['telefone'] ?? '');
    $cidade            = trim($_POST['cidade'] ?? '');   
    $estado            = trim($_POST['estado'] ?? '');   
    $senha             = trim($_POST['senha'] ?? '');

    // Dados opcionais do veículo e configurações
    $nome_veiculo      = trim($_POST['veiculo'] ?? '');
    $media_consumo     = trim($_POST['media_consumo'] ?? '');
    $preco_combustivel = trim($_POST['preco_combustivel'] ?? '');

    if (!empty($nome) && !empty($usuario) && !empty($email) && !empty($telefone) && !empty($cidade) && !empty($estado) && !empty($senha)) {
        try {
            // 1. Verifica se o nome de usuário escrito já existe
            $sql_verificacao = "SELECT id FROM usuarios WHERE usuario = :usuario LIMIT 1";
            $stmt_verif = $conn->prepare($sql_verificacao);
            $stmt_verif->execute([':usuario' => $usuario]);
            
            if ($stmt_verif->fetch()) {
                $mensagem_erro = "Este nome de usuário já está sendo utilizado. Escolha outro.";
            } else {
                $senha_criptografada = password_hash($senha, PASSWORD_DEFAULT);

                // GERADOR DE ID ALEATÓRIO DE 10 DÍGITOS
                $id_gerado_livre = false;
                $novo_id = '';

                while (!$id_gerado_livre) {
                    $novo_id = (string) mt_rand(1000000000, 9999999999);

                    $query_check = "SELECT COUNT(*) FROM usuarios WHERE id = :id";
                    $stmt_check = $conn->prepare($query_check);
                    $stmt_check->execute([':id' => $novo_id]);
                    
                    if ($stmt_check->fetchColumn() == 0) {
                        $id_gerado_livre = true;
                    }
                }

                // NOVO: Calcula a data atual mais 15 dias (Formato ideal para o MySQL: YYYY-MM-DD)
                $validade_plano = date('Y-m-d', strtotime('+15 days'));

                // INICIA TRANSAÇÃO 1: Grava o Usuário primeiro
                $conn->beginTransaction();

                // 2. Insere o novo usuário (Adicionado a coluna validade_plano e o parâmetro correspondente)
                $sql_insercao = "INSERT INTO usuarios (id, nome, usuario, email, telefone, cidade, estado, senha, plano, status_assinatura, validade_plano) 
                                 VALUES (:id, :nome, :usuario, :email, :telefone, :cidade, :estado, :senha, 'gratis', 'ativo', :validade_plano)";
                
                $stmt_ins = $conn->prepare($sql_insercao);
                $stmt_ins->execute([
                    ':id'             => $novo_id,
                    ':nome'           => $nome,
                    ':usuario'        => $usuario,
                    ':email'          => $email,
                    ':telefone'       => $telefone,
                    ':cidade'         => $cidade,
                    ':estado'         => $estado,
                    ':senha'          => $senha_criptografada,
                    ':validade_plano' => $validade_plano
                ]);

                // Faz o commit imediato do usuário
                $conn->commit();

                // INICIA TRANSAÇÃO 2: Grava o Veículo e Configurações
                $conn->beginTransaction();

                // Define os padrões do veículo caso vazios
                $veiculo_gravar = !empty($nome_veiculo) ? $nome_veiculo : 'Meu Caminhão';
                $media_gravar   = !empty($media_consumo) ? floatval(str_replace(',', '.', $media_consumo)) : 10.0;
                if ($media_gravar <= 0) { $media_gravar = 10.0; }

                // Trata o preço do combustível digitado
                $combustivel_gravar = !empty($preco_combustivel) ? floatval(str_replace(',', '.', $preco_combustivel)) : 0.00;
                if ($combustivel_gravar < 0) { $combustivel_gravar = 0.00; }

                // 3. Cadastra o Veículo usando o $novo_id gerado
                $sql_veiculo = "INSERT INTO veiculos (usuario_id, veiculo, media_consumo) 
                                VALUES (:usuario_id, :veiculo, :media_consumo)";
                $stmt_vei = $conn->prepare($sql_veiculo);
                $stmt_vei->execute([
                    ':usuario_id'   => $novo_id,
                    ':veiculo'      => $veiculo_gravar,
                    ':media_consumo'=> $media_gravar
                ]);
                
                $novo_veiculo_id = $conn->lastInsertId();

                // 4. Atualiza o veiculo_id padrão no registro do usuário
                $sql_up_user = "UPDATE usuarios SET veiculo_id = :veiculo_id WHERE id = :id";
                $stmt_up_u = $conn->prepare($sql_up_user);
                $stmt_up_u->execute([':veiculo_id' => $novo_veiculo_id, ':id' => $novo_id]);

                // 5. Cria a linha de configurações iniciais de lucro usando o ID gerado
                $sql_config = "INSERT INTO configuracoes (usuario_id, veiculo_id, preco_combustivel, lucro_max, lucro_min) 
                               VALUES (:usuario_id, :veiculo_id, :preco_combustivel, 3.00, 2.00)";
                $stmt_cfg = $conn->prepare($sql_config);
                $stmt_cfg->execute([
                    ':usuario_id'        => $novo_id,
                    ':veiculo_id'        => $novo_veiculo_id,
                    ':preco_combustivel' => $combustivel_gravar
                ]);

                // Salva o veículo e as configurações definitivamente
                $conn->commit();

                // ============================================================
                // 📧 DISPARO AUTENTICADO DE EMAIL VIA PHPMAILER (SMTP LOCAWEB)
                // ============================================================
                $mail = new PHPMailer(true);
                $mail->SMTPDebug = 0; 

                try {
                    $mail->isSMTP();
                    $mail->Host       = 'email-ssl.com.br'; 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'rogerio@rci.adm.br'; 
                    $mail->Password   = 'Rx@891602';     
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
                    $mail->Port       = 587;                         
                    $mail->CharSet    = 'UTF-8';
		      $mail->Timeout    = 120;

                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

                    $mail->setFrom('rogerio@rci.adm.br', 'Sistema RCI');
                    $mail->addAddress('rogerio@rci.adm.br'); 

                    // Preparação do número para o WhatsApp
                    $whatsapp_limpo = preg_replace('/\D/', '', $telefone);
                    $link_whatsapp = "https://wa.me" . $whatsapp_limpo;

                    // Formata a data de validade para exibir mais bonito no e-mail (DD/MM/AAAA)
                    $validade_exibir = date('d/m/Y', strtotime($validade_plano));

                    $mail->isHTML(true);
                    $mail->Subject = "🚀 Novo Motorista Cadastrado no RCI - " . $nome;
                    
                    $mail->Body = "
                    <html>
                    <head><title>Novo Cadastro RCI</title></head>
                    <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                      <div style='background-color: #2c3e50; color: #fff; padding: 15px; text-align: center; font-size: 18px; font-weight: bold;'>
                        RCI - Cálculo de Fretes
                      </div>
                      <div style='padding: 20px; border: 1px solid #eee;'>
                        <h3 style='color: #2ecc71; margin-top: 0;'>🚀 Alerta de Novo Usuário!</h2>
                        <p>Um novo motorista acabou de criar uma conta no sistema.</p>
                        <hr style='border: 0; border-top: 1px dashed #ccc;'>
                        <p><strong>👤 Nome:</strong> {$nome}</p>
                        <p><strong>🔑 ID:</strong> {$novo_id}</p>
                        <p><strong>🔑 Usuário:</strong> {$usuario}</p>
                        <p><strong>📧 </strong> {$email}</p>
                        <p><strong>📱 </strong> <a href='{$link_whatsapp}' target='_blank' style='color: #2980b9; text-decoration: underline; font-weight: bold;'>{$telefone}</a></p>
                        <p><strong>📅 Validade do Teste:</strong> {$validade_exibir} (15 dias corridos)</p>
                      </div>
                    </body>
                    </html>";

                    $mail->send();
                    $mensagem_sucesso = "Cadastro realizado com sucesso!";
		} catch (Exception $e) {
		$mensagem_erro = "Usuário cadastrado com sucesso, mas o e-mail de alerta falhou. 
		Detalhes: {$mail->ErrorInfo}";
		}
		}
	} catch (\PDOException $e) 
	{if ($conn->inTransaction()) 
	{$conn->rollBack();
	}
	$mensagem_erro = "Erro no banco de dados: " . $e->getMessage();
	}
	} else {
	$mensagem_erro = "Preencha todos os campos obrigatórios.";
	}
	}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCI - Cadastrar-se</title>
    <link rel="stylesheet" href="estilo.css?v=1.3">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #2c3e50; padding: 15px; }
        .container-cadastro { background: #ffffff; padding: 30px 25px; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); width: 100%; max-width: 400px; }
        .titulo-cadastro { text-align: center; font-size: 1.6rem; font-weight: bold; color: #2c3e50; margin-bottom: 20px; }
        .alert { padding: 12px; border-radius: 4px; font-size: 0.85rem; text-align: center; margin-bottom: 15px; font-weight: bold; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .link-voltar { display: block; text-align: center; margin-top: 15px; font-size: 0.9rem; color: #3498db; text-decoration: none; font-weight: 600; }
        .link-voltar:hover { text-decoration: underline; }
        .row-dupla { display: flex; gap: 10px; }
        .row-dupla .form-group { flex: 1; }
        
        .form-group input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 0.9rem; background-color: #fff; height: 41px; box-sizing: border-box; }
    </style>
</head>
<body>
<div class="container-cadastro">
    <div class="titulo-cadastro">Criar Nova Conta</div>
    
    <?php if (!empty($mensagem_sucesso)): ?>
        <div class="alert alert-success"><?php echo $mensagem_sucesso; ?></div>
    <?php endif; ?>
    <?php if (!empty($mensagem_erro)): ?>
        <div class="alert alert-danger"><?php echo $mensagem_erro; ?></div>
    <?php endif; ?>
    
    <form action="cadastro.php" method="POST">
        <div class="form-group">
            <label for="nome">Nome Completo:</label>
            <input type="text" id="nome" name="nome" required placeholder="Ex: João Silva" value="<?php echo htmlspecialchars($nome); ?>">
        </div>
        
        <div class="form-group">
            <label for="usuario">Usuário para Acesso:</label>
            <input type="text" id="usuario" name="usuario" required placeholder="Ex: joao.silva" value="<?php echo htmlspecialchars($usuario); ?>">
        </div>

        <div class="form-group">
            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" required placeholder="Crie uma senha forte">
        </div>

        <div class="form-group">
            <label for="telefone">Telefone / WhatsApp:</label>
            <input type="text" id="telefone" name="telefone" required placeholder="Ex: (11) 99999-9999" maxlength="15" oninput="mascaraTelefone(this)" value="<?php echo htmlspecialchars($telefone); ?>">
        </div>

        <div class="row-dupla">
            <div class="form-group" style="flex: 3;">
                <label for="cidade">Cidade:</label>
                <input type="text" id="cidade" name="cidade" required placeholder="Sua cidade" value="<?php echo htmlspecialchars($cidade); ?>">
            </div>
            <div class="form-group" style="flex: 1.2;">
                <label for="estado">UF:</label>
                <select id="estado" name="estado" required>
                    <option value=""></option>
                    <?php
                    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                    foreach($ufs as $uf) {
                        $selected = ($estado === $uf) ? 'selected' : '';
                        echo "<option value='{$uf}' {$selected}>{$uf}</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" required placeholder="Ex: joao@email.com" value="<?php echo htmlspecialchars($email); ?>">
        </div>

        <hr style="border: 0; border-top: 1px dashed #cbd5e1; margin: 20px 0;">
        <p style="font-size: 0.85rem; font-weight: bold; color: #475569; margin-bottom: 12px; text-align: center; text-transform: uppercase; letter-spacing: 0.5px;">
            🚛 Seu Veículo Principal (Opcional)
        </p>

        <div class="form-group">
            <label for="veiculo">Identificação do Veículo:</label>
            <input type="text" id="veiculo" name="veiculo" placeholder="Ex: Scania R450, Mercedes 1113" value="<?php echo htmlspecialchars($nome_veiculo); ?>">
        </div>

        <div class="row-dupla" style="margin-bottom: 22px;">
            <div class="form-group">
                <label for="media_consumo">Consumo (Km/L):</label>
                <input type="text" id="media_consumo" name="media_consumo" placeholder="Ex: 3.5 (Padrão: 10.0)" value="<?php echo htmlspecialchars($media_consumo); ?>">
            </div>
            <div class="form-group">
                <label for="preco_combustivel">Litro Combustível (R$):</label>
                <input type="text" id="preco_combustivel" name="preco_combustivel" placeholder="Ex: 5.89" value="<?php echo htmlspecialchars($preco_combustivel); ?>">
            </div>
        </div>

        <button type="submit" class="btn" style="background-color: #2ecc71;">Cadastrar Conta</button>
        <a href="login.php" class="link-voltar">➔ Voltar para o Login</a>
    </form>
</div>

<script>
function mascaraTelefone(input) {
    let valor = input.value;
    valor = valor.replace(/\D/g, "");
    if (valor.length > 0) { valor = "(" + valor; }
    if (valor.length > 3) { valor = valor.substring(0, 3) + ") " + valor.substring(3); }
    if (valor.length > 10) { valor = valor.substring(0, 10) + "-" + valor.substring(10, 14); }
    input.value = valor;
}
</script>
</body>
</html>
