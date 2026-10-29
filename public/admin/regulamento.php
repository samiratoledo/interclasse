<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin');

$admin = usuarioAtual();

function escaparRegulamentoAdmin(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

$erro = '';
$sucesso = '';

$editando = [
    'id' => '',
    'titulo' => '',
    'conteudo' => '',
    'ordem' => '0',
    'ativo' => '1',
];

if (isset($_SESSION['mensagem_regulamento'])) {
    $sucesso = (string) $_SESSION['mensagem_regulamento'];
    unset($_SESSION['mensagem_regulamento']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token_csrf'] ?? null;
    $acao = $_POST['acao'] ?? '';

    if (!validarTokenCsrf(is_string($token) ? $token : null)) {
        $erro = 'Sessão expirada ou formulário inválido. Atualize a página.';
    } else {
        try {

            if ($acao === 'salvar') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $titulo = trim((string) ($_POST['titulo'] ?? ''));
                $conteudo = trim((string) ($_POST['conteudo'] ?? ''));
                $ordem = filter_input(INPUT_POST, 'ordem', FILTER_VALIDATE_INT);
                $ativo = isset($_POST['ativo']) ? 1 : 0;

                if ($titulo === '' || $conteudo === '') {
                    throw new RuntimeException(
                        'Preencha o título e o conteúdo da seção.'
                    );
                }

                if ($ordem === false || $ordem === null || $ordem < 0) {
                    throw new RuntimeException('Informe uma ordem válida.');
                }

                if (!$id) {
                    throw new RuntimeException(
                        'Selecione uma seção existente para editar.'
                    );
                }

                $pdo->beginTransaction();

                try {
                    // Busca todas as seções na ordem atual.
                    $stmt = $pdo->query("
            SELECT id
            FROM regulamentos
            ORDER BY ordem ASC, id ASC
            FOR UPDATE
        ");

                    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    if (!in_array($id, array_map('intval', $ids), true)) {
                        throw new RuntimeException('Seção não encontrada.');
                    }

                    // Retira a seção que está sendo editada.
                    $ids = array_values(array_filter(
                        $ids,
                        fn($item) => (int) $item !== $id
                    ));

                    // Limita a posição à quantidade de seções existentes.
                    $novaPosicao = min($ordem, count($ids));

                    // Insere a seção na nova posição.
                    array_splice($ids, $novaPosicao, 0, [$id]);

                    // Atualiza os dados da seção editada.
                    $stmt = $pdo->prepare("
            UPDATE regulamentos
            SET titulo = :titulo,
                conteudo = :conteudo,
                ativo = :ativo,
                atualizado_por = :admin_id
            WHERE id = :id
        ");

                    $stmt->execute([
                        'titulo' => $titulo,
                        'conteudo' => $conteudo,
                        'ativo' => $ativo,
                        'admin_id' => (int) $admin['id'],
                        'id' => $id,
                    ]);

                    // Reorganiza todas as posições sem deixar números repetidos.
                    $stmtOrdem = $pdo->prepare("
            UPDATE regulamentos
            SET ordem = :ordem
            WHERE id = :id
        ");

                    foreach ($ids as $posicao => $idSecao) {
                        $stmtOrdem->execute([
                            'ordem' => $posicao,
                            'id' => (int) $idSecao,
                        ]);
                    }

                    $pdo->commit();

                    $_SESSION['mensagem_regulamento'] =
                        'Seção atualizada e ordem reorganizada com sucesso!';

                    unset($_SESSION['token_csrf']);

                    header('Location: regulamento.php');
                    exit;

                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    throw $e;
                }
            }



            if ($acao === 'excluir') {
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

                if (!$id) {
                    throw new RuntimeException('Seção inválida.');
                }

                $pdo->beginTransaction();

                try {
                    $stmt = $pdo->prepare("
            DELETE FROM regulamentos
            WHERE id = :id
        ");

                    $stmt->execute(['id' => $id]);

                    if ($stmt->rowCount() === 0) {
                        throw new RuntimeException('Seção não encontrada.');
                    }

                    // Busca as seções restantes.
                    $stmt = $pdo->query("
            SELECT id
            FROM regulamentos
            ORDER BY ordem ASC, id ASC
            FOR UPDATE
        ");

                    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    // Reorganiza a numeração após a exclusão.
                    $stmtOrdem = $pdo->prepare("
            UPDATE regulamentos
            SET ordem = :ordem
            WHERE id = :id
        ");

                    foreach ($ids as $posicao => $idSecao) {
                        $stmtOrdem->execute([
                            'ordem' => $posicao,
                            'id' => (int) $idSecao,
                        ]);
                    }

                    $pdo->commit();

                    $_SESSION['mensagem_regulamento'] =
                        'Seção excluída e ordem reorganizada com sucesso!';

                    unset($_SESSION['token_csrf']);

                    header('Location: regulamento.php');
                    exit;

                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    throw $e;
                }
            }

            if ($acao === 'nova') {
                throw new RuntimeException(
                    'As seções iniciais são cadastradas pelo banco de dados. ' .
                    'Para criar novas modalidades, será necessário adicionar o cadastro de novas seções.'
                );
            }

            if ($acao !== '') {
                throw new RuntimeException('Ação inválida.');
            }
        } catch (RuntimeException $e) {
            $erro = $e->getMessage();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $erro = 'Não foi possível salvar as alterações.';
        }
    }
}

$idEdicao = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT);

if ($idEdicao) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, titulo, conteudo, ordem, ativo
            FROM regulamentos
            WHERE id = :id
        ");

        $stmt->execute(['id' => $idEdicao]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($registro) {
            $editando = $registro;
        } else {
            $erro = 'Seção não encontrada.';
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $erro = 'Não foi possível carregar a seção.';
    }
}

try {
    $stmt = $pdo->query("
        SELECT id, titulo, ordem, ativo, atualizado_em
        FROM regulamentos
        ORDER BY ordem ASC, titulo ASC
    ");

    $secoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $secoes = [];
    $erro = 'Não foi possível carregar as seções.';
}

require_once __DIR__ . '/../../includes/admin/header.php';
?>

<div class="mb-5">
    <h1 class="title is-3">Gerenciar regulamento</h1>

    <p class="subtitle is-6">
        Edite as regras que ficarão disponíveis para consulta pública.
    </p>

    <a href="../regulamento.php" target="_blank" class="button is-link is-light is-small">
        Visualizar regulamento público
    </a>
</div>

<?php if ($erro !== ''): ?>
    <div class="notification is-danger is-light">
        <?= escaparRegulamentoAdmin($erro) ?>
    </div>
<?php endif; ?>

<?php if ($sucesso !== ''): ?>
    <div class="notification is-success is-light">
        <?= escaparRegulamentoAdmin($sucesso) ?>
    </div>
<?php endif; ?>

<div class="box mb-5">
    <h2 class="title is-5">
        Editar seção
    </h2>

    <form method="POST" action="">
        <input type="hidden" name="token_csrf" value="<?= escaparRegulamentoAdmin(gerarTokenCsrf()) ?>">

        <input type="hidden" name="acao" value="salvar">

        <input type="hidden" name="id" value="<?= escaparRegulamentoAdmin($editando['id']) ?>">

        <div class="field">
            <label class="label">Título da seção</label>

            <div class="control">
                <input class="input" type="text" name="titulo" maxlength="150" required
                    value="<?= escaparRegulamentoAdmin($editando['titulo']) ?>">
            </div>
        </div>

        <div class="field">
            <label class="label">Conteúdo do regulamento</label>

            <div class="control">
                <textarea class="textarea" name="conteudo" rows="14"
                    required><?= escaparRegulamentoAdmin($editando['conteudo']) ?></textarea>
            </div>

            <p class="help">
                Você pode utilizar listas e quebras de linha. O texto será
                exibido na página pública preservando a formatação.
            </p>
        </div>

        <div class="field">
            <label class="label">Ordem de exibição</label>

            <div class="control">
                <input class="input" type="number" name="ordem" min="0" required
                    value="<?= escaparRegulamentoAdmin($editando['ordem']) ?>">
            </div>
        </div>

        <div class="field">
            <label class="checkbox">
                <input type="checkbox" name="ativo" value="1" <?= (int) $editando['ativo'] === 1 ? 'checked' : '' ?>>

                Seção publicada no regulamento público
            </label>
        </div>

        <div class="buttons">
            <button type="submit" class="button is-success">
                Salvar alterações
            </button>

            <?php if (!empty($editando['id'])): ?>
                <a href="regulamento.php" class="button is-light">
                    Cancelar edição
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="box">
    <h2 class="title is-5">Seções cadastradas</h2>

    <?php if (empty($secoes)): ?>
        <p class="has-text-grey">
            Nenhuma seção cadastrada.
        </p>
    <?php else: ?>

        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Ordem</th>
                        <th>Seção</th>
                        <th>Status</th>
                        <th>Última atualização</th>
                        <th class="has-text-centered">Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($secoes as $secao): ?>
                        <tr>
                            <td>
                                <?= (int) $secao['ordem'] ?>
                            </td>

                            <td>
                                <strong>
                                    <?= escaparRegulamentoAdmin($secao['titulo']) ?>
                                </strong>
                            </td>

                            <td>
                                <?php if ((int) $secao['ativo'] === 1): ?>
                                    <span class="tag is-success is-light">
                                        Publicada
                                    </span>
                                <?php else: ?>
                                    <span class="tag is-warning is-light">
                                        Oculta
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= escaparRegulamentoAdmin(
                                    date(
                                        'd/m/Y H:i',
                                        strtotime($secao['atualizado_em'])
                                    )
                                ) ?>
                            </td>

                            <td>
                                <div class="is-flex is-justify-content-center" style="gap: .5rem;">

                                    <a href="regulamento.php?editar=<?= (int) $secao['id'] ?>"
                                        class="button is-small is-link is-light">
                                        Editar
                                    </a>

                                    <form method="POST" action=""
                                        onsubmit="return confirm('Tem certeza de que deseja excluir esta seção?');">

                                        <input type="hidden" name="token_csrf"
                                            value="<?= escaparRegulamentoAdmin(gerarTokenCsrf()) ?>">

                                        <input type="hidden" name="acao" value="excluir">

                                        <input type="hidden" name="id" value="<?= (int) $secao['id'] ?>">

                                        <button type="submit" class="button is-small is-danger is-light">
                                            Excluir
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

<div class="buttons mt-5">
    <a href="index.php" class="button is-light">
        Voltar ao painel
    </a>
</div>

<?php
require_once __DIR__ . '/../../includes/admin/footer.php';
?>