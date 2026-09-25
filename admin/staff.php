<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_admin();

if (is_post()) {
    verify_csrf();
    $action = input('action');
    $id = int_param('id');
    if ($action === 'create') {
        $d = ['first_name' => clip(input('first_name'), 60), 'last_name' => clip(input('last_name'), 60), 'email' => clip(strtolower(input('email')), 120), 'phone' => clip(input('phone'), 30) ?: null, 'role' => input('role') === 'admin' ? 'admin' : 'staff'];
        $password = (string) ($_POST['password'] ?? '');
        $password = $password === '' ? random_password() : $password;
        if ($d['first_name'] === '' || $d['last_name'] === '' || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Name and a valid email are required.');
        } elseif (val('SELECT 1 FROM users WHERE email = ?', [$d['email']])) {
            flash('error', 'That email is already in use.');
        } elseif ($problem = password_problems($password)) {
            flash('error', $problem);
        } else {
            $newId = insert('users', $d + ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
            $_SESSION['temp_password'] = ['user' => $newId, 'password' => $password, 'email' => $d['email']];
            log_activity((int) $me['id'], full_name($me) . ' created a ' . $d['role'] . ' account for ' . $d['first_name'] . ' ' . $d['last_name'], 'user-cog', 'admin/staff.php');
            flash('success', 'Account created for ' . $d['first_name'] . '.');
        }
    } elseif ($action === 'reset' && $id) {
        $user = row("SELECT * FROM users WHERE id = ? AND role IN ('admin','staff')", [$id]);
        if ($user) {
            $password = random_password();
            update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $id]);
            $_SESSION['temp_password'] = ['user' => $id, 'password' => $password, 'email' => $user['email']];
        }
    } elseif ($action === 'toggle' && $id) {
        if ($id === (int) $me['id']) {
            flash('error', "You can't deactivate your own account.");
        } else {
            q("UPDATE users SET is_active = 1 - is_active WHERE id = ? AND role IN ('admin','staff')", [$id]);
            flash('success', 'Account status updated.');
        }
    }
    redirect('admin/staff.php');
}

$temp = $_SESSION['temp_password'] ?? null;
unset($_SESSION['temp_password']);
$staff = rows("SELECT * FROM users WHERE role IN ('admin','staff') ORDER BY role, first_name");

$pageTitle = 'Staff accounts';
$activeNav = 'staff';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>Staff accounts</span></div>
        <h1>Staff accounts</h1>
        <p>Administrators can manage services, vets, staff and reports. Staff handle appointments, patients and reminders.</p>
    </div>
    <div class="page-actions"><button class="btn btn-primary" type="button" data-modal-open="modal-staff"><?= icon('user-plus') ?>Add staff member</button></div>
</div>
<?php if ($temp && !empty($temp['email'])): ?>
    <div class="temp-password mb-3"><?= icon('key-round') ?>Password for <?= e($temp['email']) ?>: <code><?= e($temp['password']) ?></code><button class="btn btn-white btn-xs" type="button" data-copy="<?= e($temp['password']) ?>"><?= icon('copy') ?>Copy</button><span class="text-sm">Shown once — share it securely.</span></div>
<?php endif; ?>
<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Role</th><th>Phone</th><th>Last login</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($staff as $s): ?>
                <tr>
                    <td><div class="cell-main"><?= avatar(full_name($s)) ?><span><span class="cell-title"><?= e(full_name($s)) ?><?= (int) $s['id'] === (int) $me['id'] ? ' <span class="badge badge-outline">You</span>' : '' ?></span><span class="cell-sub"><?= e($s['email']) ?></span></span></div></td>
                    <td><?= $s['role'] === 'admin' ? '<span class="badge badge-violet">' . icon('user-cog') . 'Administrator</span>' : '<span class="badge badge-blue">' . icon('stethoscope') . 'Staff</span>' ?></td>
                    <td><?= e($s['phone'] ?: '—') ?></td>
                    <td><?= $s['last_login_at'] ? e(time_ago($s['last_login_at'])) : 'Never' ?></td>
                    <td><?= $s['is_active'] ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Inactive</span>' ?></td>
                    <td><div class="row-actions">
                        <form method="post" data-confirm="Generate a new password for <?= e($s['first_name']) ?>?" data-confirm-ok="Reset password" data-confirm-tone="primary"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-ghost btn-xs" type="submit"><?= icon('key-round') ?>Reset password</button></form>
                        <?php if ((int) $s['id'] !== (int) $me['id']): ?>
                            <form method="post" data-confirm="<?= $s['is_active'] ? 'Deactivate' : 'Reactivate' ?> <?= e($s['first_name']) ?>'s account?" data-confirm-ok="<?= $s['is_active'] ? 'Deactivate' : 'Reactivate' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button class="btn btn-ghost btn-xs" type="submit"><?= icon($s['is_active'] ? 'user-x' : 'user-check') ?><?= $s['is_active'] ? 'Deactivate' : 'Reactivate' ?></button></form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<dialog class="modal" id="modal-staff">
    <form method="post">
        <div class="modal-head"><h3><?= icon('user-plus') ?>New staff account</h3><button class="modal-x" type="button" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <div class="form-grid">
                <div class="field"><label for="st-first">First name</label><input class="input" id="st-first" name="first_name" required></div>
                <div class="field"><label for="st-last">Last name</label><input class="input" id="st-last" name="last_name" required></div>
                <div class="field span-2"><label for="st-email">Email</label><input class="input" id="st-email" type="email" name="email" required></div>
                <div class="field"><label for="st-phone">Phone</label><input class="input" id="st-phone" name="phone"></div>
                <div class="field"><span class="label">Role</span><div class="choice-row"><label class="choice"><input type="radio" name="role" value="staff" checked><span>Staff</span></label><label class="choice"><input type="radio" name="role" value="admin"><span>Admin</span></label></div></div>
                <div class="field span-2"><label for="st-pass">Password</label><div class="input-group"><?= icon('lock') ?><input class="input" id="st-pass" type="password" name="password" placeholder="Leave blank to generate one"><button class="input-action" type="button" data-password-toggle aria-label="Show password"><?= icon('eye') ?></button></div></div>
            </div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit"><?= icon('user-plus') ?>Create account</button></div>
    </form>
</dialog>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
