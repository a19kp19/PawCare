<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();
$today = date('Y-m-d');
$presets = [
    'month'      => ['This month', date('Y-m-01'), $today],
    'last_month' => ['Last month', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    '30'         => ['Last 30 days', date('Y-m-d', strtotime('-29 days')), $today],
    '90'         => ['Last 90 days', date('Y-m-d', strtotime('-89 days')), $today],
    'year'       => ['This year', date('Y-01-01'), $today],
];
$range = param('range') ?: 'month';
$isDate = fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) && strtotime($d);
if ($range === 'custom' && $isDate(param('from')) && $isDate(param('to'))) {
    [$from, $to] = [param('from'), param('to')];
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $label = fmt_date($from) . ' – ' . fmt_date($to);
} else {
    $range = isset($presets[$range]) ? $range : 'month';
    [$label, $from, $to] = $presets[$range];
}
$span = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
$params = [$from, $to];

$sum = row("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'completed'), 0) AS completed, COALESCE(SUM(status = 'cancelled'), 0) AS cancelled,
                   COALESCE(SUM(status = 'no_show'), 0) AS no_show, COALESCE(SUM(status IN ('pending','confirmed')), 0) AS scheduled,
                   COALESCE(SUM(CASE WHEN status = 'completed' THEN price END), 0) AS revenue
            FROM appointments WHERE appointment_date BETWEEN ? AND ?", $params);
$newClients = (int) val("SELECT COUNT(*) FROM users WHERE role = 'owner' AND created_at BETWEEN ? AND ?", [$from . ' 00:00:00', $to . ' 23:59:59']);
$newPets = (int) val('SELECT COUNT(*) FROM pets WHERE created_at BETWEEN ? AND ?', [$from . ' 00:00:00', $to . ' 23:59:59']);
$avg = $sum['completed'] ? $sum['revenue'] / $sum['completed'] : 0;
$lossRate = $sum['total'] ? round(($sum['cancelled'] + $sum['no_show']) / $sum['total'] * 100) : 0;

// revenue & visits over time (daily for short ranges, monthly otherwise)
$monthly = $span > 62;
$bucket = $monthly ? "DATE_FORMAT(appointment_date, '%Y-%m')" : 'appointment_date';
$series = [];
foreach (rows("SELECT $bucket AS b, COALESCE(SUM(status = 'completed'), 0) AS visits, COALESCE(SUM(CASE WHEN status = 'completed' THEN price END), 0) AS revenue
               FROM appointments WHERE appointment_date BETWEEN ? AND ? GROUP BY b", $params) as $r) {
    $series[$r['b']] = $r;
}
$labels = $revSeries = $visitSeries = [];
for ($t = strtotime($monthly ? date('Y-m-01', strtotime($from)) : $from); $t <= strtotime($to); $t = strtotime($monthly ? '+1 month' : '+1 day', $t)) {
    $key = $monthly ? date('Y-m', $t) : date('Y-m-d', $t);
    $labels[] = $monthly ? date('M Y', $t) : date('M j', $t);
    $revSeries[] = (float) ($series[$key]['revenue'] ?? 0);
    $visitSeries[] = (int) ($series[$key]['visits'] ?? 0);
}

$byService = rows("SELECT s.name, s.category, COUNT(*) AS bookings, COALESCE(SUM(a.status = 'completed'), 0) AS completed,
                          COALESCE(SUM(CASE WHEN a.status = 'completed' THEN a.price END), 0) AS revenue
                   FROM appointments a JOIN services s ON s.id = a.service_id WHERE a.appointment_date BETWEEN ? AND ?
                   GROUP BY s.id, s.name, s.category ORDER BY revenue DESC, bookings DESC", $params);
$byVet = rows("SELECT v.name, COUNT(*) AS total, COALESCE(SUM(a.status = 'completed'), 0) AS completed, COALESCE(SUM(a.status = 'no_show'), 0) AS no_show,
                      COALESCE(SUM(CASE WHEN a.status = 'completed' THEN a.price END), 0) AS revenue
               FROM appointments a JOIN vets v ON v.id = a.vet_id WHERE a.appointment_date BETWEEN ? AND ?
               GROUP BY v.id, v.name ORDER BY revenue DESC", $params);
$bySpecies = rows("SELECT p.species, COUNT(DISTINCT a.pet_id) AS pets FROM appointments a JOIN pets p ON p.id = a.pet_id
                   WHERE a.appointment_date BETWEEN ? AND ? AND a.status = 'completed' GROUP BY p.species ORDER BY pets DESC", $params);
$catColors = ['Wellness' => '#14a697', 'Medical' => '#ff7a59', 'Diagnostics' => '#7c5cff', 'Surgery' => '#3b82f6', 'Dental' => '#f5b301', 'Grooming' => '#22c55e'];
$topServices = array_slice($byService, 0, 8);
$revenueTotal = (float) $sum['revenue'] ?: 1;

$pageTitle = 'Reports';
$activeNav = 'reports';
$useCharts = true;
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="print-only report-head-print">
    <strong style="font-size:1.2rem"><?= e(CLINIC_NAME) ?> — Clinic performance report</strong><br>
    <span class="muted"><?= e($label) ?> · generated <?= e(date('M j, Y g:i A')) ?> by <?= e(full_name($me)) ?></span><hr>
</div>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>Reports</span></div>
        <h1>Reports &amp; analytics</h1>
        <p><?= e($label) ?> · <?= e(fmt_date($from)) ?> to <?= e(fmt_date($to)) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('admin/export.php?type=appointments&from=' . $from . '&to=' . $to)) ?>"><?= icon('file-down') ?>Export CSV</a>
        <button class="btn btn-primary" type="button" data-print><?= icon('printer') ?>Print report</button>
    </div>
</div>

<form class="card filters mb-3" method="get" style="border-bottom:1px solid var(--line)">
    <div class="tabs">
        <?php foreach ($presets as $key => [$name]): ?><a class="tab<?= $range === $key ? ' active' : '' ?>" href="?range=<?= $key ?>"><?= e($name) ?></a><?php endforeach; ?>
    </div>
    <input type="hidden" name="range" value="custom">
    <input class="input" type="date" name="from" value="<?= e($from) ?>" aria-label="From">
    <span class="muted">to</span>
    <input class="input" type="date" name="to" value="<?= e($to) ?>" max="<?= e(date('Y-m-d', strtotime('+60 days'))) ?>" aria-label="To">
    <button class="btn btn-soft btn-sm" type="submit"><?= icon('calendar-range') ?>Apply</button>
</form>

<div class="kpi-grid">
    <?= kpi_card('Revenue', e(money($sum['revenue'])), 'philippine-peso', 'teal', 'From ' . plural((int) $sum['completed'], 'completed visit')) ?>
    <?= kpi_card('Average per visit', e(money($avg)), 'receipt', 'violet', 'Completed visits only') ?>
    <?= kpi_card('Appointments', (string) (int) $sum['total'], 'calendar-check', 'blue', (int) $sum['scheduled'] . ' still scheduled') ?>
    <?= kpi_card('Cancelled + no-show', $lossRate . '%', 'calendar-x', 'coral', (int) $sum['cancelled'] . ' cancelled · ' . (int) $sum['no_show'] . ' no-show') ?>
</div>

<div class="grid cols-12">
    <div class="card span-8">
        <div class="card-head"><div><h2><?= icon('chart-column') ?>Revenue &amp; visits</h2><p><?= $monthly ? 'Monthly' : 'Daily' ?> totals for completed visits</p></div></div>
        <div class="card-body"><?= chart_box(['type' => 'bar', 'money' => true, 'y1' => true, 'legend' => true, 'aria' => 'Revenue and visits over time', 'labels' => $labels, 'datasets' => [
            ['label' => 'Revenue', 'data' => $revSeries, 'color' => '#14a697', 'yAxisID' => 'y', 'order' => 2],
            ['label' => 'Visits', 'type' => 'line', 'data' => $visitSeries, 'color' => '#ff7a59', 'yAxisID' => 'y1', 'fill' => false, 'order' => 1],
        ]]) ?></div>
    </div>
    <div class="card span-4">
        <div class="card-head"><div><h2><?= icon('chart-pie') ?>Appointment outcomes</h2><p><?= plural((int) $sum['total'], 'booking') ?></p></div></div>
        <div class="card-body">
            <?= chart_box(['type' => 'doughnut', 'aria' => 'Appointment outcomes', 'labels' => ['Completed', 'Scheduled', 'Cancelled', 'No-show'], 'datasets' => [['label' => 'Appointments', 'data' => [(int) $sum['completed'], (int) $sum['scheduled'], (int) $sum['cancelled'], (int) $sum['no_show']], 'colors' => ['#14a697', '#60a5fa', '#cbd5e1', '#ff7a59']]]], 'xs') ?>
            <div class="legend">
                <?php foreach ([['Completed', $sum['completed'], '#14a697'], ['Scheduled', $sum['scheduled'], '#60a5fa'], ['Cancelled', $sum['cancelled'], '#cbd5e1'], ['No-show', $sum['no_show'], '#ff7a59']] as [$n, $c, $col]): ?>
                    <div class="legend-row"><span class="sw" style="background:<?= $col ?>"></span><?= $n ?><strong><?= (int) $c ?></strong></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="card span-7">
        <div class="card-head"><div><h2><?= icon('clipboard-list') ?>Revenue by service</h2><p>Top <?= count($topServices) ?> services</p></div></div>
        <div class="card-body"><?= chart_box(['type' => 'bar', 'horizontal' => true, 'money' => true, 'aria' => 'Revenue by service', 'labels' => array_column($topServices, 'name'), 'datasets' => [['label' => 'Revenue', 'data' => array_map('floatval', array_column($topServices, 'revenue')), 'color' => '#7c5cff']]]) ?></div>
    </div>
    <div class="card span-5">
        <div class="card-head"><div><h2><?= icon('stethoscope') ?>Visits by veterinarian</h2><p>Completed vs. no-show</p></div></div>
        <div class="card-body"><?= chart_box(['type' => 'bar', 'stacked' => true, 'legend' => true, 'aria' => 'Visits by veterinarian', 'labels' => array_map(fn ($n) => preg_replace('/^Dr\.\s*/', 'Dr. ', explode(' ', $n)[0] . ' ' . (explode(' ', $n)[1] ?? '')), array_column($byVet, 'name')), 'datasets' => [
            ['label' => 'Completed', 'data' => array_map('intval', array_column($byVet, 'completed')), 'color' => '#14a697'],
            ['label' => 'No-show', 'data' => array_map('intval', array_column($byVet, 'no_show')), 'color' => '#ff9a7f'],
        ]]) ?></div>
    </div>

    <div class="card span-8">
        <div class="card-head"><h2><?= icon('list-checks') ?>Service performance</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Service</th><th>Category</th><th class="num">Bookings</th><th class="num">Completed</th><th class="num">Revenue</th><th style="width:22%">Share of revenue</th></tr></thead>
            <tbody>
            <?php foreach ($byService as $s): $share = round($s['revenue'] / $revenueTotal * 100, 1); ?>
                <tr>
                    <td class="fw-700"><?= e($s['name']) ?></td>
                    <td><span class="badge badge-teal"><?= e($s['category']) ?></span></td>
                    <td class="num"><?= (int) $s['bookings'] ?></td>
                    <td class="num"><?= (int) $s['completed'] ?></td>
                    <td class="num fw-700"><?= money($s['revenue']) ?></td>
                    <td><div class="progress-row" style="--c:<?= e($catColors[$s['category']] ?? '#14a697') ?>"><span class="text-sm muted"><?= $share ?>%</span><span></span><div class="bar"><span style="width:<?= min(100, $share) ?>%"></span></div></div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$byService): ?><tr><td colspan="6" class="muted text-center">No appointments in this period.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>
    <div class="stack span-4">
        <div class="card">
            <div class="card-head"><h2><?= icon('user-plus') ?>Growth</h2></div>
            <div class="progress-list">
                <div class="progress-row"><span>New clients</span><strong><?= $newClients ?></strong></div>
                <div class="progress-row"><span>New patients</span><strong><?= $newPets ?></strong></div>
                <div class="progress-row"><span>Unique patients seen</span><strong><?= (int) array_sum(array_column($bySpecies, 'pets')) ?></strong></div>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2><?= icon('paw-print') ?>Patients seen by species</h2></div>
            <div class="progress-list">
                <?php $maxSp = max(array_merge([1], array_map('intval', array_column($bySpecies, 'pets')))); ?>
                <?php foreach ($bySpecies as $sp): ?>
                    <div class="progress-row"><span><?= icon(species_icon($sp['species'])) ?> <?= e($sp['species']) ?></span><strong><?= (int) $sp['pets'] ?></strong><div class="bar"><span style="width:<?= round($sp['pets'] / $maxSp * 100) ?>%"></span></div></div>
                <?php endforeach; ?>
                <?php if (!$bySpecies): ?><p class="muted text-sm mb-0">No completed visits in this period.</p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card span-12">
        <div class="card-head"><h2><?= icon('stethoscope') ?>Veterinarian performance</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Veterinarian</th><th class="num">Appointments</th><th class="num">Completed</th><th class="num">No-shows</th><th class="num">No-show rate</th><th class="num">Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($byVet as $v): ?>
                <tr><td class="fw-700"><?= e($v['name']) ?></td><td class="num"><?= (int) $v['total'] ?></td><td class="num"><?= (int) $v['completed'] ?></td><td class="num"><?= (int) $v['no_show'] ?></td><td class="num"><?= $v['total'] ? round($v['no_show'] / $v['total'] * 100) : 0 ?>%</td><td class="num fw-700"><?= money($v['revenue']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
</div>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
