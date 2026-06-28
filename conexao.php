<?php
$host = "calculo_fretes.mysql.dbaas.com.br";
$usuario = "inserir_usuario";
$senha = "inserir_senha";
$banco = "calculo_fretes";

try {
    $conn = new PDO("mysql:host=$host;dbname=$banco;charset=utf8mb4", $usuario, $senha);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- CÓDIGO PARA ATUALIZAR A ATIVIDADE ---
    // Precisamos iniciar a sessão aqui para acessar o ID do usuário
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Se o usuário estiver logado, atualiza o timestamp no banco
    if (isset($_SESSION['usuario_id'])) {
        $stmt = $conn->prepare("UPDATE usuarios SET ultima_atividade = NOW() WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['usuario_id']]);
    }
    // ------------------------------------------

} catch(PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?>
