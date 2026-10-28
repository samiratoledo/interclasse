<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('representante');

$usuarioSessao = $_SESSION['usuario'] ?? [];

if (!is_array($usuarioSessao)) {
    $usuarioSessao = [];
}

$turmaId = (int) ($usuarioSessao['turma_id'] ?? 0);
$nomeUsuario = $usuarioSessao['nome'] ?? $usuarioSessao['usuario'] ?? 'representante';

if (!is_string($nomeUsuario)) {
    $nomeUsuario = 'representante';
}

if ($turmaId <= 0) {
    http_response_code(403);
    exit('Sua conta não possui uma turma vinculada. Procure a administração.');
}

$consultaTurma = $pdo->prepare('SELECT nome, periodo FROM turmas WHERE id = :id AND ativa = 1');
$consultaTurma->execute(['id' => $turmaId]);
$turma = $consultaTurma->fetch(PDO::FETCH_ASSOC);

if (!$turma) {
    http_response_code(403);
    exit('Sua turma está inativa ou não foi encontrada. Procure a administração.');
}

$consultaAtletas = $pdo->prepare(
    'SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status_aprovacao = \'pendente\' THEN 1 ELSE 0 END) AS pendentes,
        SUM(CASE WHEN status_aprovacao = \'aprovado\' THEN 1 ELSE 0 END) AS aprovados,
        SUM(CASE WHEN status_aprovacao = \'rejeitado\' THEN 1 ELSE 0 END) AS rejeitados
     FROM atletas
     WHERE turma_id = :turma_id'
);
$consultaAtletas->execute(['turma_id' => $turmaId]);
$resumo = $consultaAtletas->fetch(PDO::FETCH_ASSOC);

function escaparPainelRepresentante(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do representante | Interclasse ETEC</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<nav class="navbar is-dark" role="navigation" aria-label="Navegação principal">
    <div class="navbar-brand">
        <a class="navbar-item has-text-weight-bold" href="index.php">Interclasse ETEC</a>
    </div>
    <div class="navbar-menu is-active">
        <div class="navbar-start">
            <a class="navbar-item is-active" href="index.php">Painel</a>
            <a class="navbar-item" href="atletas.php">Meus atletas</a>
            <a class="navbar-item" href="inscricoes.php">Inscrições</a>
        </div>
        <div class="navbar-end">
            <div class="navbar-item">
                <form action="../logout.php" method="POST">
                    <input type="hidden" name="token_csrf" value="<?= escaparPainelRepresentante(gerarTokenCsrf()) ?>">
                    <button class="button is-light is-small" type="submit">Sair</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<section class="section">
    <div class="container">
        <h1 class="title">Painel do representante</h1>
        <p class="subtitle">Bem-vindo(a), <?= escaparPainelRepresentante($nomeUsuario) ?>!</p>

        <div class="box">
            <p class="heading">Sua turma</p>
            <h2 class="title is-3"><?= escaparPainelRepresentante($turma['nome']) ?></h2>
            <p>Período: <?= escaparPainelRepresentante($turma['periodo'] === 'manha' ? 'Manhã' : 'Tarde') ?></p>
        </div>

        <div class="columns is-multiline">
            <div class="column is-3">
                <div class="box">
                    <p class="heading">Total de atletas</p>
                    <p class="title"><?= (int) ($resumo['total'] ?? 0) ?></p>
                </div>
            </div>
            <div class="column is-3">
                <div class="box">
                    <p class="heading">Aguardando aprovação</p>
                    <p class="title has-text-warning"><?= (int) ($resumo['pendentes'] ?? 0) ?></p>
                </div>
            </div>
            <div class="column is-3">
                <div class="box">
                    <p class="heading">Aprovados</p>
                    <p class="title has-text-success"><?= (int) ($resumo['aprovados'] ?? 0) ?></p>
                </div>
            </div>
            <div class="column is-3">
                <div class="box">
                    <p class="heading">Rejeitados</p>
                    <p class="title has-text-danger"><?= (int) ($resumo['rejeitados'] ?? 0) ?></p>
                </div>
            </div>
        </div>

        <div class="box">
            <h2 class="title is-4">Gerenciar atletas</h2>
            <p class="mb-4">Cadastre os alunos que representarão sua turma no interclasse. Cada cadastro ficará pendente até a análise da administração.</p>
            <a class="button is-primary" href="atletas.php">Cadastrar e consultar atletas</a>
        </div>
    </div>
</section>
</body>
</html>
