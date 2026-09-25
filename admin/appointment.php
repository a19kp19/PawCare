<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();
$a = find_appointment(int_param('id'));
if (!$a) {
    abort(404, 'Appointment not found');
}
$today = date('Y-m-d');
$record = row('SELECT m.*, v.name AS vet_name FROM medical_records m LEFT JOIN vets v ON v.id = m.vet_id WHERE m.appointment_id = ?', [$a['id']]);
$recordErrors = [];

if (is_post()) {
    verify_csrf();
    $action = input('action');
    $self = 'admin/appointment.php?id=' . $a['id'];

    if ($action === 'status') {
        $err = change_appointment_status($a, (string) input('status'), $me, clip((string) input('reason'), 200));
        $err ? flash('error', $err) : flash('success', 'Status updated — ' . $a['owner_first'] . ' has been notified.');
        redirect($self);
    }

    if ($action === 'notes') {
        update('appointments', ['staff_notes' => clip(input('staff_notes'), 2000) ?: null], 'id = :id', ['id' => $a['id']]);
        flash('success', 'Staff notes saved.');
        redirect($self);
    }

    if ($action === 'reschedule') {
        if (!in_array($a['status'], ['pending', 'confirmed'], true)) {
            flash('error', 'Only pending or confirmed appointments can be rescheduled.');
            redirect($self);
        }
        $date = input('date');
        $time = input('time');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            flash('error', 'Please choose a valid future date and an open time.');
            redirect($self . '#reschedule');
        }
        $res = reschedule_appointment($a, $date, $time, int_param('vet_id') ?: null);
        if (isset($res['error'])) {
            flash('error', $res['error']);
            redirect($self . '#reschedule');
        }
        $fresh = find_appointment((int) $a['id']);
        notify_appointment_status($fresh, 'rescheduled');
        log_activity((int) $me['id'], full_name($me) . " moved {$a['pet_name']}'s {$a['service_name']} to " . appt_when($fresh), 'calendar-clock', $self);
        flash('success', 'Appointment moved to ' . appt_when($fresh) . '.');
        redirect($self);
    }

    if ($action === 'record') {
        $in = [
            'weight_kg' => input('weight_kg'), 'temperature_c' => input('temperature_c'), 'diagnosis' => clip(input('diagnosis'), 255),
            'treatment' => clip(input('treatment'), 3000), 'prescription' => clip(input('prescription'), 2000), 'notes' => clip(input('notes'), 2000),
            'follow_up_date' => input('follow_up_date'), 'vaccine_name' => clip(input('vaccine_name'), 100), 'batch_no' => clip(input('batch_no'), 50), 'next_due_date' => input('next_due_date'),
        ];
        if ($record) $recordErrors['diagnosis'] = 'This visit already has a record.';
        if (!in_array($a['status'], ['pending', 'confirmed', 'completed'], true)) $recordErrors['diagnosis'] = 'Cancelled or missed visits cannot be recorded.';
        if ($a['appointment_date'] > $today) $recordErrors['diagnosis'] = 'You can record this visit on the appointment day.';
        if ($in['diagnosis'] === '') $recordErrors['diagnosis'] = $recordErrors['diagnosis'] ?? 'Please enter a diagnosis or visit summary.';
        if ($in['weight_kg'] !== '' && (!is_numeric($in['weight_kg']) || $in['weight_kg'] <= 0 || $in['weight_kg'] > 200)) $recordErrors['weight_kg'] = 'Enter a weight between 0 and 200 kg.';
        if ($in['temperature_c'] !== '' && (!is_numeric($in['temperature_c']) || $in['temperature_c'] < 30 || $in['temperature_c'] > 45)) $recordErrors['temperature_c'] = 'Enter a temperature between 30 and 45 °C.';
        foreach (['follow_up_date', 'next_due_date'] as $k) {
            if ($in[$k] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $in[$k])) $recordErrors[$k] = 'Invalid date.';
        }
        if (!$recordErrors) {
            $pdo = db();
            $pdo->beginTransaction();
            insert('medical_records', [
                'pet_id' => $a['pet_id'], 'appointment_id' => $a['id'], 'vet_id' => $a['vet_id'], 'visit_date' => $a['appointment_date'],
                'weight_kg' => $in['weight_kg'] !== '' ? $in['weight_kg'] : null, 'temperature_c' => $in['temperature_c'] !== '' ? $in['temperature_c'] : null,
                'diagnosis' => $in['diagnosis'], 'treatment' => $in['treatment'] ?: null, 'prescription' => $in['prescription'] ?: null,
                'notes' => $in['notes'] ?: null, 'follow_up_date' => $in['follow_up_date'] ?: null, 'created_by' => $me['id'],
            ]);
            if ($in['weight_kg'] !== '') {
                update('pets', ['weight_kg' => $in['weight_kg']], 'id = :id', ['id' => $a['pet_id']]);
            }
            if ($in['vaccine_name'] !== '') {
                insert('vaccinations', [
                    'pet_id' => $a['pet_id'], 'vaccine_name' => $in['vaccine_name'], 'date_given' => $a['appointment_date'],
                    'next_due_date' => $in['next_due_date'] ?: null, 'batch_no' => $in['batch_no'] ?: null, 'vet_id' => $a['vet_id'], 'appointment_id' => $a['id'],
                ]);
            }
            update('appointments', ['status' => 'completed'], 'id = :id', ['id' => $a['id']]);
            $pdo->commit();
            notify_appointment_status($a, 'completed');
            log_activity((int) $me['id'], full_name($me) . " completed {$a['pet_name']}'s {$a['service_name']}", 'circle-check', $self);
            flash('success', "Visit recorded and {$a['pet_name']}'s health record updated.");
            redirect($self);
        }
    }
}

$pet = row('SELECT * FROM pets WHERE id = ?', [$a['pet_id']]);
$owner = row('SELECT * FROM users WHERE id = ?', [$a['owner_id']]);
$vets = rows('SELECT id, name FROM vets WHERE is_active = 1 ORDER BY id');
$history = rows(appointment_sql() . ' WHERE a.pet_id = ? AND a.id <> ? ORDER BY a.appointment_date DESC LIMIT 4', [$a['pet_id'], $a['id']]);
$canRecord = !$record && in_array($a['status'], ['pending', 'confirmed', 'completed'], true) && $a['appointment_date'] <= $today;
$canChange = in_array($a['status'], ['pending', 'confirmed'], true);
$v = fn (string $k, $d = '') => e(is_post() && input('action') === 'record' ? input($k) : $d);

$pageTitle = $a['reference'];
$activeNav = 'appointments';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/appointments.php')) ?>">Appointments</a><?= icon('chevron-right') ?><span><?= e($a['reference']) ?></span></div>
        <h1 class="flex items-center gap-2 wrap"><?= e($a['pet_name']) ?> · <?= e($a['service_name']) ?> <?= status_badge($a['status']) ?></h1>
        <p><?= e(fmt_date($a['appointment_date'], 'l, F j, Y')) ?> · <?= e(fmt_time($a['start_time'])) ?> – <?= e(fmt_time($a['end_time'])) ?> · booked by <?= $a['booked_by'] === 'staff' ? 'clinic staff' : 'the owner online' ?> <?= e(time_ago($a['created_at'])) ?></p>
    </div>
    <div class="page-actions status-actions">
        <?php if ($a['status'] === 'pending'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><button class="btn btn-primary" name="status" value="confirmed" type="submit"><?= icon('check') ?>Confirm</button></form>
        <?php endif; ?>
        <?php if ($canChange && $a['appointment_date'] <= $today): ?>
            <form method="post" data-confirm="Mark this appointment as a no-show?" data-confirm-ok="Mark no-show"><?= csrf_field() ?><input type="hidden" name="action" value="status"><button class="btn btn-outline" name="status" value="no_show" type="submit"><?= icon('ban') ?>No-show</button></form>
        <?php endif; ?>
        <?php if ($canChange): ?>
            <form method="post" data-confirm="Cancel this appointment? <?= e($a['owner_first']) ?> will be notified." data-confirm-ok="Cancel appointment"><?= csrf_field() ?><input type="hidden" name="action" value="status"><button class="btn btn-danger-soft" name="status" value="cancelled" type="submit"><?= icon('circle-x') ?>Cancel</button></form>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= e(url('owner/ics.php?id=' . $a['id'])) ?>"><?= icon('calendar-plus') ?>.ics</a>
    </div>
</div>

<div class="detail-grid">
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2><?= icon('calendar-check') ?>Appointment details</h2><code><?= e($a['reference']) ?></code></div>
            <div class="card-body">
                <div class="info-tiles">
                    <div class="info-tile"><span><?= icon('calendar-days') ?>Date</span><strong><?= e(fmt_date($a['appointment_date'], 'D, M j, Y')) ?></strong></div>
                    <div class="info-tile"><span><?= icon('clock') ?>Time</span><strong><?= e(fmt_time($a['start_time'])) ?> – <?= e(fmt_time($a['end_time'])) ?></strong></div>
                    <div class="info-tile"><span><?= icon('stethoscope') ?>Veterinarian</span><strong><?= e($a['vet_name'] ?? 'Unassigned') ?></strong></div>
                    <div class="info-tile"><span><?= icon($a['service_icon']) ?>Service</span><strong><?= e($a['service_name']) ?></strong></div>
                    <div class="info-tile"><span><?= icon('wallet') ?>Price</span><strong><?= money($a['price'], true) ?></strong></div>
                    <div class="info-tile"><span><?= icon('history') ?>Last updated</span><strong><?= e(time_ago($a['updated_at'])) ?></strong></div>
                </div>
                <p class="section-label mt-3">Reason for visit</p>
                <div class="reason-box"><?= e($a['reason'] ?: 'No notes were added by the owner.') ?></div>
                <?php if ($a['status'] === 'cancelled'): ?><div class="alert alert-warning mt-2"><?= icon('info') ?><div><strong>Cancelled</strong><?= e($a['cancel_reason'] ?: 'No reason given') ?></div></div><?php endif; ?>
            </div>
        </div>

        <?php if ($record): ?>
            <div class="card" id="record">
                <div class="card-head"><h2><?= icon('file-heart') ?>Visit record</h2><span class="badge badge-green"><?= icon('circle-check') ?>Saved <?= e(time_ago($record['created_at'])) ?></span></div>
                <div class="card-body">
                    <h3 style="font-size:1.1rem"><?= e($record['diagnosis']) ?></h3>
                    <div class="flex gap-1 wrap mb-2">
                        <?php if ($record['weight_kg'] !== null): ?><span class="badge badge-teal"><?= icon('weight') ?><?= e((float) $record['weight_kg']) ?> kg</span><?php endif; ?>
                        <?php if ($record['temperature_c'] !== null): ?><span class="badge badge-coral"><?= icon('thermometer') ?><?= e($record['temperature_c']) ?> °C</span><?php endif; ?>
                        <?php if ($record['follow_up_date']): ?><span class="badge badge-blue"><?= icon('calendar-clock') ?>Follow-up <?= e(fmt_date($record['follow_up_date'])) ?></span><?php endif; ?>
                    </div>
                    <dl class="dl">
                        <dt>Treatment</dt><dd><?= e($record['treatment'] ?: '—') ?></dd>
                        <dt>Prescription</dt><dd><?= e($record['prescription'] ?: '—') ?></dd>
                        <dt>Notes</dt><dd><?= e($record['notes'] ?: '—') ?></dd>
                    </dl>
                </div>
            </div>
        <?php elseif ($canRecord): ?>
            <form class="card" id="record" method="post" novalidate>
                <div class="card-head"><div><h2><?= icon('clipboard-pen') ?>Record visit &amp; complete</h2><p>Saves to <?= e($a['pet_name']) ?>'s health record and notifies the owner</p></div></div>
                <div class="card-body">
                    <?= csrf_field() ?><input type="hidden" name="action" value="record">
                    <div class="form-grid cols-3">
                        <div class="field<?= isset($recordErrors['weight_kg']) ? ' has-error' : '' ?>"><label for="r-weight">Weight (kg)</label><input class="input" id="r-weight" type="number" step="0.01" name="weight_kg" value="<?= $v('weight_kg', $pet['weight_kg']) ?>"><?php if (isset($recordErrors['weight_kg'])): ?><span class="field-error"><?= e($recordErrors['weight_kg']) ?></span><?php endif; ?></div>
                        <div class="field<?= isset($recordErrors['temperature_c']) ? ' has-error' : '' ?>"><label for="r-temp">Temperature (°C)</label><input class="input" id="r-temp" type="number" step="0.1" name="temperature_c" value="<?= $v('temperature_c') ?>" placeholder="38.5"><?php if (isset($recordErrors['temperature_c'])): ?><span class="field-error"><?= e($recordErrors['temperature_c']) ?></span><?php endif; ?></div>
                        <div class="field"><label for="r-follow">Follow-up date</label><input class="input" id="r-follow" type="date" name="follow_up_date" min="<?= e($today) ?>" value="<?= $v('follow_up_date') ?>"></div>
                        <div class="field full<?= isset($recordErrors['diagnosis']) ? ' has-error' : '' ?>"><label for="r-dx">Diagnosis / visit summary <span class="req">*</span></label><input class="input" id="r-dx" name="diagnosis" value="<?= $v('diagnosis') ?>" placeholder="e.g. Healthy — routine wellness exam" required><?php if (isset($recordErrors['diagnosis'])): ?><span class="field-error"><?= e($recordErrors['diagnosis']) ?></span><?php endif; ?></div>
                        <div class="field full"><label for="r-tx">Findings &amp; treatment</label><textarea class="input" id="r-tx" name="treatment" rows="3" placeholder="Exam findings, procedures done…"><?= $v('treatment') ?></textarea></div>
                        <div class="field full"><label for="r-rx">Prescription</label><textarea class="input" id="r-rx" name="prescription" rows="2" placeholder="Medicine, dose and duration"><?= $v('prescription') ?></textarea></div>
                        <div class="field full"><label for="r-notes">Notes for the owner</label><textarea class="input" id="r-notes" name="notes" rows="2" placeholder="Home-care advice"><?= $v('notes') ?></textarea></div>
                    </div>
                    <div class="card mt-2" style="background:var(--surface-2)">
                        <div class="card-body">
                            <p class="section-label"><?= icon('syringe') ?> Vaccine given <?= $a['is_vaccination'] ? '' : '(optional)' ?></p>
                            <div class="form-grid cols-3">
                                <div class="field"><label for="r-vax">Vaccine</label><input class="input" id="r-vax" name="vaccine_name" list="vaccine-list" value="<?= $v('vaccine_name') ?>" placeholder="<?= $a['is_vaccination'] ? 'e.g. Anti-Rabies' : 'Leave blank if none' ?>"><datalist id="vaccine-list"><?php foreach (VACCINES[$a['species']] ?? array_merge(...array_values(VACCINES)) as $vx): ?><option value="<?= e($vx) ?>"><?php endforeach; ?></datalist></div>
                                <div class="field"><label for="r-batch">Batch no.</label><input class="input" id="r-batch" name="batch_no" value="<?= $v('batch_no') ?>"></div>
                                <div class="field"><label for="r-due">Next due</label><input class="input" id="r-due" type="date" name="next_due_date" value="<?= $v('next_due_date', date('Y-m-d', strtotime($a['appointment_date'] . ' +1 year'))) ?>"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('circle-check') ?>Save record &amp; complete visit</button></div>
                </div>
            </form>
        <?php elseif ($canChange): ?>
            <div class="alert alert-info"><?= icon('info') ?><div><strong>Visit recording opens on <?= e(fmt_date($a['appointment_date'], 'M j')) ?></strong>Vitals, diagnosis and prescriptions can be saved on the day of the appointment.</div></div>
        <?php endif; ?>

        <?php if ($canChange): ?>
            <form class="card" id="reschedule" method="post">
                <div class="card-head"><div><h2><?= icon('calendar-clock') ?>Reschedule</h2><p>Pick a new date and an open slot — the owner is notified automatically</p></div></div>
                <div class="card-body">
                    <?= csrf_field() ?><input type="hidden" name="action" value="reschedule">
                    <div class="form-grid">
                        <div class="field"><label for="rs-date">New date</label><input class="input" id="rs-date" type="date" name="date" min="<?= e($today) ?>" value="<?= e($a['appointment_date'] >= $today ? $a['appointment_date'] : $today) ?>"></div>
                        <div class="field"><label for="rs-vet">Veterinarian</label><select class="input" id="rs-vet" name="vet_id"><option value="">First available</option><?php foreach ($vets as $vt): ?><option value="<?= (int) $vt['id'] ?>"<?= selected($a['vet_id'], $vt['id']) ?>><?= e($vt['name']) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="mt-2" data-slot-picker data-date-input="#rs-date" data-vet-input="#rs-vet" data-service-id="<?= (int) $a['service_id'] ?>" data-ignore="<?= (int) $a['id'] ?>" data-selected="<?= e(substr($a['start_time'], 0, 5)) ?>">
                        <p class="slot-msg" data-slot-msg></p>
                        <div data-slot-list></div>
                    </div>
                    <div class="form-actions"><button class="btn btn-soft" type="submit"><?= icon('calendar-check') ?>Move appointment</button></div>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <aside class="stack">
        <div class="card">
            <div class="card-head"><h2><?= icon('paw-print') ?>Patient</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/patient.php?id=' . $pet['id'])) ?>">Health record <?= icon('arrow-right') ?></a></div>
            <div class="card-body">
                <div class="person-card"><?= pet_photo($pet, 'size-lg') ?><div><h3 style="margin:0"><?= e($pet['name']) ?> <?= sex_icon($pet['sex']) ?></h3><div class="muted text-sm"><?= e($pet['breed'] ?: $pet['species']) ?> · <?= e(pet_age($pet['birthdate'])) ?></div><div class="mt-1"><?= pet_status_chip(pet_health_status((int) $pet['id'])) ?></div></div></div>
                <?php if ($pet['allergies']): ?><div class="alert alert-danger mt-2"><?= icon('triangle-alert') ?><div><strong>Allergies</strong><?= e($pet['allergies']) ?></div></div><?php endif; ?>
                <?php if ($history): ?>
                    <p class="section-label mt-3">Previous visits</p>
                    <div class="stack-sm">
                        <?php foreach ($history as $h): ?>
                            <a class="flex items-center justify-between gap-2 text-sm" href="<?= e(url('admin/appointment.php?id=' . $h['id'])) ?>"><span><strong><?= e(fmt_date($h['appointment_date'], 'M j, Y')) ?></strong> · <?= e($h['service_name']) ?></span><?= status_badge($h['status']) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2><?= icon('user-round') ?>Owner</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/client.php?id=' . $owner['id'])) ?>">Profile <?= icon('arrow-right') ?></a></div>
            <div class="card-body">
                <div class="person-card"><?= avatar(full_name($owner)) ?><div><strong><?= e(full_name($owner)) ?></strong><div class="muted text-sm">Client since <?= e(fmt_date($owner['created_at'], 'M Y')) ?></div></div></div>
                <div class="contact-lines">
                    <?php if ($owner['phone']): ?><a href="tel:<?= e(preg_replace('/\D+/', '', $owner['phone'])) ?>"><?= icon('phone') ?><?= e($owner['phone']) ?></a><?php endif; ?>
                    <a href="mailto:<?= e($owner['email']) ?>"><?= icon('mail') ?><?= e($owner['email']) ?></a>
                    <?php if ($owner['address']): ?><span><?= icon('map-pin') ?><?= e($owner['address']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <form class="card" method="post">
            <div class="card-head"><h2><?= icon('notebook-pen') ?>Staff notes</h2><span class="badge badge-gray">Internal only</span></div>
            <div class="card-body">
                <?= csrf_field() ?><input type="hidden" name="action" value="notes">
                <textarea class="input" name="staff_notes" rows="4" placeholder="e.g. Owner prefers text updates. Bring muzzle."><?= e($a['staff_notes']) ?></textarea>
                <div class="form-actions" style="margin-top:.8rem"><button class="btn btn-soft btn-sm" type="submit"><?= icon('save') ?>Save notes</button></div>
            </div>
        </form>
    </aside>
</div>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
