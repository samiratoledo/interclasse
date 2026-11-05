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

require_once __DIR__ . '/../../includes/representante/header.php';
?>

<?php
require_once __DIR__ . '/../../includes/representante/header.php';
?>

<style>
    .inscricoes-page {
        padding-bottom: 25px;
    }

    .inscricoes-intro {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }

    .inscricoes-intro .title {
        margin-bottom: 6px;
    }

    .inscricoes-card {
        padding: 25px;
        margin-bottom: 22px;
    }

    .inscricoes-card-title {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 8px;
    }

    .inscricoes-description {
        color: #746b53;
        font-size: 13px;
        margin-bottom: 22px;
    }

    .inscricoes-form {
        max-width: 850px;
    }

    .inscricoes-form .label {
        color: #40391f;
        font-size: 13px;
    }

    .inscricoes-form .input,
    .inscricoes-form .select select,
    .inscricoes-form .textarea {
        border-radius: 8px;
    }

    .inscricoes-form .input:focus,
    .inscricoes-form .select select:focus,
    .inscricoes-form .textarea:focus {
        border-color: #e3ad12;
        box-shadow: 0 0 0 0.125em #e3ad1230;
    }

    .inscricoes-atletas {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
        gap: 10px;
        margin-top: 12px;
    }

    .inscricoes-atleta {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 13px;
        border: 1px solid #eee5ce;
        border-radius: 8px;
        background: #fffefa;
        cursor: pointer;
        transition: background 0.2s, border-color 0.2s;
        font-size: 13px;
    }

    .inscricoes-atleta:hover {
        background: #fff9e7;
        border-color: #e3c45e;
    }

    .inscricoes-atleta input {
        accent-color: #c99400;
        width: 16px;
        height: 16px;
        flex-shrink: 0;
    }

    .inscricoes-atleta span {
        line-height: 1.5;
    }

    .inscricoes-form .button.is-primary {
        background: #e3ad12;
        border-color: #e3ad12;
        color: #302b1c;
        font-weight: 700;
    }

    .inscricoes-form .button.is-primary:hover {
        background: #c99400;
        border-color: #c99400;
    }

    .inscricoes-limites {
        padding: 12px 15px;
        margin: 15px 0;
        border-radius: 8px;
        background: #fff4cc;
        color: #665018;
        font-size: 13px;
    }

    .inscricoes-table {
        overflow-x: auto;
    }

    .inscricoes-table .table {
        min-width: 1000px;
    }

    .inscricoes-table .table th {
        color: #655629;
        font-size: 12px;
        white-space: nowrap;
    }

    .inscricoes-table .table td {
        vertical-align: middle;
        font-size: 13px;
        max-width: 280px;
        overflow-wrap: anywhere;
    }

    .inscricoes-table .table tbody tr:hover {
        background: #fffaf0;
    }

    .inscricoes-empty {
        text-align: center;
        padding: 35px 15px;
        color: #746b53;
    }

    .inscricoes-count {
        background: #fff4cc;
        color: #665018;
        border-radius: 20px;
        padding: 5px 12px;
        font-size: 12px;
        font-weight: 700;
    }

    .inscricoes-page .notification {
        border-radius: 8px;
        font-size: 13px;
    }

    @media (max-width: 600px) {
        .inscricoes-card {
            padding: 17px;
        }

        .inscricoes-intro {
            align-items: flex-start;
        }

        .inscricoes-intro .button {
            width: 100%;
        }

        .inscricoes-atletas {
            grid-template-columns: 1fr;
        }

        .inscricoes-form .button {
            width: 100%;
        }
    }
</style>

<section class="inscricoes-page">

    <!-- CABEÇALHO DA PÁGINA -->

    <div class="inscricoes-intro">

        <div>
            <h1 class="title is-3">Inscrições nas modalidades</h1>

            <p class="subtitle is-6">
                Inscreva sua turma nas modalidades e acompanhe a análise da administração.
            </p>

            <span class="inscricoes-count">
                <?= count($inscricoes) ?> inscrição(ões) realizada(s)
            </span>
        </div>

        <a class="button is-dark" href="index.php">
            <span>Voltar ao painel</span>
        </a>

    </div>

    <!-- MENSAGENS -->

    <?php if ($erro !== ''): ?>
        <div class="notification is-danger is-light">
            <?= escaparInscricao($erro) ?>
        </div>
    <?php endif; ?>

    <?php if ($sucesso !== ''): ?>
        <div class="notification is-success is-light">
            <?= escaparInscricao($sucesso) ?>
        </div>
    <?php endif; ?>

    <!-- NOVA INSCRIÇÃO -->

    <div class="box inscricoes-card">

        <div class="inscricoes-card-title">
            <h2 class="title is-4 mb-0">
                Nova inscrição
            </h2>
        </div>

        <p class="inscricoes-description">
            Selecione uma modalidade e os atletas aprovados que representarão sua turma.
        </p>

        <?php if ($modalidades === []): ?>

            <div class="inscricoes-empty">
                <h3 class="title is-5">Nenhuma modalidade disponível</h3>
                <p>Não há modalidades ativas no momento. Consulte a administração.</p>
            </div>

        <?php elseif ($atletas === []): ?>

            <div class="inscricoes-empty">
                <h3 class="title is-5">Nenhum atleta aprovado</h3>
                <p>Sua turma ainda não possui atletas aprovados. Aguarde a aprovação dos cadastros.</p>
            </div>

        <?php else: ?>

            <form
                method="POST"
                action="inscricoes.php"
                id="form-inscricao"
                class="inscricoes-form"
            >

                <input
                    type="hidden"
                    name="token_csrf"
                    value="<?= escaparInscricao(gerarTokenCsrf()) ?>"
                >

                <input type="hidden" name="acao" value="inscrever">

                <div class="field">

                    <label class="label" for="modalidade_id">
                        Modalidade *
                    </label>

                    <div class="control">
                        <div class="select is-fullwidth">
                            <select id="modalidade_id" name="modalidade_id" required>

                                <option value="">Selecione uma modalidade</option>

                                <?php foreach ($modalidades as $modalidade): ?>

                                    <option
                                        value="<?= (int) $modalidade['id'] ?>"
                                        data-minimo="<?= (int) $modalidade['minimo_atletas'] ?>"
                                        data-maximo="<?= (int) $modalidade['maximo_atletas'] ?>"
                                        data-categoria="<?= escaparInscricao($modalidade['categoria']) ?>"
                                    >
                                        <?= escaparInscricao($modalidade['nome']) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>
                        </div>
                    </div>

                    <p class="help" id="limites-modalidade">
                        Selecione uma modalidade para consultar os limites de atletas.
                    </p>

                </div>

                <div id="aviso-quantidade" class="inscricoes-limites" hidden></div>

                <div class="field mt-5">

                    <label class="label">
                        Atletas disponíveis *
                    </label>

                    <div class="inscricoes-atletas">

                        <?php foreach ($atletas as $atleta): ?>

                            <label class="inscricoes-atleta">

                                <input
                                    type="checkbox"
                                    name="atletas[]"
                                    value="<?= (int) $atleta['id'] ?>"
                                    data-sexo="<?= escaparInscricao($atleta['sexo'] ?? '') ?>"
                                >

                                <span>
                                    <strong>
                                        <?= escaparInscricao($atleta['nome']) ?>
                                    </strong>

                                    <br>

                                    <small class="has-text-grey">

                                        <?php if ($atleta['numero_camisa'] !== null): ?>
                                            Camisa <?= (int) $atleta['numero_camisa'] ?>
                                        <?php endif; ?>

                                        <?php if (!empty($atleta['sexo'])): ?>
                                            <?= $atleta['numero_camisa'] !== null ? ' | ' : '' ?>
                                            <?= escaparInscricao(ucfirst($atleta['sexo'])) ?>
                                        <?php endif; ?>

                                    </small>
                                </span>

                            </label>

                        <?php endforeach; ?>

                    </div>

                    <p class="help mt-2">
                        Somente atletas aprovados da sua turma aparecem nesta lista.
                    </p>

                </div>

                <div class="field mt-5">

                    <label class="label" for="observacao">
                        Observações sobre a equipe
                    </label>

                    <div class="control">
                        <textarea
                            class="textarea"
                            id="observacao"
                            name="observacao"
                            maxlength="10000"
                            rows="3"
                            placeholder="Informe detalhes importantes sobre esta inscrição..."
                        ></textarea>
                    </div>

                    <p class="help">
                        A administração poderá consultar esta observação durante a análise.
                    </p>

                </div>

                <div class="field is-grouped is-grouped-multiline mt-5">

                    <div class="control">
                        <button class="button is-primary" type="submit">
                            Enviar inscrição para aprovação
                        </button>
                    </div>

                </div>

            </form>

        <?php endif; ?>

    </div>

    <!-- INSCRIÇÕES REALIZADAS -->

    <div class="box inscricoes-card">

        <div class="inscricoes-intro">

            <div>
                <h2 class="title is-4 mb-2">
                    Inscrições da turma
                </h2>

                <p class="inscricoes-description mb-0">
                    Consulte os atletas inscritos e acompanhe as respostas da administração.
                </p>
            </div>

            <span class="inscricoes-count">
                <?= count($inscricoes) ?> registro(s)
            </span>

        </div>

        <?php if ($inscricoes === []): ?>

            <div class="inscricoes-empty">

                <h3 class="title is-5">
                    Nenhuma inscrição realizada
                </h3>

                <p>
                    Utilize o formulário acima para inscrever sua turma em uma modalidade.
                </p>

            </div>

        <?php else: ?>

            <div class="inscricoes-table">

                <table class="table is-fullwidth is-hoverable">

                    <thead>
                        <tr>
                            <th>Modalidade</th>
                            <th>Atletas inscritos</th>
                            <th>Observação do representante</th>
                            <th>Resposta da administração</th>
                            <th>Status</th>
                            <th>Data</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($inscricoes as $inscricao): ?>

                            <?php
                            $status = $inscricao['status'];

                            $classesStatus = [
                                'aprovada' => 'is-success is-dark',
                                'rejeitada' => 'is-danger is-dark',
                                'cancelada' => 'is-dark',
                                'pendente' => 'is-warning is-dark'
                            ];

                            $rotulosStatus = [
                                'aprovada' => 'Aprovada',
                                'rejeitada' => 'Rejeitada',
                                'cancelada' => 'Cancelada',
                                'pendente' => 'Pendente'
                            ];
                            ?>

                            <tr>

                                <td>
                                    <strong>
                                        <?= escaparInscricao($inscricao['modalidade_nome']) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= escaparInscricao($inscricao['nomes_atletas'] ?: 'Nenhum atleta') ?>
                                </td>

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
                                        <span class="has-text-grey">
                                            Nenhuma resposta da administração.
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
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

    const todosCheckboxes = [
        ...formulario.querySelectorAll('input[name="atletas[]"]')
    ];

    const selecionados = todosCheckboxes.filter(
        checkbox => checkbox.checked
    );

    todosCheckboxes.forEach(checkbox => {
        const sexo = checkbox.dataset.sexo || '';
        const incompatibilidade = categoria !== 'misto' && sexo !== categoria;

        checkbox.disabled = incompatibilidade && !checkbox.checked;

        checkbox.closest('label').style.opacity =
            incompatibilidade ? '0.5' : '1';
    });

    if (!opcao || !opcao.value) {
        avisoLimites.textContent =
            'Selecione uma modalidade para consultar os limites de atletas.';

        avisoQuantidade.hidden = true;
        return true;
    }

    avisoLimites.textContent =
        `Mínimo: ${minimo} atleta(s) | Máximo: ${maximo} atleta(s).`;

    const quantidade = selecionados.length;
    let mensagem = '';

    if (quantidade < minimo) {
        mensagem =
            `Faltam ${minimo - quantidade} atleta(s) para atingir o mínimo.`;
    } else if (quantidade > maximo) {
        mensagem =
            `Você selecionou atletas demais. O máximo permitido é ${maximo}.`;
    } else {
        mensagem =
            `Quantidade válida: ${quantidade} atleta(s) selecionado(s).`;
    }

    avisoQuantidade.textContent = mensagem;
    avisoQuantidade.hidden = false;

    const quantidadeValida =
        quantidade >= minimo && quantidade <= maximo;

    avisoQuantidade.style.background = quantidadeValida
        ? '#edf8ef'
        : '#fff4cc';

    avisoQuantidade.style.borderColor = quantidadeValida
        ? '#b8dfbf'
        : '#e3c45e';

    return quantidadeValida &&
        selecionados.every(checkbox => {
            const sexo = checkbox.dataset.sexo || '';
            return categoria === 'misto' || sexo === categoria;
        });
}

if (seletorModalidade && formulario) {
    seletorModalidade.addEventListener('change', validarSelecao);

    formulario.querySelectorAll('input[name="atletas[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', validarSelecao);
    });

    formulario.addEventListener('submit', evento => {
        if (!validarSelecao()) {
            evento.preventDefault();

            avisoQuantidade.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    });

    validarSelecao();
}
</script>

<?php
require_once __DIR__ . '/../../includes/representante/footer.php';
?>