<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();
$today = date('Y-m-d');
$date = preg_match('/^\d{4}-\d{2}-\d{2}$/', param('date')) && strtotime(param('date')) ? param('date') : $today;
$hours = clinic_hours_for($date);
$vets = rows('SELECT * FROM vets WHERE is_active = 1 ORDER BY id');
$appts = rows(appointment_sql() . " WHERE a.appointment_date = ? AND a.status <> 'cancelled' ORDER BY a.start_time", [$date]);
$byVet = [];
foreach ($appts as $a) {
    $byVet[(int) $a['vet_id']][] = $a;
}
$statusCounts = array_count_values(array_column($appts, 'status'));
$row = 46; // px per 30 minutes (matches --row in app.css)
$open = $hours ? t2m($hours[0]) : 0;
$close = $hours ? t2m($hours[1]) : 0;
$px = fn (int $minutes): int => (int) round(($minutes - $open) / 30 * $row);
$prev = date('Y-m-d', strtotime("$date -1 day"));
$next = date('Y-m-d', strtotime("$date +1 day"));

$pageTitle = 'Day schedule';
$activeNav = 'schedule';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>Day schedule</span></div>
        <h1><?= e(fmt_date($date, 'l, F j')) ?><?= $date === $today ? ' <span class="badge badge-coral">Today</span>' : '' ?></h1>
        <p><?= $hours ? e(fmt_time($hours[0]) . ' – ' . fmt_time($hours[1])) . ' · ' . plural(count($appts), 'appointment') : 'Clinic closed' ?></p>
    </div>
    <form class="page-actions date-nav" method="get">
        <a class="btn btn-outline btn-icon" href="?date=<?= e($prev) ?>" aria-label="Previous day"><?= icon('chevron-left') ?></a>
        <a class="btn btn-outline" href="?date=<?= e($today) ?>">Today</a>
        <a class="btn btn-outline btn-icon" href="?date=<?= e($next) ?>" aria-label="Next day"><?= icon('chevron-right') ?></a>
        <input class="input" type="date" name="date" value="<?= e($date) ?>" data-autosubmit aria-label="Pick a date">
        <a class="btn btn-primary" href="<?= e(url('admin/appointment-new.php?date=' . $date)) ?>"><?= icon('plus') ?>Book</a>
    </form>
</div>

<?php if (!$hours): ?>
    <div class="card"><?= empty_state('moon', 'The clinic is closed on ' . weekday_name((int) date('N', strtotime($date))) . 's', 'Only emergency calls are handled today. Use the arrows to view another day.') ?></div>
<?php else: ?>
    <div class="card">
        <div class="card-head">
            <div class="legend-inline">
                <span><i class="sb-pending"></i>Pending <?= (int) ($statusCounts['pending'] ?? 0) ?></span>
                <span><i class="sb-confirmed"></i>Confirmed <?= (int) ($statusCounts['confirmed'] ?? 0) ?></span>
                <span><i class="sb-completed"></i>Completed <?= (int) ($statusCounts['completed'] ?? 0) ?></span>
                <span><i class="sb-no_show"></i>No-show <?= (int) ($statusCounts['no_show'] ?? 0) ?></span>
            </div>
            <span class="muted text-sm">Click an appointment to open it</span>
        </div>
        <div class="sched-wrap">
            <div class="sched" style="--cols:<?= count($vets) ?>">
                <div class="sched-corner"></div>
                <?php foreach ($vets as $v): $on = vet_works_on($v, $date); ?>
                    <div class="sched-head"><?= vet_photo($v) ?><div><strong><?= e($v['name']) ?></strong><span><?= $on ? plural(count($byVet[(int) $v['id']] ?? []), 'appointment') : 'Off duty' ?></span></div></div>
                <?php endforeach; ?>

                <div class="sched-times" style="height:<?= $px($close) ?>px">
                    <?php for ($m = $open; $m < $close; $m += 30): ?><div class="sched-time"><?= e(fmt_time(m2t($m))) ?></div><?php endfor; ?>
                </div>
                <?php foreach ($vets as $v): $on = vet_works_on($v, $date); ?>
                    <div class="sched-col<?= $on ? '' : ' off' ?>" style="height:<?= $px($close) ?>px">
                        <?php if (!$on): ?>
                            <div class="sched-off"><?= icon('moon') ?>&nbsp;Off duty</div>
                        <?php else: ?>
                            <?php if (LUNCH_BREAK): ?><div class="lunch-band" style="top:<?= $px(t2m(LUNCH_BREAK[0])) ?>px;height:<?= $px(t2m(LUNCH_BREAK[1])) - $px(t2m(LUNCH_BREAK[0])) ?>px">Lunch break</div><?php endif; ?>
                            <?php foreach ($byVet[(int) $v['id']] ?? [] as $a): $top = $px(t2m($a['start_time'])); $h = $px(t2m($a['end_time'])) - $top - 4; ?>
                                <a class="sched-block sb-<?= e($a['status']) ?>" style="top:<?= $top + 2 ?>px;height:<?= max(28, $h) ?>px" href="<?= e(url('admin/appointment.php?id=' . $a['id'])) ?>" title="<?= e($a['pet_name'] . ' · ' . $a['service_name'] . ' · ' . status_label($a['status'])) ?>">
                                    <?= pet_photo($a) ?>
                                    <div style="min-width:0"><strong><?= e($a['pet_name']) ?> · <?= e(fmt_time($a['start_time'])) ?></strong><span><?= e($a['service_name']) ?></span><?php if ($h > 60): ?><span><?= e($a['owner_first'] . ' ' . $a['owner_last']) ?></span><?php endif; ?></div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <?php $nowM = t2m(date('H:i')); if ($date === $today && $nowM >= $open && $nowM <= $close): ?>
                    <div class="now-line" style="top:<?= 70 + $px($nowM) ?>px" title="Now"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
