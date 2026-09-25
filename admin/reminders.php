<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();
$today = date('Y-m-d');

if (is_post()) {
    verify_csrf();
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
    $sent = 0;
    if (input('action') === 'send_vaccines' && $ids) {
        $in = implode(',', $ids);
        foreach (rows("SELECT x.*, p.name AS pet_name, p.owner_id FROM vaccinations x JOIN pets p ON p.id = x.pet_id WHERE x.id IN ($in)") as $v) {
            $st = vaccine_status($v['next_due_date']);
            $title = $st['key'] === 'overdue' ? "{$v['pet_name']}'s {$v['vaccine_name']} is overdue" : "{$v['pet_name']}'s {$v['vaccine_name']} is due soon";
            notify((int) $v['owner_id'], 'vaccine', $title, 'Due on ' . fmt_date($v['next_due_date']) . '. Book a vaccination visit in a few taps.', 'owner/book.php?pet=' . $v['pet_id'] . '&service=2');
            update('vaccinations', ['reminded_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $v['id']]);
            $sent++;
        }
        log_activity((int) $me['id'], full_name($me) . ' sent ' . plural($sent, 'vaccine reminder'), 'bell-ring', 'admin/reminders.php');
    }
    if (input('action') === 'send_appointments' && $ids) {
        $in = implode(',', $ids);
        foreach (rows(appointment_sql() . " WHERE a.id IN ($in) AND a.status IN ('pending','confirmed')") as $a) {
            notify((int) $a['owner_id'], 'reminder', 'Reminder: ' . $a['pet_name'] . "'s visit " . strtolower(relative_day($a['appointment_date'])), $a['service_name'] . ' on ' . appt_when($a) . ' with ' . ($a['vet_name'] ?? 'our team') . '. Please arrive 10 minutes early.', 'owner/appointments.php');
            update('appointments', ['reminded_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $a['id']]);
            $sent++;
        }
        log_activity((int) $me['id'], full_name($me) . ' sent ' . plural($sent, 'appointment reminder'), 'bell-ring', 'admin/reminders.php?tab=appointments');
    }
    $sent ? flash('success', plural($sent, 'reminder') . ' sent to pet owners.') : flash('warning', 'Select at least one item to remind.');
    redirect_back('admin/reminders.php');
}

$tab = param('tab') === 'appointments' ? 'appointments' : 'vaccines';
$vaccines = rows(
    'SELECT x.*, p.name AS pet_name, p.species, p.photo AS pet_photo, u.first_name, u.last_name, u.phone FROM (' . latest_vaccines_sql() . ') x
     JOIN pets p ON p.id = x.pet_id JOIN users u ON u.id = p.owner_id
     WHERE x.next_due_date BETWEEN ? AND ? ORDER BY x.next_due_date',
    [date('Y-m-d', strtotime('-90 days')), date('Y-m-d', strtotime('+' . REMINDER_WINDOW_DAYS . ' days'))]
);
$soon = rows(appointment_sql() . " WHERE a.status IN ('pending','confirmed') AND a.appointment_date BETWEEN ? AND ? ORDER BY a.appointment_date, a.start_time", [date('Y-m-d', strtotime('+1 day')), date('Y-m-d', strtotime('+2 days'))]);
$overdue = count(array_filter($vaccines, fn ($v) => $v['next_due_date'] < $today));

$pageTitle = 'Reminders';
$activeNav = 'reminders';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>Reminders</span></div>
        <h1>Reminders</h1>
        <p>Send in-app reminders for upcoming boosters and visits. Owners see them instantly in their portal.</p>
    </div>
</div>
<div class="kpi-grid">
    <?= kpi_card('Vaccines overdue', (string) $overdue, 'triangle-alert', 'coral', 'Up to 90 days past due') ?>
    <?= kpi_card('Due within ' . REMINDER_WINDOW_DAYS . ' days', (string) (count($vaccines) - $overdue), 'syringe', 'sun') ?>
    <?= kpi_card('Visits in the next 2 days', (string) count($soon), 'calendar-clock', 'blue') ?>
    <?= kpi_card('Reminders sent today', (string) (int) val('SELECT (SELECT COUNT(*) FROM vaccinations WHERE reminded_at >= ?) + (SELECT COUNT(*) FROM appointments WHERE reminded_at >= ?)', [$today, $today]), 'bell-ring', 'green') ?>
</div>
<div class="tabs mb-2">
    <a class="tab<?= $tab === 'vaccines' ? ' active' : '' ?>" href="?tab=vaccines"><?= icon('syringe') ?>Vaccines due <span class="count"><?= count($vaccines) ?></span></a>
    <a class="tab<?= $tab === 'appointments' ? ' active' : '' ?>" href="?tab=appointments"><?= icon('calendar-clock') ?>Upcoming visits <span class="count"><?= count($soon) ?></span></a>
</div>

<?php if ($tab === 'vaccines'): ?>
    <form class="card" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="send_vaccines">
        <div class="card-head"><div><h2><?= icon('syringe') ?>Boosters due or overdue</h2><p>Latest dose of each vaccine, per pet</p></div><button class="btn btn-primary btn-sm" type="submit"<?= $vaccines ? '' : ' disabled' ?>><?= icon('send') ?>Send to selected</button></div>
        <?php if ($vaccines): ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th style="width:40px"><input type="checkbox" data-check-all=".vax-check" aria-label="Select all" checked></th><th>Patient</th><th>Owner</th><th>Vaccine</th><th>Due</th><th>Status</th><th>Last reminder</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($vaccines as $v): $st = vaccine_status($v['next_due_date']); ?>
                    <tr>
                        <td><input class="vax-check" type="checkbox" name="ids[]" value="<?= (int) $v['id'] ?>"<?= $v['reminded_at'] && strtotime($v['reminded_at']) > strtotime('-7 days') ? '' : ' checked' ?> aria-label="Select"></td>
                        <td><a class="cell-main" href="<?= e(url('admin/patient.php?id=' . $v['pet_id'] . '#vaccines')) ?>"><?= pet_photo($v, 'size-sm') ?><span class="cell-title"><?= e($v['pet_name']) ?></span></a></td>
                        <td><span class="cell-title"><?= e($v['first_name'] . ' ' . $v['last_name']) ?></span><span class="cell-sub"><?= e($v['phone'] ?: '—') ?></span></td>
                        <td><?= e($v['vaccine_name']) ?></td>
                        <td class="nowrap"><?= e(fmt_date($v['next_due_date'])) ?></td>
                        <td><span class="badge badge-<?= e($st['color']) ?>"><?= e($st['label']) ?></span></td>
                        <td class="text-sm muted"><?= $v['reminded_at'] ? e(time_ago($v['reminded_at'])) : 'Never' ?></td>
                        <td><a class="btn btn-soft btn-xs" href="<?= e(url('admin/appointment-new.php?pet=' . $v['pet_id'] . '&service=2')) ?>"><?= icon('calendar-plus') ?>Book</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <?= empty_state('shield-check', 'No boosters due', 'Every patient is up to date for the next ' . REMINDER_WINDOW_DAYS . ' days.') ?>
        <?php endif; ?>
    </form>
<?php else: ?>
    <form class="card" method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="send_appointments">
        <div class="card-head"><div><h2><?= icon('calendar-clock') ?>Visits tomorrow &amp; the day after</h2><p>Reduce no-shows with a friendly reminder</p></div><button class="btn btn-primary btn-sm" type="submit"<?= $soon ? '' : ' disabled' ?>><?= icon('send') ?>Send to selected</button></div>
        <?php if ($soon): ?>
            <div class="table-wrap"><table class="table">
                <thead><tr><th style="width:40px"><input type="checkbox" data-check-all=".appt-check" aria-label="Select all" checked></th><th>When</th><th>Patient</th><th>Owner</th><th>Service</th><th>Status</th><th>Last reminder</th></tr></thead>
                <tbody>
                <?php foreach ($soon as $a): ?>
                    <tr>
                        <td><input class="appt-check" type="checkbox" name="ids[]" value="<?= (int) $a['id'] ?>"<?= $a['reminded_at'] ? '' : ' checked' ?> aria-label="Select"></td>
                        <td><span class="cell-title"><?= e(relative_day($a['appointment_date'])) ?></span><span class="cell-sub"><?= e(appt_when($a)) ?></span></td>
                        <td><div class="cell-main"><?= pet_photo($a, 'size-sm') ?><span class="cell-title"><?= e($a['pet_name']) ?></span></div></td>
                        <td><?= e($a['owner_first'] . ' ' . $a['owner_last']) ?></td>
                        <td><?= e($a['service_name']) ?></td>
                        <td><?= status_badge($a['status']) ?></td>
                        <td class="text-sm muted"><?= $a['reminded_at'] ? e(time_ago($a['reminded_at'])) : 'Never' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <?= empty_state('calendar-check', 'No visits in the next two days') ?>
        <?php endif; ?>
    </form>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
