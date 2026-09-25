<?php
/**
 * PawCare installer — creates the database, tables and (optionally) demo data.
 *   Browser: http://localhost/<project-folder>/setup.php
 *   CLI:     php setup.php [--reset] [--no-demo] [--if-missing]
 */
require_once __DIR__ . '/includes/config.php';
date_default_timezone_set(TIMEZONE);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/notify.php';
require_once __DIR__ . '/includes/scheduling.php';
require_once __DIR__ . '/includes/pets.php';
require_once __DIR__ . '/database/demo_data.php';

function setup_server(): PDO
{
    return new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

function setup_is_installed(PDO $server): bool
{
    $count = $server->query(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ' . $server->quote(DB_NAME)
        . " AND TABLE_NAME IN ('users','pets','services','appointments')"
    )->fetchColumn();
    return (int) $count === 4;
}

/** Split a .sql file into statements (skips comments and CREATE DATABASE / USE lines). */
function setup_split_sql(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $out = [];
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '' && !preg_match('/^(CREATE\s+DATABASE|USE\s)/i', $stmt)) {
            $out[] = $stmt;
        }
    }
    return $out;
}

function setup_clear_uploads(): void
{
    foreach (['pets', 'vets'] as $dir) {
        foreach (glob(__DIR__ . "/uploads/$dir/*") ?: [] as $file) {
            if (is_file($file) && basename($file) !== '.gitkeep') {
                @unlink($file);
            }
        }
    }
}

function setup_install(PDO $server, bool $withDemo, bool $reset): array
{
    $name = str_replace('`', '', DB_NAME);
    if ($reset) {
        $server->exec("DROP DATABASE IF EXISTS `$name`");
        setup_clear_uploads();
    }
    $server->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    foreach (setup_split_sql((string) file_get_contents(__DIR__ . '/database/schema.sql')) as $stmt) {
        db()->exec($stmt);
    }
    return $withDemo ? seed_demo_data() : [];
}

// ---------------------------------------------------------------------------- CLI
if (PHP_SAPI === 'cli') {
    $args = array_slice($argv, 1);
    try {
        $server = setup_server();
        $installed = setup_is_installed($server);
        if ($installed && in_array('--if-missing', $args, true)) {
            echo "PawCare database already installed — nothing to do.\n";
            exit(0);
        }
        if ($installed && !in_array('--reset', $args, true) && !in_array('--if-missing', $args, true)) {
            echo "Database already installed. Use --reset to drop and reinstall it.\n";
            exit(0);
        }
        $summary = setup_install($server, !in_array('--no-demo', $args, true), $installed);
        echo "PawCare installed into database '" . DB_NAME . "'.\n";
        foreach ($summary as $k => $v) {
            echo "  - $k: $v\n";
        }
        echo "Logins: admin@pawcare.test / admin123, staff@pawcare.test / staff123, owner@pawcare.test / owner123\n";
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Setup failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
}

// ------------------------------------------------------------------------ Browser
session_name('PAWCARESESSID');
session_set_cookie_params(['path' => base_path() . '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();

$error = null;
$summary = null;
$server = null;
$installed = false;
try {
    $server = setup_server();
    $installed = setup_is_installed($server);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
$isAdmin = false;
if ($installed && !empty($_SESSION['uid'])) {
    $isAdmin = (bool) val("SELECT 1 FROM users WHERE id = ? AND role = 'admin' AND is_active = 1", [(int) $_SESSION['uid']]);
}
$canReset = $isLocal || $isAdmin;

if ($server && is_post()) {
    if (!hash_equals(csrf_token(), (string) ($_POST['_token'] ?? ''))) {
        $error = 'Your session expired. Please try again.';
    } elseif ($installed && (!$canReset || empty($_POST['confirm_reset']))) {
        $error = $canReset ? 'Tick the confirmation box to reset the database.' : 'Only an administrator (or someone on this computer) can reset the database.';
    } else {
        try {
            $summary = setup_install($server, !empty($_POST['demo']), $installed);
            $installed = true;
            unset($_SESSION['uid']);
        } catch (Throwable $e) {
            $error = 'Installation failed: ' . $e->getMessage();
        }
    }
}

$checks = [
    ['PHP 7.4 or newer', version_compare(PHP_VERSION, '7.4.0', '>='), 'You have PHP ' . PHP_VERSION],
    ['PDO MySQL extension', extension_loaded('pdo_mysql'), 'Needed to talk to MySQL'],
    ['Uploads folder is writable', is_writable(__DIR__ . '/uploads'), 'Used for pet photos'],
    ['Connected to MySQL', $server !== null, $server ? 'Host ' . DB_HOST . ' as ' . DB_USER : 'Start MySQL in XAMPP and check includes/config.php'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · <?= e(CLINIC_SHORT_NAME) ?></title>
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
</head>
<body class="error-page setup-page">
<main class="setup-card">
    <div class="setup-brand"><?= logo_mark() ?><div><strong><?= e(CLINIC_SHORT_NAME) ?> installer</strong><span>Database: <?= e(DB_NAME) ?></span></div></div>

    <?php if ($summary !== null): ?>
        <div class="setup-success">
            <div class="setup-success-icon"><?= icon('party-popper') ?></div>
            <h1>You're all set!</h1>
            <p>The database was installed<?= $summary ? ' with demo data' : '' ?>.</p>
            <?php if ($summary): ?>
                <ul class="setup-stats">
                    <?php foreach ($summary as $k => $v): ?><li><strong><?= (int) $v ?></strong><span><?= e(ucfirst($k)) ?></span></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <h1><?= $installed ? 'Database is ready' : 'Install ' . e(CLINIC_SHORT_NAME) ?></h1>
        <p class="muted"><?= $installed ? 'Everything is installed. You can reset the demo data at any time.' : 'This creates the database, tables and starter accounts in one click.' ?></p>
    <?php endif; ?>

    <?php if ($error): ?><div class="alert alert-danger"><?= icon('circle-alert') ?><div><?= e($error) ?></div></div><?php endif; ?>

    <ul class="setup-checks">
        <?php foreach ($checks as [$label, $ok, $hint]): ?>
            <li class="<?= $ok ? 'ok' : 'bad' ?>"><?= icon($ok ? 'circle-check' : 'circle-x') ?><div><strong><?= e($label) ?></strong><span><?= e($hint) ?></span></div></li>
        <?php endforeach; ?>
    </ul>

    <?php if ($installed): ?>
        <div class="setup-logins">
            <strong>Demo logins</strong>
            <div><span>Administrator</span><code>admin@pawcare.test</code><code>admin123</code></div>
            <div><span>Clinic staff</span><code>staff@pawcare.test</code><code>staff123</code></div>
            <div><span>Pet owner</span><code>owner@pawcare.test</code><code>owner123</code></div>
        </div>
        <div class="error-actions">
            <a class="btn btn-primary" href="<?= e(url()) ?>"><?= icon('house') ?>Open website</a>
            <a class="btn btn-outline" href="<?= e(url('login.php')) ?>"><?= icon('log-in') ?>Log in</a>
        </div>
        <?php if ($canReset): ?>
            <form method="post" class="setup-reset">
                <?= csrf_field() ?>
                <label class="check"><input type="checkbox" name="demo" value="1" checked> Include demo data</label>
                <label class="check"><input type="checkbox" name="confirm_reset" value="1"> I understand this deletes all current data</label>
                <button class="btn btn-danger-soft btn-sm" type="submit"><?= icon('rotate-ccw') ?>Reset &amp; reinstall</button>
            </form>
        <?php endif; ?>
    <?php elseif ($server): ?>
        <form method="post" class="setup-install">
            <?= csrf_field() ?>
            <label class="check"><input type="checkbox" name="demo" value="1" checked> Include demo data (sample clients, pets and appointments)</label>
            <button class="btn btn-primary btn-lg btn-block" type="submit"><?= icon('zap') ?>Install now</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
