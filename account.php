<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$errors = [];

if (is_post()) {
    verify_csrf();
    if (input('action') === 'profile') {
        $d = ['first_name' => clip(input('first_name'), 60), 'last_name' => clip(input('last_name'), 60), 'email' => clip(strtolower(input('email')), 120), 'phone' => clip(input('phone'), 30), 'address' => clip(input('address'), 255)];
        if ($d['first_name'] === '' || $d['last_name'] === '') $errors['profile'] = 'Please enter your first and last name.';
        elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['profile'] = 'Please enter a valid email address.';
        elseif (val('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$d['email'], $me['id']])) $errors['profile'] = 'Another account already uses that email.';
        if (!$errors) {
            update('users', array_merge($d, ['phone' => $d['phone'] ?: null, 'address' => $d['address'] ?: null]), 'id = :id', ['id' => $me['id']]);
            flash('success', 'Your profile was updated.');
            redirect('account.php');
        }
    }
    if (input('action') === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        if (!password_verify($current, $me['password_hash'])) $errors['password'] = 'Your current password is incorrect.';
        elseif ($problem = password_problems($new)) $errors['password'] = $problem;
        elseif ($new !== (string) ($_POST['confirm_password'] ?? '')) $errors['password'] = 'The new passwords do not match.';
        if (!$errors) {
            update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = :id', ['id' => $me['id']]);
            session_regenerate_id(true);
            flash('success', 'Password changed. Use your new password next time you log in.');
            redirect('account.php');
        }
    }
}
$v = fn (string $k) => e(is_post() && input('action') === 'profile' ? input($k) : $me[$k]);
$petCount = (int) val('SELECT COUNT(*) FROM pets WHERE owner_id = ?', [$me['id']]);

$pageTitle = 'My account';
$activeNav = 'account';
include __DIR__ . '/includes/layout/app_header.php';
?>
<div class="page-head">
    <div><h1>My account</h1><p>Manage your contact details and password.</p></div>
</div>
<div class="settings-grid">
    <div class="stack">
        <form class="card" method="post" novalidate>
            <div class="card-head"><h2><?= icon('user-round') ?>Profile</h2></div>
            <div class="card-body">
                <?= csrf_field() ?><input type="hidden" name="action" value="profile">
                <?php if (isset($errors['profile'])): ?><div class="alert alert-danger mb-2"><?= icon('circle-alert') ?><div><?= e($errors['profile']) ?></div></div><?php endif; ?>
                <div class="form-grid">
                    <div class="field"><label for="a-first">First name</label><input class="input" id="a-first" name="first_name" value="<?= $v('first_name') ?>" required></div>
                    <div class="field"><label for="a-last">Last name</label><input class="input" id="a-last" name="last_name" value="<?= $v('last_name') ?>" required></div>
                    <div class="field"><label for="a-email">Email</label><div class="input-group"><?= icon('mail') ?><input class="input" id="a-email" type="email" name="email" value="<?= $v('email') ?>" required></div></div>
                    <div class="field"><label for="a-phone">Mobile</label><div class="input-group"><?= icon('phone') ?><input class="input" id="a-phone" name="phone" value="<?= $v('phone') ?>"></div></div>
                    <div class="field span-2"><label for="a-address">Address</label><div class="input-group"><?= icon('map-pin') ?><input class="input" id="a-address" name="address" value="<?= $v('address') ?>"></div></div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('save') ?>Save profile</button></div>
            </div>
        </form>
        <form class="card" method="post" novalidate>
            <div class="card-head"><h2><?= icon('lock') ?>Change password</h2></div>
            <div class="card-body">
                <?= csrf_field() ?><input type="hidden" name="action" value="password">
                <?php if (isset($errors['password'])): ?><div class="alert alert-danger mb-2"><?= icon('circle-alert') ?><div><?= e($errors['password']) ?></div></div><?php endif; ?>
                <div class="form-grid">
                    <div class="field span-2"><label for="p-cur">Current password</label><div class="input-group"><?= icon('lock') ?><input class="input" id="p-cur" type="password" name="current_password" autocomplete="current-password" required><button class="input-action" type="button" data-password-toggle aria-label="Show password"><?= icon('eye') ?></button></div></div>
                    <div class="field"><label for="p-new">New password</label><div class="input-group"><?= icon('key-round') ?><input class="input" id="p-new" type="password" name="new_password" autocomplete="new-password" data-password-meter="#pw-meter" required><button class="input-action" type="button" data-password-toggle aria-label="Show password"><?= icon('eye') ?></button></div>
                        <div><div class="pw-meter" id="pw-meter" data-score="0"><span></span><span></span><span></span><span></span></div><div class="pw-label" data-pw-label>Use 8+ characters with letters and a number</div></div></div>
                    <div class="field"><label for="p-confirm">Confirm new password</label><div class="input-group"><?= icon('key-round') ?><input class="input" id="p-confirm" type="password" name="confirm_password" autocomplete="new-password" required></div></div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('key-round') ?>Update password</button></div>
            </div>
        </form>
    </div>
    <aside class="card">
        <div class="card-body">
            <div class="person-card"><?= avatar(full_name($me), '') ?><div><strong style="font-size:1.1rem"><?= e(full_name($me)) ?></strong><div class="muted text-sm"><?= e(role_label($me['role'])) ?></div></div></div>
            <dl class="dl mt-3">
                <dt>Member since</dt><dd><?= e(fmt_date($me['created_at'], 'F j, Y')) ?></dd>
                <dt>Last login</dt><dd><?= $me['last_login_at'] ? e(fmt_datetime($me['last_login_at'])) : '—' ?></dd>
                <?php if (!is_staff($me)): ?><dt>Pets</dt><dd><?= $petCount ?></dd><?php endif; ?>
            </dl>
            <div class="alert alert-info mt-3"><?= icon('shield-check') ?><div><strong>Your data is protected</strong>Passwords are stored as secure hashes and every form is protected against cross-site request forgery.</div></div>
        </div>
    </aside>
</div>
<?php include __DIR__ . '/includes/layout/app_footer.php'; ?>
