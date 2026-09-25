<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_owner();
$uid = (int) $user['id'];

if (is_post() && input('action') === 'cancel') {
    verify_csrf();
    $a = find_appointment(int_param('id'));
    if (!$a || (int) $a['owner_id'] !== $uid) {
        abort(404, 'Appointment not found');
    }
    if (!owner_can_cancel($a)) {
        flash('error', 'This appointment can no longer be cancelled online. Please call the clinic at ' . CLINIC_PHONE . '.');
        redirect('owner/appointments.php');
    }
    $reason = clip(input('reason'), 200) ?: 'Cancelled by pet owner';
    update('appointments', ['status' => 'cancelled', 'cancel_reason' => $reason], 'id = :id', ['id' => $a['id']]);
    notify_staff('cancelled', 'Appointment cancelled by client', full_name($user) . " cancelled {$a['pet_name']}'s {$a['service_name']} on " . appt_when($a) . '.', 'admin/appointment.php?id=' . $a['id']);
    log_activity($uid, full_name($user) . " cancelled {$a['pet_name']}'s {$a['service_name']}", 'calendar-x', 'admin/appointment.php?id=' . $a['id']);
    flash('success', 'Your appointment was cancelled. The time slot is now free for others.');
    redirect('owner/appointments.php?tab=cancelled');
}

$all = rows(appointment_sql() . ' WHERE a.owner_id = ? ORDER BY a.appointment_date DESC, a.start_time DESC', [$uid]);
$groups = ['upcoming' => [], 'past' => [], 'cancelled' => []];
foreach ($all as $a) {
    if ($a['status'] === 'cancelled') {
        $groups['cancelled'][] = $a;
    } elseif (in_array($a['status'], ['pending', 'confirmed'], true) && strtotime($a['appointment_date'] . ' ' . $a['end_time']) >= time()) {
        $groups['upcoming'][] = $a;
    } else {
        $groups['past'][] = $a;
    }
}
$groups['upcoming'] = array_reverse($groups['upcoming']);
$tab = array_key_exists(param('tab'), $groups) ? param('tab') : 'upcoming';
$booked = param('booked') ? row(appointment_sql() . ' WHERE a.reference = ? AND a.owner_id = ?', [param('booked'), $uid]) : null;
if ($booked) {
    $bodyAttrs = 'data-confetti';
}
$tabMeta = ['upcoming' => ['Upcoming', 'calendar-clock'], 'past' => ['Past visits', 'history'], 'cancelled' => ['Cancelled', 'calendar-x']];

$pageTitle = 'Appointments';
$activeNav = 'appointments';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('owner/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>Appointments</span></div>
        <h1>Appointments</h1>
        <p>Track requests, confirmations and your pets' visit history.</p>
    </div>
    <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('owner/book.php')) ?>"><?= icon('calendar-plus') ?>Book a visit</a></div>
</div>

<?php if ($booked): ?>
    <div class="alert alert-success mb-3" style="padding:1.2rem 1.3rem">
        <?= icon('party-popper') ?>
        <div><strong style="font-size:1.05rem">Booking request sent! Reference <?= e($booked['reference']) ?></strong>
            <?= e($booked['pet_name']) ?>'s <?= e($booked['service_name']) ?> on <?= e(appt_when($booked)) ?> is now pending. We'll notify you as soon as the clinic confirms.</div>
    </div>
<?php endif; ?>

<div class="tabs mb-3" role="tablist">
    <?php foreach ($tabMeta as $key => [$label, $ic]): ?>
        <a class="tab<?= $tab === $key ? ' active' : '' ?>" href="?tab=<?= $key ?>" role="tab" aria-selected="<?= $tab === $key ? 'true' : 'false' ?>"><?= icon($ic) ?><?= e($label) ?> <span class="count"><?= count($groups[$key]) ?></span></a>
    <?php endforeach; ?>
</div>

<?php if (!$groups[$tab]): ?>
    <div class="card"><?= empty_state($tabMeta[$tab][1], $tab === 'upcoming' ? 'No upcoming appointments' : 'Nothing here yet', $tab === 'upcoming' ? 'Book a visit and it will appear here with its status.' : '', $tab === 'upcoming' ? '<a class="btn btn-primary btn-sm" href="' . e(url('owner/book.php')) . '">' . icon('calendar-plus') . 'Book a visit</a>' : '') ?></div>
<?php else: ?>
    <div class="appt-list">
        <?php foreach ($groups[$tab] as $a): ?>
            <article class="appt-card">
                <?= date_block($a['appointment_date'], $tab === 'upcoming' ? '' : 'muted-block') ?>
                <?= pet_photo($a, 'size-md') ?>
                <div class="appt-main">
                    <h3><?= e($a['service_name']) ?> · <?= e($a['pet_name']) ?> <?= status_badge($a['status']) ?></h3>
                    <div class="appt-meta">
                        <span><?= icon('clock') ?><?= e(fmt_time($a['start_time'])) ?> – <?= e(fmt_time($a['end_time'])) ?></span>
                        <span><?= icon('stethoscope') ?><?= e($a['vet_name'] ?? 'Any vet') ?></span>
                        <span><?= icon('hash') ?><?= e($a['reference']) ?></span>
                        <span><?= icon('wallet') ?><?= money($a['price']) ?></span>
                    </div>
                    <?php if ($a['status'] === 'cancelled' && $a['cancel_reason']): ?><div class="text-sm muted mt-1">Reason: <?= e($a['cancel_reason']) ?></div><?php endif; ?>
                </div>
                <div class="appt-actions">
                    <?php if ($tab === 'upcoming'): ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('owner/ics.php?id=' . $a['id'])) ?>"><?= icon('calendar-plus') ?>Add to calendar</a>
                        <?php if (owner_can_cancel($a)): ?>
                            <button class="btn btn-danger-soft btn-sm" type="button" data-modal-open="modal-cancel" data-fill="<?= e(json_encode(['id' => $a['id'], 'summary' => $a['pet_name'] . ' · ' . $a['service_name'] . ' · ' . appt_when($a)])) ?>"><?= icon('x') ?>Cancel</button>
                        <?php endif; ?>
                    <?php elseif ($a['status'] === 'completed'): ?>
                        <a class="btn btn-soft btn-sm" href="<?= e(url('owner/pet.php?id=' . $a['pet_id'] . '#records')) ?>"><?= icon('file-heart') ?>Visit notes</a>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('owner/book.php?pet=' . $a['pet_id'] . '&service=' . $a['service_id'])) ?>"><?= icon('rotate-ccw') ?>Book again</a>
                    <?php else: ?>
                        <a class="btn btn-outline btn-sm" href="<?= e(url('owner/book.php?pet=' . $a['pet_id'] . '&service=' . $a['service_id'])) ?>"><?= icon('calendar-plus') ?>Rebook</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<dialog class="modal modal-sm" id="modal-cancel">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="id" value="">
        <div class="confirm-body">
            <div class="confirm-icon"><?= icon('calendar-x') ?></div>
            <h3>Cancel this appointment?</h3>
            <p data-fill-text="summary"></p>
        </div>
        <div class="modal-body" style="padding-top:0">
            <div class="field">
                <label for="cancel-reason">Reason <span class="muted">(optional)</span></label>
                <select class="input" id="cancel-reason" name="reason">
                    <option value="">Prefer not to say</option>
                    <option>Schedule conflict</option>
                    <option>Pet is feeling better</option>
                    <option>Will rebook another day</option>
                    <option>Found another clinic</option>
                </select>
            </div>
        </div>
        <div class="modal-foot"><button class="btn btn-outline" type="button" data-modal-close>Keep it</button><button class="btn btn-danger" type="submit"><?= icon('x') ?>Cancel appointment</button></div>
    </form>
</dialog>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
