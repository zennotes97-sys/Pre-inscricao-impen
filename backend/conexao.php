<?php

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$configuracaoBanco = [
    'host' => 'sql213.infinityfree.com',
    'utilizador' => 'if0_43144066',
    'senha' => 'mGOBATV7s7Wlg',
    'banco' => 'if0_43144066_pre_inscricao_impen',
];

foreach ($configuracaoBanco as $valor) {
    if (strpos($valor, 'COLOQUE_AQUI_') === 0) {
        error_log('As credenciais da base de dados ainda não foram configuradas em backend/conexao.php.');
        http_response_code(500);
        die('Erro na configuração do servidor.');
    }
}

try {
    $conn = new mysqli(
        $configuracaoBanco['host'],
        $configuracaoBanco['utilizador'],
        $configuracaoBanco['senha'],
        $configuracaoBanco['banco']
    );
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log('Erro na conexão com a base de dados: ' . $e->getMessage());
    http_response_code(500);
    die('Erro ao conectar com a base de dados.');
}
