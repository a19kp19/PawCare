<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();
$errors = [];

if (is_post() && input('action') === 'create') {
    verify_csrf();
    $d = ['first_name' => clip(input('first_name'), 60), 'last_name' => clip(input('last_name'), 60), 'email' => clip(strtolower(input('email')), 120), 'phone' => clip(input('phone'), 30), 'address' => clip(input('address'), 255)];
    if ($d['first_name'] === '' || $d['last_name'] === '') $errors[] = 'First and last name are required.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    elseif (val('SELECT 1 FROM users WHERE email = ?', [$d['email']])) $errors[] = 'That email is already registered.';
    if (!$errors) {
        $password = random_password();
        $id = insert('users', array_merge($d, ['role' => 'owner', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'phone' => $d['phone'] ?: null, 'address' => $d['address'] ?: null]));
        notify($id, 'system', 'Welcome to ' . CLINIC_SHORT_NAME . ', ' . $d['first_name'] . '!', 'The clinic created your account. You can now view health records and book visits online.', 'owner/');
        log_activity((int) $me['id'], full_name($me) . ' registered a new client: ' . $d['first_name'] . ' ' . $d['last_name'], 'user-plus', 'admin/client.php?id=' . $id);
        $_SESSION['temp_password'] = ['user' => $id, 'password' => $password];
        flash('success', 'Client account created.');
        redirect('admin/client.php?id=' . $id);
    }
}

$q = param('q');
$where = "WHERE u.role = 'owner'";
$params = [];
if ($q !== '') {
    $where .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$total = (int) val("SELECT COUNT(*) FROM users u $where", $params);
$p = paginate($total, 15);
$list = rows(
    "SELECT u.*,
            (SELECT COUNT(*) FROM appointments a WHERE a.owner_id = u.id AND a.status = 'completed') AS visits,
            (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.owner_id = u.id AND a.status = 'completed') AS last_visit,
            (SELECT COALESCE(SUM(a.price), 0) FROM appointments a WHERE a.owner_id = u.id AND a.status = 'completed') AS spent
     FROM users u $where ORDER BY u.last_name, u.first_name LIMIT {$p['per']} OFFSET {$p['offset']}",
    $params
);
$petsByOwner = [];
if ($list) {
    $ids = implode(',', array_map('intval', array_column($list, 'id')));
    foreach (rows("SELECT id, owner_id, name, species, photo FROM pets WHERE owner_id IN ($ids) ORDER BY name") as $pp) {
        $petsByOwner[(int) $pp['owner_id']][] = $pp;
    }
}

$pageTitle = 'Clients';
$activeNav = 'clients';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>Clients</span></div>
        <h1>Clients</h1>
        <p><?= plural($total, 'pet owner') ?> registered<?= $q !== '' ? ' matching “' . e($q) . '”' : '' ?>.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('admin/export.php?type=clients')) ?>"><?= icon('download') ?>Export CSV</a>
        <button class="btn btn-primary" type="button" data-modal-open="modal-client"><?= icon('user-plus') ?>Register client</button>
    </div>
</div>
<?php if ($errors): ?><div class="alert alert-danger mb-2"><?= icon('circle-alert') ?><div><?= e(implode(' ', $errors)) ?></div></div><?php endif; ?>
<div class="card">
    <form class="filters" method="get">
        <div class="input-group grow"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name, email or phone"></div>
        <button class="btn btn-soft btn-sm" type="submit"><?= icon('search') ?>Search</button>
    </form>
    <?php if ($list): ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Client</th><th>Phone</th><th>Pets</th><th class="num">Visits</th><th class="num">Total spent</th><th>Last visit</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($list as $c): $pp = $petsByOwner[(int) $c['id']] ?? []; ?>
                    <tr>
                        <td><a class="cell-main" href="<?= e(url('admin/client.php?id=' . $c['id'])) ?>"><?= avatar(full_name($c)) ?><span><span class="cell-title"><?= e(full_name($c)) ?></span><span class="cell-sub"><?= e($c['email']) ?></span></span></a></td>
                        <td><?= e($c['phone'] ?: '—') ?></td>
                        <td><div class="flex items-center gap-1"><?php foreach (array_slice($pp, 0, 3) as $pet): ?><?= pet_photo($pet, 'size-xs round') ?><?php endforeach; ?><span class="text-sm muted"><?= $pp ? e(implode(', ', array_column(array_slice($pp, 0, 3), 'name'))) . (count($pp) > 3 ? ' +' . (count($pp) - 3) : '') : 'No pets yet' ?></span></div></td>
                        <td class="num"><?= (int) $c['visits'] ?></td>
                        <td class="num"><?= money($c['spent']) ?></td>
                        <td><?= e(fmt_date($c['last_visit'])) ?></td>
                        <td><?= $c['is_active'] ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Inactive</span>' ?></td>
                        <td><a class="btn btn-ghost btn-icon btn-xs" href="<?= e(url('admin/client.php?id=' . $c['id'])) ?>" aria-label="Open client"><?= icon('chevron-right') ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= pagination_links($p) ?>
    <?php else: ?>
        <?= empty_state('users', 'No clients found', 'Try another search or register a walk-in client.') ?>
    <?php endif; ?>
</div>

<dialog class="modal" id="modal-client"<?= param('new') || $errors ? ' data-open-on-load' : '' ?>>
    <form method="post" novalidate>
        <div class="modal-head"><h3><?= icon('user-plus') ?>Register a client</h3><button class="modal-x" type="button" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="field"><label for="c-first">First name <span class="req">*</span></label><input class="input" id="c-first" name="first_name" value="<?= e(old('first_name')) ?>" required></div>
                <div class="field"><label for="c-last">Last name <span class="req">*</span></label><input class="input" id="c-last" name="last_name" value="<?= e(old('last_name')) ?>" required></div>
                <div class="field"><label for="c-email">Email <span class="req">*</span></label><input class="input" id="c-email" type="email" name="email" value="<?= e(old('email')) ?>" required></div>
                <div class="field"><label for="c-phone">Mobile</label><input class="input" id="c-phone" name="phone" value="<?= e(old('phone')) ?>" placeholder="0917 000 0000"></div>
                <div class="field span-2"><label for="c-address">Address</label><input class="input" id="c-address" name="address" value="<?= e(old('address')) ?>"></div>
            </div>
            <p class="field-hint mt-2"><?= icon('key-round') ?> A temporary password is generated and shown once so you can give it to the client.</p>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit"><?= icon('user-plus') ?>Create account</button></div>
    </form>
</dialog>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
