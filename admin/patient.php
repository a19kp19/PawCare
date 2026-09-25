<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();
$pet = row(
    'SELECT p.*, u.first_name AS owner_first, u.last_name AS owner_last, u.phone AS owner_phone, u.email AS owner_email
     FROM pets p JOIN users u ON u.id = p.owner_id WHERE p.id = ?',
    [int_param('id')]
);
if (!$pet) {
    abort(404, 'Patient not found');
}
$today = date('Y-m-d');
$self = 'admin/patient.php?id=' . $pet['id'];

if (is_post()) {
    verify_csrf();
    $action = input('action');
    if ($action === 'add_record') {
        $date = input('visit_date') ?: $today;
        $diag = clip(input('diagnosis'), 255);
        $kg = input('weight_kg');
        $temp = input('temperature_c');
        if ($diag === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > $today
            || ($kg !== '' && (!is_numeric($kg) || $kg <= 0 || $kg > 200)) || ($temp !== '' && (!is_numeric($temp) || $temp < 30 || $temp > 45))) {
            flash('error', 'Please enter a diagnosis, a visit date that is not in the future, and valid vitals.');
            redirect($self . '#records');
        }
        insert('medical_records', [
            'pet_id' => $pet['id'], 'vet_id' => int_param('vet_id') ?: null, 'visit_date' => $date,
            'weight_kg' => $kg !== '' ? $kg : null, 'temperature_c' => $temp !== '' ? $temp : null, 'diagnosis' => $diag,
            'treatment' => clip(input('treatment'), 3000) ?: null, 'prescription' => clip(input('prescription'), 2000) ?: null,
            'notes' => clip(input('notes'), 2000) ?: null, 'created_by' => $me['id'],
        ]);
        $latest = (string) val('SELECT MAX(visit_date) FROM medical_records WHERE pet_id = ? AND weight_kg IS NOT NULL', [$pet['id']]);
        if ($kg !== '' && $date >= $latest) {
            update('pets', ['weight_kg' => $kg], 'id = :id', ['id' => $pet['id']]);
        }
        notify((int) $pet['owner_id'], 'record', 'New health record entry', "The clinic added visit notes to {$pet['name']}'s health record.", 'owner/pet.php?id=' . $pet['id'] . '#records');
        log_activity((int) $me['id'], full_name($me) . " added a medical record for {$pet['name']}", 'file-heart', $self);
        flash('success', 'Medical record added.');
        redirect($self . '#records');
    }
    if ($action === 'add_vaccine') {
        $name = clip(input('vaccine_name'), 100);
        $given = input('date_given') ?: $today;
        $due = input('next_due_date');
        if ($name === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $given) || $given > $today || ($due !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) || $due < $given))) {
            flash('error', 'Please enter the vaccine name, a valid date given and a due date after it.');
            redirect($self . '#vaccines');
        }
        insert('vaccinations', [
            'pet_id' => $pet['id'], 'vaccine_name' => $name, 'date_given' => $given, 'next_due_date' => $due ?: null,
            'batch_no' => clip(input('batch_no'), 50) ?: null, 'vet_id' => int_param('vet_id') ?: null,
        ]);
        notify((int) $pet['owner_id'], 'vaccine', 'Vaccine card updated', "$name was added to {$pet['name']}'s vaccine card" . ($due ? ' — next dose due ' . fmt_date($due) : '') . '.', 'owner/pet.php?id=' . $pet['id'] . '#vaccines');
        log_activity((int) $me['id'], full_name($me) . " recorded $name for {$pet['name']}", 'syringe', $self);
        flash('success', 'Vaccination recorded.');
        redirect($self . '#vaccines');
    }
    if ($action === 'delete_vaccine') {
        q('DELETE FROM vaccinations WHERE id = ? AND pet_id = ?', [int_param('vaccine_id'), $pet['id']]);
        flash('success', 'Vaccination entry removed.');
        redirect($self . '#vaccines');
    }
}

$vets = rows('SELECT id, name FROM vets WHERE is_active = 1 ORDER BY id');
$isStaff = true;
$pageTitle = $pet['name'] . ' · Patient';
$activeNav = 'patients';
$useCharts = true;
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="crumbs"><a href="<?= e(url('admin/patients.php')) ?>">Patients</a><?= icon('chevron-right') ?><span><?= e($pet['name']) ?></span></div>
<?php include __DIR__ . '/../includes/partials/pet_profile.php'; ?>

<dialog class="modal modal-lg" id="modal-record">
    <form method="post">
        <div class="modal-head"><h3><?= icon('clipboard-plus') ?>Add medical record</h3><button class="modal-x" type="button" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="add_record">
            <div class="form-grid cols-3">
                <div class="field"><label for="m-date">Visit date</label><input class="input" id="m-date" type="date" name="visit_date" value="<?= e($today) ?>" max="<?= e($today) ?>"></div>
                <div class="field"><label for="m-kg">Weight (kg)</label><input class="input" id="m-kg" type="number" step="0.01" name="weight_kg" value="<?= e($pet['weight_kg']) ?>"></div>
                <div class="field"><label for="m-temp">Temperature (°C)</label><input class="input" id="m-temp" type="number" step="0.1" name="temperature_c" placeholder="38.5"></div>
                <div class="field full"><label for="m-vet">Veterinarian</label><select class="input" id="m-vet" name="vet_id"><?php foreach ($vets as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['name']) ?></option><?php endforeach; ?></select></div>
                <div class="field full"><label for="m-dx">Diagnosis / summary <span class="req">*</span></label><input class="input" id="m-dx" name="diagnosis" required placeholder="e.g. Otitis externa (ear infection)"></div>
                <div class="field full"><label for="m-tx">Findings &amp; treatment</label><textarea class="input" id="m-tx" name="treatment" rows="3"></textarea></div>
                <div class="field full"><label for="m-rx">Prescription</label><textarea class="input" id="m-rx" name="prescription" rows="2"></textarea></div>
                <div class="field full"><label for="m-notes">Notes</label><textarea class="input" id="m-notes" name="notes" rows="2"></textarea></div>
            </div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit"><?= icon('save') ?>Save record</button></div>
    </form>
</dialog>

<dialog class="modal" id="modal-vaccine">
    <form method="post">
        <div class="modal-head"><h3><?= icon('syringe') ?>Record vaccination</h3><button class="modal-x" type="button" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="add_vaccine">
            <div class="form-grid">
                <div class="field span-2"><label for="v-name">Vaccine <span class="req">*</span></label><input class="input" id="v-name" name="vaccine_name" list="v-list" required placeholder="Start typing…"><datalist id="v-list"><?php foreach (VACCINES[$pet['species']] ?? array_merge(...array_values(VACCINES)) as $vx): ?><option value="<?= e($vx) ?>"><?php endforeach; ?></datalist></div>
                <div class="field"><label for="v-given">Date given</label><input class="input" id="v-given" type="date" name="date_given" value="<?= e($today) ?>" max="<?= e($today) ?>"></div>
                <div class="field"><label for="v-due">Next due</label><input class="input" id="v-due" type="date" name="next_due_date" data-due-from="#v-given"></div>
                <div class="field"><label for="v-batch">Batch no.</label><input class="input" id="v-batch" name="batch_no"></div>
                <div class="field"><label for="v-vet">Given by</label><select class="input" id="v-vet" name="vet_id"><?php foreach ($vets as $v): ?><option value="<?= (int) $v['id'] ?>"><?= e($v['name']) ?></option><?php endforeach; ?></select></div>
            </div>
            <p class="field-hint mt-2">The next due date defaults to one year after the date given.</p>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit"><?= icon('save') ?>Save vaccination</button></div>
    </form>
</dialog>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
