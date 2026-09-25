<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();
$client = row("SELECT * FROM users WHERE id = ? AND role = 'owner'", [int_param('id')]);
if (!$client) {
    abort(404, 'Client not found');
}
$self = 'admin/client.php?id=' . $client['id'];

if (is_post()) {
    verify_csrf();
    $action = input('action');
    if ($action === 'update') {
        $d = ['first_name' => clip(input('first_name'), 60), 'last_name' => clip(input('last_name'), 60), 'email' => clip(strtolower(input('email')), 120), 'phone' => clip(input('phone'), 30) ?: null, 'address' => clip(input('address'), 255) ?: null];
        if ($d['first_name'] === '' || $d['last_name'] === '' || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Name and a valid email are required.');
        } elseif (val('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$d['email'], $client['id']])) {
            flash('error', 'Another account already uses that email.');
        } else {
            update('users', $d, 'id = :id', ['id' => $client['id']]);
            flash('success', 'Client details updated.');
        }
        redirect($self);
    }
    if ($action === 'reset_password') {
        $password = random_password();
        update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $client['id']]);
        $_SESSION['temp_password'] = ['user' => (int) $client['id'], 'password' => $password];
        log_activity((int) $me['id'], full_name($me) . ' reset the password of ' . full_name($client), 'key-round', $self);
        redirect($self);
    }
    if ($action === 'toggle') {
        update('users', ['is_active' => $client['is_active'] ? 0 : 1], 'id = :id', ['id' => $client['id']]);
        flash('success', full_name($client) . ($client['is_active'] ? ' can no longer log in.' : ' can log in again.'));
        redirect($self);
    }
}

$temp = null;
if (!empty($_SESSION['temp_password']) && (int) $_SESSION['temp_password']['user'] === (int) $client['id']) {
    $temp = $_SESSION['temp_password']['password'];
    unset($_SESSION['temp_password']);
}
$pets = rows('SELECT * FROM pets WHERE owner_id = ? ORDER BY name', [$client['id']]);
$appts = rows(appointment_sql() . ' WHERE a.owner_id = ? ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 10', [$client['id']]);
$stats = row("SELECT COUNT(*) AS total, SUM(status = 'completed') AS done, SUM(status = 'no_show') AS missed, COALESCE(SUM(CASE WHEN status = 'completed' THEN price END), 0) AS spent FROM appointments WHERE owner_id = ?", [$client['id']]);

$pageTitle = full_name($client);
$activeNav = 'clients';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/clients.php')) ?>">Clients</a><?= icon('chevron-right') ?><span><?= e(full_name($client)) ?></span></div>
        <h1 class="flex items-center gap-2 wrap"><?= e(full_name($client)) ?> <?= $client['is_active'] ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Inactive</span>' ?></h1>
        <p>Client since <?= e(fmt_date($client['created_at'], 'F Y')) ?> · last login <?= $client['last_login_at'] ? e(time_ago($client['last_login_at'])) : 'never' ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('admin/patient-form.php?owner=' . $client['id'])) ?>"><?= icon('plus') ?>Add pet</a>
        <a class="btn btn-primary" href="<?= e(url('admin/appointment-new.php?owner=' . $client['id'])) ?>"><?= icon('calendar-plus') ?>Book appointment</a>
    </div>
</div>
<?php if ($temp): ?>
    <div class="temp-password mb-3"><?= icon('key-round') ?>Temporary password for <?= e($client['email']) ?>: <code><?= e($temp) ?></code><button class="btn btn-white btn-xs" type="button" data-copy="<?= e($temp) ?>"><?= icon('copy') ?>Copy</button><span class="text-sm" style="font-weight:600">Share it with the client — it won't be shown again.</span></div>
<?php endif; ?>

<div class="kpi-grid">
    <?= kpi_card('Pets', (string) count($pets), 'paw-print', 'teal') ?>
    <?= kpi_card('Completed visits', (string) (int) $stats['done'], 'circle-check', 'green', plural((int) $stats['total'], 'booking') . ' in total') ?>
    <?= kpi_card('No-shows', (string) (int) $stats['missed'], 'ban', 'coral') ?>
    <?= kpi_card('Total spent', e(money($stats['spent'])), 'wallet', 'violet', 'Completed visits') ?>
</div>

<div class="detail-grid">
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2><?= icon('paw-print') ?>Pets</h2><a class="btn btn-soft btn-sm" href="<?= e(url('admin/patient-form.php?owner=' . $client['id'])) ?>"><?= icon('plus') ?>Add pet</a></div>
            <?php if ($pets): ?>
                <div class="mini-pets">
                    <?php foreach ($pets as $pet): ?>
                        <a class="mini-pet" href="<?= e(url('admin/patient.php?id=' . $pet['id'])) ?>"><?= pet_photo($pet) ?><div><strong><?= e($pet['name']) ?></strong><span><?= e($pet['breed'] ?: $pet['species']) ?> · <?= e(pet_age($pet['birthdate'])) ?></span></div></a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('paw-print', 'No pets yet', 'Add the first pet for this client.') ?>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-head"><h2><?= icon('calendar-days') ?>Recent appointments</h2></div>
            <?php if ($appts): ?>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Date</th><th>Pet</th><th>Service</th><th>Status</th><th class="num">Price</th></tr></thead>
                    <tbody>
                    <?php foreach ($appts as $a): ?>
                        <tr>
                            <td><a class="cell-title" href="<?= e(url('admin/appointment.php?id=' . $a['id'])) ?>"><?= e(fmt_date($a['appointment_date'])) ?></a><span class="cell-sub"><?= e(fmt_time($a['start_time'])) ?> · <?= e($a['reference']) ?></span></td>
                            <td><?= e($a['pet_name']) ?></td>
                            <td><?= e($a['service_name']) ?></td>
                            <td><?= status_badge($a['status']) ?></td>
                            <td class="num"><?= money($a['price']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php else: ?>
                <?= empty_state('calendar-days', 'No appointments yet') ?>
            <?php endif; ?>
        </div>
    </div>
    <aside class="stack">
        <div class="card">
            <div class="card-head"><h2><?= icon('user-round') ?>Contact</h2><button class="btn btn-ghost btn-sm" type="button" data-modal-open="modal-edit"><?= icon('pencil') ?>Edit</button></div>
            <div class="card-body">
                <div class="person-card"><?= avatar(full_name($client)) ?><div><strong><?= e(full_name($client)) ?></strong><div class="muted text-sm">Pet owner</div></div></div>
                <div class="contact-lines">
                    <a href="mailto:<?= e($client['email']) ?>"><?= icon('mail') ?><?= e($client['email']) ?></a>
                    <?php if ($client['phone']): ?><a href="tel:<?= e(preg_replace('/\D+/', '', $client['phone'])) ?>"><?= icon('phone') ?><?= e($client['phone']) ?></a><?php endif; ?>
                    <?php if ($client['address']): ?><span><?= icon('map-pin') ?><?= e($client['address']) ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h2><?= icon('lock') ?>Account access</h2></div>
            <div class="card-body stack-sm">
                <form method="post" data-confirm="Generate a new temporary password for <?= e($client['first_name']) ?>? The old password stops working." data-confirm-ok="Reset password" data-confirm-tone="primary"><?= csrf_field() ?><input type="hidden" name="action" value="reset_password"><button class="btn btn-outline btn-sm btn-block" type="submit"><?= icon('key-round') ?>Reset password</button></form>
                <form method="post" data-confirm="<?= $client['is_active'] ? 'Deactivate this account? The client will not be able to log in.' : 'Reactivate this account?' ?>" data-confirm-ok="<?= $client['is_active'] ? 'Deactivate' : 'Reactivate' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><button class="btn <?= $client['is_active'] ? 'btn-danger-soft' : 'btn-soft' ?> btn-sm btn-block" type="submit"><?= icon($client['is_active'] ? 'user-x' : 'user-check') ?><?= $client['is_active'] ? 'Deactivate account' : 'Reactivate account' ?></button></form>
            </div>
        </div>
    </aside>
</div>

<dialog class="modal" id="modal-edit">
    <form method="post">
        <div class="modal-head"><h3><?= icon('pencil') ?>Edit client</h3><button class="modal-x" type="button" data-modal-close aria-label="Close"><?= icon('x') ?></button></div>
        <div class="modal-body">
            <?= csrf_field() ?><input type="hidden" name="action" value="update">
            <div class="form-grid">
                <div class="field"><label for="e-first">First name</label><input class="input" id="e-first" name="first_name" value="<?= e($client['first_name']) ?>" required></div>
                <div class="field"><label for="e-last">Last name</label><input class="input" id="e-last" name="last_name" value="<?= e($client['last_name']) ?>" required></div>
                <div class="field"><label for="e-email">Email</label><input class="input" id="e-email" type="email" name="email" value="<?= e($client['email']) ?>" required></div>
                <div class="field"><label for="e-phone">Mobile</label><input class="input" id="e-phone" name="phone" value="<?= e($client['phone']) ?>"></div>
                <div class="field span-2"><label for="e-address">Address</label><input class="input" id="e-address" name="address" value="<?= e($client['address']) ?>"></div>
            </div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" type="button" data-modal-close>Cancel</button><button class="btn btn-primary" type="submit"><?= icon('save') ?>Save</button></div>
    </form>
</dialog>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
