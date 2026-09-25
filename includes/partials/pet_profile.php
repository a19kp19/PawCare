<?php
/**
 * Pet health record (shared by owner/pet.php and admin/patient.php).
 * Expects: $pet (staff version also has owner_* columns) and $isStaff (bool).
 */
$records = pet_records((int) $pet['id']);
$vaccines = pet_vaccinations((int) $pet['id']);
$latestVax = pet_latest_vaccines((int) $pet['id']);
$latestIds = array_map('intval', array_column($latestVax, 'id'));
$health = pet_health_status((int) $pet['id']);
$petAppts = rows(appointment_sql() . ' WHERE a.pet_id = ? ORDER BY a.appointment_date DESC, a.start_time DESC', [$pet['id']]);
$upcomingAppts = array_values(array_filter($petAppts, fn ($a) => in_array($a['status'], ['pending', 'confirmed'], true) && $a['appointment_date'] >= date('Y-m-d')));
$weights = array_reverse(array_values(array_filter($records, fn ($r) => $r['weight_kg'] !== null)));
$lastW = $weights ? $weights[count($weights) - 1] : null;
$prevW = count($weights) > 1 ? $weights[count($weights) - 2] : null;
$nextDue = null;
foreach ($latestVax as $v) {
    if ($v['next_due_date'] && (!$nextDue || $v['next_due_date'] < $nextDue['next_due_date'])) {
        $nextDue = $v;
    }
}
$bookUrl = $isStaff ? 'admin/appointment-new.php?pet=' . $pet['id'] : 'owner/book.php?pet=' . $pet['id'];
$editUrl = $isStaff ? 'admin/patient-form.php?id=' . $pet['id'] : 'owner/pet-form.php?id=' . $pet['id'];
$fmtKg = fn ($kg) => rtrim(rtrim(number_format((float) $kg, 2), '0'), '.') . ' kg';
?>
<div class="print-only report-head-print">
    <strong style="font-size:1.2rem"><?= e(CLINIC_NAME) ?></strong><br>
    <span class="muted"><?= e(CLINIC_ADDRESS) ?> · <?= e(CLINIC_PHONE) ?></span>
    <h2 style="margin:.8rem 0 .2rem">Pet health record</h2>
    <span class="muted">Printed <?= e(date('F j, Y g:i A')) ?></span>
    <hr>
</div>

<section class="profile-head">
    <?= pet_photo($pet) ?>
    <div class="profile-main">
        <h1><?= e($pet['name']) ?> <?= sex_icon($pet['sex']) ?></h1>
        <div class="sub"><?= e($pet['breed'] ?: 'Breed not set') ?> · <?= e($pet['species']) ?><?= $pet['color'] ? ' · ' . e($pet['color']) : '' ?></div>
        <div class="profile-tags">
            <?= pet_status_chip($health) ?>
            <span class="badge badge-teal"><?= icon('cake') ?><?= e(pet_age($pet['birthdate'])) ?></span>
            <?php if ($pet['is_neutered']): ?><span class="badge badge-violet"><?= icon('shield-check') ?><?= $pet['sex'] === 'Female' ? 'Spayed' : 'Neutered' ?></span><?php endif; ?>
            <?php if ($isStaff): ?><a class="badge badge-outline" href="<?= e(url('admin/client.php?id=' . $pet['owner_id'])) ?>"><?= icon('user-round') ?><?= e($pet['owner_first'] . ' ' . $pet['owner_last']) ?></a><?php endif; ?>
        </div>
    </div>
    <div class="profile-actions">
        <a class="btn btn-primary" href="<?= e(url($bookUrl)) ?>"><?= icon('calendar-plus') ?>Book visit</a>
        <?php if ($isStaff): ?>
            <button class="btn btn-soft" type="button" data-modal-open="modal-record"><?= icon('clipboard-plus') ?>Add record</button>
            <button class="btn btn-soft" type="button" data-modal-open="modal-vaccine"><?= icon('syringe') ?>Add vaccine</button>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= e(url($editUrl)) ?>"><?= icon('pencil') ?>Edit</a>
        <button class="btn btn-outline" type="button" data-print><?= icon('printer') ?>Print</button>
    </div>
</section>

<?php if ($pet['allergies']): ?>
    <div class="alert alert-danger allergy-alert"><?= icon('triangle-alert') ?><div><strong>Allergies</strong><?= e($pet['allergies']) ?></div></div>
<?php endif; ?>

<div class="vitals">
    <div class="vital tone-teal"><span class="kpi-icon"><?= icon('weight') ?></span><div><span>Weight</span><strong><?= $lastW ? e($fmtKg($lastW['weight_kg'])) : ($pet['weight_kg'] !== null ? e($fmtKg($pet['weight_kg'])) : '—') ?></strong>
        <?php if ($lastW && $prevW): $diff = (float) $lastW['weight_kg'] - (float) $prevW['weight_kg']; ?>
            <small class="<?= $diff >= 0 ? 'trend-up' : 'trend-down' ?>"><?= icon($diff >= 0 ? 'trending-up' : 'trending-down') ?><?= ($diff >= 0 ? '+' : '') . e(number_format($diff, 2)) ?> kg</small>
        <?php endif; ?></div></div>
    <div class="vital tone-sun"><span class="kpi-icon"><?= icon('cake') ?></span><div><span>Age</span><strong><?= e(pet_age($pet['birthdate'])) ?></strong><small class="muted"><?= $pet['birthdate'] ? 'Born ' . e(fmt_date($pet['birthdate'])) : 'Birth date unknown' ?></small></div></div>
    <div class="vital tone-violet"><span class="kpi-icon"><?= icon('stethoscope') ?></span><div><span>Last visit</span><strong><?= $records ? e(fmt_date($records[0]['visit_date'])) : 'No visits yet' ?></strong><small class="muted"><?= $records ? e(excerpt($records[0]['diagnosis'], 34)) : 'Book a first check-up' ?></small></div></div>
    <div class="vital tone-coral"><span class="kpi-icon"><?= icon('syringe') ?></span><div><span>Next vaccine due</span><strong><?= $nextDue ? e(fmt_date($nextDue['next_due_date'])) : '—' ?></strong><small class="muted"><?= $nextDue ? e($nextDue['vaccine_name']) : 'No boosters scheduled' ?></small></div></div>
</div>

<div class="tabs mb-2" data-tabs data-tabs-hash role="tablist">
    <button class="tab active" type="button" data-tab="overview" role="tab"><?= icon('layout-grid') ?>Overview</button>
    <button class="tab" type="button" data-tab="records" role="tab"><?= icon('file-heart') ?>Medical history <span class="count"><?= count($records) ?></span></button>
    <button class="tab" type="button" data-tab="vaccines" role="tab"><?= icon('syringe') ?>Vaccinations <span class="count"><?= count($vaccines) ?></span></button>
    <button class="tab" type="button" data-tab="visits" role="tab"><?= icon('calendar-days') ?>Appointments <span class="count"><?= count($petAppts) ?></span></button>
</div>

<div class="tab-panel" id="overview" role="tabpanel">
    <div class="grid cols-12">
        <div class="card span-7">
            <div class="card-head"><div><h3><?= icon('chart-line') ?>Weight trend</h3><p>Recorded at each visit</p></div></div>
            <div class="card-body">
                <?php if (count($weights) >= 2): ?>
                    <?= chart_box([
                        'type' => 'line', 'aria' => 'Weight history', 'fitY' => true, 'unit' => 'kg',
                        'labels' => array_map(fn ($r) => fmt_date($r['visit_date'], 'M j, Y'), $weights),
                        'datasets' => [['label' => 'Weight', 'data' => array_map(fn ($r) => (float) $r['weight_kg'], $weights), 'color' => '#14a697', 'pointRadius' => 4]],
                    ], 'sm') ?>
                <?php else: ?>
                    <?= empty_state('chart-line', 'Not enough data yet', 'A weight trend appears after two recorded visits.') ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="card span-5">
            <div class="card-head"><h3><?= icon('clipboard-list') ?>Profile details</h3></div>
            <div class="card-body">
                <dl class="dl">
                    <dt>Species</dt><dd><?= e($pet['species']) ?></dd>
                    <dt>Breed</dt><dd><?= e($pet['breed'] ?: '—') ?></dd>
                    <dt>Sex</dt><dd><?= e($pet['sex']) ?><?= $pet['is_neutered'] ? ' · ' . ($pet['sex'] === 'Female' ? 'spayed' : 'neutered') : '' ?></dd>
                    <dt>Birth date</dt><dd><?= e(fmt_date($pet['birthdate'])) ?></dd>
                    <dt>Colour</dt><dd><?= e($pet['color'] ?: '—') ?></dd>
                    <dt>Allergies</dt><dd><?= e($pet['allergies'] ?: 'None known') ?></dd>
                    <dt>Notes</dt><dd><?= e($pet['notes'] ?: '—') ?></dd>
                    <?php if ($isStaff): ?>
                        <dt>Owner</dt><dd><a class="fw-700" href="<?= e(url('admin/client.php?id=' . $pet['owner_id'])) ?>"><?= e($pet['owner_first'] . ' ' . $pet['owner_last']) ?></a><br><span class="muted"><?= e($pet['owner_phone'] ?: $pet['owner_email']) ?></span></dd>
                    <?php endif; ?>
                    <dt>Registered</dt><dd><?= e(fmt_date($pet['created_at'])) ?></dd>
                </dl>
            </div>
        </div>
        <div class="card span-12">
            <div class="card-head"><h3><?= icon('calendar-clock') ?>Upcoming visits</h3><a class="btn btn-soft btn-sm" href="<?= e(url($bookUrl)) ?>"><?= icon('plus') ?>Book</a></div>
            <?php if ($upcomingAppts): ?>
                <div class="list">
                    <?php foreach (array_reverse($upcomingAppts) as $a): ?>
                        <a class="list-item" href="<?= e(url($isStaff ? 'admin/appointment.php?id=' . $a['id'] : 'owner/appointments.php')) ?>">
                            <?= date_block($a['appointment_date']) ?>
                            <div class="li-main"><div class="li-title"><?= e($a['service_name']) ?></div><div class="li-sub"><?= e(fmt_time($a['start_time'])) ?> · <?= e($a['vet_name'] ?? 'Any vet') ?> · <?= e($a['reference']) ?></div></div>
                            <?= status_badge($a['status']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('calendar-plus', 'No upcoming visits', 'Keep ' . $pet['name'] . ' healthy with a check-up every year.', '<a class="btn btn-primary btn-sm" href="' . e(url($bookUrl)) . '">' . icon('calendar-plus') . 'Book a visit</a>') ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="tab-panel" id="records" role="tabpanel" hidden>
    <?php if ($records): ?>
        <div class="timeline">
            <?php foreach ($records as $r): ?>
                <div class="tl-item">
                    <span class="tl-dot"><?= icon(stripos($r['diagnosis'], 'vaccin') !== false ? 'syringe' : 'check') ?></span>
                    <div class="tl-card">
                        <div class="tl-head">
                            <div><h4><?= e($r['diagnosis']) ?></h4><div class="tl-meta"><?= e(fmt_date($r['visit_date'], 'D, M j, Y')) ?> · <?= e($r['vet_name'] ?? 'Clinic vet') ?><?= $r['service_name'] ? ' · ' . e($r['service_name']) : '' ?></div></div>
                            <div class="flex gap-1 wrap">
                                <?php if ($r['weight_kg'] !== null): ?><span class="badge badge-teal"><?= icon('weight') ?><?= e($fmtKg($r['weight_kg'])) ?></span><?php endif; ?>
                                <?php if ($r['temperature_c'] !== null): ?><span class="badge badge-coral"><?= icon('thermometer') ?><?= e($r['temperature_c']) ?> °C</span><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($r['treatment'] || $r['prescription'] || $r['notes'] || $r['follow_up_date']): ?>
                            <div class="tl-body">
                                <?php if ($r['treatment']): ?><div class="full"><span>Findings &amp; treatment</span><?= e($r['treatment']) ?></div><?php endif; ?>
                                <?php if ($r['prescription']): ?><div class="full"><span>Prescription</span><div class="rx"><?= icon('pill') ?> <?= e($r['prescription']) ?></div></div><?php endif; ?>
                                <?php if ($r['notes']): ?><div class="full"><span>Notes</span><?= e($r['notes']) ?></div><?php endif; ?>
                                <?php if ($r['follow_up_date']): ?><div><span>Follow-up</span><?= e(fmt_date($r['follow_up_date'])) ?></div><?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card"><?= empty_state('file-heart', 'No medical records yet', 'Visit notes, diagnoses and prescriptions will appear here after each visit.') ?></div>
    <?php endif; ?>
</div>

<div class="tab-panel" id="vaccines" role="tabpanel" hidden>
    <div class="card">
        <div class="card-head"><div><h3><?= icon('syringe') ?>Vaccine card</h3><p>Latest dose of each vaccine determines the status</p></div>
            <?php if ($isStaff): ?><button class="btn btn-soft btn-sm no-print" type="button" data-modal-open="modal-vaccine"><?= icon('plus') ?>Add vaccine</button><?php endif; ?></div>
        <?php if ($vaccines): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Vaccine</th><th>Date given</th><th>Next due</th><th>Status</th><th>Vet</th><th>Batch</th><?php if ($isStaff): ?><th class="no-print"></th><?php endif; ?></tr></thead>
                    <tbody>
                    <?php foreach ($vaccines as $v): $isLatest = in_array((int) $v['id'], $latestIds, true); $st = vaccine_status($v['next_due_date']); ?>
                        <tr>
                            <td><span class="cell-title"><?= e($v['vaccine_name']) ?></span></td>
                            <td><?= e(fmt_date($v['date_given'])) ?></td>
                            <td><?= e(fmt_date($v['next_due_date'])) ?></td>
                            <td><?= $isLatest ? '<span class="badge badge-' . e($st['color']) . '">' . e($st['label']) . '</span>' : '<span class="badge badge-gray">Previous dose</span>' ?></td>
                            <td><?= e($v['vet_name'] ?? '—') ?></td>
                            <td><code><?= e($v['batch_no'] ?: '—') ?></code></td>
                            <?php if ($isStaff): ?>
                                <td class="no-print"><form method="post" data-confirm="Remove this vaccination entry?" data-confirm-ok="Remove"><?= csrf_field() ?><input type="hidden" name="action" value="delete_vaccine"><input type="hidden" name="vaccine_id" value="<?= (int) $v['id'] ?>"><button class="btn btn-ghost btn-icon btn-xs" type="submit" aria-label="Remove"><?= icon('trash-2') ?></button></form></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <?= empty_state('shield-plus', 'No vaccinations on file', 'Vaccines given at the clinic are recorded here automatically.') ?>
        <?php endif; ?>
    </div>
</div>

<div class="tab-panel" id="visits" role="tabpanel" hidden>
    <div class="card">
        <?php if ($petAppts): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Date</th><th>Service</th><th>Veterinarian</th><th>Status</th><th class="num">Price</th><th>Reference</th></tr></thead>
                    <tbody>
                    <?php foreach ($petAppts as $a): ?>
                        <tr>
                            <td><span class="cell-title"><?= e(fmt_date($a['appointment_date'])) ?></span><span class="cell-sub"><?= e(fmt_time($a['start_time'])) ?></span></td>
                            <td><?= e($a['service_name']) ?></td>
                            <td><?= e($a['vet_name'] ?? '—') ?></td>
                            <td><?= status_badge($a['status']) ?></td>
                            <td class="num"><?= money($a['price']) ?></td>
                            <td><?= $isStaff ? '<a class="fw-700" href="' . e(url('admin/appointment.php?id=' . $a['id'])) . '">' . e($a['reference']) . '</a>' : '<code>' . e($a['reference']) . '</code>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <?= empty_state('calendar-days', 'No appointments yet', 'Booked visits for ' . $pet['name'] . ' will be listed here.') ?>
        <?php endif; ?>
    </div>
</div>
