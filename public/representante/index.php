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
$nomeUsuario = $usuarioSessao['nome'] ?? $usuarioSessao['usuario'] ?? 'Representante';

if (!is_string($nomeUsuario)) {
    $nomeUsuario = 'Representante';
}

if ($turmaId <= 0) {
    http_response_code(403);
    exit('Sua conta não possui uma turma vinculada. Procure a administração.');
}

$consultaTurma = $pdo->prepare(
    'SELECT id, nome, periodo, imagem
     FROM turmas
     WHERE id = :id AND ativa = 1'
);

$consultaTurma->execute(['id' => $turmaId]);
$turma = $consultaTurma->fetch(PDO::FETCH_ASSOC);

if (!$turma) {
    http_response_code(403);
    exit('Sua turma está inativa ou não foi encontrada. Procure a administração.');
}

$consultaAtletas = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status_aprovacao = 'pendente' THEN 1 ELSE 0 END) AS pendentes,
        SUM(CASE WHEN status_aprovacao = 'aprovado' THEN 1 ELSE 0 END) AS aprovados,
        SUM(CASE WHEN status_aprovacao = 'rejeitado' THEN 1 ELSE 0 END) AS rejeitados
     FROM atletas
     WHERE turma_id = :turma_id"
);

$consultaAtletas->execute(['turma_id' => $turmaId]);
$resumo = $consultaAtletas->fetch(PDO::FETCH_ASSOC);

function escaparPainelRepresentante(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/../../includes/representante/header.php';

?>

<div class="mb-5">
    <h1 class="title is-3">Painel do representante</h1>
    <p class="subtitle is-6">
        Bem-vindo(a), <?= escaparPainelRepresentante($nomeUsuario) ?>!
    </p>
</div>

<div class="columns is-multiline">

    <div class="column is-12">
        <div class="box">
            <div class="columns is-vcentered is-variable is-5">
                <div class="column is-narrow has-text-centered">
                    <?php if (!empty($turma['imagem'])): ?>
                        <figure class="image is-128x128 is-inline-block">
                            <img
                                src="<?= escaparPainelRepresentante(
                                    '/interclasse-etec/' . ltrim((string) $turma['imagem'], '/')
                                ) ?>"
                                alt="Brasão da turma <?= escaparPainelRepresentante($turma['nome']) ?>"
                                style="width:128px;height:128px;object-fit:contain;"
                            >
                        </figure>
                    <?php else: ?>
                        <div
                            class="has-background-light is-flex is-align-items-center is-justify-content-center"
                            style="width:128px;height:128px;border-radius:8px;"
                        >
                            <span class="icon is-large">
                                <i class="fas fa-shield-alt fa-3x"></i>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="column">
                    <p class="heading">Sua turma</p>
                    <h2 class="title is-3">
                        <?= escaparPainelRepresentante($turma['nome']) ?>
                    </h2>

                    <p>
                        <strong>Período:</strong>
                        <?= escaparPainelRepresentante(
                            $turma['periodo'] === 'manha' ? 'Manhã' : 'Tarde'
                        ) ?>
                    </p>

                    <div class="mt-4">
                        <a class="button is-link is-light" href="brasao.php">
                            Gerenciar brasão da turma
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="column is-3-desktop is-6-tablet">
        <div class="box">
            <p class="heading">Total de atletas</p>
            <p class="title is-3"><?= (int) ($resumo['total'] ?? 0) ?></p>
        </div>
    </div>

    <div class="column is-3-desktop is-6-tablet">
        <div class="box">
            <p class="heading">Aguardando aprovação</p>
            <p class="title is-3 has-text-warning">
                <?= (int) ($resumo['pendentes'] ?? 0) ?>
            </p>
        </div>
    </div>

    <div class="column is-3-desktop is-6-tablet">
        <div class="box">
            <p class="heading">Aprovados</p>
            <p class="title is-3 has-text-success">
                <?= (int) ($resumo['aprovados'] ?? 0) ?>
            </p>
        </div>
    </div>

    <div class="column is-3-desktop is-6-tablet">
        <div class="box">
            <p class="heading">Rejeitados</p>
            <p class="title is-3 has-text-danger">
                <?= (int) ($resumo['rejeitados'] ?? 0) ?>
            </p>
        </div>
    </div>

    <div class="column is-12">
        <div class="box">
            <h2 class="title is-4">Gerenciar atletas</h2>

            <p class="mb-4">
                Cadastre os alunos que representarão sua turma no interclasse.
                Cada cadastro ficará pendente até a análise da administração.
            </p>

            <a class="button is-primary" href="atletas.php">
                Cadastrar e consultar atletas
            </a>
        </div>
    </div>

    <div class="column is-12">
        <div class="box">
            <h2 class="title is-4">Inscrições nas modalidades</h2>

            <p class="mb-4">
                Consulte e acompanhe as inscrições da sua turma nas modalidades
                disponíveis para o evento.
            </p>

            <a class="button is-link" href="inscricoes.php">
                Gerenciar inscrições
            </a>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../../includes/representante/footer.php'; ?>
