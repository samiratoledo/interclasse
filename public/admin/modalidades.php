<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['csrf_modalidades'])) {
    $_SESSION['csrf_modalidades'] = bin2hex(random_bytes(32));
}

$tokenCsrf = $_SESSION['csrf_modalidades'];
$erros = [];
$mensagem = '';

$categorias = [
    'masculino' => 'Masculino',
    'feminino' => 'Feminino',
    'misto' => 'Misto'
];

function escaparModalidade(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function validarTokenModalidades(): bool
{
    return isset($_POST['token_csrf'], $_SESSION['csrf_modalidades'])
        && is_string($_POST['token_csrf'])
        && hash_equals($_SESSION['csrf_modalidades'], $_POST['token_csrf']);
}

function buscarModalidade(PDO $pdo, int $id): ?array
{
    $sql = 'SELECT id, nome, descricao, icone, categoria,
                   minimo_atletas, maximo_atletas, ativa
            FROM modalidades
            WHERE id = :id
            LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);

    $modalidade = $stmt->fetch(PDO::FETCH_ASSOC);

    return $modalidade ?: null;
}

$editando = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validarTokenModalidades()) {
        $erros[] = 'Sessão de segurança inválida. Atualize a página e tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';

        if ($acao === 'salvar') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $id = $id !== false && $id !== null ? (int) $id : 0;

            $nome = trim((string) ($_POST['nome'] ?? ''));
            $descricao = trim((string) ($_POST['descricao'] ?? ''));
            $icone = trim((string) ($_POST['icone'] ?? ''));
            $categoria = (string) ($_POST['categoria'] ?? 'misto');

            $minimo = filter_input(INPUT_POST, 'minimo_atletas', FILTER_VALIDATE_INT);
            $maximo = filter_input(INPUT_POST, 'maximo_atletas', FILTER_VALIDATE_INT);

            $minimo = $minimo !== false && $minimo !== null ? (int) $minimo : 0;
            $maximo = $maximo !== false && $maximo !== null ? (int) $maximo : 0;

            if ($nome === '') {
                $erros[] = 'Informe o nome da modalidade.';
            } elseif (mb_strlen($nome) > 100) {
                $erros[] = 'O nome deve ter no máximo 100 caracteres.';
            }

            if (!array_key_exists($categoria, $categorias)) {
                $erros[] = 'Selecione uma categoria válida.';
            }

            if ($minimo < 1) {
                $erros[] = 'O mínimo de atletas deve ser pelo menos 1.';
            }

            if ($maximo < 1 || $maximo > 65535) {
                $erros[] = 'Informe um máximo de atletas válido.';
            }

            if ($minimo > $maximo) {
                $erros[] = 'O mínimo de atletas não pode ser maior que o máximo.';
            }

            if (mb_strlen($descricao) > 5000) {
                $erros[] = 'A descrição deve ter no máximo 5.000 caracteres.';
            }

            if (mb_strlen($icone) > 100) {
                $erros[] = 'O campo de ícone deve ter no máximo 100 caracteres.';
            }

            if ($id > 0 && !buscarModalidade($pdo, $id)) {
                $erros[] = 'A modalidade selecionada não foi encontrada.';
            }

            if (!$erros) {
                try {
                    if ($id > 0) {
                        $sql = 'UPDATE modalidades
                                SET nome = :nome,
                                    descricao = :descricao,
                                    icone = :icone,
                                    categoria = :categoria,
                                    minimo_atletas = :minimo,
                                    maximo_atletas = :maximo
                                WHERE id = :id';

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            'nome' => $nome,
                            'descricao' => $descricao !== '' ? $descricao : null,
                            'icone' => $icone !== '' ? $icone : null,
                            'categoria' => $categoria,
                            'minimo' => $minimo,
                            'maximo' => $maximo,
                            'id' => $id
                        ]);

                        header('Location: modalidades.php?sucesso=editada');
                        exit;
                    }

                    $sql = 'INSERT INTO modalidades
                                (nome, descricao, icone, categoria,
                                 minimo_atletas, maximo_atletas, ativa)
                            VALUES
                                (:nome, :descricao, :icone, :categoria,
                                 :minimo, :maximo, 1)';

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'nome' => $nome,
                        'descricao' => $descricao !== '' ? $descricao : null,
                        'icone' => $icone !== '' ? $icone : null,
                        'categoria' => $categoria,
                        'minimo' => $minimo,
                        'maximo' => $maximo
                    ]);

                    header('Location: modalidades.php?sucesso=cadastrada');
                    exit;
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        $erros[] = 'Já existe uma modalidade com esse nome.';
                    } else {
                        error_log($e->getMessage());
                        $erros[] = 'Não foi possível salvar a modalidade. Verifique os dados e tente novamente.';
                    }
                }
            }

            $editando = [
                'id' => $id,
                'nome' => $nome,
                'descricao' => $descricao,
                'icone' => $icone,
                'categoria' => $categoria,
                'minimo_atletas' => $minimo,
                'maximo_atletas' => $maximo,
                'ativa' => 1
            ];
        } elseif ($acao === 'alternar_status') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $id = $id !== false && $id !== null ? (int) $id : 0;

            $modalidade = $id > 0 ? buscarModalidade($pdo, $id) : null;

            if (!$modalidade) {
                $erros[] = 'Modalidade não encontrada.';
            } else {
                $novoStatus = (int) $modalidade['ativa'] === 1 ? 0 : 1;

                $stmt = $pdo->prepare(
                    'UPDATE modalidades SET ativa = :ativa WHERE id = :id'
                );

                $stmt->execute([
                    'ativa' => $novoStatus,
                    'id' => $id
                ]);

                header('Location: modalidades.php?sucesso=status');
                exit;
            }
        } elseif ($acao === 'editar') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $id = $id !== false && $id !== null ? (int) $id : 0;

            $editando = $id > 0 ? buscarModalidade($pdo, $id) : null;

            if (!$editando) {
                $erros[] = 'Modalidade não encontrada.';
            }
        } else {
            $erros[] = 'Ação inválida.';
        }
    }
}

if (isset($_GET['sucesso'])) {
    $mensagem = match ($_GET['sucesso']) {
        'cadastrada' => 'Modalidade cadastrada com sucesso!',
        'editada' => 'Modalidade atualizada com sucesso!',
        'status' => 'Status da modalidade atualizado!',
        default => ''
    };
}

$stmt = $pdo->query(
    'SELECT id, nome, descricao, icone, categoria,
            minimo_atletas, maximo_atletas, ativa, criado_em
     FROM modalidades
     ORDER BY nome ASC'
);

$modalidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

$valoresFormulario = $editando ?? [
    'id' => '',
    'nome' => '',
    'descricao' => '',
    'icone' => '',
    'categoria' => 'misto',
    'minimo_atletas' => 1,
    'maximo_atletas' => 30,
    'ativa' => 1
];

require_once __DIR__ . '/../../includes/admin/header.php';
?>

<div class="mb-5">
    <h1 class="title is-3">Gerenciar modalidades</h1>
    <p class="subtitle is-6">
        Cadastre esportes, defina a categoria e configure os limites de atletas.
    </p>
</div>

<?php if ($mensagem !== ''): ?>
    <div class="notification is-success is-light">
        <button class="delete" type="button" onclick="this.parentElement.remove()"></button>
        <?= escaparModalidade($mensagem) ?>
    </div>
<?php endif; ?>

<?php if ($erros): ?>
    <div class="notification is-danger is-light">
        <strong>Confira os seguintes pontos:</strong>
        <ul class="mt-2">
            <?php foreach ($erros as $erro): ?>
                <li><?= escaparModalidade($erro) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="box mb-5">
    <h2 class="title is-5">
        <?= !empty($valoresFormulario['id']) ? 'Editar modalidade' : 'Cadastrar modalidade' ?>
    </h2>

    <form method="POST" action="modalidades.php">
        <input type="hidden" name="token_csrf" value="<?= escaparModalidade($tokenCsrf) ?>">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= escaparModalidade($valoresFormulario['id'] ?? '') ?>">

        <div class="columns is-multiline">
            <div class="column is-6">
                <label class="label" for="nome">Nome da modalidade *</label>
                <div class="control">
                    <input class="input" type="text" id="nome" name="nome"
                           maxlength="100" required
                           value="<?= escaparModalidade($valoresFormulario['nome'] ?? '') ?>"
                           placeholder="Ex.: Futsal">
                </div>
            </div>

            <div class="column is-3">
                <label class="label" for="categoria">Categoria *</label>
                <div class="control">
                    <div class="select is-fullwidth">
                        <select id="categoria" name="categoria" required>
                            <?php foreach ($categorias as $valor => $rotulo): ?>
                                <option value="<?= escaparModalidade($valor) ?>"
                                    <?= ($valoresFormulario['categoria'] ?? 'misto') === $valor ? 'selected' : '' ?>>
                                    <?= escaparModalidade($rotulo) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="column is-3">
                <label class="label" for="icone">Ícone (opcional)</label>
                <div class="control">
                    <input class="input" type="text" id="icone" name="icone"
                           maxlength="100"
                           value="<?= escaparModalidade($valoresFormulario['icone'] ?? '') ?>"
                           placeholder="Ex.: ⚽">
                </div>
            </div>

            <div class="column is-3">
                <label class="label" for="minimo_atletas">Mínimo de atletas *</label>
                <div class="control">
                    <input class="input" type="number" id="minimo_atletas" name="minimo_atletas"
                           min="1" max="65535" required
                           value="<?= escaparModalidade($valoresFormulario['minimo_atletas'] ?? 1) ?>">
                </div>
            </div>

            <div class="column is-3">
                <label class="label" for="maximo_atletas">Máximo de atletas *</label>
                <div class="control">
                    <input class="input" type="number" id="maximo_atletas" name="maximo_atletas"
                           min="1" max="65535" required
                           value="<?= escaparModalidade($valoresFormulario['maximo_atletas'] ?? 30) ?>">
                </div>
            </div>

            <div class="column is-6">
                <label class="label" for="descricao">Descrição</label>
                <div class="control">
                    <textarea class="textarea" id="descricao" name="descricao"
                              rows="2" maxlength="5000"
                              placeholder="Informações adicionais sobre a modalidade"><?= escaparModalidade($valoresFormulario['descricao'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="buttons mt-4">
            <button type="submit" class="button is-link">
                <?= !empty($valoresFormulario['id']) ? 'Salvar alterações' : 'Cadastrar modalidade' ?>
            </button>

            <?php if (!empty($valoresFormulario['id'])): ?>
                <a href="modalidades.php" class="button is-light">Cancelar edição</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="box">
    <h2 class="title is-5">Modalidades cadastradas (<?= count($modalidades) ?>)</h2>

    <?php if (!$modalidades): ?>
        <div class="has-text-centered has-text-grey p-5">
            Nenhuma modalidade cadastrada ainda.
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Modalidade</th>
                        <th>Categoria</th>
                        <th>Limite de atletas</th>
                        <th>Status</th>
                        <th class="has-text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($modalidades as $modalidade): ?>
                        <tr>
                            <td>
                                <div class="is-flex is-align-items-center" style="gap: 10px;">
                                    <?php if (!empty($modalidade['icone'])): ?>
                                        <span style="font-size: 1.5rem;">
                                            <?= escaparModalidade($modalidade['icone']) ?>
                                        </span>
                                    <?php endif; ?>

                                    <div>
                                        <div class="has-text-weight-semibold">
                                            <?= escaparModalidade($modalidade['nome']) ?>
                                        </div>

                                        <?php if (!empty($modalidade['descricao'])): ?>
                                            <div class="is-size-7 has-text-grey" style="min-width: 180px; max-width: 300px;">
                                                <?= escaparModalidade($modalidade['descricao']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?= escaparModalidade($categorias[$modalidade['categoria']] ?? 'Misto') ?>
                            </td>

                            <td>
                                <?= (int) $modalidade['minimo_atletas'] ?>
                                a
                                <?= (int) $modalidade['maximo_atletas'] ?>
                            </td>

                            <td>
                                <?php if ((int) $modalidade['ativa'] === 1): ?>
                                    <span class="tag is-success is-light">Ativa</span>
                                <?php else: ?>
                                    <span class="tag is-light">Inativa</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="is-flex is-justify-content-flex-end is-align-items-center" style="gap: 8px;">
                                    <form method="POST" action="modalidades.php">
                                        <input type="hidden" name="token_csrf" value="<?= escaparModalidade($tokenCsrf) ?>">
                                        <input type="hidden" name="acao" value="editar">
                                        <input type="hidden" name="id" value="<?= (int) $modalidade['id'] ?>">
                                        <button type="submit" class="button is-small is-link is-light">Editar</button>
                                    </form>

                                    <form method="POST" action="modalidades.php"
                                          onsubmit="return confirm('Deseja realmente <?= (int) $modalidade['ativa'] === 1 ? 'desativar' : 'ativar' ?> esta modalidade?');">
                                        <input type="hidden" name="token_csrf" value="<?= escaparModalidade($tokenCsrf) ?>">
                                        <input type="hidden" name="acao" value="alternar_status">
                                        <input type="hidden" name="id" value="<?= (int) $modalidade['id'] ?>">
                                        <button type="submit" class="button is-small <?= (int) $modalidade['ativa'] === 1 ? 'is-danger is-light' : 'is-success is-light' ?>">
                                            <?= (int) $modalidade['ativa'] === 1 ? 'Desativar' : 'Ativar' ?>
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

<p class="is-size-7 has-text-grey mt-4">
    Modalidades inativas deixam de aparecer para novas inscrições. O histórico das inscrições existentes é preservado.
</p>

<?php require_once __DIR__ . '/../../includes/admin/footer.php'; ?>