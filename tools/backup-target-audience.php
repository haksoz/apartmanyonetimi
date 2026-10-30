<?php

// Local-only export. No Laravel bootstrap, schema changes, or database writes.
// Usage: php tools/backup-target-audience.php <existing-private-directory-outside-repo>
// Set AIDAT_BACKUP_KEY to a base64-encoded random 32-byte key, stored separately.
require dirname(__DIR__).'/vendor/autoload.php';

$root = realpath(dirname(__DIR__));
$directory = realpath($argv[1] ?? '');
$normalizedRoot = strtolower(str_replace('\\', '/', $root));
$normalizedDirectory = strtolower(str_replace('\\', '/', $directory ?: ''));
if (! isset($argv[1]) || ! $directory || ! is_dir($directory)
    || $normalizedDirectory === $normalizedRoot || str_starts_with($normalizedDirectory.'/', $normalizedRoot.'/')
    || preg_match('~/(public|www|wwwroot|htdocs|inetpub)(/|$)~i', $normalizedDirectory)) {
    throw new RuntimeException('Choose an existing private directory outside the repository and web roots.');
}
$key = base64_decode(getenv('AIDAT_BACKUP_KEY') ?: '', true);
if ($key === false || strlen($key) !== 32) {
    throw new RuntimeException('AIDAT_BACKUP_KEY must contain a base64-encoded random 32-byte key.');
}
$env = Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
if (($env['APP_ENV'] ?? '') !== 'local' || ($env['DB_CONNECTION'] ?? '') !== 'mysql'
    || ! in_array($env['DB_HOST'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Only local MySQL export is allowed by this tool.');
}
$pdo = new PDO('mysql:host='.$env['DB_HOST'].';port='.($env['DB_PORT'] ?? '3306').';dbname='.$env['DB_DATABASE'].';charset=utf8mb4',
    $env['DB_USERNAME'], $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
try {
    $backup = ['format' => 1, 'database' => $env['DB_DATABASE'], 'created_at_utc' => gmdate('c'), 'tables' => []];
    foreach (['dues', 'due_batches', 'due_plans', 'assessment_plans'] as $table) {
        $backup['tables'][$table] = [
            'schema' => $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1],
            'rows' => $pdo->query('SELECT id, apartment_id, due_type, target_audience FROM `'.$table.'` ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
    $plaintext = json_encode($backup, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $nonce = random_bytes(12);
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'AidatCep-target-audience-v1');
    if ($ciphertext === false) throw new RuntimeException('Encryption failed.');
    $envelope = json_encode(['format' => 'AidatCep-target-audience-v1', 'nonce' => base64_encode($nonce),
        'tag' => base64_encode($tag), 'ciphertext' => base64_encode($ciphertext)], JSON_THROW_ON_ERROR);
    // Verify the encrypted payload before writing. No plaintext backup is written to disk.
    if (openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'AidatCep-target-audience-v1') !== $plaintext) {
        throw new RuntimeException('Backup encryption verification failed.');
    }
    $path = $directory.DIRECTORY_SEPARATOR.'aidat-target-audience-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.encrypted.json';
    $handle = fopen($path, 'xb');
    if ($handle === false) throw new RuntimeException('Could not exclusively create backup file.');
    try {
        if (fwrite($handle, $envelope) !== strlen($envelope)) throw new RuntimeException('Incomplete backup write.');
        fflush($handle);
    } finally {
        fclose($handle);
    }
    if (file_get_contents($path) !== $envelope) throw new RuntimeException('Backup read-back failed.');
    echo 'Encrypted backup verified: '.$path.PHP_EOL;
    echo 'SHA256: '.hash('sha256', $envelope).PHP_EOL;
} finally {
    $pdo->exec('ROLLBACK');
}
