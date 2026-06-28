<?php
require_once 'trava.php';
require_once 'conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Captura os dados básicos do formulário
    $usuario_id        = intval($_POST['usuario_id'] ?? 0);
    $veiculo_id        = intval($_POST['veiculo_id'] ?? 0);
    $viagem_id         = !empty($_POST['viagem_id']) ? intval($_POST['viagem_id']) : null;
    
    $preco_combustivel = floatval($_POST['preco_combustivel'] ?? 0);
    $distancia_km      = floatval($_POST['distancia_km'] ?? 0);
    $pedagio           = floatval($_POST['pedagio'] ?? 0);
    $ajudante          = floatval($_POST['ajudante'] ?? 0);
    $outros            = floatval($_POST['outros'] ?? 0);
    $ajuste            = floatval($_POST['ajuste'] ?? 0);
    
    $origem            = trim($_POST['origem'] ?? '');
    $destino           = trim($_POST['destino'] ?? '');
    $solicitante       = trim($_POST['solicitante'] ?? '');
    $telefone_cliente  = trim($_POST['telefone_cliente'] ?? '');
    $data_viagem       = trim($_POST['data_frete'] ?? '');
    
    // Captura os valores finais calculados ocultos
    $total_frete       = floatval($_POST['total_frete'] ?? 0);
    $gasto_combustivel = floatval($_POST['gasto_combustivel'] ?? 0);
    $lucro_liquido     = floatval($_POST['lucro_liquido'] ?? 0);

    // 2. Captura os Status dos Checkboxes (0 ou 1)
    $faturar = isset($_POST['faturar']) ? 1 : 0;
    $viagem  = isset($_POST['agendar']) ? 1 : 0; 

    // Validação mínima de segurança
    if ($usuario_id > 0 && $distancia_km > 0 && !empty($origem) && !empty($destino)) {
        try {
            if ($viagem_id) {
                // 📝 MODO EDIÇÃO (UPDATE)
                $sql = "UPDATE viagens SET 
                            veiculo_id = :veiculo_id,
                            preco_combustivel = :preco_combustivel,
                            distancia_km = :distancia_km,
                            pedagio = :pedagio,
                            ajudante = :ajudante,
                            outros = :outros,
                            ajuste = :ajuste,
                            origem = :origem,
                            destino = :destino,
                            solicitante = :solicitante,
                            telefone_cliente = :telefone_cliente,
                            data_viagem = :data_viagem,
                            total_frete = :total_frete,
                            gasto_combustivel = :gasto_combustivel,
                            lucro_liquido = :lucro_liquido,
                            faturar = :faturar,
                            viagem = :viagem 
                        WHERE id = :id AND usuario_id = :usuario_id";
                
                $stmt = $conn->prepare($sql);
                $parametros = [
                    ':veiculo_id'        => $veiculo_id,
                    ':preco_combustivel' => $preco_combustivel,
                    ':distancia_km'      => $distancia_km,
                    ':pedagio'           => $pedagio,
                    ':ajudante'          => $ajudante,
                    ':outros'            => $outros,
                    ':ajuste'            => $ajuste,
                    ':origem'            => $origem,
                    ':destino'           => $destino,
                    ':solicitante'       => $solicitante,
                    ':telefone_cliente'  => $telefone_cliente,
                    ':data_viagem'       => $data_viagem,
                    ':total_frete'       => $total_frete,
                    ':gasto_combustivel' => $gasto_combustivel,
                    ':lucro_liquido'     => $lucro_liquido,
                    ':faturar'           => $faturar,
                    ':viagem'            => $viagem,
                    ':id'                => $viagem_id,
                    ':usuario_id'        => $usuario_id
                ];
                
                $stmt->execute($parametros);
                $id_retorno = $viagem_id;

            } else {
                // 🟢 MODO NOVO REGISTRO (INSERT) COM GERADOR DE ID DE 10 DÍGITOS
                $id_gerado_livre = false;
                $novo_id = '';

                while (!$id_gerado_livre) {
                    // Gera um número randômico de 10 dígitos
                    $novo_id = (string) mt_rand(1000000000, 9999999999);

                    // Consulta o banco para verificar se esse ID já existe na tabela de VIAGENS
                    $query_check = "SELECT COUNT(*) FROM viagens WHERE id = :id";
                    $stmt_check = $conn->prepare($query_check);
                    $stmt_check->execute([':id' => $novo_id]);
                    
                    if ($stmt_check->fetchColumn() == 0) {
                        $id_gerado_livre = true;
                    }
                }

                // Insere incluindo a coluna 'id' de forma manual
                $sql = "INSERT INTO viagens (
                            id, usuario_id, veiculo_id, preco_combustivel, distancia_km, pedagio, 
                            ajudante, outros, ajuste, origem, destino, solicitante, 
                            telefone_cliente, data_viagem, total_frete, gasto_combustivel, 
                            lucro_liquido, faturar, viagem
                        ) VALUES (
                            :id, :usuario_id, :veiculo_id, :preco_combustivel, :distancia_km, :pedagio, 
                            :ajudante, :outros, :ajuste, :origem, :destino, :solicitante, 
                            :telefone_cliente, :data_viagem, :total_frete, :gasto_combustivel, 
                            :lucro_liquido, :faturar, :viagem
                        )";
                
                $stmt = $conn->prepare($sql);
                $parametros = [
                    ':id'                => $novo_id, // Vincula o ID de 10 dígitos gerado
                    ':usuario_id'        => $usuario_id,
                    ':veiculo_id'        => $veiculo_id,
                    ':preco_combustivel' => $preco_combustivel,
                    ':distancia_km'      => $distancia_km,
                    ':pedagio'           => $pedagio,
                    ':ajudante'          => $ajudante,
                    ':outros'            => $outros,
                    ':ajuste'            => $ajuste,
                    ':origem'            => $origem,
                    ':destino'           => $destino,
                    ':solicitante'       => $solicitante,
                    ':telefone_cliente'  => $telefone_cliente,
                    ':data_viagem'       => $data_viagem,
                    ':total_frete'       => $total_frete,
                    ':gasto_combustivel' => $gasto_combustivel,
                    ':lucro_liquido'     => $lucro_liquido,
                    ':faturar'           => $faturar,
                    ':viagem'            => $viagem
                ];
                
                $stmt->execute($parametros);
                $id_retorno = $novo_id;
            }
            
            // Redireciona de volta passando o ID correto gerado ou editado
            header("Location: calculo.php?sucesso=1&editar_id=" . $id_retorno);
            exit;

        } catch (PDOException $e) {
            die("Erro ao salvar no banco de dados: " . $e->getMessage());
        }
    } else {
        die("Erro: Dados obrigatórios do formulário não foram preenchidos.");
    }
} else {
    header("Location: calculo.php");
    exit;
}
?>

