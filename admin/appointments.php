<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();
$today = date('Y-m-d');

if (is_post() && input('action') === 'status') {
    verify_csrf();
    $a = find_appointment(int_param('id'));
    if (!$a) {
        flash('error', 'Appointment not found.');
        redirect_back('admin/appointments.php');
    }
    $status = (string) input('status');
    $err = change_appointment_status($a, $status, $me, clip((string) input('reason'), 200));
    $messages = ['confirmed' => 'confirmed', 'cancelled' => 'cancelled', 'no_show' => 'marked as a no-show'];
    $err ? flash('error', $err) : flash('success', "{$a['pet_name']}'s appointment was " . ($messages[$status] ?? 'updated') . '. The owner has been notified.');
    redirect_back('admin/appointments.php');
}

$status = array_key_exists(param('status'), APPOINTMENT_STATUSES) ? param('status') : '';
$when = in_array(param('when'), ['upcoming', 'today', 'week', 'past', 'all'], true) ? param('when') : ($status ? 'all' : 'upcoming');
$q = param('q');
$vet = int_param('vet');

$where = [];
$params = [];
switch ($when) {
    case 'upcoming': $where[] = 'a.appointment_date >= ?'; $params[] = $today; break;
    case 'today': $where[] = 'a.appointment_date = ?'; $params[] = $today; break;
    case 'week': $where[] = 'a.appointment_date BETWEEN ? AND ?'; $params[] = date('Y-m-d', strtotime('monday this week')); $params[] = date('Y-m-d', strtotime('sunday this week')); break;
    case 'past': $where[] = 'a.appointment_date < ?'; $params[] = $today; break;
}
if ($q !== '') {
    $where[] = "(a.reference LIKE ? OR p.name LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR s.name LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($vet) {
    $where[] = 'a.vet_id = ?';
    $params[] = $vet;
}
$base = ' FROM appointments a JOIN pets p ON p.id = a.pet_id JOIN services s ON s.id = a.service_id JOIN users u ON u.id = a.owner_id';
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$counts = ['' => 0];
foreach (rows('SELECT a.status, COUNT(*) AS c' . $base . $whereSql . ' GROUP BY a.status', $params) as $r) {
    $counts[$r['status']] = (int) $r['c'];
    $counts[''] += (int) $r['c'];
}
if ($status) {
    $whereSql .= ($whereSql ? ' AND ' : ' WHERE ') . 'a.status = ?';
    $params[] = $status;
}
$total = (int) val('SELECT COUNT(*)' . $base . $whereSql, $params);
$p = paginate($total, 15);
$order = $when === 'past' ? 'a.appointment_date DESC, a.start_time DESC' : ($when === 'all' ? 'a.appointment_date DESC, a.start_time' : 'a.appointment_date, a.start_time');
$list = rows(appointment_sql() . $whereSql . " ORDER BY $order LIMIT {$p['per']} OFFSET {$p['offset']}", $params);
$vets = rows('SELECT id, name FROM vets ORDER BY id');

$pageTitle = 'Appointments';
$activeNav = 'appointments';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>Appointments</span></div>
        <h1>Appointments</h1>
        <p>Confirm requests, record visits and keep the schedule running smoothly.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('admin/export.php?type=appointments')) ?>"><?= icon('download') ?>Export CSV</a>
        <a class="btn btn-primary" href="<?= e(url('admin/appointment-new.php')) ?>"><?= icon('plus') ?>New appointment</a>
    </div>
</div>

<div class="card">
    <div class="status-tabs">
        <div class="tabs">
            <a class="tab<?= $status === '' ? ' active' : '' ?>" href="<?= e(qs(['status' => null, 'page' => null])) ?: '?' ?>">All <span class="count"><?= $counts[''] ?></span></a>
            <?php foreach (APPOINTMENT_STATUSES as $key => [$label]): ?>
                <a class="tab<?= $status === $key ? ' active' : '' ?>" href="<?= e(qs(['status' => $key, 'page' => null])) ?>"><?= e($label) ?> <span class="count"><?= $counts[$key] ?? 0 ?></span></a>
            <?php endforeach; ?>
        </div>
    </div>
    <form class="filters" method="get">
        <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <div class="input-group grow"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by pet, owner, service or reference"></div>
        <select class="input" name="when" data-autosubmit aria-label="Date range">
            <?php foreach (['upcoming' => 'Upcoming', 'today' => 'Today', 'week' => 'This week', 'past' => 'Past', 'all' => 'All dates'] as $k => $label): ?>
                <option value="<?= $k ?>"<?= selected($when, $k) ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <select class="input" name="vet" data-autosubmit aria-label="Veterinarian">
            <option value="">All veterinarians</option>
            <?php foreach ($vets as $v): ?><option value="<?= (int) $v['id'] ?>"<?= selected($vet, $v['id']) ?>><?= e($v['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-soft btn-sm" type="submit"><?= icon('filter') ?>Filter</button>
        <?php if ($q !== '' || $vet || param('when')): ?><a class="btn btn-ghost btn-sm" href="?<?= $status ? 'status=' . e($status) : '' ?>">Reset</a><?php endif; ?>
    </form>

    <?php if ($list): ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Date &amp; time</th><th>Patient</th><th>Owner</th><th>Service</th><th>Veterinarian</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($list as $a): ?>
                    <tr>
                        <td><a class="cell-title" href="<?= e(url('admin/appointment.php?id=' . $a['id'])) ?>"><?= e(fmt_date($a['appointment_date'], 'D, M j')) ?></a><span class="cell-sub"><?= e(fmt_time($a['start_time'])) ?> · <?= e($a['reference']) ?></span></td>
                        <td><a class="cell-main" href="<?= e(url('admin/patient.php?id=' . $a['pet_id'])) ?>"><?= pet_photo($a, 'size-sm') ?><span><span class="cell-title"><?= e($a['pet_name']) ?></span><span class="cell-sub"><?= e($a['breed'] ?: $a['species']) ?></span></span></a></td>
                        <td><span class="cell-title"><?= e($a['owner_first'] . ' ' . $a['owner_last']) ?></span><span class="cell-sub"><?= e($a['owner_phone'] ?: $a['owner_email']) ?></span></td>
                        <td><span class="cell-title"><?= e($a['service_name']) ?></span><span class="cell-sub"><?= money($a['price']) ?></span></td>
                        <td><?= e($a['vet_name'] ?? '—') ?></td>
                        <td><?= status_badge($a['status']) ?></td>
                        <td>
                            <div class="row-actions">
                                <?php if ($a['status'] === 'pending'): ?>
                                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-primary btn-xs" name="status" value="confirmed" type="submit" title="Confirm"><?= icon('check') ?>Confirm</button></form>
                                <?php endif; ?>
                                <?php if (in_array($a['status'], ['pending', 'confirmed'], true) && $a['appointment_date'] <= $today): ?>
                                    <a class="btn btn-soft btn-xs" href="<?= e(url('admin/appointment.php?id=' . $a['id'] . '#record')) ?>" title="Record visit"><?= icon('clipboard-pen') ?>Record</a>
                                <?php endif; ?>
                                <a class="btn btn-ghost btn-icon btn-xs" href="<?= e(url('admin/appointment.php?id=' . $a['id'])) ?>" title="View details" aria-label="View details"><?= icon('eye') ?></a>
                                <?php if (in_array($a['status'], ['pending', 'confirmed'], true)): ?>
                                    <form method="post" data-confirm="Cancel <?= e($a['pet_name']) ?>'s <?= e($a['service_name']) ?> on <?= e(appt_when($a)) ?>? The owner will be notified." data-confirm-ok="Cancel appointment"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-ghost btn-icon btn-xs" name="status" value="cancelled" type="submit" title="Cancel" aria-label="Cancel appointment"><?= icon('circle-x') ?></button></form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($p) ?>
    <?php else: ?>
        <?= empty_state('calendar-x', 'No appointments found', 'Try a different filter or date range.', '<a class="btn btn-primary btn-sm" href="' . e(url('admin/appointment-new.php')) . '">' . icon('plus') . 'New appointment</a>') ?>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
