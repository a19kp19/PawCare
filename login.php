<?php
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) {
    redirect(home_for($u));
}
$next = safe_next(param('next') ?: input('next'));
$error = null;

if (is_post()) {
    verify_csrf();
    if (($wait = login_lock_remaining()) > 0) {
        $error = "Too many attempts. Please wait $wait seconds and try again.";
    } else {
        $user = row('SELECT * FROM users WHERE email = ?', [strtolower(input('email'))]);
        $password = (string) ($_POST['password'] ?? '');
        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                $error = 'This account has been deactivated. Please contact the clinic.';
            } else {
                if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                    update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $user['id']]);
                }
                login_user($user);
                flash('success', 'Welcome back, ' . $user['first_name'] . '!');
                // never send owners into the admin area (or staff into the owner area)
                if ($next && strpos($next, is_staff($user) ? 'owner/' : 'admin/') === 0) {
                    $next = '';
                }
                redirect($next ?: home_for($user));
            }
        } else {
            register_login_failure();
            $error = 'That email and password combination is incorrect.';
        }
    }
}

$pageTitle = 'Log in';
include __DIR__ . '/includes/layout/auth_open.php';
?>
<div class="auth">
    <main class="auth-main">
        <div class="auth-top">
            <a class="brand" href="<?= e(url()) ?>"><?= logo_mark() ?><span><?= e(CLINIC_SHORT_NAME) ?><small>Veterinary Clinic</small></span></a>
            <a class="back" href="<?= e(url()) ?>"><?= icon('arrow-left') ?>Back to website</a>
        </div>
        <div class="auth-form-wrap">
            <h1>Welcome back <span aria-hidden="true">👋</span></h1>
            <p class="auth-sub">Log in to book visits, view health records and manage your pets.</p>
            <?php if ($error): ?><div class="alert alert-danger mb-2"><?= icon('circle-alert') ?><div><?= e($error) ?></div></div><?php endif; ?>
            <form class="auth-form" method="post" data-login-form novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">
                <div class="field">
                    <label for="email">Email address</label>
                    <div class="input-group"><?= icon('mail') ?><input class="input" id="email" type="email" name="email" value="<?= e(old('email')) ?>" placeholder="you@example.com" autocomplete="email" required autofocus></div>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-group"><?= icon('lock') ?><input class="input" id="password" type="password" name="password" placeholder="Your password" autocomplete="current-password" required><button class="input-action" type="button" data-password-toggle aria-label="Show password"><?= icon('eye') ?></button></div>
                </div>
                <div class="auth-row"><span class="muted">Pet owners and clinic staff use the same login.</span><a href="<?= e(url()) ?>#contact">Forgot password?</a></div>
                <button class="btn btn-primary btn-lg btn-block" type="submit"><?= icon('log-in') ?>Log in</button>
            </form>
            <?php if (SHOW_DEMO_LOGINS): ?>
                <div class="demo-box">
                    <strong><?= icon('sparkles') ?>Demo accounts — click to sign in instantly</strong>
                    <div class="demo-accounts">
                        <button type="button" class="demo-btn" data-demo-login data-email="owner@pawcare.test" data-password="owner123"><strong><?= icon('paw-print') ?>Pet owner</strong>Maria Santos</button>
                        <button type="button" class="demo-btn" data-demo-login data-email="staff@pawcare.test" data-password="staff123"><strong><?= icon('stethoscope') ?>Staff</strong>Front desk</button>
                        <button type="button" class="demo-btn" data-demo-login data-email="admin@pawcare.test" data-password="admin123"><strong><?= icon('user-cog') ?>Admin</strong>Clinic owner</button>
                    </div>
                </div>
            <?php endif; ?>
            <p class="auth-switch">New to <?= e(CLINIC_SHORT_NAME) ?>? <a href="<?= e(url('register.php' . ($next ? '?next=' . rawurlencode($next) : ''))) ?>">Create a free account</a></p>
        </div>
    </main>
    <aside class="auth-side">
        <div style="position:relative">
            <div class="auth-photo"><img src="<?= e(url('assets/img/auth-login.jpg')) ?>" alt="A cat and a dog sitting side by side"></div>
            <div class="auth-chip c1"><span class="fc-icon fc-teal"><?= icon('calendar-check') ?></span><div><strong>Visit confirmed</strong>Mon · 9:30 AM</div></div>
            <div class="auth-chip c2"><span class="fc-icon fc-coral"><?= icon('syringe') ?></span><div><strong>Booster due soon</strong>We'll remind you</div></div>
        </div>
        <div class="auth-quote">
            <p>“All of Coco's vaccines, visits and prescriptions in one place — I never miss a booster anymore.”</p>
            <span>Maria S. · pet parent to Coco, Mochi &amp; Bruno</span>
        </div>
        <div class="auth-stats">
            <div class="auth-stat"><strong>8,500+</strong><span>pets treated</span></div>
            <div class="auth-stat"><strong>4.9 ★</strong><span>average rating</span></div>
            <div class="auth-stat"><strong>24/7</strong><span>online booking</span></div>
        </div>
    </aside>
</div>
<?php include __DIR__ . '/includes/layout/js_boot.php'; ?>
<script src="<?= e(asset('assets/js/core.js')) ?>"></script>
<script src="<?= e(asset('assets/js/site.js')) ?>"></script>
</body>
</html>
