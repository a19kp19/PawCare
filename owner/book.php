<?php
require __DIR__ . '/../includes/bootstrap.php';
if (!current_user()) {
    flash('info', 'Log in or create a free account to book a visit.');
    redirect('login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? 'owner/book.php'));
}
$user = require_owner();
$uid = (int) $user['id'];

$pets = rows('SELECT * FROM pets WHERE owner_id = ? ORDER BY created_at', [$uid]);
$services = rows('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order, name');
$vets = rows('SELECT * FROM vets WHERE is_active = 1 ORDER BY id');
$error = null;
$startStep = 0;
$sel = ['pet' => int_param('pet'), 'service' => int_param('service'), 'vet' => int_param('vet'), 'date' => param('date'), 'time' => '', 'reason' => ''];

if (is_post()) {
    verify_csrf();
    $sel = ['pet' => (int) input('pet_id'), 'service' => (int) input('service_id'), 'vet' => (int) input('vet_id'), 'date' => input('date'), 'time' => input('time'), 'reason' => clip(input('reason'), 500)];
    $pet = row('SELECT * FROM pets WHERE id = ? AND owner_id = ?', [$sel['pet'], $uid]);
    $vetOk = !$sel['vet'] || val('SELECT 1 FROM vets WHERE id = ? AND is_active = 1', [$sel['vet']]);
    $startStep = 2;
    if (!$pet) {
        $error = 'Please choose one of your pets.';
        $startStep = 0;
    } elseif (!$vetOk) {
        $error = 'Please choose a valid veterinarian.';
    } elseif (!preg_match('/^\d{2}:\d{2}$/', (string) $sel['time'])) {
        $error = 'Please pick an available time.';
    } elseif (empty($_POST['agree'])) {
        $error = 'Please confirm the clinic policies to finish booking.';
        $startStep = 3;
    } else {
        $res = book_appointment([
            'pet_id' => $pet['id'], 'owner_id' => $uid, 'service_id' => $sel['service'], 'vet_id' => $sel['vet'] ?: null,
            'date' => $sel['date'], 'time' => $sel['time'], 'reason' => $sel['reason'], 'status' => 'pending',
        ]);
        if (isset($res['error'])) {
            $error = $res['error'];
        } else {
            $a = find_appointment($res['id']);
            notify($uid, 'booking', 'Booking request received', "{$a['pet_name']}'s {$a['service_name']} on " . appt_when($a) . " is waiting for confirmation. We'll let you know soon!", 'owner/appointments.php');
            notify_staff('booking', 'New booking request', full_name($user) . " requested {$a['service_name']} for {$a['pet_name']} on " . appt_when($a) . '.', 'admin/appointment.php?id=' . $a['id']);
            log_activity($uid, full_name($user) . " booked {$a['service_name']} for {$a['pet_name']}", 'calendar-plus', 'admin/appointment.php?id=' . $a['id']);
            redirect('owner/appointments.php?booked=' . rawurlencode($a['reference']));
        }
    }
}

$grouped = [];
foreach ($services as $s) {
    $grouped[$s['category']][] = $s;
}
$config = [
    'today'   => date('Y-m-d'),
    'firstDate' => first_bookable_date(),
    'maxDate' => date('Y-m-d', strtotime('+' . BOOKING_WINDOW_DAYS . ' days')),
    'hours'   => array_map(fn ($h) => $h !== null, CLINIC_HOURS),
    'vets'    => [],
    'services' => [],
    'pets'    => [],
    'placeholderPhoto' => '<span class="pet-photo pet-fallback species-other">' . icon('paw-print') . '</span>',
    'selectedTime' => $sel['time'],
    'startStep' => $error ? $startStep : null,
];
foreach ($vets as $v) {
    $config['vets'][$v['id']] = ['name' => $v['name'], 'days' => vet_work_days($v)];
}
foreach ($services as $s) {
    $config['services'][$s['id']] = ['name' => $s['name'], 'price' => (float) $s['price'], 'duration' => (int) $s['duration_minutes']];
}
foreach ($pets as $p) {
    $config['pets'][$p['id']] = ['name' => $p['name'], 'sub' => ($p['breed'] ?: $p['species']) . ' · ' . pet_age($p['birthdate']), 'photo' => pet_photo($p)];
}
if (count($pets) === 1 && !$sel['pet']) {
    $sel['pet'] = (int) $pets[0]['id'];
}

$pageTitle = 'Book a visit';
$activeNav = 'book';
$extraScripts = ['assets/js/booking.js'];
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('owner/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>Book a visit</span></div>
        <h1>Book a visit</h1>
        <p>Four quick steps. Real-time availability — no phone calls, no waiting in line.</p>
    </div>
</div>

<?php if ($error): ?><div class="alert alert-danger mb-2"><?= icon('circle-alert') ?><div><strong>We couldn't book that yet</strong><?= e($error) ?></div></div><?php endif; ?>

<?php if (!$pets): ?>
    <div class="card"><?= empty_state('paw-print', 'Add a pet first', 'We need to know who is visiting! Create a quick profile for your pet, then come back to book.', '<a class="btn btn-primary" href="' . e(url('owner/pet-form.php?first=1')) . '">' . icon('plus') . 'Add a pet</a>') ?></div>
<?php else: ?>
<form method="post" data-booking novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="date" value="<?= e($sel['date']) ?>">
    <div class="booking">
        <div>
            <div class="stepper" role="list">
                <?php foreach ([['paw-print', 'Pet'], ['stethoscope', 'Service'], ['calendar-days', 'Date & time'], ['check', 'Confirm']] as $i => [$ic, $label]): ?>
                    <button type="button" class="stepper-item" data-step-indicator role="listitem"><span class="stepper-num"><span><?= $i + 1 ?></span><?= icon('check') ?></span><span class="st-label"><small>Step <?= $i + 1 ?></small><?= e($label) ?></span></button>
                <?php endforeach; ?>
            </div>
            <div class="card">
                <div class="card-body">
                    <section class="step-panel" data-step>
                        <h2 class="step-title">Who's coming in?</h2>
                        <p class="step-sub">Choose the pet you are booking for.</p>
                        <div class="option-grid">
                            <?php foreach ($pets as $p): ?>
                                <label class="option">
                                    <input type="radio" name="pet_id" value="<?= (int) $p['id'] ?>"<?= checked($sel['pet'] === (int) $p['id']) ?>>
                                    <span class="option-card"><?= pet_photo($p) ?><strong><?= e($p['name']) ?> <?= sex_icon($p['sex']) ?></strong><span class="o-sub"><?= e($p['breed'] ?: $p['species']) ?> · <?= e(pet_age($p['birthdate'])) ?></span></span>
                                    <span class="option-check"><?= icon('check') ?></span>
                                </label>
                            <?php endforeach; ?>
                            <a class="add-card" style="min-height:0" href="<?= e(url('owner/pet-form.php')) ?>"><span class="add-icon"><?= icon('plus') ?></span><strong>Add another pet</strong></a>
                        </div>
                    </section>

                    <section class="step-panel" data-step>
                        <h2 class="step-title">What does your pet need?</h2>
                        <p class="step-sub">Prices are starting rates. Your vet will confirm any extra costs before treatment.</p>
                        <?php foreach ($grouped as $cat => $list): ?>
                            <div class="service-group-label"><?= e($cat) ?></div>
                            <div class="option-grid tight">
                                <?php foreach ($list as $s): ?>
                                    <label class="option <?= category_class($s['category']) ?>">
                                        <input type="radio" name="service_id" value="<?= (int) $s['id'] ?>"<?= checked($sel['service'] === (int) $s['id']) ?>>
                                        <span class="option-card">
                                            <span class="o-icon"><?= icon($s['icon']) ?></span>
                                            <strong><?= e($s['name']) ?></strong>
                                            <span class="o-sub"><?= e(excerpt($s['description'], 78)) ?></span>
                                            <span class="o-meta"><span><?= money($s['price']) ?></span><span class="muted"><?= icon('clock') ?> <?= (int) $s['duration_minutes'] ?> min</span></span>
                                        </span>
                                        <span class="option-check"><?= icon('check') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </section>

                    <section class="step-panel" data-step>
                        <h2 class="step-title">Pick a vet, date &amp; time</h2>
                        <p class="step-sub">Only open slots can be selected. Choose "First available" for the most options.</p>
                        <div class="option-grid tight">
                            <label class="option vet-option">
                                <input type="radio" name="vet_id" value=""<?= checked(!$sel['vet']) ?>>
                                <span class="option-card"><span class="any-vet"><?= icon('sparkles') ?></span><span><strong>First available</strong><br><span class="o-sub">Most open times</span></span></span>
                                <span class="option-check"><?= icon('check') ?></span>
                            </label>
                            <?php foreach ($vets as $v): ?>
                                <label class="option vet-option">
                                    <input type="radio" name="vet_id" value="<?= (int) $v['id'] ?>"<?= checked($sel['vet'] === (int) $v['id']) ?>>
                                    <span class="option-card"><?= vet_photo($v) ?><span><strong><?= e($v['name']) ?></strong><br><span class="o-sub"><?= e(implode(', ', array_map(fn ($d) => weekday_name($d, true), vet_work_days($v)))) ?></span></span></span>
                                    <span class="option-check"><?= icon('check') ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="when-grid">
                            <div class="calendar" data-calendar></div>
                            <div class="slots-panel">
                                <h4 data-slots-title>Available times</h4>
                                <p class="slot-msg" data-slots-msg></p>
                                <div data-slots></div>
                            </div>
                        </div>
                    </section>

                    <section class="step-panel" data-step>
                        <h2 class="step-title">Almost done!</h2>
                        <p class="step-sub">Anything the vet should know before the visit?</p>
                        <div class="field">
                            <label for="reason">Reason for visit / notes <span class="muted">(optional)</span></label>
                            <textarea class="input" id="reason" name="reason" rows="4" maxlength="500" placeholder="e.g. Scratching her ears a lot since Monday. She's due for her booster too."><?= e($sel['reason']) ?></textarea>
                        </div>
                        <div class="alert alert-info mt-2"><?= icon('info') ?><div><strong>Clinic policies</strong>Please arrive 10 minutes early. You can cancel online up to <?= (int) (CANCEL_NOTICE_MINUTES / 60) ?> hours before your visit. Payment is made at the clinic (cash, GCash, Maya or card).</div></div>
                        <label class="check mt-2"><input type="checkbox" name="agree" value="1"<?= checked(!empty($_POST['agree'])) ?>> <span>I have read the clinic policies and confirm the details are correct.</span></label>
                    </section>

                    <div class="step-actions">
                        <button class="btn btn-outline" type="button" data-prev><?= icon('arrow-left') ?>Back</button>
                        <span></span>
                        <button class="btn btn-primary" type="button" data-next>Continue <?= icon('arrow-right') ?></button>
                        <button class="btn btn-accent btn-lg" type="submit" data-submit hidden><?= icon('calendar-check') ?>Confirm booking</button>
                    </div>
                </div>
            </div>
        </div>

        <aside class="card summary-card" aria-live="polite">
            <div class="summary-hero" data-sum-hero>
                <span data-sum-photo></span>
                <div><small>Booking for</small><strong data-sum-pet>Your pet</strong><span class="text-sm" data-sum-pet-sub></span></div>
            </div>
            <div class="summary-rows">
                <div class="summary-row"><span class="s-icon"><?= icon('stethoscope') ?></span><div><small>Service</small><strong data-sum="service">Not selected</strong></div></div>
                <div class="summary-row"><span class="s-icon"><?= icon('user-round') ?></span><div><small>Veterinarian</small><strong data-sum="vet">First available vet</strong></div></div>
                <div class="summary-row"><span class="s-icon"><?= icon('calendar-days') ?></span><div><small>Date &amp; time</small><strong data-sum="when">Not selected</strong></div></div>
                <div class="summary-row"><span class="s-icon"><?= icon('map-pin') ?></span><div><small>Location</small><strong><?= e(CLINIC_SHORT_NAME) ?> · <?= e(explode(',', CLINIC_ADDRESS)[0]) ?></strong></div></div>
            </div>
            <div class="summary-total"><span>Estimated total</span><strong data-sum-total>—</strong></div>
            <div class="summary-note"><?= icon('shield-check') ?><span>No payment needed now. You'll get a notification as soon as the clinic confirms.</span></div>
        </aside>
    </div>
</form>
<script type="application/json" id="booking-config"><?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
