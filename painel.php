<?php
// Define uma sessão isolada para o painel
session_name("rci_painel_admin");
session_start();
require_once 'conexao.php';

// 1. AÇÃO DE LOGOUT
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: painel.php");
    exit;
}

// 2. LÓGICA DE LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_painel'])) {
    $u = $_POST['usuario'];
    $p = $_POST['senha'];
    
    $stmt = $conn->prepare("SELECT id, senha FROM usuarios WHERE usuario = :u AND nivel_acesso = 'admin'");
    $stmt->execute([':u' => $u]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($p, $admin['senha'])) {
        $_SESSION['logado_painel'] = true;
    } else {
        $erro = "Usuário ou senha inválidos!";
    }
}

// 3. SALVAR ALTERAÇÕES
if (isset($_POST['salvar_alteracao']) && isset($_SESSION['logado_painel'])) {
    $stmt = $conn->prepare("UPDATE usuarios SET plano = :p, status_assinatura = :s, validade_plano = :v WHERE id = :id");
    $stmt->execute([
        ':p' => $_POST['plano'], 
        ':s' => $_POST['status'], 
        ':v' => !empty($_POST['validade']) ? $_POST['validade'] : null,
        ':id' => $_POST['id']
    ]);
    header("Location: painel.php");
    exit;
}

// --- FORMULÁRIO DE LOGIN ---
if (!isset($_SESSION['logado_painel'])): ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin</title>
</head>
<body style="background:#2c3e50; display:flex; justify-content:center; align-items:center; height:100vh; font-family:sans-serif; margin:0;">
    <form method="POST" style="background:white; padding:20px; border-radius:8px; width:90%; max-width:300px; box-shadow:0 4px 10px rgba(0,0,0,0.2);">
        <h2 style="text-align:center;">Painel Admin</h2>
        <?php if(isset($erro)) echo "<p style='color:red; text-align:center;'>$erro</p>"; ?>
        <input type="text" name="usuario" placeholder="Usuário" required style="width:100%; padding:10px; margin-bottom:10px; box-sizing:border-box;">
        <input type="password" name="senha" placeholder="Senha" required style="width:100%; padding:10px; margin-bottom:10px; box-sizing:border-box;">
        <button type="submit" name="login_painel" style="width:100%; padding:10px; background:#2ecc71; color:white; border:none; cursor:pointer;">Entrar</button>
    </form>
</body>
</html>
<?php exit; endif;

// --- PAINEL (CARREGA DADOS) ---
$stats = $conn->query("SELECT 
    SUM(CASE WHEN status_assinatura = 'ativo' THEN 1 ELSE 0 END) as ativas,
    SUM(CASE WHEN status_assinatura = 'vencido' THEN 1 ELSE 0 END) as vencidos,
    SUM(CASE WHEN status_assinatura = 'inativo' THEN 1 ELSE 0 END) as inativos,
    SUM(CASE WHEN plano IN ('7 dias', '15 dias', '30 dias', '3 meses', 'vitalicio') THEN 1 ELSE 0 END) as pagantes,
    SUM(CASE WHEN ultima_atividade > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) as online
    FROM usuarios WHERE nivel_acesso != 'admin'")->fetch(PDO::FETCH_ASSOC);

$motoristas = $conn->query("SELECT * FROM usuarios WHERE nivel_acesso != 'admin' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo RCI</title>
    <style>
        body { font-family: sans-serif; background: #f4f6f9; padding: 10px; margin: 0; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin-bottom: 20px; }
        .card { background: white; padding: 15px; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card h3 { margin: 0; font-size: 0.7rem; color: #666; text-transform: uppercase; }
        .card p { margin: 5px 0 0; font-size: 1.2rem; font-weight: bold; }
        
        .table-container { width: 100%; overflow-x: auto; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        table { width: 100%; min-width: 500px; border-collapse: collapse; font-size: 0.85rem; }
        th { background: #2c3e50; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #eee; }
        
        select, input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ddd; border-radius: 4px; }
        .btn-salvar { background: #2ecc71; color: white; border: none; padding: 8px; cursor: pointer; width: 100%; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>
    <div style="display:flex; justify-content:space-between; margin-bottom:15px; align-items:center;">
        <h2 style="font-size:1.2rem;">Dashboard</h2>
        <a href="?logout=true" style="color:red; text-decoration:none; font-size:0.9rem;">Sair</a>
    </div>

    <div class="grid">
        <div class="card"><h3>Online</h3><p><?= (int)$stats['online'] ?></p></div>
        <div class="card"><h3>Pagantes</h3><p><?= (int)$stats['pagantes'] ?></p></div>
        <div class="card"><h3>Vencidos</h3><p style="color:red;"><?= (int)$stats['vencidos'] ?></p></div>
        <div class="card"><h3>Inativos</h3><p><?= (int)$stats['inativos'] ?></p></div>
    </div>

    <div class="table-container">
        <table>
            <thead><tr><th>Nome</th><th>Plano</th><th>Status</th><th>Validade</th><th>Ação</th></tr></thead>
            <tbody>
                <?php foreach ($motoristas as $m): ?>
                <tr>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                        <td><?= htmlspecialchars($m['nome']) ?></td>
                        <td>
                            <select name="plano">
                                <option value="gratis" <?= $m['plano']=='gratis'?'selected':'' ?>>Grátis</option>
                                <option value="7 dias" <?= $m['plano']=='7 dias'?'selected':'' ?>>7 Dias</option>
                                <option value="15 dias" <?= $m['plano']=='15 dias'?'selected':'' ?>>15 Dias</option>
                                <option value="30 dias" <?= $m['plano']=='30 dias'?'selected':'' ?>>30 Dias</option>
                                <option value="3 meses" <?= $m['plano']=='3 meses'?'selected':'' ?>>3 Meses</option>
                                <option value="vitalicio" <?= $m['plano']=='vitalicio'?'selected':'' ?>>Vitalício</option>
                            </select>
                        </td>
                        <td>
                            <select name="status">
                                <option value="ativo" <?= $m['status_assinatura']=='ativo'?'selected':'' ?>>Ativo</option>
                                <option value="vencido" <?= $m['status_assinatura']=='vencido'?'selected':'' ?>>Vencido</option>
                                <option value="inativo" <?= $m['status_assinatura']=='inativo'?'selected':'' ?>>Inativo</option>
                            </select>
                        </td>
                        <td><input type="date" name="validade" value="<?= substr($m['validade_plano'], 0, 10) ?>"></td>
                        <td><button type="submit" name="salvar_alteracao" class="btn-salvar">OK</button></td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
