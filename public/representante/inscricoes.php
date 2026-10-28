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
$erro = '';
$sucesso = is_string($_GET['sucesso'] ?? null) ? $_GET['sucesso'] : '';
$modalidades = [];
$atletas = [];
$inscricoes = [];

function escaparInscricao(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

if ($turmaId <= 0) {
    http_response_code(403);
    exit('Não foi possível identificar sua turma. Entre novamente no sistema.');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validarTokenCsrf($_POST['token_csrf'] ?? null)) {
            throw new RuntimeException(
                'Formulário inválido ou expirado. Atualize a página e tente novamente.'
            );
        }

        if (($_POST['acao'] ?? '') !== 'inscrever') {
            throw new RuntimeException('Ação inválida.');
        }

        $modalidadeId = filter_var(
            $_POST['modalidade_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($modalidadeId === false || $modalidadeId <= 0) {
            throw new RuntimeException('Selecione uma modalidade válida.');
        }

        $observacao = trim((string) ($_POST['observacao'] ?? ''));

        if (strlen($observacao) > 10000) {
            throw new RuntimeException(
                'A observação deve ter no máximo 10.000 bytes.'
            );
        }

        $atletasSelecionados = $_POST['atletas'] ?? [];

        if (!is_array($atletasSelecionados) || $atletasSelecionados === []) {
            throw new RuntimeException('Selecione pelo menos um atleta aprovado.');
        }

        $idsAtletas = [];

        foreach ($atletasSelecionados as $id) {
            $idValidado = filter_var($id, FILTER_VALIDATE_INT);

            if ($idValidado === false || $idValidado <= 0) {
                throw new RuntimeException(
                    'A lista de atletas contém um identificador inválido.'
                );
            }

            $idsAtletas[] = (int) $idValidado;
        }

        $idsAtletas = array_values(array_unique($idsAtletas));

        // Busca as regras configuradas pelo administrador.
        $consultaModalidade = $pdo->prepare(
            'SELECT id, nome, categoria, minimo_atletas, maximo_atletas
             FROM modalidades
             WHERE id = :id AND ativa = 1'
        );

        $consultaModalidade->execute(['id' => $modalidadeId]);

        $modalidadeSelecionada = $consultaModalidade->fetch(PDO::FETCH_ASSOC);

        if (!$modalidadeSelecionada) {
            throw new RuntimeException(
                'Essa modalidade não está disponível para inscrição.'
            );
        }

        // Valida a quantidade de atletas selecionados.
        $minimoAtletas = (int) $modalidadeSelecionada['minimo_atletas'];
        $maximoAtletas = (int) $modalidadeSelecionada['maximo_atletas'];
        $quantidadeSelecionada = count($idsAtletas);

        if (
            $minimoAtletas < 1 ||
            $maximoAtletas < $minimoAtletas
        ) {
            throw new RuntimeException(
                'Os limites desta modalidade estão incorretos. Avise a administração.'
            );
        }

        if ($quantidadeSelecionada < $minimoAtletas) {
            throw new RuntimeException(
                'A modalidade ' . $modalidadeSelecionada['nome'] .
                ' exige pelo menos ' . $minimoAtletas .
                ' atleta(s). Você selecionou ' . $quantidadeSelecionada . '.'
            );
        }

        if ($quantidadeSelecionada > $maximoAtletas) {
            throw new RuntimeException(
                'A modalidade ' . $modalidadeSelecionada['nome'] .
                ' permite no máximo ' . $maximoAtletas .
                ' atleta(s). Você selecionou ' . $quantidadeSelecionada . '.'
            );
        }

        // Confere se todos os atletas pertencem à turma e estão aprovados.
        $placeholders = implode(',', array_fill(0, count($idsAtletas), '?'));

        $consultaAtletas = $pdo->prepare(
            "SELECT id, sexo
             FROM atletas
             WHERE turma_id = ?
               AND ativo = 1
               AND status_aprovacao = 'aprovado'
               AND id IN ($placeholders)"
        );

        $consultaAtletas->execute(array_merge([$turmaId], $idsAtletas));

        $atletasPermitidos = $consultaAtletas->fetchAll(PDO::FETCH_ASSOC);

        $idsPermitidos = array_map(
            'intval',
            array_column($atletasPermitidos, 'id')
        );

        if (count($idsPermitidos) !== count($idsAtletas)) {
            throw new RuntimeException(
                'Um ou mais atletas não pertencem à sua turma ou ainda não foram aprovados.'
            );
        }

        // Confere a categoria quando a modalidade não é mista.
        $categoria = $modalidadeSelecionada['categoria'];

        if (!in_array($categoria, ['masculino', 'feminino', 'misto'], true)) {
            throw new RuntimeException(
                'A categoria desta modalidade está inválida. Avise a administração.'
            );
        }

        if ($categoria !== 'misto') {
            foreach ($atletasPermitidos as $atleta) {
                if ($atleta['sexo'] !== $categoria) {
                    throw new RuntimeException(
                        'Esta modalidade aceita apenas atletas da categoria ' .
                        ($categoria === 'masculino' ? 'masculina' : 'feminina') . '.'
                    );
                }
            }
        }

        $pdo->beginTransaction();

        $consultaExistente = $pdo->prepare(
            'SELECT id, status
             FROM inscricoes
             WHERE turma_id = :turma_id
               AND modalidade_id = :modalidade_id
             FOR UPDATE'
        );

        $consultaExistente->execute([
            'turma_id' => $turmaId,
            'modalidade_id' => $modalidadeId
        ]);

        $inscricaoExistente = $consultaExistente->fetch(PDO::FETCH_ASSOC);

        if (
            $inscricaoExistente &&
            in_array(
                $inscricaoExistente['status'],
                ['pendente', 'aprovada'],
                true
            )
        ) {
            throw new RuntimeException(
                'Sua turma já possui uma inscrição pendente ou aprovada nessa modalidade.'
            );
        }

        if ($inscricaoExistente) {
            $inscricaoId = (int) $inscricaoExistente['id'];

            $atualizarInscricao = $pdo->prepare(
                "UPDATE inscricoes
                 SET status = 'pendente',
                     observacao = :observacao,
                     observacao_admin = NULL
                 WHERE id = :id"
            );

            $atualizarInscricao->execute([
                'id' => $inscricaoId,
                'observacao' => $observacao !== '' ? $observacao : null
            ]);

            $removerAtletasAntigos = $pdo->prepare(
                'DELETE FROM inscricao_atletas
                 WHERE inscricao_id = :id'
            );

            $removerAtletasAntigos->execute(['id' => $inscricaoId]);
        } else {
            $novaInscricao = $pdo->prepare(
                "INSERT INTO inscricoes
                    (turma_id, modalidade_id, status, observacao)
                 VALUES
                    (:turma_id, :modalidade_id, 'pendente', :observacao)"
            );

            $novaInscricao->execute([
                'turma_id' => $turmaId,
                'modalidade_id' => $modalidadeId,
                'observacao' => $observacao !== '' ? $observacao : null
            ]);

            $inscricaoId = (int) $pdo->lastInsertId();
        }

        $inserirAtleta = $pdo->prepare(
            'INSERT INTO inscricao_atletas (inscricao_id, atleta_id)
             VALUES (:inscricao_id, :atleta_id)'
        );

        foreach ($idsPermitidos as $idAtleta) {
            $inserirAtleta->execute([
                'inscricao_id' => $inscricaoId,
                'atleta_id' => $idAtleta
            ]);
        }

        $pdo->commit();

        header(
            'Location: inscricoes.php?sucesso=' .
            urlencode('Inscrição enviada para análise da administração.')
        );
        exit;
    }

    // Carrega as modalidades ativas e suas regras.
    $consultaModalidades = $pdo->query(
        'SELECT id, nome, descricao, categoria,
                minimo_atletas, maximo_atletas
         FROM modalidades
         WHERE ativa = 1
         ORDER BY nome'
    );

    $modalidades = $consultaModalidades->fetchAll(PDO::FETCH_ASSOC);

    // Carrega somente os atletas aprovados da turma do representante.
    $consultaAtletas = $pdo->prepare(
        "SELECT id, nome, numero_camisa, sexo
         FROM atletas
         WHERE turma_id = :turma_id
           AND ativo = 1
           AND status_aprovacao = 'aprovado'
         ORDER BY nome"
    );

    $consultaAtletas->execute(['turma_id' => $turmaId]);
    $atletas = $consultaAtletas->fetchAll(PDO::FETCH_ASSOC);

    // Carrega as inscrições e as duas observações.
    $consultaInscricoes = $pdo->prepare(
        "SELECT
            i.id,
            i.status,
            i.observacao,
            i.observacao_admin,
            i.criado_em,
            m.nome AS modalidade_nome,
            GROUP_CONCAT(
                a.nome ORDER BY a.nome SEPARATOR ', '
            ) AS nomes_atletas
         FROM inscricoes i
         INNER JOIN modalidades m ON m.id = i.modalidade_id
         LEFT JOIN inscricao_atletas ia ON ia.inscricao_id = i.id
         LEFT JOIN atletas a ON a.id = ia.atleta_id
         WHERE i.turma_id = :turma_id
         GROUP BY
            i.id, i.status, i.observacao, i.observacao_admin,
            i.criado_em, m.nome
         ORDER BY i.criado_em DESC"
    );

    $consultaInscricoes->execute(['turma_id' => $turmaId]);
    $inscricoes = $consultaInscricoes->fetchAll(PDO::FETCH_ASSOC);

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $erro = $e->getMessage();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    $erro = 'Não foi possível concluir a inscrição. Confira os dados e tente novamente.';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscrições | Interclasse ETEC</title>
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
            <a class="navbar-item" href="index.php">Painel</a>
            <a class="navbar-item" href="atletas.php">Meus atletas</a>
            <a class="navbar-item is-active" href="inscricoes.php">Inscrições</a>
        </div>

        <div class="navbar-end">
            <div class="navbar-item">
                <form action="../logout.php" method="POST">
                    <input type="hidden" name="token_csrf" value="<?= escaparInscricao(gerarTokenCsrf()) ?>">
                    <button class="button is-light is-small" type="submit">Sair</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<section class="section">
    <div class="container">
        <h1 class="title">Inscrições nas modalidades</h1>
        <p class="subtitle">Selecione os atletas aprovados e confira os limites de cada modalidade.</p>

        <?php if ($erro !== ''): ?>
            <div class="notification is-danger is-light"><?= escaparInscricao($erro) ?></div>
        <?php endif; ?>

        <?php if ($sucesso !== ''): ?>
            <div class="notification is-success is-light"><?= escaparInscricao($sucesso) ?></div>
        <?php endif; ?>

        <div class="box">
            <h2 class="title is-4">Nova inscrição</h2>

            <?php if ($modalidades === []): ?>
                <div class="notification is-info is-light">Não há modalidades ativas no momento. Consulte a administração.</div>
            <?php elseif ($atletas === []): ?>
                <div class="notification is-info is-light">Sua turma ainda não possui atletas aprovados. Aguarde a aprovação.</div>
            <?php else: ?>
                <form method="POST" action="inscricoes.php" id="form-inscricao">
                    <input type="hidden" name="token_csrf" value="<?= escaparInscricao(gerarTokenCsrf()) ?>">
                    <input type="hidden" name="acao" value="inscrever">

                    <div class="field">
                        <label class="label" for="modalidade_id">Modalidade</label>
                        <div class="control">
                            <div class="select is-fullwidth">
                                <select id="modalidade_id" name="modalidade_id" required>
                                    <option value="">Selecione uma modalidade</option>
                                    <?php foreach ($modalidades as $modalidade): ?>
                                        <option
                                            value="<?= (int) $modalidade['id'] ?>"
                                            data-minimo="<?= (int) $modalidade['minimo_atletas'] ?>"
                                            data-maximo="<?= (int) $modalidade['maximo_atletas'] ?>"
                                            data-categoria="<?= escaparInscricao($modalidade['categoria']) ?>">
                                            <?= escaparInscricao($modalidade['nome']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <p class="help" id="limites-modalidade">Selecione uma modalidade para consultar os limites de atletas.</p>
                    </div>

                    <div id="aviso-quantidade" class="notification is-info is-light" hidden></div>

                    <div class="field">
                        <label class="label">Selecione os atletas</label>

                        <?php foreach ($atletas as $atleta): ?>
                            <label class="checkbox is-block mb-3">
                                <input
                                    type="checkbox"
                                    name="atletas[]"
                                    value="<?= (int) $atleta['id'] ?>"
                                    data-sexo="<?= escaparInscricao($atleta['sexo'] ?? '') ?>">
                                <?= escaparInscricao($atleta['nome']) ?>
                                <?php if ($atleta['numero_camisa'] !== null): ?>
                                    <span class="has-text-grey">— Camisa <?= (int) $atleta['numero_camisa'] ?></span>
                                <?php endif; ?>
                                <?php if (!empty($atleta['sexo'])): ?>
                                    <span class="has-text-grey">— <?= escaparInscricao(ucfirst($atleta['sexo'])) ?></span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>

                        <p class="help">Somente atletas aprovados da sua turma aparecem nesta lista.</p>
                    </div>

                    <div class="field">
                        <label class="label" for="observacao">Observações sobre a equipe</label>
                        <div class="control">
                            <textarea class="textarea" id="observacao" name="observacao" maxlength="10000" placeholder="Informe detalhes importantes sobre esta inscrição..."></textarea>
                        </div>
                        <p class="help">A administração poderá consultar esta observação durante a análise.</p>
                    </div>

                    <button class="button is-primary" type="submit">Enviar inscrição para aprovação</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="box">
            <h2 class="title is-4">Inscrições da turma</h2>

            <?php if ($inscricoes === []): ?>
                <div class="notification is-info is-light">Sua turma ainda não realizou inscrições.</div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table is-fullwidth is-striped is-hoverable">
                        <thead>
                            <tr>
                                <th>Modalidade</th>
                                <th>Atletas inscritos</th>
                                <th>Observação do representante</th>
                                <th>Resposta da administração</th>
                                <th>Status</th>
                                <th>Data da inscrição</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inscricoes as $inscricao): ?>
                                <tr>
                                    <td><?= escaparInscricao($inscricao['modalidade_nome']) ?></td>
                                    <td><?= escaparInscricao($inscricao['nomes_atletas'] ?: 'Nenhum atleta') ?></td>
                                    <td>
                                        <?php if (!empty($inscricao['observacao'])): ?>
                                            <?= nl2br(escaparInscricao($inscricao['observacao'])) ?>
                                        <?php else: ?>
                                            <span class="has-text-grey">Nenhuma</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($inscricao['observacao_admin'])): ?>
                                            <?= nl2br(escaparInscricao($inscricao['observacao_admin'])) ?>
                                        <?php else: ?>
                                            <span class="has-text-grey">Nenhuma resposta da administração.</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $inscricao['status'];
                                        $classesStatus = [
                                            'aprovada' => 'is-success',
                                            'rejeitada' => 'is-danger',
                                            'cancelada' => 'is-dark',
                                            'pendente' => 'is-warning'
                                        ];
                                        $rotulosStatus = [
                                            'aprovada' => 'Aprovada',
                                            'rejeitada' => 'Rejeitada',
                                            'cancelada' => 'Cancelada',
                                            'pendente' => 'Pendente'
                                        ];
                                        ?>
                                        <span class="tag <?= escaparInscricao($classesStatus[$status] ?? 'is-light') ?>">
                                            <?= escaparInscricao($rotulosStatus[$status] ?? $status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= !empty($inscricao['criado_em'])
                                            ? escaparInscricao(date('d/m/Y H:i', strtotime($inscricao['criado_em'])))
                                            : '—' ?>
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

<script>
const seletorModalidade = document.getElementById('modalidade_id');
const formulario = document.getElementById('form-inscricao');
const avisoLimites = document.getElementById('limites-modalidade');
const avisoQuantidade = document.getElementById('aviso-quantidade');

function validarSelecao() {
    if (!seletorModalidade || !formulario) return true;

    const opcao = seletorModalidade.options[seletorModalidade.selectedIndex];
    const minimo = Number(opcao?.dataset.minimo || 0);
    const maximo = Number(opcao?.dataset.maximo || 0);
    const categoria = opcao?.dataset.categoria || '';
    const selecionados = [...formulario.querySelectorAll('input[name="atletas[]"]:checked')];

    formulario.querySelectorAll('input[name="atletas[]"]').forEach((checkbox) => {
        const sexo = checkbox.dataset.sexo || '';
        const incompatível = categoria !== 'misto' && sexo !== categoria;
        checkbox.disabled = incompatível && !checkbox.checked;
        checkbox.closest('label').style.opacity = incompatível ? '0.5' : '1';
    });

    if (!opcao || !opcao.value) {
        avisoLimites.textContent = 'Selecione uma modalidade para consultar os limites de atletas.';
        avisoQuantidade.hidden = true;
        return true;
    }

    avisoLimites.textContent = `Mínimo: ${minimo} atleta(s) • Máximo: ${maximo} atleta(s).`;

    const quantidade = selecionados.length;
    let mensagem = '';

    if (quantidade < minimo) {
        mensagem = `Faltam ${minimo - quantidade} atleta(s) para atingir o mínimo.`;
    } else if (quantidade > maximo) {
        mensagem = `Você selecionou atletas demais. O máximo permitido é ${maximo}.`;
    } else {
        mensagem = `Quantidade válida: ${quantidade} atleta(s) selecionado(s).`;
    }

    avisoQuantidade.textContent = mensagem;
    avisoQuantidade.hidden = false;
    avisoQuantidade.className = quantidade >= minimo && quantidade <= maximo
        ? 'notification is-success is-light'
        : 'notification is-warning is-light';

    return quantidade >= minimo &&
        quantidade <= maximo &&
        selecionados.every((checkbox) => {
            const sexo = checkbox.dataset.sexo || '';
            return categoria === 'misto' || sexo === categoria;
        });
}

if (seletorModalidade && formulario) {
    seletorModalidade.addEventListener('change', validarSelecao);

    formulario.querySelectorAll('input[name="atletas[]"]').forEach((checkbox) => {
        checkbox.addEventListener('change', validarSelecao);
    });

    formulario.addEventListener('submit', (evento) => {
        if (!validarSelecao()) {
            evento.preventDefault();
            avisoQuantidade.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    validarSelecao();
}
</script>
</body>
</html>