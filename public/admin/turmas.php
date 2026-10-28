<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin');

const TAMANHO_MAXIMO_IMAGEM = 2 * 1024 * 1024;

$diretorioImagens = __DIR__ . '/../assets/turmas/';
$caminhoPublicoImagens = '../assets/turmas/';

if (!is_dir($diretorioImagens)) {
    if (!mkdir($diretorioImagens, 0755, true) && !is_dir($diretorioImagens)) {
        exit('Não foi possível preparar a pasta de imagens.');
    }
}

$erro = '';
$sucesso = '';

$periodos = [
    'manha' => 'Manhã',
    'tarde' => 'Tarde',
];

function escapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function processarImagem(array $arquivo, string $diretorio): ?string
{
    if (!isset($arquivo['error']) || $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível enviar a imagem.');
    }

    if ($arquivo['size'] > TAMANHO_MAXIMO_IMAGEM) {
        throw new RuntimeException('A imagem deve ter no máximo 2 MB.');
    }

    if (!is_uploaded_file($arquivo['tmp_name'])) {
        throw new RuntimeException('Arquivo enviado inválido.');
    }

    $informacoes = new finfo(FILEINFO_MIME_TYPE);
    $mime = $informacoes->file($arquivo['tmp_name']);

    $extensoesPermitidas = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensoesPermitidas[$mime])) {
        throw new RuntimeException('Envie uma imagem JPG, PNG ou WEBP.');
    }

    $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensoesPermitidas[$mime];

    if (!move_uploaded_file($arquivo['tmp_name'], $diretorio . $nomeArquivo)) {
        throw new RuntimeException('Não foi possível salvar a imagem.');
    }

    return 'assets/turmas/' . $nomeArquivo;
}

function imagemAntigaValida(?string $caminho): ?string
{
    if (
        $caminho !== null
        && preg_match('~\Aassets/turmas/[a-f0-9]{32}\.(?:jpg|png|webp)\z~', $caminho)
    ) {
        return basename($caminho);
    }

    return null;
}

$turmaEdicao = null;
$turmas = [];
$contagem = [
    'total' => 0,
    'ativas' => 0,
    'inativas' => 0,
];

$filtroPeriodo = 'todos';
$filtroStatus = 'ativas';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validarTokenCsrf($_POST['token_csrf'] ?? null)) {
            throw new RuntimeException('Formulário expirado ou inválido. Atualize a página.');
        }

        $acao = $_POST['acao'] ?? '';

        if ($acao === 'salvar') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $id = $id !== false && $id > 0 ? $id : null;

            $nome = trim((string) ($_POST['nome'] ?? ''));
            $periodo = (string) ($_POST['periodo'] ?? '');

            if ($nome === '' || mb_strlen($nome, 'UTF-8') > 30) {
                throw new RuntimeException('Informe um nome com até 30 caracteres.');
            }

            if (!array_key_exists($periodo, $periodos)) {
                throw new RuntimeException('Selecione um período válido.');
            }

            $imagemAtual = null;

            if ($id !== null) {
                $consulta = $pdo->prepare('SELECT imagem FROM turmas WHERE id = :id');
                $consulta->execute(['id' => $id]);
                $existente = $consulta->fetch(PDO::FETCH_ASSOC);

                if (!$existente) {
                    throw new RuntimeException('A turma selecionada não existe.');
                }

                $imagemAtual = $existente['imagem'];
            }

            $novaImagem = processarImagem($_FILES['imagem'] ?? [], $diretorioImagens);
            $imagem = $novaImagem ?? $imagemAtual;

            if ($id === null) {
                $stmt = $pdo->prepare(
                    'INSERT INTO turmas (nome, periodo, imagem)
                     VALUES (:nome, :periodo, :imagem)'
                );

                $stmt->execute([
                    'nome' => $nome,
                    'periodo' => $periodo,
                    'imagem' => $imagem,
                ]);

                header('Location: turmas.php?sucesso=cadastrada');
                exit;
            }

            $stmt = $pdo->prepare(
                'UPDATE turmas
                 SET nome = :nome, periodo = :periodo, imagem = :imagem
                 WHERE id = :id'
            );

            $stmt->execute([
                'nome' => $nome,
                'periodo' => $periodo,
                'imagem' => $imagem,
                'id' => $id,
            ]);

            header('Location: turmas.php?sucesso=atualizada');
            exit;
        }

        if ($acao === 'alternar_status') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

            if ($id === false || $id <= 0) {
                throw new RuntimeException('Turma inválida.');
            }

            $stmt = $pdo->prepare(
                'UPDATE turmas SET ativa = NOT ativa WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                $verificar = $pdo->prepare('SELECT id FROM turmas WHERE id = :id');
                $verificar->execute(['id' => $id]);

                if (!$verificar->fetch()) {
                    throw new RuntimeException('A turma selecionada não existe.');
                }
            }

            header('Location: turmas.php?sucesso=status');
            exit;
        }

        throw new RuntimeException('Ação não reconhecida.');
    }

    $mensagens = [
        'cadastrada' => 'Turma cadastrada com sucesso!',
        'atualizada' => 'Turma atualizada com sucesso!',
        'status' => 'Status da turma atualizado!',
    ];

    $sucesso = $mensagens[$_GET['sucesso'] ?? ''] ?? '';

    if (isset($_GET['editar'])) {
        $idEdicao = filter_var($_GET['editar'], FILTER_VALIDATE_INT);

        if ($idEdicao === false || $idEdicao <= 0) {
            throw new RuntimeException('Identificador inválido.');
        }

        $stmt = $pdo->prepare(
            'SELECT id, nome, periodo, imagem, ativa
             FROM turmas WHERE id = :id'
        );
        $stmt->execute(['id' => $idEdicao]);
        $turmaEdicao = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$turmaEdicao) {
            throw new RuntimeException('Turma não encontrada.');
        }
    }

    $filtroPeriodo = (string) ($_GET['periodo'] ?? 'todos');
    $filtroStatus = (string) ($_GET['status'] ?? 'ativas');

    if (
        !in_array($filtroPeriodo, ['todos', 'manha', 'tarde'], true)
        || !in_array($filtroStatus, ['todas', 'ativas', 'inativas'], true)
    ) {
        throw new RuntimeException('Filtro inválido.');
    }

    $condicoes = [];
    $parametros = [];

    if ($filtroPeriodo !== 'todos') {
        $condicoes[] = 'periodo = :periodo';
        $parametros['periodo'] = $filtroPeriodo;
    }

    if ($filtroStatus === 'ativas') {
        $condicoes[] = 'ativa = 1';
    } elseif ($filtroStatus === 'inativas') {
        $condicoes[] = 'ativa = 0';
    }

    $sql = 'SELECT id, nome, periodo, imagem, ativa, criado_em FROM turmas';

    if ($condicoes !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $condicoes);
    }

    $sql .= " ORDER BY CASE periodo
                WHEN 'manha' THEN 1
                WHEN 'tarde' THEN 2
              END, nome";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
    $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $contagem = $pdo->query(
        'SELECT COUNT(*) AS total,
                COALESCE(SUM(ativa = 1), 0) AS ativas,
                COALESCE(SUM(ativa = 0), 0) AS inativas
         FROM turmas'
    )->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível concluir a operação. Verifique se o nome da turma já está cadastrado.';
} catch (RuntimeException $e) {
    $erro = $e->getMessage();
}

$nomeFormulario = $turmaEdicao['nome'] ?? '';
$periodoFormulario = $turmaEdicao['periodo'] ?? 'manha';
$imagemFormulario = $turmaEdicao['imagem'] ?? null;
$idFormulario = $turmaEdicao['id'] ?? '';

require_once __DIR__ . '/../../includes/admin/header.php';
?>

<div class="mb-5">
    <h1 class="title is-3">Gerenciar turmas</h1>
    <p class="subtitle is-6">
        Cadastre turmas, organize períodos e gerencie brasões.
    </p>
</div>

<?php if ($erro !== ''): ?>
    <div class="notification is-danger is-light">
        <?= escapar($erro) ?>
    </div>
<?php endif; ?>

<?php if ($sucesso !== ''): ?>
    <div class="notification is-success is-light">
        <?= escapar($sucesso) ?>
    </div>
<?php endif; ?>

<div class="columns">
    <div class="column">
        <div class="box">
            <p class="heading">Total de turmas</p>
            <p class="title"><?= escapar($contagem['total']) ?></p>
        </div>
    </div>

    <div class="column">
        <div class="box">
            <p class="heading">Ativas</p>
            <p class="title has-text-success"><?= escapar($contagem['ativas']) ?></p>
        </div>
    </div>

    <div class="column">
        <div class="box">
            <p class="heading">Inativas</p>
            <p class="title has-text-grey"><?= escapar($contagem['inativas']) ?></p>
        </div>
    </div>
</div>

<div class="box">
    <h2 class="title is-4">
        <?= $turmaEdicao ? 'Editar turma' : 'Cadastrar turma' ?>
    </h2>

    <form method="POST" action="turmas.php" enctype="multipart/form-data">
        <input type="hidden" name="token_csrf" value="<?= escapar(gerarTokenCsrf()) ?>">
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= escapar($idFormulario) ?>">

        <div class="columns is-multiline">
            <div class="column is-half">
                <label class="label" for="nome">Nome da turma</label>
                <input class="input" type="text" id="nome" name="nome"
                       maxlength="30" placeholder="Ex.: 1º ADM-A"
                       value="<?= escapar($nomeFormulario) ?>" required>
            </div>

            <div class="column is-half">
                <label class="label" for="periodo">Período</label>
                <div class="select is-fullwidth">
                    <select id="periodo" name="periodo" required>
                        <?php foreach ($periodos as $valor => $rotulo): ?>
                            <option value="<?= escapar($valor) ?>"
                                <?= $periodoFormulario === $valor ? 'selected' : '' ?>>
                                <?= escapar($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="field">
            <label class="label" for="imagem">Brasão da turma</label>

            <?php if ($imagemFormulario): ?>
                <div class="mb-3">
                    <img
                        src="<?= escapar($caminhoPublicoImagens . basename($imagemFormulario)) ?>"
                        alt="Brasão atual da turma"
                        style="max-width:120px;max-height:120px;object-fit:contain;"
                    >
                    <p class="help">Se não escolher outra imagem, o brasão atual será mantido.</p>
                </div>
            <?php endif; ?>

            <input class="input" type="file" id="imagem" name="imagem"
                   accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
            <p class="help">Formatos permitidos: JPG, PNG e WEBP. Máximo: 2 MB.</p>
        </div>

        <div class="buttons mt-5">
            <button class="button is-link" type="submit">
                <?= $turmaEdicao ? 'Salvar alterações' : 'Cadastrar turma' ?>
            </button>

            <?php if ($turmaEdicao): ?>
                <a class="button is-light" href="turmas.php">Cancelar edição</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="box">
    <h2 class="title is-4">Turmas cadastradas</h2>

    <form method="GET" action="turmas.php" class="mb-5">
        <div class="columns is-align-items-flex-end">
            <div class="column">
                <label class="label" for="filtro-periodo">Período</label>
                <div class="select is-fullwidth">
                    <select id="filtro-periodo" name="periodo">
                        <option value="todos" <?= $filtroPeriodo === 'todos' ? 'selected' : '' ?>>Todos os períodos</option>
                        <option value="manha" <?= $filtroPeriodo === 'manha' ? 'selected' : '' ?>>Manhã</option>
                        <option value="tarde" <?= $filtroPeriodo === 'tarde' ? 'selected' : '' ?>>Tarde</option>
                    </select>
                </div>
            </div>

            <div class="column">
                <label class="label" for="filtro-status">Situação</label>
                <div class="select is-fullwidth">
                    <select id="filtro-status" name="status">
                        <option value="ativas" <?= $filtroStatus === 'ativas' ? 'selected' : '' ?>>Somente ativas</option>
                        <option value="inativas" <?= $filtroStatus === 'inativas' ? 'selected' : '' ?>>Somente inativas</option>
                        <option value="todas" <?= $filtroStatus === 'todas' ? 'selected' : '' ?>>Todas</option>
                    </select>
                </div>
            </div>

            <div class="column is-narrow">
                <button class="button is-link" type="submit">Filtrar</button>
            </div>
        </div>
    </form>

    <?php if ($turmas === []): ?>
        <div class="notification is-light">
            Nenhuma turma encontrada para esses filtros.
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Brasão</th>
                        <th>Turma</th>
                        <th>Período</th>
                        <th>Situação</th>
                        <th class="has-text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($turmas as $turma): ?>
                        <tr>
                            <td>
                                <?php if ($turma['imagem']): ?>
                                    <img
                                        src="<?= escapar($caminhoPublicoImagens . basename($turma['imagem'])) ?>"
                                        alt="Brasão de <?= escapar($turma['nome']) ?>"
                                        loading="lazy"
                                        style="width:48px;height:48px;object-fit:contain;"
                                    >
                                <?php else: ?>
                                    <span class="tag is-light">Sem brasão</span>
                                <?php endif; ?>
                            </td>

                            <td><strong><?= escapar($turma['nome']) ?></strong></td>

                            <td><?= escapar($periodos[$turma['periodo']] ?? 'Não definido') ?></td>

                            <td>
                                <?php if ((int) $turma['ativa'] === 1): ?>
                                    <span class="tag is-success is-light">Ativa</span>
                                <?php else: ?>
                                    <span class="tag is-light">Inativa</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="is-flex is-justify-content-flex-end is-align-items-center" style="gap:8px;">
                                    <a class="button is-small is-link is-light"
                                       href="turmas.php?editar=<?= (int) $turma['id'] ?>">
                                        Editar
                                    </a>

                                    <form method="POST" action="turmas.php"
                                          onsubmit="return confirm('Deseja alterar a situação desta turma?');">
                                        <input type="hidden" name="token_csrf" value="<?= escapar(gerarTokenCsrf()) ?>">
                                        <input type="hidden" name="acao" value="alternar_status">
                                        <input type="hidden" name="id" value="<?= (int) $turma['id'] ?>">

                                        <button class="button is-small <?= (int) $turma['ativa'] === 1 ? 'is-danger is-light' : 'is-success is-light' ?>"
                                                type="submit">
                                            <?= (int) $turma['ativa'] === 1 ? 'Desativar' : 'Ativar' ?>
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

<?php require_once __DIR__ . '/../../includes/admin/footer.php'; ?>
