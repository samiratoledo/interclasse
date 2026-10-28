<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

exigirPerfil('admin');

$erro = '';
$sucesso = '';
$turmasDisponiveis = [];
$representantes = [];
$turmasComRepresentante = [];
$totalRepresentantes = 0;
$representantesAtivos = 0;
$representantesInativos = 0;

function escaparRepresentantes(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function redirecionarRepresentantes(string $mensagem): never
{
    header('Location: representantes.php?sucesso=' . urlencode($mensagem));
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validarTokenCsrf($_POST['token_csrf'] ?? null)) {
            throw new RuntimeException('Formulário expirado ou inválido. Atualize a página.');
        }

        $acao = $_POST['acao'] ?? '';

        if ($acao === 'cadastrar') {
            $usuario = trim((string) ($_POST['usuario'] ?? ''));
            $senha = (string) ($_POST['senha'] ?? '');
            $turmaId = filter_var($_POST['turma_id'] ?? '', FILTER_VALIDATE_INT);

            if ($usuario === '' || mb_strlen($usuario, 'UTF-8') > 50 || !preg_match('/^[a-zA-Z0-9._-]+$/', $usuario)) {
                throw new RuntimeException('O login deve ter até 50 caracteres e conter apenas letras, números, ponto, hífen ou sublinhado.');
            }

            if (strlen($senha) < 8) {
                throw new RuntimeException('A senha inicial deve ter pelo menos 8 caracteres.');
            }

            if ($turmaId === false || $turmaId <= 0) {
                throw new RuntimeException('Selecione uma turma válida.');
            }

            $consultaTurma = $pdo->prepare('SELECT id FROM turmas WHERE id = :id AND ativa = 1');
            $consultaTurma->execute(['id' => $turmaId]);

            if (!$consultaTurma->fetch()) {
                throw new RuntimeException('A turma selecionada não existe ou está inativa.');
            }

            $consultaUsuario = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = :usuario LIMIT 1');
            $consultaUsuario->execute(['usuario' => $usuario]);

            if ($consultaUsuario->fetch()) {
                throw new RuntimeException('Esse login já está cadastrado.');
            }

            $consultaRepresentante = $pdo->prepare("SELECT id, ativo FROM usuarios WHERE turma_id = :turma_id AND perfil = 'representante' LIMIT 1");
            $consultaRepresentante->execute(['turma_id' => $turmaId]);
            $representanteExistente = $consultaRepresentante->fetch(PDO::FETCH_ASSOC);

            if ($representanteExistente) {
                if ((int) $representanteExistente['ativo'] === 0) {
                    throw new RuntimeException('Essa turma já possui um representante desativado. Reative a conta existente.');
                }

                throw new RuntimeException('Essa turma já possui um representante cadastrado.');
            }

            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

            $cadastro = $pdo->prepare("INSERT INTO usuarios (nome, usuario, senha_hash, perfil, turma_id, ativo) VALUES (:nome, :usuario, :senha_hash, 'representante', :turma_id, 1)");
            $cadastro->execute([
                'nome' => $usuario,
                'usuario' => $usuario,
                'senha_hash' => $senhaHash,
                'turma_id' => $turmaId
            ]);

            redirecionarRepresentantes('cadastrado');
        }

        if ($acao === 'alternar_status') {
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

            if ($id === false || $id <= 0) {
                throw new RuntimeException('Representante inválido.');
            }

            $stmt = $pdo->prepare("UPDATE usuarios SET ativo = NOT ativo WHERE id = :id AND perfil = 'representante'");
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                $verificar = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id AND perfil = 'representante'");
                $verificar->execute(['id' => $id]);

                if (!$verificar->fetch()) {
                    throw new RuntimeException('Representante não encontrado.');
                }
            }

            redirecionarRepresentantes('status');
        }

        throw new RuntimeException('Ação não reconhecida.');
    }

    $mensagens = [
        'cadastrado' => 'Representante cadastrado com sucesso!',
        'status' => 'Status do representante atualizado!'
    ];

    $sucesso = $mensagens[$_GET['sucesso'] ?? ''] ?? '';

    $stmt = $pdo->query("SELECT id, nome, periodo FROM turmas WHERE ativa = 1 ORDER BY CASE periodo WHEN 'manha' THEN 1 WHEN 'tarde' THEN 2 ELSE 3 END, nome");
    $turmasDisponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sql = "SELECT u.id, u.nome, u.usuario, u.ativo, u.turma_id, u.criado_em, t.nome AS nome_turma, t.periodo
            FROM usuarios u
            LEFT JOIN turmas t ON t.id = u.turma_id
            WHERE u.perfil = 'representante'
            ORDER BY t.nome, u.usuario";

    $stmt = $pdo->query($sql);
    $representantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($representantes as $representante) {
        if ($representante['turma_id'] !== null) {
            $turmasComRepresentante[(int) $representante['turma_id']] = $representante;
        }

        if ((int) $representante['ativo'] === 1) {
            $representantesAtivos++;
        } else {
            $representantesInativos++;
        }
    }

    $totalRepresentantes = count($representantes);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $erro = 'Não foi possível concluir a operação. Confira se as colunas da tabela usuarios estão corretas.';
} catch (RuntimeException $e) {
    $erro = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar representantes | Interclasse ETEC</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@1.0.4/css/bulma.min.css">
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<section class="section">
    <div class="container">

        <div class="level is-align-items-flex-start">
            <div class="level-left">
                <div>
                    <h1 class="title">Gerenciar representantes</h1>
                    <p class="subtitle">Crie um login e uma senha para cada turma.</p>
                </div>
            </div>
            <div class="level-right">
                <a class="button is-light" href="index.php">Voltar ao painel</a>
            </div>
        </div>

        <?php if ($erro !== ''): ?>
            <div class="notification is-danger is-light"><?= escaparRepresentantes($erro) ?></div>
        <?php endif; ?>

        <?php if ($sucesso !== ''): ?>
            <div class="notification is-success is-light"><?= escaparRepresentantes($sucesso) ?></div>
        <?php endif; ?>

        <div class="columns">
            <div class="column">
                <div class="box">
                    <p class="heading">Total</p>
                    <p class="title"><?= $totalRepresentantes ?></p>
                </div>
            </div>
            <div class="column">
                <div class="box">
                    <p class="heading">Ativos</p>
                    <p class="title has-text-success"><?= $representantesAtivos ?></p>
                </div>
            </div>
            <div class="column">
                <div class="box">
                    <p class="heading">Inativos</p>
                    <p class="title has-text-grey"><?= $representantesInativos ?></p>
                </div>
            </div>
        </div>

        <div class="box">
            <h2 class="title is-4">Cadastrar representante</h2>
            <p class="mb-4">Cada turma pode ter somente um representante. A senha é salva no banco em formato protegido (hash).</p>

            <?php if ($turmasDisponiveis === []): ?>
                <div class="notification is-warning is-light">Não há turmas ativas disponíveis. Cadastre ou ative uma turma antes de continuar.</div>
            <?php else: ?>
                <form method="POST" action="representantes.php">
                    <input type="hidden" name="token_csrf" value="<?= escaparRepresentantes(gerarTokenCsrf()) ?>">
                    <input type="hidden" name="acao" value="cadastrar">

                    <div class="columns">
                        <div class="column">
                            <div class="field">
                                <label class="label" for="turma_id">Turma responsável</label>
                                <div class="control">
                                    <div class="select is-fullwidth">
                                        <select id="turma_id" name="turma_id" required>
                                            <option value="">Selecione uma turma</option>
                                            <?php foreach ($turmasDisponiveis as $turma): ?>
                                                <?php $turmaIdAtual = (int) $turma['id']; ?>
                                                <?php $representanteDaTurma = $turmasComRepresentante[$turmaIdAtual] ?? null; ?>
                                                <option value="<?= $turmaIdAtual ?>" <?= $representanteDaTurma !== null ? 'disabled' : '' ?>>
                                                    <?= escaparRepresentantes($turma['nome']) ?> — <?= $turma['periodo'] === 'manha' ? 'Manhã' : 'Tarde' ?><?= $representanteDaTurma !== null ? ' (já possui representante)' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="column">
                            <div class="field">
                                <label class="label" for="usuario">Login do representante</label>
                                <div class="control">
                                    <input class="input" type="text" id="usuario" name="usuario" maxlength="50" pattern="[a-zA-Z0-9._-]+" autocomplete="off" required>
                                </div>
                                <p class="help">Use letras, números, ponto, hífen ou sublinhado.</p>
                            </div>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="senha">Senha inicial</label>
                        <div class="control">
                            <input class="input" type="password" id="senha" name="senha" minlength="8" autocomplete="new-password" required>
                        </div>
                        <p class="help">A senha deve ter pelo menos 8 caracteres.</p>
                    </div>

                    <div class="field mt-5">
                        <button class="button is-primary" type="submit">Cadastrar representante</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="box">
            <h2 class="title is-4">Representantes cadastrados</h2>

            <?php if ($representantes === []): ?>
                <div class="notification is-light">Nenhum representante cadastrado até o momento.</div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table is-fullwidth is-striped is-hoverable">
                        <thead>
                            <tr>
                                <th>Login</th>
                                <th>Turma</th>
                                <th>Situação</th>
                                <th class="has-text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($representantes as $representante): ?>
                                <tr>
                                    <td><strong><?= escaparRepresentantes($representante['usuario']) ?></strong></td>
                                    <td><?= escaparRepresentantes($representante['nome_turma'] ?? 'Sem turma vinculada') ?></td>
                                    <td>
                                        <?php if ((int) $representante['ativo'] === 1): ?>
                                            <span class="tag is-success is-light">Ativo</span>
                                        <?php else: ?>
                                            <span class="tag is-light">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="has-text-right">
                                        <form method="POST" action="representantes.php" onsubmit="return confirm('Deseja alterar o acesso deste representante?');">
                                            <input type="hidden" name="token_csrf" value="<?= escaparRepresentantes(gerarTokenCsrf()) ?>">
                                            <input type="hidden" name="acao" value="alternar_status">
                                            <input type="hidden" name="id" value="<?= (int) $representante['id'] ?>">
                                            <button class="button is-small <?= (int) $representante['ativo'] === 1 ? 'is-danger is-light' : 'is-success is-light' ?>" type="submit"><?= (int) $representante['ativo'] === 1 ? 'Desativar acesso' : 'Reativar acesso' ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>
</section>
</body>
</html>