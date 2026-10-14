<?php

require_once "conexao.php";
require_once __DIR__ . "/emailjs.php";

$expectsJson = strpos($_SERVER["HTTP_ACCEPT"] ?? "", "application/json") !== false;

function respondToRequest(int $status, string $message, bool $success = false, array $extra = []): void
{
    global $expectsJson;

    http_response_code($status);
    if ($expectsJson) {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(
            array_merge(["success" => $success, "message" => $message], $extra),
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    die(htmlspecialchars($message, ENT_QUOTES, "UTF-8"));
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    respondToRequest(405, "Método não permitido.");
}


/* Receber os dados */

$email = strtolower(trim($_POST["email"] ?? ""));
$contacto = trim($_POST["contacto"] ?? "");
$telefoneOpcional = trim($_POST["telefone_opcional"] ?? "");
$curso = trim($_POST["curso"] ?? "");
$turno = trim($_POST["turno"] ?? "");
$morada = trim($_POST["morada"] ?? "");
$classeAnterior = trim($_POST["classe_anterior"] ?? "");
$escolaAnterior = trim($_POST["escola_anterior"] ?? "");
$nivelAnterior = trim($_POST["nivel_anterior"] ?? "");
$anoConclusaoInformado = trim($_POST["ano_conclusao"] ?? "");
$anoConclusao = $anoConclusaoInformado === ""
    ? null
    : filter_var($anoConclusaoInformado, FILTER_VALIDATE_INT);
$apelido = trim($_POST["apelido"] ?? "");
$outrosNomes = trim($_POST["outros_nomes"] ?? "");
$nome = $outrosNomes;
$genero = trim($_POST["genero"] ?? "");
$estadoCivil = trim($_POST["estado_civil"] ?? "");
$dataNascimento = trim($_POST["data_nascimento"] ?? "");
$tipoDocumento = trim($_POST["tipo_documento"] ?? "");
$numeroDocumento = trim($_POST["numero_documento"] ?? "");
$validadeDocumento = trim($_POST["validade_documento"] ?? "");
$localEmissao = trim($_POST["local_emissao"] ?? "");
$provincia = trim($_POST["provincia"] ?? "");
$cidadeDistrito = trim($_POST["cidade_distrito"] ?? "");


/* Verificar campos obrigatórios */

if (
    empty($nome) ||
    empty($email) ||
    empty($contacto) ||
    empty($curso) ||
    empty($turno) ||
    empty($classeAnterior) ||
    empty($nivelAnterior) ||
    empty($apelido) ||
    empty($outrosNomes) ||
    empty($genero) ||
    empty($dataNascimento) ||
    empty($tipoDocumento) ||
    empty($numeroDocumento) ||
    empty($validadeDocumento) ||
    empty($localEmissao) ||
    empty($provincia) ||
    empty($cidadeDistrito)
) {
    respondToRequest(422, "Por favor, preencha todos os campos obrigatórios.");
}

if ($anoConclusao === false) {
    respondToRequest(422, "Introduza um ano de conclusão válido.");
}
if ($anoConclusao !== null && ($anoConclusao < 1950 || $anoConclusao > 2100)) {
    respondToRequest(422, "Introduza um ano de conclusão válido.");
}

$validarData = static function (string $valor): bool {
    $data = DateTime::createFromFormat("!Y-m-d", $valor);
    return $data instanceof DateTime && $data->format("Y-m-d") === $valor;
};
if (!$validarData($dataNascimento) || !$validarData($validadeDocumento)) {
    respondToRequest(422, "Verifique as datas de nascimento e de validade do documento.");
}
if ($dataNascimento > date("Y-m-d")) {
    respondToRequest(422, "A data de nascimento não pode estar no futuro.");
}

$opcoes = [
    [$curso, [
        "Suporte Informático", "Programação Web", "Contabilidade",
        "Gestão de Empresas", "Gestão de Recursos Humanos",
        "Gestão Financeira & Contabilidade", "Eletricidade Industrial",
        "Eletricidade & Eletrónica Industrial", "Mecânica Industrial"
    ]],
    [$turno, ["Diurno", "Noturno", "A Distância"]],
    [$nivelAnterior, ["Ensino Básico", "Ensino Médio", "Ensino Superior"]],
    [$genero, ["Masculino", "Feminino"]],
    [$tipoDocumento, ["Bilhete de Identidade", "Passaporte", "Cédula Pessoal"]],
    [$provincia, [
        "Cabo Delgado", "Gaza", "Inhambane", "Manica", "Maputo Cidade",
        "Maputo Província", "Nampula", "Niassa", "Sofala", "Tete", "Zambézia"
    ]],
];
foreach ($opcoes as [$valor, $valoresPermitidos]) {
    if (!in_array($valor, $valoresPermitidos, true)) {
        respondToRequest(422, "Uma das opções seleccionadas não é válida.");
    }
}
if ($estadoCivil !== "" && !in_array($estadoCivil, ["Solteiro(a)", "Casado(a)"], true)) {
    respondToRequest(422, "Uma das opções seleccionadas não é válida.");
}

/* Validar email */

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondToRequest(422, "O endereço de email informado não é válido.");
}

$contactoNormalizado = preg_replace('/[\s().-]/', '', $contacto);
if (
    !is_string($contactoNormalizado) ||
    !preg_match('/^(?:(?:\+258|00258|258))?(8[2-7]\d{7})$/', $contactoNormalizado, $matches)
) {
    respondToRequest(
        422,
        "Introduza um número moçambicano válido (82 a 87, com 9 dígitos)."
    );
}
$contacto = "+258" . $matches[1];

if ($telefoneOpcional !== "") {
    $telefoneOpcionalNormalizado = preg_replace('/[\s().-]/', '', $telefoneOpcional);
    if (
        !is_string($telefoneOpcionalNormalizado) ||
        !preg_match('/^(?:(?:\+258|00258|258))?(8[2-7]\d{7})$/', $telefoneOpcionalNormalizado, $telefoneMatches)
    ) {
        respondToRequest(422, "Introduza um número de telefone opcional moçambicano válido.");
    }
    $telefoneOpcional = "+258" . $telefoneMatches[1];
}

$checkSql = "SELECT id
             FROM inscricoes
             WHERE LOWER(email) = LOWER(?)
               AND LOWER(curso) = LOWER(?)
               AND estado IN ('Pendente', 'Aprovada')
             LIMIT 1";
$checkStmt = $conn->prepare($checkSql);
if (!$checkStmt) {
    respondToRequest(500, "Não foi possível verificar a inscrição duplicada.");
}
$checkStmt->bind_param("ss", $email, $curso);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult && $checkResult->num_rows > 0) {
    respondToRequest(409, "Este email já tem uma pré-inscrição pendente ou aprovada neste curso.");
}
$checkStmt->close();

/* Preparar a consulta */

$sql = "INSERT INTO inscricoes
        (nome, apelido, outros_nomes, email, contacto, telefone_opcional,
         curso, turno, classe_anterior, escola_anterior, nivel_anterior,
         ano_conclusao, genero, estado_civil, data_nascimento,
         tipo_documento, numero_documento, validade_documento, local_emissao,
         provincia, cidade_distrito, morada)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

try {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssssissssssssss",
        $nome,
        $apelido,
        $outrosNomes,
        $email,
        $contacto,
        $telefoneOpcional,
        $curso,
        $turno,
        $classeAnterior,
        $escolaAnterior,
        $nivelAnterior,
        $anoConclusao,
        $genero,
        $estadoCivil,
        $dataNascimento,
        $tipoDocumento,
        $numeroDocumento,
        $validadeDocumento,
        $localEmissao,
        $provincia,
        $cidadeDistrito,
        $morada
    );
    $stmt->execute();
} catch (mysqli_sql_exception $e) {
    error_log("Erro ao registar pré-inscrição: " . $e->getMessage());
    respondToRequest(500, "Não foi possível guardar a pré-inscrição. Tente novamente.");
}

if ($expectsJson) {
    $emailResults = sendEmailNotifications($conn, $_POST);
    respondToRequest(201, "Pré-inscrição registada com sucesso.", true, [
        "emailAdmin" => $emailResults["admin"],
        "emailApplicant" => $emailResults["applicant"],
        "emailMessage" => $emailResults["message"],
    ]);
}

    echo "
    <!DOCTYPE html>
    <html lang='pt'>
    <head>
        <meta charset='UTF-8'>
        <title>Pré-Inscrição Enviada</title>
        

        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f4f7fb;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
            }

            .mensagem {
                background: white;
                width: 90%;
                max-width: 500px;
                padding: 40px;
                text-align: center;
                border-radius: 15px;
                box-shadow: 0 10px 30px rgba(0,0,0,.08);
            }

            .icone {
                font-size: 50px;
                margin-bottom: 15px;
            }

            h1 {
                color: #0b3d91;
            }

            p {
                color: #555;
                line-height: 1.6;
            }

            a {
                display: inline-block;
                margin-top: 20px;
                padding: 13px 25px;
                background: #0b3d91;
                color: white;
                text-decoration: none;
                border-radius: 8px;
            }
        </style>
    </head>

    <body>

        <div class='mensagem'>

            <div class='icone'>✓</div>

            <h1>Pré-inscrição enviada!</h1>

            <p>
                A sua pré-inscrição foi registada com sucesso.
                Aguarde a confirmação do Instituto.
            </p>

            <a href='index.html'>
                Voltar ao formulário
            </a>

        </div>

    </body>
    </html>
    ";

$stmt->close();
$conn->close();

?>