<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

if (is_post()) {
    verify_csrf();
    $id = int_param('id');
    if (input('action') === 'toggle') {
        q('UPDATE vets SET is_active = 1 - is_active WHERE id = ?', [$id]);
        flash('success', 'Availability updated.');
        redirect('admin/vets.php');
    }
    $days = array_values(array_intersect(array_map('intval', (array) ($_POST['work_days'] ?? [])), range(1, 7)));
    sort($days);
    $d = ['name' => clip(input('name'), 120), 'title' => clip(input('title'), 120), 'specialty' => clip(input('specialty'), 120) ?: null, 'bio' => clip(input('bio'), 1000) ?: null, 'work_days' => implode(',', $days), 'is_active' => isset($_POST['is_active']) ? 1 : 0];
    $upload = handle_photo_upload('photo', 'vets');
    if ($d['name'] === '' || $d['title'] === '' || !$days || $upload['error']) {
        flash('error', $upload['error'] ?: 'Name, title and at least one clinic day are required.');
        delete_upload($upload['path']);
        redirect('admin/vets.php');
    }
    if ($upload['path']) {
        $d['photo'] = $upload['path'];
    }
    if ($id) {
        $old = row('SELECT photo FROM vets WHERE id = ?', [$id]);
        if ($upload['path'] && $old) {
            delete_upload($old['photo']);
        }
        update('vets', $d, 'id = :id', ['id' => $id]);
        flash('success', $d['name'] . "'s profile was updated.");
    } else {
        insert('vets', $d);
        log_activity((int) $me['id'], full_name($me) . ' added ' . $d['name'] . ' to the team', 'stethoscope', 'admin/vets.php');
        flash('success', $d['name'] . ' joined the team!');
    }
    redirect('admin/vets.php');
}

$vets = rows(
    "SELECT v.*, (SELECT COUNT(*) FROM appointments a WHERE a.vet_id = v.id AND a.appointment_date BETWEEN ? AND ? AND a.status <> 'cancelled') AS month_count,
            (SELECT COUNT(*) FROM appointments a WHERE a.vet_id = v.id AND a.appointment_date >= ? AND a.status IN ('pending','confirmed')) AS upcoming
     FROM vets v ORDER BY v.id",
    [date('Y-m-01'), date('Y-m-t'), date('Y-m-d')]
);
$blank = ['id' => '', 'name' => '', 'title' => 'Veterinarian', 'specialty' => '', 'bio' => '', 'work_days[]' => [1, 2, 3, 4, 5], 'is_active' => 1, 'photo_url' => ''];

$pageTitle = 'Veterinarians';
$activeNav = 'vets';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>Veterinarians</span></div>
        <h1>Veterinarians</h1>
        <p>Profiles appear on the website. Clinic days control which dates can be booked with each vet.</p>
    </div>
    <div class="page-actions"><button class="btn btn-primary" type="button" data-modal-open="modal-vet" data-fill="<?= e(json_encode($blank)) ?>"><?= icon('plus') ?>Add veterinarian</button></div>
</div>
<div class="vet-admin-grid">
    <?php foreach ($vets as $v): $days = vet_work_days($v); ?>
        <article class="vet-admin-card">
            <div class="va-photo"><?= vet_photo($v) ?><?= $v['is_active'] ? '<span class="badge badge-green">Accepting bookings</span>' : '<span class="badge badge-gray">Hidden</span>' ?></div>
            <div class="va-body">
                <div><h3><?= e($v['name']) ?></h3><div class="muted text-sm"><?= e($v['title']) ?><?= $v['specialty'] ? ' · ' . e($v['specialty']) : '' ?></div></div>
                <div class="day-chips"><?php for ($d = 1; $d <= 7; $d++): ?><span class="day-chip<?= in_array($d, $days, true) ? ' on' : '' ?>"><?= e(weekday_name($d, true)) ?></span><?php endfor; ?></div>
                <div class="flex gap-2 text-sm"><span><strong><?= (int) $v['month_count'] ?></strong> <span class="muted">this month</span></span><span><strong><?= (int) $v['upcoming'] ?></strong> <span class="muted">upcoming</span></span></div>
                <div class="flex gap-1 mt-1">
                    <button class="btn btn-outline btn-sm" type="button" data-modal-open="modal-vet" data-fill="<?= e(json_encode(['id' => $v['id'], 'name' => $v['name'], 'title' => $v['title'], 'specialty' => $v['specialty'], 'bio' => $v['bio'], 'work_days[]' => $days, 'is_active' => $v['is_active'], 'photo_url' => $v['photo'] ? url($v['photo']) : ''])) ?>"><?= icon('pencil') ?>Edit</button>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $v['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit"><?= icon($v['is_active'] ? 'eye-off' : 'eye') ?><?= $v['is_active'] ? 'Hide' : 'Show' ?></button></form>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<dialog class="modal modal-lg" id="modal-vet">
    <form method="post" enctype="multipart/form-data">
        <div class="modal-head"><h3><?= icon('stethoscope') ?>Veterinarian profile</h3><button class="modal-x" type="button" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
            <div class="form-grid">
                <div class="field span-2">
                    <label class="dropzone" data-dropzone data-max-mb="<?= (int) MAX_UPLOAD_MB ?>">
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" aria-label="Upload photo">
                        <span class="dz-preview" data-dz-preview data-fill-src="photo_url"><?= icon('camera') ?></span>
                        <span class="dz-text"><strong data-dz-label>Profile photo</strong><span>Portrait orientation looks best · up to <?= (int) MAX_UPLOAD_MB ?> MB</span></span>
                    </label>
                </div>
                <div class="field"><label for="v-name">Full name <span class="req">*</span></label><input class="input" id="v-name" name="name" placeholder="Dr. Juana Dela Cruz" required></div>
                <div class="field"><label for="v-title">Title <span class="req">*</span></label><input class="input" id="v-title" name="title" placeholder="Veterinarian" required></div>
                <div class="field span-2"><label for="v-spec">Specialty</label><input class="input" id="v-spec" name="specialty" placeholder="e.g. Dermatology"></div>
                <div class="field span-2"><label for="v-bio">Short bio</label><textarea class="input" id="v-bio" name="bio" rows="3" maxlength="1000"></textarea></div>
                <div class="field span-2"><span class="label">Clinic days</span>
                    <div class="days-picker"><?php for ($d = 1; $d <= 7; $d++): ?><label class="choice"><input type="checkbox" name="work_days[]" value="<?= $d ?>"><span><?= e(weekday_name($d, true)) ?></span></label><?php endfor; ?></div>
                </div>
                <div class="field span-2"><label class="switch"><input type="checkbox" name="is_active" value="1" checked> Accepting bookings &amp; shown on website</label></div>
            </div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit"><?= icon('save') ?>Save profile</button></div>
    </form>
</dialog>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
