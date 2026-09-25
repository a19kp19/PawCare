<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();
$durations = [15, 30, 45, 60, 90, 120, 180];

if (is_post()) {
    verify_csrf();
    $action = input('action');
    $id = int_param('id');
    if ($action === 'save') {
        $d = [
            'name' => clip(input('name'), 100), 'category' => input('category'), 'description' => clip(input('description'), 500),
            'price' => input('price'), 'duration_minutes' => (int) input('duration_minutes'), 'icon' => input('icon'),
            'is_vaccination' => isset($_POST['is_vaccination']) ? 1 : 0, 'is_active' => isset($_POST['is_active']) ? 1 : 0, 'sort_order' => (int) input('sort_order'),
        ];
        $problems = [];
        if ($d['name'] === '') $problems[] = 'a name';
        if (!in_array($d['category'], SERVICE_CATEGORIES, true)) $problems[] = 'a category';
        if (!is_numeric($d['price']) || $d['price'] < 0) $problems[] = 'a valid price';
        if (!in_array($d['duration_minutes'], $durations, true)) $problems[] = 'a duration';
        if (!in_array($d['icon'], SERVICE_ICON_CHOICES, true)) $d['icon'] = 'stethoscope';
        if ($problems) {
            flash('error', 'Please provide ' . implode(', ', $problems) . '.');
        } elseif ($id) {
            update('services', $d, 'id = :id', ['id' => $id]);
            flash('success', $d['name'] . ' was updated.');
        } else {
            insert('services', $d);
            log_activity((int) $me['id'], full_name($me) . ' added a new service: ' . $d['name'], 'clipboard-list', 'admin/services.php');
            flash('success', $d['name'] . ' was added to the price list.');
        }
    } elseif ($action === 'toggle') {
        q('UPDATE services SET is_active = 1 - is_active WHERE id = ?', [$id]);
        flash('success', 'Service visibility updated.');
    } elseif ($action === 'delete') {
        if (val('SELECT COUNT(*) FROM appointments WHERE service_id = ?', [$id])) {
            flash('error', 'This service has bookings, so it can only be hidden, not deleted.');
        } else {
            q('DELETE FROM services WHERE id = ?', [$id]);
            flash('success', 'Service deleted.');
        }
    }
    redirect('admin/services.php');
}

$services = rows(
    "SELECT s.*, (SELECT COUNT(*) FROM appointments a WHERE a.service_id = s.id AND a.appointment_date >= ?) AS recent,
            (SELECT COUNT(*) FROM appointments a WHERE a.service_id = s.id) AS total
     FROM services s ORDER BY s.sort_order, s.name",
    [date('Y-m-d', strtotime('-30 days'))]
);
$blank = ['id' => '', 'name' => '', 'category' => 'Wellness', 'description' => '', 'price' => '', 'duration_minutes' => 30, 'icon' => 'stethoscope', 'is_vaccination' => 0, 'is_active' => 1, 'sort_order' => count($services) + 1];

$pageTitle = 'Services & Prices';
$activeNav = 'services';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>Services &amp; prices</span></div>
        <h1>Services &amp; prices</h1>
        <p>Everything here appears on the website and in the booking wizard. Duration controls how many slots a booking uses.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('services.php')) ?>" target="_blank" rel="noopener"><?= icon('external-link') ?>View public page</a>
        <button class="btn btn-primary" type="button" data-modal-open="modal-service" data-fill="<?= e(json_encode($blank)) ?>"><?= icon('plus') ?>Add service</button>
    </div>
</div>
<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Service</th><th>Category</th><th class="num">Duration</th><th class="num">Price</th><th class="num">Bookings (30d)</th><th>Visible</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($services as $s): ?>
                <tr>
                    <td><div class="cell-main"><span class="svc-icon-sm <?= category_class($s['category']) ?>"><?= icon($s['icon']) ?></span><span><span class="cell-title"><?= e($s['name']) ?><?= $s['is_vaccination'] ? ' <span class="badge badge-violet">' . icon('syringe') . 'Vaccine</span>' : '' ?></span><span class="cell-sub"><?= e(excerpt($s['description'], 80)) ?></span></span></div></td>
                    <td><span class="badge badge-teal"><?= e($s['category']) ?></span></td>
                    <td class="num"><?= (int) $s['duration_minutes'] ?> min</td>
                    <td class="num fw-700"><?= money($s['price'], true) ?></td>
                    <td class="num"><?= (int) $s['recent'] ?> <span class="muted text-xs">/ <?= (int) $s['total'] ?></span></td>
                    <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><label class="switch" title="Show on website"><input type="checkbox" data-autosubmit<?= checked((bool) $s['is_active']) ?> aria-label="Visible"></label></form></td>
                    <td><div class="row-actions">
                        <button class="btn btn-ghost btn-xs" type="button" data-modal-open="modal-service" data-fill="<?= e(json_encode(array_intersect_key($s, $blank))) ?>"><?= icon('pencil') ?>Edit</button>
                        <?php if (!(int) $s['total']): ?>
                            <form method="post" data-confirm="Delete the “<?= e($s['name']) ?>” service?" data-confirm-ok="Delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-ghost btn-icon btn-xs" type="submit" aria-label="Delete"><?= icon('trash-2') ?></button></form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<dialog class="modal modal-lg" id="modal-service">
    <form method="post">
        <div class="modal-head"><h3><?= icon('clipboard-list') ?>Service details</h3><button class="modal-x" type="button" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="">
            <div class="form-grid">
                <div class="field span-2"><label for="s-name">Name <span class="req">*</span></label><input class="input" id="s-name" name="name" maxlength="100" required></div>
                <div class="field"><label for="s-cat">Category</label><select class="input" id="s-cat" name="category"><?php foreach (SERVICE_CATEGORIES as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
                <div class="field"><label for="s-price">Starting price (<?= e(CURRENCY) ?>)</label><input class="input" id="s-price" type="number" name="price" min="0" step="50" required></div>
                <div class="field"><label for="s-dur">Duration</label><select class="input" id="s-dur" name="duration_minutes"><?php foreach ($durations as $d): ?><option value="<?= $d ?>"><?= $d ?> minutes</option><?php endforeach; ?></select></div>
                <div class="field"><label for="s-sort">Display order</label><input class="input" id="s-sort" type="number" name="sort_order" min="0"></div>
                <div class="field span-2"><label for="s-desc">Description</label><textarea class="input" id="s-desc" name="description" rows="3" maxlength="500"></textarea></div>
                <div class="field span-2"><span class="label">Icon</span>
                    <div class="icon-picker">
                        <?php foreach (SERVICE_ICON_CHOICES as $ic): ?><label class="choice" title="<?= e($ic) ?>"><input type="radio" name="icon" value="<?= e($ic) ?>"><span><?= icon($ic) ?></span></label><?php endforeach; ?>
                    </div>
                </div>
                <div class="field"><label class="switch"><input type="checkbox" name="is_vaccination" value="1"> Vaccination service (shows vaccine fields when recording)</label></div>
                <div class="field"><label class="switch"><input type="checkbox" name="is_active" value="1" checked> Visible on website &amp; booking</label></div>
            </div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit"><?= icon('save') ?>Save service</button></div>
    </form>
</dialog>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
