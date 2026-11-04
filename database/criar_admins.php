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

echo "================================\n";
echo "     CRIAR ADMINISTRADOR\n";
echo "================================\n\n";

$nome = perguntar('Nome completo: ');
if ($nome === '') {
    exit("O nome não pode ficar vazio.\n");
}

$login = perguntar('Nome de usuário para login: ');
if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $login)) {
    exit("Use de 3 a 50 caracteres no usuário: letras, números, ponto, hífen ou sublinhado.\n");
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

    $total = (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    if ($total > 0) {
        $pdo->rollBack();
        exit("Já existem usuários cadastrados. Cadastro cancelado.\n");
    }

    $sql = 'INSERT INTO usuarios (nome, usuario_login, senha_hash, perfil, turma_id, ativo)
            VALUES (:nome, :usuario_login, :senha_hash, :perfil, NULL, 1)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'nome' => $nome,
        'usuario_login' => $login,
        'senha_hash' => password_hash($senha, PASSWORD_DEFAULT),
        'perfil' => 'admin',
    ]);

    $pdo->commit();
    echo "\nAdministrador criado com sucesso!\n";
    echo "Login: $login\n";
    echo "Agora você pode entrar pelo sistema.\n";
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    exit("Não foi possível criar o administrador. Confira se o banco e as tabelas foram criados corretamente.\n");
} finally {
    unset($senha, $confirmacao);
}

// php.exe database/criar_admins.php