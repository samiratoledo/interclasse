<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Execute este arquivo pelo terminal.');
}

require_once __DIR__ . '/../config/database.php';

function perguntar(string $mensagem): string
{
    echo $mensagem;
    return trim((string) fgets(STDIN));
}

echo "=== CADASTRO DO ADMINISTRADOR ===\n\n";

$nome = perguntar('Escolha seu nome de usuário: ');

if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $nome)) {
    exit("Use de 3 a 50 caracteres: letras, números, ponto, hífen ou sublinhado.\n");
}

$senha = perguntar('Crie sua senha (mínimo de 10 caracteres): ');
$confirmacao = perguntar('Confirme sua senha: ');

if (strlen($senha) < 10) {
    exit("A senha precisa ter pelo menos 10 caracteres.\n");
}

if (!hash_equals($senha, $confirmacao)) {
    exit("As senhas não coincidem.\n");
}

try {
    $pdo->beginTransaction();

    $total = (int) $pdo
        ->query('SELECT COUNT(*) FROM usuarios')
        ->fetchColumn();

    if ($total > 0) {
        $pdo->rollBack();
        exit("Já existem usuários cadastrados. Cadastro cancelado.\n");
    }

    $sql = 'INSERT INTO usuarios
            (nome, senha_hash, perfil, turma_id, ativo)
            VALUES (:nome, :senha_hash, :perfil, NULL, 1)';

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'nome' => $nome,
        'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
        'perfil' => 'admin',
    ]);

    $pdo->commit();

    echo "\nConta de administrador criada com sucesso!\n";
    echo "Guarde suas credenciais em local seguro.\n";
    echo "Apague este arquivo depois de utilizá-lo.\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());
    exit("Não foi possível criar a conta. Verifique os registros do PHP.\n");
} finally {
    unset($senha, $confirmacao);
}
