<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin');

$erro = '';
$sucesso = '';
$senhaGerada = '';
$nomeCriado = '';
$turmaCriada = '';
$usuarioCriado = '';
$linkSistema = 'http://localhost/interclasse-etec/public/';

$turmas = [];
$representantes = [];
$totalRepresentantes = 0;
$representantesAtivos = 0;
$representantesInativos = 0;

function escaparRepresentantes(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function gerarSenhaRepresentante(): string
{
    $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $senha = '';

    for ($i = 0; $i < 8; $i++) {
        $senha .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }

    return 'Etec-' . substr($senha, 0, 4) . '-' . substr($senha, 4, 4);
}

function gerarUsuarioRepresentante(string $nomeTurma): string
{
    return strtolower(
        preg_replace('/[^a-zA-Z0-9]/', '', $nomeTurma)
    );
}

function redirecionarRepresentantes(string $mensagem): never
{
    header('Location: representantes.php?sucesso=' . urlencode($mensagem));
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validarTokenCsrf(
            isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
                ? $_POST['csrf_token']
                : null
        )) {
            throw new RuntimeException(
                'Sessão expirada ou formulário inválido. Atualize a página.'
            );
        }

        $acao = $_POST['acao'] ?? 'cadastrar';

        if ($acao === 'cadastrar') {
            // Recebe os dados do formulário.
            $nome = trim((string) ($_POST['nome'] ?? ''));

            $turmaId = filter_var(
                $_POST['turma_id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if ($nome === '' || mb_strlen($nome, 'UTF-8') > 120) {
                throw new RuntimeException(
                    'Informe um nome válido, com até 120 caracteres.'
                );
            }

            if (!$turmaId || $turmaId <= 0) {
                throw new RuntimeException('Selecione uma turma válida.');
            }

            // Inicia a transação.
            $pdo->beginTransaction();

            // Busca a turma e verifica se já possui representante.
            $consulta = $pdo->prepare("
                SELECT t.nome
                FROM turmas t
                WHERE t.id = :turma_id
                  AND t.ativa = 1
                  AND NOT EXISTS (
                      SELECT 1
                      FROM usuarios u
                      WHERE u.turma_id = t.id
                        AND u.perfil = 'representante'
                  )
                FOR UPDATE
            ");

            $consulta->execute(['turma_id' => $turmaId]);

            $turma = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$turma) {
                throw new RuntimeException(
                    'Essa turma está indisponível ou já possui representante.'
                );
            }

            // Gera o usuário a partir do nome da turma.
            // Exemplo: 1ADM-A gera 1adma.
            $usuarioLogin = gerarUsuarioRepresentante($turma['nome']);

            if ($usuarioLogin === '') {
                throw new RuntimeException(
                    'Não foi possível gerar o usuário a partir do nome da turma.'
                );
            }

            if (strlen($usuarioLogin) > 50) {
                throw new RuntimeException(
                    'O usuário gerado ultrapassa o limite permitido.'
                );
            }

            // Gera e protege a senha.
            $senhaGerada = gerarSenhaRepresentante();
            $senhaHash = password_hash($senhaGerada, PASSWORD_DEFAULT);

            if ($senhaHash === false) {
                throw new RuntimeException(
                    'Não foi possível gerar a senha.'
                );
            }

            // Insere o representante.
            $inserir = $pdo->prepare("
                INSERT INTO usuarios
                    (nome, usuario_login, senha_hash, perfil, turma_id, ativo)
                VALUES
                    (:nome, :usuario_login, :senha_hash, 'representante', :turma_id, 1)
            ");

            $inserir->execute([
                'nome' => $nome,
                'usuario_login' => $usuarioLogin,
                'senha_hash' => $senhaHash,
                'turma_id' => $turmaId,
            ]);

            // Confirma o cadastro.
            $pdo->commit();

            $nomeCriado = $nome;
            $turmaCriada = $turma['nome'];
            $usuarioCriado = $usuarioLogin;

            $sucesso = 'Representante cadastrado com sucesso!';

        } elseif ($acao === 'alternar_status') {
            $id = filter_var(
                $_POST['id'] ?? '',
                FILTER_VALIDATE_INT
            );

            if (!$id || $id <= 0) {
                throw new RuntimeException('Representante inválido.');
            }

            $consulta = $pdo->prepare("
                SELECT id, ativo
                FROM usuarios
                WHERE id = :id
                  AND perfil = 'representante'
            ");

            $consulta->execute(['id' => $id]);

            $representante = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$representante) {
                throw new RuntimeException(
                    'Representante não encontrado.'
                );
            }

            $novoStatus = (int) $representante['ativo'] === 1 ? 0 : 1;

            $atualizar = $pdo->prepare("
                UPDATE usuarios
                SET ativo = :ativo
                WHERE id = :id
                  AND perfil = 'representante'
            ");

            $atualizar->execute([
                'ativo' => $novoStatus,
                'id' => $id,
            ]);

            redirecionarRepresentantes('status');

        } else {
            throw new RuntimeException('Ação não reconhecida.');
        }
    }

    // Mensagens após redirecionamento.
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $mensagens = [
            'cadastrado' => 'Representante cadastrado com sucesso!',
            'status' => 'Status do representante atualizado!',
        ];

        $sucesso = $mensagens[$_GET['sucesso'] ?? ''] ?? '';
    }

    // Busca as turmas disponíveis para cadastro.
    $stmt = $pdo->query("
        SELECT t.id, t.nome, t.periodo
        FROM turmas t
        WHERE t.ativa = 1
          AND NOT EXISTS (
              SELECT 1
              FROM usuarios u
              WHERE u.turma_id = t.id
                AND u.perfil = 'representante'
          )
        ORDER BY
            CASE t.periodo
                WHEN 'manha' THEN 1
                WHEN 'tarde' THEN 2
                ELSE 3
            END,
            t.nome
    ");

    $turmas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Lista os representantes e contabiliza os atletas por turma.
    $stmt = $pdo->query("
        SELECT
            u.id,
            u.nome,
            u.ativo,
            u.turma_id,
            u.criado_em,
            t.nome AS nome_turma,
            t.periodo,
            COUNT(DISTINCT a.id) AS total_atletas
        FROM usuarios u
        LEFT JOIN turmas t
            ON t.id = u.turma_id
        LEFT JOIN atletas a
            ON a.turma_id = u.turma_id
        WHERE u.perfil = 'representante'
        GROUP BY
            u.id,
            u.nome,
            u.ativo,
            u.turma_id,
            u.criado_em,
            t.nome,
            t.periodo
        ORDER BY t.nome, u.nome
    ");

    $representantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($representantes as $representante) {
        if ((int) $representante['ativo'] === 1) {
            $representantesAtivos++;
        } else {
            $representantesInativos++;
        }
    }

    $totalRepresentantes = count($representantes);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    $erro = 'Não foi possível concluir a operação. Verifique a estrutura das tabelas usuarios, turmas e atletas.';

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $erro = $e->getMessage();
}

require_once __DIR__ . '/../../includes/admin/header.php';
?>

<div class="mb-5">
    <h1 class="title is-3">Gerenciar representantes</h1>

    <p class="subtitle is-6">
        Cadastre representantes, acompanhe os atletas das turmas e gerencie os acessos.
    </p>
</div>

<?php if ($erro !== ''): ?>
    <div class="notification is-danger is-dark">
        <?= escaparRepresentantes($erro) ?>
    </div>
<?php endif; ?>

<?php if ($sucesso !== ''): ?>
    <div class="notification is-success is-dark">
        <h2 class="title is-5">
            <?= escaparRepresentantes($sucesso) ?>
        </h2>

        <?php if ($senhaGerada !== ''): ?>
            <?php
            $mensagemRepresentante =
                "Olá, {$nomeCriado}!\n\n" .
                "Você foi cadastrado(a) como representante da turma " .
                "{$turmaCriada} no Interclasse ETEC.\n\n" .
                "DADOS DE ACESSO\n" .
                "Nome: {$nomeCriado}\n" .
                "Turma: {$turmaCriada}\n" .
                "Usuário: {$usuarioCriado}\n" .
                "Senha: {$senhaGerada}\n\n" .
                "LINK PARA ACESSAR O SISTEMA:\n" .
                "{$linkSistema}\n\n" .
                "Acesse o sistema utilizando seu usuário e senha. Guarde seus dados em segurança!";
            ?>

            <p class="mb-3">
                Copie a mensagem completa e envie ao representante.
            </p>

            <div class="field">
                <label class="label" for="mensagemRepresentante">
                    Mensagem para enviar
                </label>

                <div class="control">
                    <textarea
                        id="mensagemRepresentante"
                        class="textarea"
                        rows="9"
                        readonly
                    ><?= escaparRepresentantes($mensagemRepresentante) ?></textarea>
                </div>
            </div>

            <button
                class="button is-link"
                type="button"
                onclick="copiarMensagemRepresentante()"
            >
                Copiar mensagem completa
            </button>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="box mb-5">
    <p class="heading">Total de representantes</p>
    <p class="title is-3"><?= $totalRepresentantes ?></p>

    <div class="tags mt-3">
        <span class="tag is-success is-dark">
            <?= $representantesAtivos ?> ativos
        </span>

        <span class="tag is-dark">
            <?= $representantesInativos ?> inativos
        </span>
    </div>
</div>

<div class="box mb-5">
    <div class="level mb-4">
        <div class="level-left">
            <div>
                <h2 class="title is-5">Cadastrar representante</h2>
                <p class="subtitle is-6">
                    Cada turma pode ter apenas um representante.
                </p>
            </div>
        </div>
    </div>

    <?php if ($turmas === []): ?>
        <div class="notification is-warning is-dark">
            Todas as turmas ativas já possuem representante ou não há turmas disponíveis.
        </div>
    <?php else: ?>
        <form method="POST" action="representantes.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= escaparRepresentantes(gerarTokenCsrf()) ?>"
            >

            <input type="hidden" name="acao" value="cadastrar">

            <div class="field">
                <label class="label" for="nome">Nome completo</label>

                <div class="control">
                    <input
                        class="input"
                        type="text"
                        id="nome"
                        name="nome"
                        maxlength="120"
                        autocomplete="off"
                        required
                    >
                </div>
            </div>

            <div class="field">
                <label class="label" for="turma_id">Turma</label>

                <div class="control">
                    <div class="select is-fullwidth">
                        <select id="turma_id" name="turma_id" required>
                            <option value="">Selecione uma turma</option>

                            <?php foreach ($turmas as $turma): ?>
                                <option value="<?= (int) $turma['id'] ?>">
                                    <?= escaparRepresentantes($turma['nome']) ?>
                                    — <?= $turma['periodo'] === 'manha' ? 'Manhã' : 'Tarde' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="notification is-info is-light">
                O usuário será gerado automaticamente com base na turma.
                Exemplo: <strong>1ADM-A → 1adma</strong>.

                A senha seguirá o padrão
                <strong>Etec-XXXX-XXXX</strong> e será armazenada de forma protegida.
            </div>

            <div class="field mt-4">
                <button class="button is-link" type="submit">
                    Cadastrar representante
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<div class="box">
    <div class="level mb-4">
        <div class="level-left">
            <div>
                <h2 class="title is-5">Representantes cadastrados</h2>
                <p class="subtitle is-6">
                    Consulte os representantes e gerencie seus acessos.
                </p>
            </div>
        </div>

        <div class="level-right">
            <span class="tag is-info is-dark is-medium">
                <?= $totalRepresentantes ?>
                <?= $totalRepresentantes === 1 ? 'representante' : 'representantes' ?>
            </span>
        </div>
    </div>

    <?php if ($representantes === []): ?>

        <div class="has-text-centered py-6">
            <p class="title is-5">Nenhum representante cadastrado</p>
            <p class="has-text-grey">
                Os representantes cadastrados aparecerão nesta tabela.
            </p>
        </div>

    <?php else: ?>

        <div class="table-container">
            <table class="table is-fullwidth is-striped is-hoverable">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Turma</th>
                        <th>Atletas cadastrados</th>
                        <th>Situação</th>
                        <th class="has-text-centered">Ações</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($representantes as $representante): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= escaparRepresentantes($representante['nome']) ?>
                                </strong>

                                <br>

                                <span class="is-size-7 has-text-grey">
                                    Cadastro:
                                    <?= escaparRepresentantes(
                                        date(
                                            'd/m/Y H:i',
                                            strtotime($representante['criado_em'])
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>
                                <?= escaparRepresentantes(
                                    $representante['nome_turma'] ?? 'Sem turma vinculada'
                                ) ?>

                                <?php if (!empty($representante['periodo'])): ?>
                                    <br>
                                    <span class="is-size-7 has-text-grey">
                                        <?= $representante['periodo'] === 'manha' ? 'Manhã' : 'Tarde' ?>
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= (int) $representante['total_atletas'] ?>
                            </td>

                            <td>
                                <?php if ((int) $representante['ativo'] === 1): ?>
                                    <span class="tag is-success is-dark">
                                        Ativo
                                    </span>
                                <?php else: ?>
                                    <span class="tag is-dark">
                                        Inativo
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="is-flex is-justify-content-center">
                                    <form
                                        method="POST"
                                        action="representantes.php"
                                        onsubmit="return confirm('Deseja realmente <?= (int) $representante['ativo'] === 1 ? 'desativar' : 'reativar' ?> o acesso deste representante?');"
                                    >
                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= escaparRepresentantes(gerarTokenCsrf()) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="acao"
                                            value="alternar_status"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $representante['id'] ?>"
                                        >

                                        <button
                                            class="button is-small <?= (int) $representante['ativo'] === 1 ? 'is-danger is-dark' : 'is-success is-dark' ?>"
                                            type="submit"
                                        >
                                            <?= (int) $representante['ativo'] === 1 ? 'Desativar acesso' : 'Reativar acesso' ?>
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

<script>
async function copiarMensagemRepresentante() {
    const campo = document.getElementById('mensagemRepresentante');

    if (!campo) return;

    try {
        await navigator.clipboard.writeText(campo.value);
        alert('Mensagem copiada!');
    } catch (erro) {
        campo.focus();
        campo.select();
        campo.setSelectionRange(0, campo.value.length);

        const copiado = document.execCommand('copy');

        alert(
            copiado
                ? 'Mensagem copiada!'
                : 'Selecione e copie a mensagem manualmente.'
        );
    }
}
</script>

<?php
require_once __DIR__ . '/../../includes/admin/footer.php';
?>