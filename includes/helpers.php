<?php
/**
 * General helpers: output escaping, URLs, CSRF, flash messages and formatting.
 */

// ---------------------------------------------------------------------------
// Output & URLs
// ---------------------------------------------------------------------------

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL path of the project folder, e.g. "/pawcare" when installed in htdocs/pawcare.
 * Worked out from the running script so the app runs from any folder name.
 */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $base = '';
    if (PHP_SAPI === 'cli' || empty($_SERVER['SCRIPT_NAME']) || empty($_SERVER['SCRIPT_FILENAME'])) {
        return $base;
    }
    $root   = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $script = str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME']));
    $name   = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    if ($root !== '' && stripos($script, $root) === 0) {
        $relative = substr($script, strlen($root));          // e.g. /admin/index.php
        if ($relative !== '' && substr($name, -strlen($relative)) === $relative) {
            $base = substr($name, 0, -strlen($relative));
        }
    }
    $base = rtrim($base, '/');
    return $base;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Asset URL with a cache-busting version based on the file's modified time. */
function asset(string $path): string
{
    $file = __DIR__ . '/../' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 1;
    return url($path) . '?v=' . $v;
}

function redirect(string $path): void
{
    $target = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

/** Redirect back to the current page (keeping its query string). */
function redirect_back(string $fallback = ''): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

/** Current request path relative to the app root, e.g. "admin/patients.php". */
function current_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = base_path();
    if ($base !== '' && strpos($path, $base) === 0) {
        $path = substr($path, strlen($base));
    }
    $path = ltrim($path, '/');
    if ($path === '' || substr($path, -1) === '/') {
        $path .= 'index.php';
    }
    return $path;
}

/** Build a query string from the current GET params merged with overrides. */
function qs(array $overrides = []): string
{
    $params = array_merge($_GET, $overrides);
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return $params ? '?' . http_build_query($params) : '';
}

/** Only allow redirects to paths inside this app (prevents open redirects). */
function safe_next(?string $next): string
{
    $next = (string) $next;
    if ($next === '' || preg_match('#^(https?:)?//#i', $next) || strpos($next, "\\") !== false) {
        return '';
    }
    $base = base_path();
    if ($base !== '' && strpos($next, $base . '/') === 0) {
        $next = substr($next, strlen($base));
    }
    return ltrim($next, '/');
}

// ---------------------------------------------------------------------------
// Requests
// ---------------------------------------------------------------------------

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function param(string $key, $default = '')
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function int_param(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? $_POST[$key] ?? $default;
    return is_numeric($v) ? (int) $v : $default;
}

function old(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function json_response($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function is_api_request(): bool
{
    return strpos(current_path(), 'api/') === 0;
}

// ---------------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        if (is_api_request()) {
            json_response(['ok' => false, 'error' => 'Your session expired. Please refresh the page.'], 419);
        }
        flash('error', 'Your session expired. Please try again.');
        redirect_back();
    }
}

// ---------------------------------------------------------------------------
// Flash messages (shown as toasts)
// ---------------------------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function render_toasts(): string
{
    $icons = ['success' => 'circle-check', 'error' => 'circle-alert', 'warning' => 'triangle-alert', 'info' => 'info'];
    $html = '<div class="toast-stack" data-toast-stack aria-live="polite">';
    foreach (pull_flashes() as $f) {
        $type = isset($icons[$f['type']]) ? $f['type'] : 'info';
        $html .= '<div class="toast toast-' . $type . '" data-toast>'
            . '<span class="toast-icon">' . icon($icons[$type]) . '</span>'
            . '<p>' . e($f['message']) . '</p>'
            . '<button type="button" class="toast-close" data-toast-close aria-label="Dismiss">' . icon('x') . '</button>'
            . '</div>';
    }
    return $html . '</div>';
}

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------

function money($amount, bool $decimals = false): string
{
    return CURRENCY . number_format((float) $amount, $decimals ? 2 : 0);
}

function fmt_date(?string $date, string $format = 'M j, Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function fmt_time(?string $time): string
{
    if (!$time) {
        return '—';
    }
    return date('g:i A', strtotime('2000-01-01 ' . $time));
}

function fmt_datetime(?string $dt): string
{
    return $dt ? date('M j, Y · g:i A', strtotime($dt)) : '—';
}

function time_ago(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 0) {
        return 'just now';
    }
    if ($diff < 60) {
        return 'just now';
    }
    $units = [31536000 => 'year', 2592000 => 'month', 604800 => 'week', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $secs => $name) {
        if ($diff >= $secs) {
            $n = (int) floor($diff / $secs);
            return $n . ' ' . $name . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

/** "in 2 days", "tomorrow", "today" relative wording for a future date. */
function relative_day(string $date): string
{
    $days = (int) round((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
    if ($days === 0) return 'Today';
    if ($days === 1) return 'Tomorrow';
    if ($days === -1) return 'Yesterday';
    if ($days > 1) return 'In ' . $days . ' days';
    return abs($days) . ' days ago';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = $parts[0] ?? '';
    $last = count($parts) > 1 ? end($parts) : '';
    return strtoupper(substr($first, 0, 1) . substr((string) $last, 0, 1));
}

function greeting(): string
{
    $h = (int) date('G');
    if ($h < 12) return 'Good morning';
    if ($h < 18) return 'Good afternoon';
    return 'Good evening';
}

function plural(int $n, string $word, ?string $pluralWord = null): string
{
    return $n . ' ' . ($n === 1 ? $word : ($pluralWord ?? $word . 's'));
}

function excerpt(?string $text, int $len = 90): string
{
    $text = trim((string) $text);
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($text, 0, $len, '…');
    }
    return strlen($text) > $len ? substr($text, 0, $len - 1) . '…' : $text;
}

/** Cut a string to a maximum length (multibyte-safe when mbstring is available). */
function clip(string $text, int $len): string
{
    return function_exists('mb_substr') ? mb_substr($text, 0, $len) : substr($text, 0, $len);
}

function weekday_name(int $iso, bool $short = false): string
{
    $names = [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    $n = $names[$iso] ?? '';
    return $short ? substr($n, 0, 3) : $n;
}

function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(bool $cond): string
{
    return $cond ? ' checked' : '';
}

// ---------------------------------------------------------------------------
// UI fragments
// ---------------------------------------------------------------------------

const APPOINTMENT_STATUSES = [
    'pending'   => ['Pending', 'amber', 'hourglass'],
    'confirmed' => ['Confirmed', 'blue', 'calendar-check'],
    'completed' => ['Completed', 'green', 'circle-check'],
    'cancelled' => ['Cancelled', 'gray', 'circle-x'],
    'no_show'   => ['No-show', 'red', 'ban'],
];

/** Status pill; $compact renders icon-only (label kept for tooltips and screen readers). */
function status_badge(string $status, bool $compact = false): string
{
    [$label, $color, $ic] = APPOINTMENT_STATUSES[$status] ?? [ucfirst($status), 'gray', 'circle'];
    if ($compact) {
        return '<span class="badge badge-icon badge-' . $color . '" title="' . e($label) . '">' . icon($ic) . '<span class="sr-only">' . e($label) . '</span></span>';
    }
    return '<span class="badge badge-' . $color . '">' . icon($ic) . e($label) . '</span>';
}

function status_label(string $status): string
{
    return APPOINTMENT_STATUSES[$status][0] ?? ucfirst($status);
}

function avatar(string $name, string $class = ''): string
{
    $palette = ['teal', 'coral', 'sun', 'violet', 'blue', 'green'];
    $color = $palette[abs(crc32($name)) % count($palette)];
    return '<span class="avatar avatar-' . $color . ' ' . e($class) . '" aria-hidden="true">' . e(initials($name)) . '</span>';
}

function empty_state(string $iconName, string $title, string $text = '', string $actions = ''): string
{
    return '<div class="empty-state">'
        . '<div class="empty-art">' . icon($iconName) . '<span class="empty-dot d1"></span><span class="empty-dot d2"></span><span class="empty-dot d3"></span></div>'
        . '<h3>' . e($title) . '</h3>'
        . ($text ? '<p>' . e($text) . '</p>' : '')
        . ($actions ? '<div class="empty-actions">' . $actions . '</div>' : '')
        . '</div>';
}

/** Brand mark (inline SVG so it can be themed). */
function logo_mark(string $class = 'logo-mark'): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 48 48" aria-hidden="true" focusable="false">'
        . '<defs><linearGradient id="lg-pc" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#14b8a6"/><stop offset="1" stop-color="#0b6b62"/></linearGradient></defs>'
        . '<rect width="48" height="48" rx="14" fill="url(#lg-pc)"/>'
        . '<g fill="#fff"><ellipse cx="17" cy="15.5" rx="3.6" ry="4.6"/><ellipse cx="31" cy="15.5" rx="3.6" ry="4.6"/>'
        . '<ellipse cx="10.5" cy="24" rx="3.2" ry="4"/><ellipse cx="37.5" cy="24" rx="3.2" ry="4"/>'
        . '<path d="M24 22.5c-5.4 0-10.5 6.6-10.5 10.6 0 3 2.3 4.4 5 4.4 2.3 0 3.4-1.2 5.5-1.2s3.2 1.2 5.5 1.2c2.7 0 5-1.4 5-4.4 0-4-5.1-10.6-10.5-10.6z"/></g>'
        . '<path d="M36 6.5c1.6-1.7 4.6-.6 4.6 1.9 0 2.2-2.9 4-4.6 5.4-1.7-1.4-4.6-3.2-4.6-5.4 0-2.5 3-3.6 4.6-1.9z" fill="#ff7a59"/>'
        . '</svg>';
}

/** Simple pagination helper. */
function paginate(int $total, int $perPage = 12): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, int_param('page', 1)), $pages);
    return ['total' => $total, 'per' => $perPage, 'page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage];
}

function pagination_links(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $from = $p['offset'] + 1;
    $to = min($p['total'], $p['offset'] + $p['per']);
    $html = '<nav class="pagination" aria-label="Pagination"><span class="pagination-info">Showing ' . $from . '–' . $to . ' of ' . $p['total'] . '</span><div class="pagination-links">';
    $html .= $p['page'] > 1
        ? '<a class="page-link" href="' . e(qs(['page' => $p['page'] - 1])) . '" aria-label="Previous">' . icon('chevron-left') . '</a>'
        : '<span class="page-link disabled">' . icon('chevron-left') . '</span>';
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($i === 1 || $i === $p['pages'] || abs($i - $p['page']) <= 1) {
            $html .= $i === $p['page']
                ? '<span class="page-link active">' . $i . '</span>'
                : '<a class="page-link" href="' . e(qs(['page' => $i])) . '">' . $i . '</a>';
        } elseif (abs($i - $p['page']) === 2) {
            $html .= '<span class="page-link gap">…</span>';
        }
    }
    $html .= $p['page'] < $p['pages']
        ? '<a class="page-link" href="' . e(qs(['page' => $p['page'] + 1])) . '" aria-label="Next">' . icon('chevron-right') . '</a>'
        : '<span class="page-link disabled">' . icon('chevron-right') . '</span>';
    return $html . '</div></nav>';
}

// ---------------------------------------------------------------------------
// Error pages
// ---------------------------------------------------------------------------

function render_exception(Throwable $e): void
{
    $dbProblem = $e instanceof PDOException;
    $code = $dbProblem ? (string) $e->getCode() : '';
    $notInstalled = $dbProblem && (in_array($code, ['1049', '42S02'], true)
        || stripos($e->getMessage(), 'Unknown database') !== false
        || stripos($e->getMessage(), "doesn't exist") !== false);
    $cannotConnect = $dbProblem && (in_array($code, ['2002', '1045', '2006'], true)
        || stripos($e->getMessage(), 'Connection refused') !== false
        || stripos($e->getMessage(), 'Access denied') !== false);

    if (!headers_sent()) {
        http_response_code(500);
    }
    if (is_api_request()) {
        json_response(['ok' => false, 'error' => APP_DEBUG ? $e->getMessage() : 'Server error'], 500);
    }

    if ($notInstalled) {
        $title = 'Almost there — let\'s set up the database';
        $msg = 'The "' . DB_NAME . '" database has not been installed yet. Run the one-click installer to create the tables and demo data.';
    } elseif ($cannotConnect) {
        $title = 'Cannot connect to MySQL';
        $msg = 'Make sure MySQL is running in the XAMPP Control Panel and that the credentials in includes/config.php are correct.';
    } else {
        $title = 'Something went wrong';
        $msg = 'An unexpected error occurred while loading this page.';
    }
    $details = APP_DEBUG ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title) . '</title><link rel="stylesheet" href="' . e(asset('assets/css/base.css')) . '"></head>'
        . '<body class="error-page"><div class="error-card">' . logo_mark() . '<h1>' . e($title) . '</h1><p>' . e($msg) . '</p>'
        . ($details ? '<pre>' . e($details) . '</pre>' : '')
        . '<div class="error-actions">'
        . ($notInstalled || $cannotConnect ? '<a class="btn btn-primary" href="' . e(url('setup.php')) . '">Open installer</a>' : '')
        . '<a class="btn btn-outline" href="' . e(url()) . '">Back to home</a></div></div></body></html>';
    exit;
}

function abort(int $status, string $message = 'Page not found'): void
{
    http_response_code($status);
    if (is_api_request()) {
        json_response(['ok' => false, 'error' => $message], $status);
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $status . '</title><link rel="stylesheet" href="' . e(asset('assets/css/base.css')) . '"></head>'
        . '<body class="error-page"><div class="error-card">' . logo_mark() . '<p class="error-code">' . $status . '</p><h1>' . e($message) . '</h1>'
        . '<p>The page you are looking for may have moved, or you may not have access to it.</p>'
        . '<div class="error-actions"><a class="btn btn-primary" href="' . e(url()) . '">Go to homepage</a></div></div></body></html>';
    exit;
}
