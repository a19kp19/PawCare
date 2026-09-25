<?php
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) {
    redirect(home_for($u));
}
$next = safe_next(param('next') ?: input('next'));
$errors = [];

if (is_post()) {
    verify_csrf();
    $d = [
        'first_name' => clip(input('first_name'), 60),
        'last_name'  => clip(input('last_name'), 60),
        'email'      => clip(strtolower(input('email')), 120),
        'phone'      => clip(input('phone'), 30),
    ];
    $password = (string) ($_POST['password'] ?? '');
    if ($d['first_name'] === '') $errors['first_name'] = 'Please enter your first name.';
    if ($d['last_name'] === '') $errors['last_name'] = 'Please enter your last name.';
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif (val('SELECT 1 FROM users WHERE email = ?', [$d['email']])) {
        $errors['email'] = 'An account with this email already exists. Try logging in instead.';
    }
    if ($d['phone'] !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $d['phone'])) $errors['phone'] = 'Please enter a valid phone number.';
    if ($problem = password_problems($password)) $errors['password'] = $problem;
    if ($password !== (string) ($_POST['password_confirm'] ?? '')) $errors['password_confirm'] = 'Passwords do not match.';
    if (empty($_POST['terms'])) $errors['terms'] = 'Please agree to the clinic policies to continue.';

    if (!$errors) {
        $id = insert('users', array_merge($d, ['role' => 'owner', 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'phone' => $d['phone'] !== '' ? $d['phone'] : null]));
        $user = row('SELECT * FROM users WHERE id = ?', [$id]);
        notify($id, 'system', 'Welcome to ' . CLINIC_SHORT_NAME . ', ' . $d['first_name'] . '!', 'Your pet health portal is ready. Add your pets and book visits online anytime.', 'owner/');
        notify_staff('account', 'New client registered', full_name($user) . ' created an account on the website.', 'admin/client.php?id=' . $id);
        log_activity($id, 'New client registered: ' . full_name($user), 'user-plus', 'admin/client.php?id=' . $id);
        login_user($user);
        flash('success', 'Welcome, ' . $d['first_name'] . '! Let\'s add your first pet.');
        redirect($next && strpos($next, 'owner/') === 0 ? $next : 'owner/pet-form.php?first=1');
    }
}

function reg_field(string $name, string $label, array $errors, string $type = 'text', string $placeholder = '', string $iconName = '', string $extra = ''): void
{
    $has = isset($errors[$name]);
    echo '<div class="field' . ($has ? ' has-error' : '') . '"><label for="' . $name . '">' . e($label) . '</label>';
    echo $iconName ? '<div class="input-group">' . icon($iconName) : '';
    echo '<input class="input" id="' . $name . '" name="' . $name . '" type="' . $type . '" placeholder="' . e($placeholder) . '"'
        . ($type !== 'password' ? ' value="' . e(old($name)) . '"' : '') . ' ' . $extra . '>';
    if ($type === 'password') {
        echo '<button class="input-action" type="button" data-password-toggle aria-label="Show password">' . icon('eye') . '</button>';
    }
    echo $iconName ? '</div>' : '';
    if ($has) {
        echo '<span class="field-error">' . e($errors[$name]) . '</span>';
    }
    echo '</div>';
}

$pageTitle = 'Create your account';
include __DIR__ . '/includes/layout/auth_open.php';
?>
<div class="auth">
    <main class="auth-main">
        <div class="auth-top">
            <a class="brand" href="<?= e(url()) ?>"><?= logo_mark() ?><span><?= e(CLINIC_SHORT_NAME) ?><small>Veterinary Clinic</small></span></a>
            <a class="back" href="<?= e(url()) ?>"><?= icon('arrow-left') ?>Back to website</a>
        </div>
        <div class="auth-form-wrap wide">
            <h1>Create your free account</h1>
            <p class="auth-sub">Join <?= e(CLINIC_SHORT_NAME) ?> to book visits online, get vaccine reminders and keep your pets' health records in one place.</p>
            <form class="auth-form" method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <div class="form-grid">
                    <?php reg_field('first_name', 'First name', $errors, 'text', 'Maria', '', 'autocomplete="given-name" required autofocus'); ?>
                    <?php reg_field('last_name', 'Last name', $errors, 'text', 'Santos', '', 'autocomplete="family-name" required'); ?>
                    <?php reg_field('email', 'Email address', $errors, 'email', 'you@example.com', 'mail', 'autocomplete="email" required'); ?>
                    <?php reg_field('phone', 'Mobile number', $errors, 'tel', '0917 000 0000', 'phone', 'autocomplete="tel"'); ?>
                    <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
                        <label for="password">Password</label>
                        <div class="input-group"><?= icon('lock') ?><input class="input" id="password" name="password" type="password" placeholder="At least 8 characters" autocomplete="new-password" data-password-meter="#pw-meter" required><button class="input-action" type="button" data-password-toggle aria-label="Show password"><?= icon('eye') ?></button></div>
                        <div><div class="pw-meter" id="pw-meter" data-score="0"><span></span><span></span><span></span><span></span></div><div class="pw-label" data-pw-label>Use 8+ characters with letters and a number</div></div>
                        <?php if (isset($errors['password'])): ?><span class="field-error"><?= e($errors['password']) ?></span><?php endif; ?>
                    </div>
                    <?php reg_field('password_confirm', 'Confirm password', $errors, 'password', 'Type it again', 'lock', 'autocomplete="new-password" required'); ?>
                </div>
                <div class="field<?= isset($errors['terms']) ? ' has-error' : '' ?>">
                    <label class="check"><input type="checkbox" name="terms" value="1"<?= checked(!empty($_POST['terms'])) ?>> <span>I agree to the clinic's booking &amp; cancellation policies and consent to storing my pets' health records.</span></label>
                    <?php if (isset($errors['terms'])): ?><span class="field-error"><?= e($errors['terms']) ?></span><?php endif; ?>
                </div>
                <button class="btn btn-primary btn-lg btn-block" type="submit"><?= icon('user-plus') ?>Create account</button>
            </form>
            <p class="auth-switch">Already have an account? <a href="<?= e(url('login.php' . ($next ? '?next=' . rawurlencode($next) : ''))) ?>">Log in</a></p>
        </div>
    </main>
    <aside class="auth-side">
        <div style="position:relative">
            <div class="auth-photo"><img src="<?= e(url('assets/img/auth-register.jpg')) ?>" alt="A calico cat resting in a pink pet carrier"></div>
            <div class="auth-chip c1"><span class="fc-icon fc-teal"><?= icon('file-heart') ?></span><div><strong>Health record</strong>Always up to date</div></div>
            <div class="auth-chip c2"><span class="fc-icon fc-sun"><?= icon('bell-ring') ?></span><div><strong>Smart reminders</strong>Never miss a booster</div></div>
        </div>
        <div class="auth-quote">
            <p>What you get with a free account</p>
            <ul class="check-list" style="margin:1rem 0 0">
                <li><span class="ck"><?= icon('check') ?></span><div><strong style="color:#fff">Online booking 24/7</strong><span style="color:#b7e0d8">Choose your vet, date and time slot.</span></div></li>
                <li><span class="ck"><?= icon('check') ?></span><div><strong style="color:#fff">Digital pet profiles</strong><span style="color:#b7e0d8">Visits, vaccines, weight and prescriptions.</span></div></li>
                <li><span class="ck"><?= icon('check') ?></span><div><strong style="color:#fff">Reminders &amp; updates</strong><span style="color:#b7e0d8">Confirmations and booster alerts in-app.</span></div></li>
            </ul>
        </div>
    </aside>
</div>
<?php include __DIR__ . '/includes/layout/js_boot.php'; ?>
<script src="<?= e(asset('assets/js/core.js')) ?>"></script>
<script src="<?= e(asset('assets/js/site.js')) ?>"></script>
</body>
</html>
