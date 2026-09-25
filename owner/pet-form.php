<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_owner();
$id = int_param('id');
$pet = $id ? row('SELECT * FROM pets WHERE id = ? AND owner_id = ?', [$id, $user['id']]) : null;
if ($id && !$pet) {
    abort(404, 'Pet not found');
}
$first = param('first') !== '';
$errors = [];
$hasHistory = $pet ? (int) val('SELECT (SELECT COUNT(*) FROM appointments WHERE pet_id = ?) + (SELECT COUNT(*) FROM medical_records WHERE pet_id = ?)', [$pet['id'], $pet['id']]) : 0;

if (is_post()) {
    verify_csrf();
    if (input('action') === 'delete' && $pet) {
        if ($hasHistory) {
            flash('error', $pet['name'] . ' has visit history, so the profile can only be removed by the clinic.');
            redirect('owner/pet-form.php?id=' . $pet['id']);
        }
        q('DELETE FROM pets WHERE id = ? AND owner_id = ?', [$pet['id'], $user['id']]);
        delete_upload($pet['photo']);
        flash('success', $pet['name'] . "'s profile was removed.");
        redirect('owner/pets.php');
    }

    $data = pet_form_data();
    $errors = validate_pet($data);
    $upload = handle_photo_upload('photo');
    if ($upload['error']) {
        $errors['photo'] = $upload['error'];
    }
    if (!$errors) {
        $data = normalize_pet($data);
        if ($upload['path']) {
            $data['photo'] = $upload['path'];
            if ($pet) {
                delete_upload($pet['photo']);
            }
        }
        if ($pet) {
            update('pets', $data, 'id = :id', ['id' => $pet['id']]);
            flash('success', $data['name'] . "'s profile was updated.");
            redirect('owner/pet.php?id=' . $pet['id']);
        }
        $data['owner_id'] = $user['id'];
        $newId = insert('pets', $data);
        log_activity((int) $user['id'], full_name($user) . ' added a new pet: ' . $data['name'], 'paw-print', 'admin/patient.php?id=' . $newId);
        flash('success', 'Welcome to the family, ' . $data['name'] . '! 🐾');
        redirect($first ? 'owner/book.php?pet=' . $newId : 'owner/pet.php?id=' . $newId);
    }
    delete_upload($upload['path']);
}

$defaults = ['name' => '', 'species' => 'Dog', 'breed' => '', 'sex' => 'Unknown', 'birthdate' => '', 'weight_kg' => '', 'color' => '', 'is_neutered' => 0, 'allergies' => '', 'notes' => '', 'photo' => null];
$values = array_merge($defaults, $pet ?: []);
if (is_post()) {
    $values = array_merge($values, pet_form_data());
}

$pageTitle = $pet ? 'Edit ' . $pet['name'] : 'Add a pet';
$activeNav = 'pets';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('owner/pets.php')) ?>">My pets</a><?= icon('chevron-right') ?><span><?= $pet ? e($pet['name']) : 'New pet' ?></span></div>
        <h1><?= $pet ? 'Edit ' . e($pet['name']) . "'s profile" : 'Add a new pet' ?></h1>
        <p><?= $pet ? 'Keep details current so our vets always have the full picture.' : 'Tell us about your pet — you can update these details anytime.' ?></p>
    </div>
</div>
<?php if ($first && !$pet): ?>
    <div class="alert alert-success mb-2"><?= icon('party-popper') ?><div><strong>Your account is ready!</strong>Add your first pet below, then we'll take you straight to booking.</div></div>
<?php endif; ?>
<div class="settings-grid">
    <form class="card" method="post" enctype="multipart/form-data" novalidate>
        <div class="card-head"><h2><?= icon('paw-print') ?>Pet details</h2></div>
        <div class="card-body">
            <?= csrf_field() ?>
            <?php include __DIR__ . '/../includes/partials/pet_fields.php'; ?>
            <div class="form-actions">
                <a class="btn btn-outline" href="<?= e(url($pet ? 'owner/pet.php?id=' . $pet['id'] : 'owner/pets.php')) ?>">Cancel</a>
                <button class="btn btn-primary" type="submit"><?= icon('save') ?><?= $pet ? 'Save changes' : 'Add pet' ?></button>
            </div>
        </div>
    </form>
    <aside class="stack">
        <div class="card">
            <div class="card-body">
                <h3 class="flex items-center gap-1"><?= icon('lightbulb', 'text-amber') ?>Tips for a great profile</h3>
                <ul class="check-list" style="margin:1rem 0 0">
                    <li><span class="ck"><?= icon('camera') ?></span><div><strong>Add a clear photo</strong><span class="muted text-sm">It helps our team recognise your pet at the front desk.</span></div></li>
                    <li><span class="ck"><?= icon('triangle-alert') ?></span><div><strong>List every allergy</strong><span class="muted text-sm">Food or medicine allergies are flagged for the vet.</span></div></li>
                    <li><span class="ck"><?= icon('weight') ?></span><div><strong>Weight matters</strong><span class="muted text-sm">Medicine doses are based on your pet's weight.</span></div></li>
                </ul>
            </div>
        </div>
        <?php if ($pet): ?>
            <div class="card">
                <div class="card-body">
                    <h3>Remove profile</h3>
                    <?php if ($hasHistory): ?>
                        <p class="muted text-sm mb-0"><?= e($pet['name']) ?> has visit history at the clinic, so the profile is kept for medical records. Contact the clinic if you need it removed.</p>
                    <?php else: ?>
                        <p class="muted text-sm">This permanently deletes <?= e($pet['name']) ?>'s profile.</p>
                        <form method="post" data-confirm="Delete <?= e($pet['name']) ?>'s profile permanently?" data-confirm-ok="Delete profile"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><button class="btn btn-danger-soft btn-sm" type="submit"><?= icon('trash-2') ?>Delete profile</button></form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </aside>
</div>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
