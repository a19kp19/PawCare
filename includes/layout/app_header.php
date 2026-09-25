<?php
/**
 * Portal layout (pet owners + clinic staff).
 * Set before including: $pageTitle, $activeNav; optional: $useCharts, $extraScripts, $bodyAttrs.
 */
$me = current_user();
$isStaffUser = is_staff($me);
$unread = unread_count((int) $me['id']);
$myNotifications = recent_notifications((int) $me['id'], 8);
$today = date('Y-m-d');

if ($isStaffUser) {
    $navPending = (int) val("SELECT COUNT(*) FROM appointments WHERE status = 'pending' AND appointment_date >= ?", [$today]);
    $navMessages = (int) val("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'");
    $navDue = (int) val(
        'SELECT COUNT(*) FROM (' . latest_vaccines_sql() . ') x WHERE x.next_due_date BETWEEN ? AND ?',
        [date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('+' . REMINDER_WINDOW_DAYS . ' days'))]
    );
    $nav = [
        'Overview' => [
            ['dashboard', 'Dashboard', 'layout-dashboard', 'admin/'],
            ['schedule', 'Day Schedule', 'calendar-days', 'admin/schedule.php'],
        ],
        'Clinic' => [
            ['appointments', 'Appointments', 'calendar-check', 'admin/appointments.php', $navPending],
            ['patients', 'Patients', 'paw-print', 'admin/patients.php'],
            ['clients', 'Clients', 'users', 'admin/clients.php'],
            ['reminders', 'Reminders', 'bell-ring', 'admin/reminders.php', $navDue, 'soft'],
            ['messages', 'Messages', 'inbox', 'admin/messages.php', $navMessages],
        ],
    ];
    if (is_admin($me)) {
        $nav['Management'] = [
            ['reports', 'Reports', 'chart-column', 'admin/reports.php'],
            ['services', 'Services & Prices', 'clipboard-list', 'admin/services.php'],
            ['vets', 'Veterinarians', 'stethoscope', 'admin/vets.php'],
            ['staff', 'Staff Accounts', 'user-cog', 'admin/staff.php'],
        ];
    }
} else {
    $nav = [
        'My PawCare' => [
            ['dashboard', 'Dashboard', 'layout-dashboard', 'owner/'],
            ['pets', 'My Pets', 'paw-print', 'owner/pets.php'],
            ['book', 'Book a Visit', 'calendar-plus', 'owner/book.php'],
            ['appointments', 'Appointments', 'calendar-check', 'owner/appointments.php'],
        ],
    ];
    $nextVisit = row(
        appointment_sql() . " WHERE a.owner_id = ? AND a.status IN ('pending','confirmed') AND (a.appointment_date > ? OR (a.appointment_date = ? AND a.start_time >= ?))
        ORDER BY a.appointment_date, a.start_time LIMIT 1",
        [$me['id'], $today, $today, date('H:i:s')]
    );
}
$nav['Account'] = [
    ['notifications', 'Notifications', 'bell', 'notifications.php', $unread],
    ['account', 'My Account', 'user-round', 'account.php'],
];
$clinicNow = clinic_status();
?>
<!doctype html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($pageTitle ?? 'Dashboard') . ' · ' . CLINIC_SHORT_NAME) ?></title>
<meta name="theme-color" content="#0d8a7d">
<script>try { var t = localStorage.getItem('pc-theme'); if (t) document.documentElement.dataset.theme = t; } catch (e) {}</script>
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(url('assets/fonts/plus-jakarta-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body class="app-body" <?= $bodyAttrs ?? '' ?>>
<a class="skip-link" href="#main">Skip to content</a>
<?= render_toasts() ?>
<div class="app">
    <aside class="sidebar" aria-label="Sidebar">
        <a class="sidebar-brand" href="<?= e(url($isStaffUser ? 'admin/' : 'owner/')) ?>"><?= logo_mark() ?><span><?= e(CLINIC_SHORT_NAME) ?><small><?= $isStaffUser ? 'Clinic Admin' : 'Pet Portal' ?></small></span></a>
        <?php foreach ($nav as $section => $items): ?>
            <nav class="nav-section" aria-label="<?= e($section) ?>">
                <div class="nav-label"><?= e($section) ?></div>
                <?php foreach ($items as $it): ?>
                    <a class="nav-item<?= ($activeNav ?? '') === $it[0] ? ' active' : '' ?>" href="<?= e(url($it[3])) ?>"<?= ($activeNav ?? '') === $it[0] ? ' aria-current="page"' : '' ?>>
                        <?= icon($it[2]) ?><span><?= e($it[1]) ?></span>
                        <?php if (!empty($it[4])): ?><span class="nav-badge <?= e($it[5] ?? '') ?>"><?= (int) $it[4] > 99 ? '99+' : (int) $it[4] ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endforeach; ?>
        <div class="sidebar-foot">
            <?php if ($isStaffUser): ?>
                <div class="side-card">
                    <strong><?= icon($clinicNow['open'] ? 'circle-check' : 'clock') ?><?= e($clinicNow['label']) ?></strong>
                    <p><?= e(ucfirst($clinicNow['detail'] ?: 'See you tomorrow')) ?></p>
                    <a class="btn btn-white btn-sm" href="<?= e(url('admin/appointment-new.php')) ?>"><?= icon('plus') ?>New appointment</a>
                </div>
            <?php else: ?>
                <div class="side-card">
                    <strong><?= icon('siren') ?>Pet emergency?</strong>
                    <p>Our hotline is open 24/7. Don't wait — call us right away.</p>
                    <a class="btn btn-white btn-sm" href="tel:<?= e(preg_replace('/\D+/', '', CLINIC_EMERGENCY)) ?>"><?= icon('phone-call') ?><?= e(CLINIC_EMERGENCY) ?></a>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <div class="main-area">
        <header class="app-topbar">
            <button class="icon-btn menu-btn" type="button" data-sidebar-toggle aria-label="Open menu"><?= icon('menu') ?></button>
            <?php if ($isStaffUser): ?>
                <div class="topbar-search" data-global-search role="search">
                    <?= icon('search') ?>
                    <input class="input" type="search" placeholder="Search patients, clients or booking ref…" aria-label="Search patients, clients or bookings" autocomplete="off">
                    <span class="kbd">/</span>
                    <div class="search-results" data-search-results></div>
                </div>
            <?php elseif (!empty($nextVisit)): ?>
                <a class="next-visit-pill" href="<?= e(url('owner/appointments.php')) ?>"><?= pet_photo($nextVisit) ?><span class="hide-sm">Next visit:</span><strong><?= e($nextVisit['pet_name']) ?> · <?= e(appt_when($nextVisit)) ?></strong></a>
            <?php else: ?>
                <div class="topbar-greeting"><?= icon('paw-print') ?><span><?= e(greeting()) ?>, <?= e($me['first_name']) ?>!</span></div>
            <?php endif; ?>
            <div class="topbar-actions">
                <a class="icon-btn hide-xs" href="<?= e(url()) ?>" title="View website" aria-label="View website"><?= icon('globe') ?></a>
                <button class="icon-btn" type="button" data-theme-toggle title="Toggle dark mode" aria-label="Toggle dark mode"><span class="theme-icon-light"><?= icon('moon') ?></span><span class="theme-icon-dark"><?= icon('sun') ?></span></button>
                <div class="dropdown" data-dropdown data-notifications>
                    <button class="icon-btn" type="button" data-dropdown-toggle aria-label="Notifications" aria-expanded="false"><?= icon('bell') ?><span class="dot-count" data-notif-count data-value="<?= $unread ?>"<?= $unread ? '' : ' hidden' ?>><?= $unread > 9 ? '9+' : $unread ?></span></button>
                    <div class="dropdown-menu notif-menu">
                        <div class="notif-head"><strong>Notifications</strong><button type="button" data-notif-read-all>Mark all as read</button></div>
                        <div class="notif-list">
                            <?php foreach ($myNotifications as $n): ?>
                                <a class="notif-item notif-type-<?= e($n['type']) ?><?= $n['is_read'] ? '' : ' unread' ?>" href="<?= e(url($n['link'] ?: 'notifications.php')) ?>" data-notif-id="<?= (int) $n['id'] ?>">
                                    <span class="notif-icon"><?= icon(notification_icon($n['type'])) ?></span>
                                    <div><strong><?= e($n['title']) ?></strong><p><?= e(excerpt($n['message'], 110)) ?></p><time><?= e(time_ago($n['created_at'])) ?></time></div>
                                </a>
                            <?php endforeach; ?>
                            <?php if (!$myNotifications): ?><div class="sr-empty">You're all caught up!</div><?php endif; ?>
                        </div>
                        <a class="notif-foot" href="<?= e(url('notifications.php')) ?>">View all notifications</a>
                    </div>
                </div>
                <div class="dropdown" data-dropdown>
                    <button class="user-chip" type="button" data-dropdown-toggle aria-expanded="false" aria-label="Account menu">
                        <?= avatar(full_name($me)) ?>
                        <span class="uc-text"><strong><?= e(full_name($me)) ?></strong><span><?= e(role_label($me['role'])) ?></span></span>
                        <?= icon('chevron-down') ?>
                    </button>
                    <div class="dropdown-menu">
                        <div class="menu-head"><strong><?= e(full_name($me)) ?></strong><span><?= e($me['email']) ?></span></div>
                        <a class="menu-item" href="<?= e(url('account.php')) ?>"><?= icon('user-round') ?>My account</a>
                        <a class="menu-item" href="<?= e(url('notifications.php')) ?>"><?= icon('bell') ?>Notifications</a>
                        <a class="menu-item" href="<?= e(url()) ?>"><?= icon('globe') ?>Visit website</a>
                        <div class="menu-sep"></div>
                        <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="menu-item danger" type="submit" data-no-loading><?= icon('log-out') ?>Log out</button></form>
                    </div>
                </div>
            </div>
        </header>
        <main class="app-content" id="main">
