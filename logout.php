<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$_SESSION = array();
session_destroy();

// 💡 CORRIGIDO: Em vez de usar header Location, usamos o replace do JavaScript 
// para limpar o histórico de navegação do celular antes de ir para a tela de login.
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Saindo...</title>
    <script>
        // O replace limpa o histórico da sessão, a página anterior "some" para o celular
        window.location.replace("login.php");
    </script>
</head>
<body>
</body>
</html>