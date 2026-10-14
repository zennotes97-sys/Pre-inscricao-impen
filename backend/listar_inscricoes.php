<?php

require_once __DIR__ . '/admin_auth.php';
requireAdmin();

require_once "conexao.php";

$erroBaseDados = '';
$csrfToken = adminCsrfToken();
$escapeHtml = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
};
$displayValue = static function ($value) use ($escapeHtml): string {
    $value = trim((string) ($value ?? ''));
    return $value !== '' ? $escapeHtml($value) : '—';
};
$formatDate = static function ($value, bool $includeTime = false): string {
    $timestamp = strtotime((string) ($value ?? ''));
    if ($timestamp === false) {
        return '—';
    }

    return date($includeTime ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
};

try {
    $resultado = $conn->query(
        "SELECT id, nome, apelido, outros_nomes, email, contacto, telefone_opcional,
                curso, turno, classe_anterior, escola_anterior, nivel_anterior,
                ano_conclusao, genero, estado_civil, data_nascimento,
                tipo_documento, numero_documento, validade_documento, local_emissao,
                provincia, cidade_distrito, morada, estado, data_inscricao
         FROM inscricoes
         ORDER BY id DESC"
    );
    $statsResult = $conn->query(
        "SELECT COUNT(*) AS total,
                COALESCE(SUM(estado = 'Pendente'), 0) AS pendentes,
                COALESCE(SUM(estado = 'Aprovada'), 0) AS aprovadas,
            COALESCE(SUM(estado = 'Rejeitada'), 0) AS rejeitadas,
            COALESCE(SUM(estado = 'Concluída'), 0) AS concluidas
         FROM inscricoes"
    );
    $stats = $statsResult->fetch_assoc();
} catch (mysqli_sql_exception $exception) {
    error_log('Erro ao carregar pré-inscrições: ' . $exception->getMessage());
    http_response_code(500);
    $erroBaseDados = 'Não foi possível carregar as pré-inscrições. Tente novamente mais tarde.';
    $resultado = false;
    $stats = ['total' => 0, 'pendentes' => 0, 'aprovadas' => 0, 'rejeitadas' => 0, 'concluidas' => 0];
}

?>

<!DOCTYPE html>

<html lang="pt">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Pré-Inscrições | IMPEN</title>
    
    <link rel="shortcut icon" href="logo.jpg" type="image/x-icon">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f1f5f9;
            color: #1f2937;
            overflow-x: hidden;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            background: linear-gradient(135deg, #0b3d91, #1261c9);
            color: white;
            padding: 22px max(25px, calc((100% - 1400px) / 2));
        }

        .header form {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .header p {
            margin: 6px 0 0;
            opacity: .88;
        }

        .logout-button {
            padding: 10px 15px;
            border: 1px solid rgba(255,255,255,.55);
            border-radius: 8px;
            color: white;
            background: rgba(255,255,255,.12);
            font-weight: 700;
            cursor: pointer;
        }

        .logout-button:hover {
            background: rgba(255,255,255,.22);
        }

        .container {
            width: min(95%, 1400px);
            max-width: 1400px;
            margin: 30px auto;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0,0,0,.07);
            min-width: 0;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 145px), 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .summary-item {
            padding: 17px 20px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: white;
            box-shadow: 0 5px 18px rgba(15, 41, 82, .04);
        }

        .summary-item span {
            display: block;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .summary-item strong {
            display: block;
            margin-top: 6px;
            color: #0b3d91;
            font-size: 26px;
        }

        .table-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 16px;
        }

        .table-heading h2 {
            margin: 0;
            color: #0b3d91;
            font-size: 20px;
        }

        .search {
            width: 100%;
            min-width: 0;
            padding: 12px 76px 12px 42px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            outline: none;
            background: #fff;
            color: #1f2937;
            font: inherit;
            font-size: 14px;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .search-control {
            position: relative;
            width: min(100%, 370px);
        }

        .search-label {
            display: block;
            margin-bottom: 6px;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
        }

        .search-control::before {
            position: absolute;
            top: 35px;
            left: 15px;
            width: 12px;
            height: 12px;
            border: 2px solid #64748b;
            border-radius: 50%;
            content: '';
            pointer-events: none;
        }

        .search-control::after {
            position: absolute;
            top: 48px;
            left: 28px;
            width: 7px;
            height: 2px;
            transform: rotate(45deg);
            transform-origin: left center;
            background: #64748b;
            content: '';
            pointer-events: none;
        }

        .search:focus {
            border-color: #1261c9;
            box-shadow: 0 0 0 3px rgba(18, 97, 201, .14);
        }

        .search::placeholder {
            color: #94a3b8;
        }

        .search-clear {
            position: absolute;
            top: 29px;
            right: 8px;
            padding: 7px 9px;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: #315da8;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .search-clear:hover {
            background: #eff6ff;
        }

        .search-clear:focus-visible {
            outline: 2px solid #1261c9;
            outline-offset: 2px;
        }

        .search-empty {
            margin-top: 14px;
            padding: 20px;
            border: 1px solid #dbe3ed;
            border-radius: 9px;
            background: #f8fafc;
            color: #475569;
            text-align: center;
        }

        .search-empty[hidden] {
            display: none;
        }

        .search-empty p {
            margin: 0 0 12px;
        }

        .search-empty button {
            padding: 8px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: white;
            color: #315da8;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .error {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 9px;
            color: #991b1b;
            background: #fef2f2;
        }

        .estado {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .pendente {
            background: #fff7ed;
            color: #c2410c;
        }

        .aprovada {
            background: #ecfdf5;
            color: #047857;
        }

        .rejeitada {
            background: #fef2f2;
            color: #b91c1c;
        }

        .concluida {
            background: #eff6ff;
            color: #1d4ed8;
        }

        .status-form {
            display: flex;
            gap: 6px;
            margin-top: 8px;
        }

        .status-form select,
        .status-form button {
            padding: 7px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            background: white;
            color: #1f2937;
        }

        .status-form button {
            cursor: pointer;
            font-weight: 700;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .vazio {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        .registration-list {
            display: grid;
            gap: 16px;
        }

        .registration-card {
            overflow: hidden;
            border: 1px solid #dbe3ed;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(15, 41, 82, .06);
            transition: box-shadow .2s ease, border-color .2s ease;
        }

        .registration-card:hover {
            border-color: #b8cbea;
            box-shadow: 0 12px 30px rgba(15, 41, 82, .1);
        }

        .registration-card[hidden] {
            display: none;
        }

        .registration-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            padding: 22px 24px;
        }

        .registration-identity {
            min-width: 0;
        }

        .registration-number {
            display: block;
            margin-bottom: 7px;
            color: #64748b;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .registration-name {
            margin: 0;
            color: #102b55;
            font-size: 20px;
            line-height: 1.3;
            overflow-wrap: anywhere;
        }

        .registration-course {
            margin: 7px 0 0;
            color: #315da8;
            font-size: 14px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .registration-meta {
            display: flex;
            flex-shrink: 0;
            align-items: center;
            gap: 12px;
            color: #64748b;
            font-size: 12px;
        }

        .registration-date {
            text-align: right;
        }

        .registration-date strong {
            display: block;
            margin-bottom: 4px;
            color: #334155;
        }

        .registration-body {
            padding: 0 24px 20px;
        }

        .registration-details {
            border-top: 1px solid #edf0f4;
        }

        .registration-details summary {
            width: fit-content;
            padding: 14px 0 4px;
            color: #1554a6;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            list-style: none;
        }

        .registration-details summary::-webkit-details-marker {
            display: none;
        }

        .registration-details summary::after {
            display: inline-block;
            margin-left: 8px;
            content: '+';
            font-size: 17px;
            line-height: 12px;
            vertical-align: -1px;
        }

        .registration-details[open] summary::after {
            content: '−';
        }

        .registration-details summary:hover,
        .registration-details summary:focus-visible {
            color: #0b3d91;
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            padding-top: 16px;
        }

        .details-section {
            min-width: 0;
            padding: 17px;
            border: 1px solid #e5eaf1;
            border-radius: 12px;
            background: #f8fafc;
        }

        .details-section h3 {
            margin: 0 0 13px;
            color: #17447f;
            font-size: 13px;
        }

        .details-section dl {
            display: grid;
            grid-template-columns: minmax(110px, .42fr) minmax(0, 1fr);
            gap: 9px 12px;
            margin: 0;
            font-size: 13px;
            line-height: 1.45;
        }

        .details-section dt {
            color: #64748b;
        }

        .details-section dd {
            min-width: 0;
            margin: 0;
            color: #1f2937;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .details-section a {
            color: #1554a6;
            text-decoration: none;
        }

        .details-section a:hover {
            text-decoration: underline;
        }

        .status-form {
            flex-wrap: wrap;
        }

        @media (max-width: 850px) {
            .header {
                align-items: stretch;
                padding: 20px max(16px, 3vw);
            }

            .header form {
                width: 100%;
            }

            .header h1 {
                font-size: 20px;
            }

            .container {
                width: calc(100% - 24px);
                margin: 16px auto;
            }

            .card {
                padding: 16px;
            }

            .table-heading {
                align-items: stretch;
                flex-direction: column;
            }

            .search-control {
                width: 100%;
            }

            .registration-card-header {
                padding: 18px;
            }

            .registration-body {
                padding: 0 18px 18px;
            }

            .status-form label,
            .status-form select {
                min-width: 0;
                max-width: 100%;
            }
        }

        @media (max-width: 420px) {
            .card {
                padding: 12px;
            }

            .summary-item {
                padding: 13px;
            }

            .registration-card-header {
                flex-direction: column;
                gap: 12px;
            }

            .registration-meta {
                width: 100%;
                justify-content: space-between;
                flex-wrap: wrap;
            }

            .registration-date {
                text-align: left;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .details-section dl {
                grid-template-columns: minmax(95px, .4fr) minmax(0, 1fr);
            }

            .status-form {
                grid-column: auto;
            }
        }
    .aprovada {
        background: #ecfdf5;
        color: #047857;
    }   
    .admin-link {
        display: inline-block;
        margin-top: 0;
        padding: 13px 25px;
        background: #0b3d91;
        color: white;
        text-decoration: none;
        border-radius: 8px;
    }
    </style>

</head>

<body>


<header class="header">
    <div>
        <h1>Pré-Inscrições</h1>
        <p>Instituto Médio Politécnico de Engenharia e Negócios</p>
    </div>
    <form method="post" action="admin_logout.php">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <a href="../index.html" class="admin-link">Voltar ao início</a>
        <button class="logout-button" type="submit">Terminar sessão</button>
    </form>
</header>


<main class="container">

    <div class="card">

        <?php if ($erroBaseDados !== ''): ?>
            <div class="error" role="alert"><?= htmlspecialchars($erroBaseDados, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <section class="summary" aria-label="Resumo das pré-inscrições">
            <div class="summary-item"><span>Total</span><strong><?= (int) $stats['total'] ?></strong></div>
            <div class="summary-item"><span>Pendentes</span><strong><?= (int) $stats['pendentes'] ?></strong></div>
            <div class="summary-item"><span>Aprovadas</span><strong><?= (int) $stats['aprovadas'] ?></strong></div>
            <div class="summary-item"><span>Rejeitadas</span><strong><?= (int) $stats['rejeitadas'] ?></strong></div>
            <div class="summary-item"><span>Concluídas</span><strong><?= (int) $stats['concluidas'] ?></strong></div>
        </section>

        <?php if ($resultado && $resultado->num_rows > 0): ?>

            <div class="table-heading">
                <h2>Inscrições recebidas</h2>
                <div class="search-control">
                    <label class="search-label" for="search">Pesquisar inscrições</label>
                    <input class="search" id="search" type="search" placeholder="Nome, email, curso ou contacto" autocomplete="off">
                    <button class="search-clear" id="search-clear" type="button" aria-label="Limpar pesquisa">Limpar</button>
                </div>
            </div>

            <section class="registration-list" aria-label="Lista de pré-inscrições">
                <?php while ($inscricao = $resultado->fetch_assoc()): ?>
                    <?php
                    $nomeCompleto = trim(
                        (string) ($inscricao['nome'] ?? '') . ' ' . (string) ($inscricao['apelido'] ?? '')
                    );
                    $estadoClasses = [
                        'Pendente' => 'pendente',
                        'Aprovada' => 'aprovada',
                        'Rejeitada' => 'rejeitada',
                        'Concluída' => 'concluida',
                    ];
                    $estadoClasse = $estadoClasses[$inscricao['estado']] ?? '';
                    ?>
                    <article class="registration-card">
                        <div class="registration-card-header">
                            <div class="registration-identity">
                                <span class="registration-number">Inscrição #<?= (int) $inscricao['id'] ?></span>
                                <h3 class="registration-name"><?= $escapeHtml($nomeCompleto !== '' ? $nomeCompleto : 'Candidato sem nome') ?></h3>
                                <p class="registration-course"><?= $displayValue($inscricao['curso']) ?></p>
                            </div>
                            <div class="registration-meta">
                                <span class="estado <?= $escapeHtml($estadoClasse) ?>"><?= $displayValue($inscricao['estado']) ?></span>
                                <span class="registration-date">
                                    <strong><?= $formatDate($inscricao['data_inscricao'], true) ?></strong>
                                    Data de inscrição
                                </span>
                            </div>
                        </div>

                        <div class="registration-body">
                            <details class="registration-details">
                                <summary>Ver mais informações</summary>
                                <div class="details-grid">
                                    <section class="details-section" aria-label="Contacto">
                                        <h3>Contacto</h3>
                                        <dl>
                                            <dt>E-mail</dt><dd><?= $displayValue($inscricao['email']) ?></dd>
                                            <dt>Telefone</dt><dd><?= $displayValue($inscricao['contacto']) ?></dd>
                                            <dt>Telefone alternativo</dt><dd><?= $displayValue($inscricao['telefone_opcional']) ?></dd>
                                        </dl>
                                    </section>

                                    <section class="details-section" aria-label="Dados pessoais">
                                        <h3>Dados pessoais</h3>
                                        <dl>
                                            <dt>Nome registado</dt><dd><?= $displayValue($inscricao['outros_nomes']) ?></dd>
                                            <dt>Apelido</dt><dd><?= $displayValue($inscricao['apelido']) ?></dd>
                                            <dt>Género</dt><dd><?= $displayValue($inscricao['genero']) ?></dd>
                                            <dt>Estado civil</dt><dd><?= $displayValue($inscricao['estado_civil']) ?></dd>
                                            <dt>Data de nascimento</dt><dd><?= $formatDate($inscricao['data_nascimento']) ?></dd>
                                        </dl>
                                    </section>

                                    <section class="details-section" aria-label="Dados académicos">
                                        <h3>Dados académicos</h3>
                                        <dl>
                                            <dt>Curso pretendido</dt><dd><?= $displayValue($inscricao['curso']) ?></dd>
                                            <dt>Regime / turno</dt><dd><?= $displayValue($inscricao['turno']) ?></dd>
                                            <dt>Curso / classe anterior</dt><dd><?= $displayValue($inscricao['classe_anterior']) ?></dd>
                                            <dt>Escola anterior</dt><dd><?= $displayValue($inscricao['escola_anterior']) ?></dd>
                                            <dt>Nível anterior</dt><dd><?= $displayValue($inscricao['nivel_anterior']) ?></dd>
                                            <dt>Ano de conclusão</dt><dd><?= $displayValue($inscricao['ano_conclusao']) ?></dd>
                                        </dl>
                                    </section>

                                    <section class="details-section" aria-label="Documento de identificação">
                                        <h3>Documento de identificação</h3>
                                        <dl>
                                            <dt>Tipo de documento</dt><dd><?= $displayValue($inscricao['tipo_documento']) ?></dd>
                                            <dt>Número</dt><dd><?= $displayValue($inscricao['numero_documento']) ?></dd>
                                            <dt>Validade</dt><dd><?= $formatDate($inscricao['validade_documento']) ?></dd>
                                            <dt>Local de emissão</dt><dd><?= $displayValue($inscricao['local_emissao']) ?></dd>
                                        </dl>
                                    </section>

                                    <section class="details-section" aria-label="Residência">
                                        <h3>Residência</h3>
                                        <dl>
                                            <dt>Província</dt><dd><?= $displayValue($inscricao['provincia']) ?></dd>
                                            <dt>Cidade / Distrito</dt><dd><?= $displayValue($inscricao['cidade_distrito']) ?></dd>
                                            <dt>Endereço</dt><dd><?= $displayValue($inscricao['morada']) ?></dd>
                                        </dl>
                                    </section>
                                </div>
                            </details>

                            <form class="status-form" method="post" action="atualizar_estado.php">
                                <input type="hidden" name="csrf_token" value="<?= $escapeHtml($csrfToken) ?>">
                                <input type="hidden" name="id" value="<?= (int) $inscricao['id'] ?>">
                                <label>
                                    <span class="sr-only">Alterar estado da inscrição de <?= $escapeHtml($nomeCompleto) ?></span>
                                    <select name="estado" aria-label="Alterar estado da inscrição de <?= $escapeHtml($nomeCompleto) ?>">
                                        <?php foreach (['Pendente', 'Aprovada', 'Rejeitada', 'Concluída'] as $opcaoEstado): ?>
                                            <option value="<?= $escapeHtml($opcaoEstado) ?>" <?= $inscricao['estado'] === $opcaoEstado ? 'selected' : '' ?>><?= $escapeHtml($opcaoEstado) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <button type="submit">Atualizar estado</button>
                            </form>
                        </div>
                    </article>
                <?php endwhile; ?>
            </section>

            <div class="search-empty" id="search-empty" role="status" aria-live="polite" hidden>
                <p id="search-empty-message">Não encontrámos nenhuma inscrição correspondente à pesquisa.</p>
                <button id="search-empty-clear" type="button">Limpar pesquisa</button>
            </div>

        <?php else: ?>

            <div class="vazio">

                <h3>Nenhuma pré-inscrição encontrada.</h3>
                <p>Ainda não existem candidatos registados.</p>

            </div>

        <?php endif; ?>

    </div>

</main>

<script>
    const searchInput = document.getElementById('search');
    if (searchInput) {
        const clearSearchButton = document.getElementById('search-clear');
        const noResults = document.getElementById('search-empty');
        const noResultsMessage = document.getElementById('search-empty-message');
        const clearFromMessageButton = document.getElementById('search-empty-clear');
        const registrationRows = [...document.querySelectorAll('.registration-card')];

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLocaleLowerCase();
            let visibleCount = 0;

            registrationRows.forEach((row) => {
                const matches = row.textContent.toLocaleLowerCase().includes(query);
                row.hidden = !matches;
                visibleCount += matches ? 1 : 0;
            });

            noResults.hidden = visibleCount > 0;
            if (visibleCount === 0) {
                noResultsMessage.textContent = `Não encontrámos nenhuma inscrição correspondente a "${searchInput.value.trim()}".`;
            }
        });

        const clearSearch = () => {
            searchInput.value = '';
            searchInput.dispatchEvent(new Event('input'));
            searchInput.focus();
        };

        clearSearchButton.addEventListener('click', clearSearch);
        clearFromMessageButton.addEventListener('click', clearSearch);
    }
</script>

</body>

</html>

<?php

$conn->close();

?>