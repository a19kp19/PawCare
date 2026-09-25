<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();
$today = date('Y-m-d');

$owners = rows("SELECT id, first_name, last_name, phone, email FROM users WHERE role = 'owner' AND is_active = 1 ORDER BY last_name, first_name");
$services = rows('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order, name');
$vets = rows('SELECT id, name FROM vets WHERE is_active = 1 ORDER BY id');

$sel = ['owner' => int_param('owner'), 'pet' => int_param('pet'), 'service' => int_param('service') ?: (int) ($services[0]['id'] ?? 0), 'vet' => int_param('vet'), 'date' => param('date') ?: $today, 'time' => '', 'status' => 'confirmed', 'reason' => ''];
if ($sel['pet'] && !$sel['owner']) {
    $sel['owner'] = (int) val('SELECT owner_id FROM pets WHERE id = ?', [$sel['pet']]);
}
$error = null;

if (is_post()) {
    verify_csrf();
    $sel = ['owner' => (int) input('owner_id'), 'pet' => (int) input('pet_id'), 'service' => (int) input('service_id'), 'vet' => (int) input('vet_id'),
            'date' => input('date'), 'time' => input('time'), 'status' => input('status') === 'pending' ? 'pending' : 'confirmed', 'reason' => clip(input('reason'), 500)];
    $pet = row('SELECT * FROM pets WHERE id = ? AND owner_id = ?', [$sel['pet'], $sel['owner']]);
    if (!$pet) {
        $error = 'Please choose a client and one of their pets.';
    } elseif (!preg_match('/^\d{2}:\d{2}$/', (string) $sel['time'])) {
        $error = 'Please pick an open time slot.';
    } else {
        $res = book_appointment(['pet_id' => $pet['id'], 'owner_id' => $pet['owner_id'], 'service_id' => $sel['service'], 'vet_id' => $sel['vet'] ?: null,
                                 'date' => $sel['date'], 'time' => $sel['time'], 'reason' => $sel['reason'], 'status' => $sel['status']], true);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $a = find_appointment($res['id']);
            notify((int) $a['owner_id'], 'appointment', $sel['status'] === 'confirmed' ? 'Appointment booked for you' : 'Appointment request created',
                "The clinic scheduled {$a['pet_name']}'s {$a['service_name']} on " . appt_when($a) . '.', 'owner/appointments.php');
            log_activity((int) $me['id'], full_name($me) . " booked {$a['service_name']} for {$a['pet_name']}", 'calendar-plus', 'admin/appointment.php?id=' . $a['id']);
            flash('success', "Booked {$a['pet_name']} for " . appt_when($a) . " with " . ($a['vet_name'] ?? 'the first available vet') . '.');
            redirect('admin/appointment.php?id=' . $a['id']);
        }
    }
}
$ownerPets = $sel['owner'] ? rows('SELECT id, name, species, breed FROM pets WHERE owner_id = ? ORDER BY name', [$sel['owner']]) : [];

$pageTitle = 'New appointment';
$activeNav = 'appointments';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/appointments.php')) ?>">Appointments</a><?= icon('chevron-right') ?><span>New</span></div>
        <h1>New appointment</h1>
        <p>For walk-ins and phone bookings. Double-booking is prevented automatically.</p>
    </div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('admin/clients.php?new=1')) ?>"><?= icon('user-plus') ?>Register new client</a></div>
</div>
<?php if ($error): ?><div class="alert alert-danger mb-2"><?= icon('circle-alert') ?><div><?= e($error) ?></div></div><?php endif; ?>
<div class="settings-grid">
    <form class="card" method="post" novalidate>
        <div class="card-head"><h2><?= icon('calendar-plus') ?>Booking details</h2></div>
        <div class="card-body">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field span-2">
                    <label for="owner_id">Client <span class="req">*</span></label>
                    <input class="input mb-1" type="search" placeholder="Type to filter clients…" data-filter-select="#owner_id" aria-label="Filter clients">
                    <select class="input" id="owner_id" name="owner_id" data-owner-pets="#pet_id" required>
                        <option value="">Select a client</option>
                        <?php foreach ($owners as $o): ?><option value="<?= (int) $o['id'] ?>"<?= selected($sel['owner'], $o['id']) ?>><?= e($o['last_name'] . ', ' . $o['first_name'] . ' · ' . ($o['phone'] ?: $o['email'])) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field span-2">
                    <label for="pet_id">Pet <span class="req">*</span></label>
                    <select class="input" id="pet_id" name="pet_id" required>
                        <?php if (!$sel['owner']): ?><option value="">Choose a client first</option><?php endif; ?>
                        <?php foreach ($ownerPets as $op): ?><option value="<?= (int) $op['id'] ?>"<?= selected($sel['pet'], $op['id']) ?>><?= e($op['name'] . ' · ' . $op['species'] . ($op['breed'] ? ' (' . $op['breed'] . ')' : '')) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="service_id">Service</label>
                    <select class="input" id="service_id" name="service_id">
                        <?php foreach ($services as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($sel['service'], $s['id']) ?>><?= e($s['name'] . ' · ' . $s['duration_minutes'] . ' min · ' . money($s['price'])) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="vet_id">Veterinarian</label>
                    <select class="input" id="vet_id" name="vet_id"><option value="">First available</option><?php foreach ($vets as $v): ?><option value="<?= (int) $v['id'] ?>"<?= selected($sel['vet'], $v['id']) ?>><?= e($v['name']) ?></option><?php endforeach; ?></select>
                </div>
                <div class="field">
                    <label for="date">Date</label>
                    <input class="input" id="date" type="date" name="date" min="<?= e($today) ?>" value="<?= e($sel['date']) ?>">
                </div>
                <div class="field">
                    <span class="label">Status</span>
                    <div class="choice-row">
                        <label class="choice"><input type="radio" name="status" value="confirmed"<?= checked($sel['status'] === 'confirmed') ?>><span><?= icon('calendar-check') ?>Confirmed</span></label>
                        <label class="choice"><input type="radio" name="status" value="pending"<?= checked($sel['status'] === 'pending') ?>><span><?= icon('hourglass') ?>Pending</span></label>
                    </div>
                </div>
                <div class="field span-2">
                    <span class="label">Time</span>
                    <div data-slot-picker data-service-input="#service_id" data-vet-input="#vet_id" data-date-input="#date" data-selected="<?= e($sel['time']) ?>">
                        <p class="slot-msg" data-slot-msg></p>
                        <div data-slot-list></div>
                    </div>
                </div>
                <div class="field span-2">
                    <label for="reason">Reason / notes</label>
                    <textarea class="input" id="reason" name="reason" rows="3" placeholder="e.g. Walk-in: limping since yesterday"><?= e($sel['reason']) ?></textarea>
                </div>
            </div>
            <div class="form-actions"><a class="btn btn-outline" href="<?= e(url('admin/appointments.php')) ?>">Cancel</a><button class="btn btn-primary" type="submit"><?= icon('calendar-check') ?>Create appointment</button></div>
        </div>
    </form>
    <aside class="card">
        <div class="card-body">
            <h3 class="flex items-center gap-1"><?= icon('info', 'text-amber') ?>How slots work</h3>
            <ul class="check-list" style="margin:1rem 0 0">
                <li><span class="ck"><?= icon('clock') ?></span><div><strong>Service length matters</strong><span class="muted text-sm">A 2-hour surgery blocks four half-hour slots for that vet.</span></div></li>
                <li><span class="ck"><?= icon('users') ?></span><div><strong>"First available" balances the load</strong><span class="muted text-sm">The least busy vet on duty gets the booking.</span></div></li>
                <li><span class="ck"><?= icon('shield-check') ?></span><div><strong>No double-booking</strong><span class="muted text-sm">Every slot is re-checked when you press Create.</span></div></li>
                <li><span class="ck"><?= icon('bell-ring') ?></span><div><strong>Owner is notified</strong><span class="muted text-sm">The booking appears in the client's portal instantly.</span></div></li>
            </ul>
        </div>
    </aside>
</div>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
