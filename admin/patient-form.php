<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();
$id = int_param('id');
$pet = $id ? row('SELECT * FROM pets WHERE id = ?', [$id]) : null;
if ($id && !$pet) {
    abort(404, 'Patient not found');
}
$ownerOptions = rows("SELECT id, first_name, last_name, email FROM users WHERE role = 'owner' ORDER BY last_name, first_name");
$errors = [];

if (is_post()) {
    verify_csrf();
    if (input('action') === 'delete' && $pet) {
        if (!is_admin($me)) {
            abort(403, 'Only administrators can delete patients');
        }
        q('DELETE FROM pets WHERE id = ?', [$pet['id']]);
        delete_upload($pet['photo']);
        log_activity((int) $me['id'], full_name($me) . " deleted the patient record of {$pet['name']}", 'trash-2');
        flash('success', $pet['name'] . "'s record was deleted.");
        redirect('admin/patients.php');
    }
    $data = pet_form_data();
    $ownerId = (int) input('owner_id');
    $errors = validate_pet($data);
    if (!val("SELECT 1 FROM users WHERE id = ? AND role = 'owner'", [$ownerId])) {
        $errors['owner_id'] = 'Please choose the owner.';
    }
    $upload = handle_photo_upload('photo');
    if ($upload['error']) {
        $errors['photo'] = $upload['error'];
    }
    if (!$errors) {
        $data = normalize_pet($data) + ['owner_id' => $ownerId];
        if ($upload['path']) {
            $data['photo'] = $upload['path'];
            if ($pet) {
                delete_upload($pet['photo']);
            }
        }
        if ($pet) {
            update('pets', $data, 'id = :id', ['id' => $pet['id']]);
            flash('success', $data['name'] . "'s details were updated.");
            redirect('admin/patient.php?id=' . $pet['id']);
        }
        $newId = insert('pets', $data);
        log_activity((int) $me['id'], full_name($me) . ' registered a new patient: ' . $data['name'], 'paw-print', 'admin/patient.php?id=' . $newId);
        flash('success', $data['name'] . ' was added as a new patient.');
        redirect('admin/patient.php?id=' . $newId);
    }
    delete_upload($upload['path']);
}

$defaults = ['owner_id' => int_param('owner'), 'name' => '', 'species' => 'Dog', 'breed' => '', 'sex' => 'Unknown', 'birthdate' => '', 'weight_kg' => '', 'color' => '', 'is_neutered' => 0, 'allergies' => '', 'notes' => '', 'photo' => null];
$values = array_merge($defaults, $pet ?: []);
if (is_post()) {
    $values = array_merge($values, pet_form_data(), ['owner_id' => (int) input('owner_id')]);
}

$pageTitle = $pet ? 'Edit ' . $pet['name'] : 'New patient';
$activeNav = 'patients';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/patients.php')) ?>">Patients</a><?= icon('chevron-right') ?><span><?= $pet ? e($pet['name']) : 'New patient' ?></span></div>
        <h1><?= $pet ? 'Edit ' . e($pet['name']) : 'Register a new patient' ?></h1>
        <p>Linked to the owner's account so they can see records and book online.</p>
    </div>
</div>
<div class="settings-grid">
    <form class="card" method="post" enctype="multipart/form-data" novalidate>
        <div class="card-head"><h2><?= icon('paw-print') ?>Patient details</h2></div>
        <div class="card-body">
            <?= csrf_field() ?>
            <?php include __DIR__ . '/../includes/partials/pet_fields.php'; ?>
            <div class="form-actions">
                <a class="btn btn-outline" href="<?= e(url($pet ? 'admin/patient.php?id=' . $pet['id'] : 'admin/patients.php')) ?>">Cancel</a>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><?= $pet ? 'Save changes' : 'Add patient' ?></button>
            </div>
        </div>
    </form>
    <aside class="stack">
        <div class="card"><div class="card-body">
            <h3 class="flex items-center gap-1"><?= icon('user-plus', 'text-amber') ?>New client?</h3>
            <p class="muted text-sm">Register the owner first so the pet can be linked to their account.</p>
            <a class="btn btn-soft btn-sm" href="<?= e(url('admin/clients.php?new=1')) ?>"><?= icon('user-plus') ?>Register client</a>
        </div></div>
        <?php if ($pet && is_admin($me)): ?>
            <div class="card"><div class="card-body">
                <h3>Delete patient</h3>
                <p class="muted text-sm">Deletes the profile together with its appointments, medical records and vaccinations.</p>
                <form method="post" data-confirm="Permanently delete <?= e($pet['name']) ?> and all related records?" data-confirm-ok="Delete everything"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger-soft btn-sm" type="submit"><?= icon('trash-2') ?>Delete patient</button></form>
            </div></div>
        <?php endif; ?>
    </aside>
</div>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
