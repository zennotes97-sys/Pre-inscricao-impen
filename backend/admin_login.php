<?php

declare(strict_types=1);

require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/conexao.php';

startAdminSession();

if (($_SESSION['admin_authenticated'] ?? false) === true) {
    header('Location: listar_inscricoes.php');
    exit;
}

$erro = '';
$csrfToken = adminCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $password = $_POST['senha'] ?? '';

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        http_response_code(400);
        $erro = 'A sessão expirou. Atualize a página e tente novamente.';
    } elseif (!is_string($password) || $password === '') {
        $erro = 'Introduza a senha de administrador.';
    } else {
        try {
            $result = $conn->query('SELECT senha FROM senha LIMIT 1');
            $adminPassword = $result->fetch_assoc();
            $storedPassword = $adminPassword ? (string) $adminPassword['senha'] : '';
            $legacyPasswordMatches = $adminPassword && hash_equals($storedPassword, $password);
            $passwordMatches = $adminPassword && (
                password_verify($password, $storedPassword) || $legacyPasswordMatches
            );

            if ($passwordMatches) {
                if ($legacyPasswordMatches) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $update = $conn->prepare('UPDATE senha SET senha = ? WHERE senha = ? LIMIT 1');
                    $update->bind_param('ss', $hashedPassword, $password);
                    $update->execute();
                    $update->close();
                }

                session_regenerate_id(true);
                $_SESSION['admin_authenticated'] = true;
                $_SESSION['admin_last_activity'] = time();
                header('Location: listar_inscricoes.php');
                exit;
            }

            http_response_code(401);
            $erro = 'Senha incorreta.';
        } catch (mysqli_sql_exception $exception) {
            error_log('Erro ao autenticar administrador: ' . $exception->getMessage());
            http_response_code(500);
            $erro = 'Não foi possível verificar o acesso. Confirme a configuração da base de dados.';
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso administrativo | IMPEN</title>
    <link rel="shortcut icon" href="../logo.png" type="image/x-icon">
    <style>
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            padding: 24px;
            display: grid;
            place-items: center;
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
            background: linear-gradient(135deg, #eaf2ff, #f8fafc);
        }
        .login-card {
            width: min(100%, 430px);
            padding: 38px;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 18px 50px rgba(15, 41, 82, .14);
        }
        .brand {
            margin-bottom: 24px;
            color: #0b3d91;
            font-weight: 800;
            letter-spacing: .08em;
        }
        h1 { margin: 0 0 10px; color: #0b3d91; font-size: 26px; }
        p { margin: 0 0 25px; color: #64748b; line-height: 1.55; }
        label { display: block; margin-bottom: 8px; font-weight: 700; }
        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            font-size: 16px;
        }
        input:focus {
            outline: 3px solid rgba(18, 97, 201, .16);
            border-color: #1261c9;
        }
        button {
            width: 100%;
            margin-top: 20px;
            padding: 14px;
            border: 0;
            border-radius: 9px;
            color: #fff;
            background: linear-gradient(135deg, #0b3d91, #1261c9);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }
        .error {
            margin-bottom: 18px;
            padding: 12px 14px;
            border-radius: 8px;
            color: #991b1b;
            background: #fef2f2;
            line-height: 1.5;
        }
        .back {
            display: block;
            margin-top: 20px;
            color: #0b3d91;
            text-align: center;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="login-card">
        <div class="brand">IMPEN · ADMINISTRAÇÃO</div>
        <h1>Acesso administrativo</h1>
        <p>Introduza a senha para consultar e gerir as pré-inscrições.</p>

        <?php if ($erro !== ''): ?>
            <div class="error" role="alert"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="admin_login.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <label for="senha">Senha de administrador</label>
            <input type="password" id="senha" name="senha" autocomplete="current-password" required autofocus>
            <button type="submit">Entrar</button>
        </form>
        <a class="back" href="../index.php">Voltar ao formulário público</a>
    </main>
</body>
</html>
