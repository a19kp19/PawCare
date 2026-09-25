<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();
$admin = is_admin($me);
$today = date('Y-m-d');

$todayAppts = rows(appointment_sql() . " WHERE a.appointment_date = ? AND a.status <> 'cancelled' ORDER BY a.start_time", [$today]);
$todayDone = count(array_filter($todayAppts, fn ($a) => $a['status'] === 'completed'));
$pending = rows(appointment_sql() . " WHERE a.status = 'pending' AND a.appointment_date >= ? ORDER BY a.appointment_date, a.start_time LIMIT 6", [$today]);
$pendingCount = (int) val("SELECT COUNT(*) FROM appointments WHERE status = 'pending' AND appointment_date >= ?", [$today]);
$petCount = (int) val('SELECT COUNT(*) FROM pets');
$newPets = (int) val('SELECT COUNT(*) FROM pets WHERE created_at >= ?', [date('Y-m-01')]);
$monthStart = date('Y-m-01');
$prevStart = date('Y-m-01', strtotime('first day of last month'));
$prevEnd = date('Y-m-d', min(strtotime($prevStart . ' +' . (date('j') - 1) . ' days'), strtotime('last day of last month')));
$revenueSql = "SELECT COALESCE(SUM(price), 0) FROM appointments WHERE status = 'completed' AND appointment_date BETWEEN ? AND ?";
$revenue = (float) val($revenueSql, [$monthStart, $today]);
$revenuePrev = (float) val($revenueSql, [$prevStart, $prevEnd]);
$revenueChange = $revenuePrev > 0 ? (int) round(($revenue - $revenuePrev) / $revenuePrev * 100) : null;
$dueSoon = rows(
    'SELECT x.*, p.name AS pet_name, p.species, p.photo AS pet_photo, u.first_name, u.last_name FROM (' . latest_vaccines_sql() . ') x
     JOIN pets p ON p.id = x.pet_id JOIN users u ON u.id = p.owner_id
     WHERE x.next_due_date BETWEEN ? AND ? ORDER BY x.next_due_date LIMIT 6',
    [date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('+14 days'))]
);
$activity = rows('SELECT * FROM activity_log ORDER BY created_at DESC, id DESC LIMIT 6');

// Chart: appointment activity (past 2 weeks + next week)
$from = date('Y-m-d', strtotime('-13 days'));
$to = date('Y-m-d', strtotime('+7 days'));
$byDay = [];
foreach (rows('SELECT appointment_date AS d, status, COUNT(*) AS c FROM appointments WHERE appointment_date BETWEEN ? AND ? GROUP BY appointment_date, status', [$from, $to]) as $r) {
    $byDay[$r['d']][$r['status']] = (int) $r['c'];
}
$labels = $done = $upcoming = $missed = [];
for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) {
    $d = date('Y-m-d', $t);
    $labels[] = $d === $today ? 'Today' : date('M j', $t);
    $done[] = $byDay[$d]['completed'] ?? 0;
    $upcoming[] = ($byDay[$d]['pending'] ?? 0) + ($byDay[$d]['confirmed'] ?? 0);
    $missed[] = ($byDay[$d]['cancelled'] ?? 0) + ($byDay[$d]['no_show'] ?? 0);
}

// Chart: services mix (last 30 days)
$catColors = ['Wellness' => '#14a697', 'Medical' => '#ff7a59', 'Diagnostics' => '#7c5cff', 'Surgery' => '#3b82f6', 'Dental' => '#f5b301', 'Grooming' => '#22c55e'];
$mix = rows("SELECT s.category, COUNT(*) AS c FROM appointments a JOIN services s ON s.id = a.service_id
             WHERE a.appointment_date BETWEEN ? AND ? AND a.status <> 'cancelled' GROUP BY s.category ORDER BY c DESC", [date('Y-m-d', strtotime('-30 days')), $today]);
$mixTotal = array_sum(array_column($mix, 'c')) ?: 1;

// Chart: weekly revenue (admin) or species (staff)
$weeks = [];
$weekStart = strtotime('monday this week', strtotime('-11 weeks'));
foreach (rows("SELECT YEARWEEK(appointment_date, 1) AS wk, SUM(price) AS total FROM appointments WHERE status = 'completed' AND appointment_date >= ? GROUP BY wk", [date('Y-m-d', $weekStart)]) as $r) {
    $weeks[(string) $r['wk']] = (float) $r['total'];
}
$wLabels = $wValues = [];
for ($i = 0; $i < 12; $i++) {
    $ws = strtotime("+$i week", $weekStart);
    $wLabels[] = date('M j', $ws);
    $wValues[] = $weeks[date('oW', $ws)] ?? 0;
}
$species = rows('SELECT species, COUNT(*) AS c FROM pets GROUP BY species ORDER BY c DESC');
$speciesColors = ['#ff9a5c', '#7c5cff', '#ec4899', '#22c55e', '#f5b301', '#14a697', '#94a3b8'];

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$useCharts = true;
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><?= icon('calendar-days') ?><span><?= e(date('l, F j, Y')) ?></span></div>
        <h1><?= e(greeting()) ?>, <?= e($me['first_name']) ?> <span aria-hidden="true">👋</span></h1>
        <p>Here's what's happening at <?= e(CLINIC_SHORT_NAME) ?> today.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('admin/schedule.php')) ?>"><?= icon('calendar-days') ?>Day schedule</a>
        <a class="btn btn-primary" href="<?= e(url('admin/appointment-new.php')) ?>"><?= icon('plus') ?>New appointment</a>
    </div>
</div>

<div class="kpi-grid">
    <?= kpi_card("Today's appointments", (string) count($todayAppts), 'calendar-check', 'teal', $todayDone . ' completed · ' . (count($todayAppts) - $todayDone) . ' remaining', 'admin/schedule.php',
        '<div class="mini-progress"><span style="width:' . (count($todayAppts) ? round($todayDone / count($todayAppts) * 100) : 0) . '%"></span></div>') ?>
    <?= kpi_card('Pending requests', (string) $pendingCount, 'hourglass', 'sun', $pendingCount ? 'Waiting for confirmation' : 'All caught up', 'admin/appointments.php?status=pending') ?>
    <?= kpi_card('Registered patients', (string) $petCount, 'paw-print', 'violet', '<span class="trend-up">' . icon('trending-up') . '+' . $newPets . '</span> new this month', 'admin/patients.php') ?>
    <?php if ($admin): ?>
        <?= kpi_card('Revenue this month', e(money($revenue)), 'philippine-peso', 'coral',
            $revenueChange === null ? 'Completed visits only' : '<span class="' . ($revenueChange >= 0 ? 'trend-up' : 'trend-down') . '">' . icon($revenueChange >= 0 ? 'trending-up' : 'trending-down') . ($revenueChange >= 0 ? '+' : '') . $revenueChange . '%</span> vs same period last month', 'admin/reports.php') ?>
    <?php else: ?>
        <?= kpi_card('Vaccines due soon', (string) count($dueSoon), 'syringe', 'coral', 'Due within 14 days or overdue', 'admin/reminders.php') ?>
    <?php endif; ?>
</div>

<div class="grid cols-12">
    <div class="card span-8" data-reveal>
        <div class="card-head"><div><h2><?= icon('chart-column') ?>Appointment activity</h2><p>Past two weeks and the week ahead</p></div>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/appointments.php')) ?>">View all <?= icon('arrow-right') ?></a></div>
        <div class="card-body">
            <?= chart_box(['type' => 'bar', 'stacked' => true, 'legend' => true, 'aria' => 'Appointments per day', 'labels' => $labels, 'datasets' => [
                ['label' => 'Completed', 'data' => $done, 'color' => '#14a697'],
                ['label' => 'Upcoming', 'data' => $upcoming, 'color' => '#60a5fa'],
                ['label' => 'Cancelled / no-show', 'data' => $missed, 'color' => '#ff9a7f'],
            ]]) ?>
        </div>
    </div>

    <div class="card span-4" data-reveal>
        <div class="card-head"><div><h2><?= icon('clock') ?>Today's schedule</h2><p><?= plural(count($todayAppts), 'appointment') ?></p></div><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/schedule.php')) ?>">Open</a></div>
        <?php if ($todayAppts): ?>
            <div class="list" style="max-height:360px;overflow-y:auto">
                <?php foreach ($todayAppts as $a): $isNow = time() >= strtotime($today . ' ' . $a['start_time']) && time() < strtotime($today . ' ' . $a['end_time']); ?>
                    <a class="list-item" href="<?= e(url('admin/appointment.php?id=' . $a['id'])) ?>">
                        <span class="time-pill<?= $isNow ? ' now' : '' ?>"><?= e(fmt_time($a['start_time'])) ?></span>
                        <?= pet_photo($a, 'size-sm') ?>
                        <div class="li-main"><div class="li-title"><?= e($a['pet_name']) ?> · <?= e($a['service_name']) ?></div><div class="li-sub"><?= e($a['owner_first'] . ' ' . $a['owner_last']) ?> · <?= e($a['vet_name'] ?? 'Unassigned') ?></div></div>
                        <?= status_badge($a['status'], true) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?= empty_state('calendar-days', clinic_hours_for($today) ? 'No appointments today' : 'The clinic is closed today', 'Enjoy the quiet — or book a walk-in.') ?>
        <?php endif; ?>
    </div>

    <div class="card span-8" data-reveal>
        <div class="card-head"><div><h2><?= icon('hourglass') ?>Booking requests</h2><p>Confirm or decline new online bookings</p></div><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/appointments.php?status=pending')) ?>">All pending <?= icon('arrow-right') ?></a></div>
        <?php if ($pending): ?>
            <div class="list">
                <?php foreach ($pending as $a): ?>
                    <div class="list-item">
                        <?= date_block($a['appointment_date']) ?>
                        <?= pet_photo($a, 'size-sm') ?>
                        <div class="li-main">
                            <a class="li-title" href="<?= e(url('admin/appointment.php?id=' . $a['id'])) ?>"><?= e($a['pet_name']) ?> · <?= e($a['service_name']) ?></a>
                            <div class="li-sub"><?= e(fmt_time($a['start_time'])) ?> · <?= e($a['owner_first'] . ' ' . $a['owner_last']) ?> · <?= e($a['vet_name'] ?? 'Any vet') ?> · requested <?= e(time_ago($a['created_at'])) ?></div>
                        </div>
                        <div class="li-end">
                            <form method="post" action="<?= e(url('admin/appointments.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-primary btn-xs" name="status" value="confirmed" type="submit"><?= icon('check') ?>Confirm</button></form>
                            <form method="post" action="<?= e(url('admin/appointments.php')) ?>" data-confirm="Decline <?= e($a['pet_name']) ?>'s <?= e($a['service_name']) ?> request? The owner will be notified." data-confirm-ok="Decline request"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><input type="hidden" name="reason" value="Declined by the clinic — please choose another time"><button class="btn btn-danger-soft btn-xs" name="status" value="cancelled" type="submit"><?= icon('x') ?>Decline</button></form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?= empty_state('circle-check', 'No pending requests', 'New online bookings will appear here for confirmation.') ?>
        <?php endif; ?>
    </div>

    <div class="card span-4" data-reveal>
        <div class="card-head"><div><h2><?= icon('syringe') ?>Vaccines due</h2><p>Overdue or due within 14 days</p></div><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/reminders.php')) ?>">Reminders</a></div>
        <?php if ($dueSoon): ?>
            <div class="list">
                <?php foreach ($dueSoon as $v): $st = vaccine_status($v['next_due_date']); ?>
                    <div class="list-item">
                        <?= pet_photo($v, 'size-sm') ?>
                        <div class="li-main"><a class="li-title" href="<?= e(url('admin/patient.php?id=' . $v['pet_id'] . '#vaccines')) ?>"><?= e($v['pet_name']) ?> · <?= e($v['vaccine_name']) ?></a><div class="li-sub"><span class="text-<?= $st['key'] === 'overdue' ? 'red' : 'amber' ?> fw-700"><?= e($st['label']) ?></span> · <?= e($v['first_name'] . ' ' . $v['last_name']) ?></div></div>
                        <form method="post" action="<?= e(url('admin/reminders.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="send_vaccines"><input type="hidden" name="ids[]" value="<?= (int) $v['id'] ?>"><button class="btn btn-soft btn-xs" type="submit" title="<?= $v['reminded_at'] ? 'Last reminded ' . e(time_ago($v['reminded_at'])) : 'Send an in-app reminder' ?>"><?= icon($v['reminded_at'] ? 'check' : 'bell-ring') ?><?= $v['reminded_at'] ? 'Sent' : 'Remind' ?></button></form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?= empty_state('shield-check', 'Nothing due', 'All patients are up to date for the next two weeks.') ?>
        <?php endif; ?>
    </div>

    <div class="card span-4" data-reveal>
        <div class="card-head"><div><h2><?= icon('chart-pie') ?>Service mix</h2><p>Last 30 days</p></div></div>
        <div class="card-body">
            <?= chart_box(['type' => 'doughnut', 'aria' => 'Service mix', 'labels' => array_column($mix, 'category'), 'datasets' => [['label' => 'Visits', 'data' => array_map('intval', array_column($mix, 'c')), 'colors' => array_map(fn ($c) => $catColors[$c] ?? '#94a3b8', array_column($mix, 'category'))]]], 'xs') ?>
            <div class="legend">
                <?php foreach ($mix as $m): ?>
                    <div class="legend-row"><span class="sw" style="background:<?= e($catColors[$m['category']] ?? '#94a3b8') ?>"></span><?= e($m['category']) ?><strong><?= round($m['c'] / $mixTotal * 100) ?>%</strong></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card span-4" data-reveal>
        <?php if ($admin): ?>
            <div class="card-head"><div><h2><?= icon('chart-line') ?>Weekly revenue</h2><p>Completed visits · last 12 weeks</p></div></div>
            <div class="card-body"><?= chart_box(['type' => 'line', 'money' => true, 'aria' => 'Weekly revenue', 'labels' => $wLabels, 'datasets' => [['label' => 'Revenue', 'data' => $wValues, 'color' => '#ff7a59']]], 'tall') ?></div>
        <?php else: ?>
            <div class="card-head"><div><h2><?= icon('paw-print') ?>Patients by species</h2><p>All registered pets</p></div></div>
            <div class="card-body"><?= chart_box(['type' => 'doughnut', 'legend' => true, 'legendPosition' => 'bottom', 'aria' => 'Patients by species', 'labels' => array_column($species, 'species'), 'datasets' => [['label' => 'Pets', 'data' => array_map('intval', array_column($species, 'c')), 'colors' => array_slice($speciesColors, 0, count($species))]]], 'tall') ?></div>
        <?php endif; ?>
    </div>

    <div class="card span-4" data-reveal>
        <div class="card-head"><div><h2><?= icon('activity') ?>Recent activity</h2><p>Across the clinic</p></div></div>
        <div class="activity">
            <?php foreach ($activity as $act): ?>
                <a class="activity-item" href="<?= e(url($act['link'] ?: 'admin/')) ?>">
                    <span class="activity-icon"><?= icon($act['icon']) ?></span>
                    <div><p><?= e($act['action']) ?></p><time><?= e(time_ago($act['created_at'])) ?></time></div>
                </a>
            <?php endforeach; ?>
            <?php if (!$activity): ?><p class="muted text-sm">No activity yet.</p><?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
