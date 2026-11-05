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
$sucesso = '';

function escaparBrasao(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

if ($turmaId <= 0) {
    die('Turma não identificada.');
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validarTokenCsrf($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Solicitação inválida. Atualize a página.');
        }

        if (
            !isset($_FILES['brasao']) ||
            $_FILES['brasao']['error'] !== UPLOAD_ERR_OK
        ) {
            throw new RuntimeException('Selecione uma imagem para enviar.');
        }

        $arquivo = $_FILES['brasao'];

        if ($arquivo['size'] > 2 * 1024 * 1024) {
            throw new RuntimeException('A imagem deve ter no máximo 2 MB.');
        }

        $imagemInfo = getimagesize($arquivo['tmp_name']);

        if ($imagemInfo === false) {
            throw new RuntimeException('O arquivo enviado não é uma imagem válida.');
        }

        $formatosPermitidos = [
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_WEBP => 'webp'
        ];

        if (!isset($formatosPermitidos[$imagemInfo[2]])) {
            throw new RuntimeException('Utilize uma imagem PNG, JPG ou WEBP.');
        }

        $extensao = $formatosPermitidos[$imagemInfo[2]];

        $pasta = __DIR__ . '/../assets/brasoes/';

        if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
            throw new RuntimeException('Não foi possível criar a pasta de imagens.');
        }

        $nomeArquivo = 'turma_' . $turmaId . '.' . $extensao;
        $destino = $pasta . $nomeArquivo;

        // Busca o brasão atual da turma.
        $stmt = $pdo->prepare("
            SELECT imagem
            FROM turmas
            WHERE id = :turma_id
            LIMIT 1
        ");

        $stmt->execute(['turma_id' => $turmaId]);
        $turma = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$turma) {
            throw new RuntimeException('Turma não encontrada.');
        }

        if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
            throw new RuntimeException('Não foi possível salvar a imagem.');
        }

        $caminhoImagem = 'assets/brasoes/' . $nomeArquivo;

        $stmt = $pdo->prepare("
            UPDATE turmas
            SET imagem = :imagem
            WHERE id = :turma_id
        ");

        $stmt->execute([
            'imagem' => $caminhoImagem,
            'turma_id' => $turmaId
        ]);

        // Remove o arquivo anterior, caso tenha outra extensão.
        if (!empty($turma['imagem']) && $turma['imagem'] !== $caminhoImagem) {
            $arquivoAntigo = __DIR__ . '/../' . $turma['imagem'];

            if (is_file($arquivoAntigo)) {
                unlink($arquivoAntigo);
            }
        }

        $sucesso = 'Brasão atualizado com sucesso!';
    }

    // Busca os dados da turma e o brasão atual.
    $stmt = $pdo->prepare("
        SELECT id, nome, imagem
        FROM turmas
        WHERE id = :turma_id
        LIMIT 1
    ");

    $stmt->execute(['turma_id' => $turmaId]);
    $turma = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turma) {
        throw new RuntimeException('Não foi possível localizar sua turma.');
    }

} catch (RuntimeException $e) {
    $erro = $e->getMessage();
} catch (PDOException $e) {
    $erro = 'Erro no banco: ' . $e->getMessage();
}

require_once __DIR__ . '/../../includes/representante/header.php';
?>

<style>
.brasao-page {
    padding-bottom: 25px;
}

.brasao-intro {
    margin-bottom: 25px;
}

.brasao-intro h1 {
    color: #29231b;
    font-size: 1.8rem;
    font-weight: 800;
    margin-bottom: 8px;
}

.brasao-intro p {
    color: #62594d;
    font-size: 0.95rem;
}

.brasao-card {
    background: #fff;
    border: 1px solid #eee6d8;
    border-radius: 16px;
    padding: 25px;
    margin-bottom: 22px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.04);
}

.brasao-card h2 {
    color: #29231b;
    font-size: 1.2rem;
    font-weight: 800;
    margin-bottom: 18px;
}

.brasao-preview {
    width: 190px;
    height: 190px;
    border: 2px dashed #e5c76b;
    border-radius: 16px;
    background: #fffdf7;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 20px auto;
    padding: 15px;
}

.brasao-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.brasao-vazio {
    color: #81745f;
    font-size: 0.9rem;
    text-align: center;
}

.brasao-form {
    max-width: 650px;
}

.brasao-form .label {
    color: #29231b;
    font-weight: 700;
}

.brasao-ajuda {
    color: #716858;
    font-size: 0.85rem;
    margin-top: 8px;
}

.brasao-acoes {
    display: flex;
    justify-content: flex-end;
    margin-top: 20px;
}

@media (max-width: 600px) {
    .brasao-card {
        padding: 18px;
    }

    .brasao-acoes .button {
        width: 100%;
    }
}
</style>

<div class="brasao-page">

    <div class="brasao-intro">
        <h1>Brasão da turma</h1>
        <p>
            Personalize a identificação da sua turma enviando o brasão oficial.
        </p>
    </div>

    <?php if ($erro !== ''): ?>
        <div class="notification is-danger is-light">
            <?= escaparBrasao($erro) ?>
        </div>
    <?php endif; ?>

    <?php if ($sucesso !== ''): ?>
        <div class="notification is-success is-light">
            <?= escaparBrasao($sucesso) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($turma)): ?>

        <section class="brasao-card">
            <h2>Identificação da turma</h2>

            <p>
                <strong>Turma:</strong>
                <?= escaparBrasao($turma['nome']) ?>
            </p>

            <div class="brasao-preview">
                <?php if (!empty($turma['imagem'])): ?>
                    <img
                        src="../<?= escaparBrasao($turma['imagem']) ?>?v=<?= time() ?>"
                        alt="Brasão da turma"
                    >
                <?php else: ?>
                    <div class="brasao-vazio">
                        Nenhum brasão cadastrado.
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="brasao-card">
            <h2>Enviar ou atualizar brasão</h2>

            <form
                method="POST"
                enctype="multipart/form-data"
                class="brasao-form"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= escaparBrasao(gerarTokenCsrf()) ?>"
                >

                <div class="field">
                    <label class="label" for="brasao">
                        Escolha a imagem
                    </label>

                    <div class="control">
                        <input
                            class="input"
                            type="file"
                            id="brasao"
                            name="brasao"
                            accept=".png,.jpg,.jpeg,.webp"
                            required
                        >
                    </div>

                    <p class="brasao-ajuda">
                        Formatos aceitos: PNG, JPG e WEBP. Tamanho máximo: 2 MB.
                    </p>
                </div>

                <div class="brasao-acoes">
                    <button
                        type="submit"
                        class="button is-warning has-text-weight-bold"
                    >
                        Salvar brasão
                    </button>
                </div>
            </form>
        </section>

    <?php endif; ?>

</div>

<?php
require_once __DIR__ . '/../../includes/representante/footer.php';
?>
