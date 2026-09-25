<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_owner();
$uid = (int) $user['id'];
$today = date('Y-m-d');

$pets = rows('SELECT * FROM pets WHERE owner_id = ? ORDER BY created_at', [$uid]);
$upcoming = rows(
    appointment_sql() . " WHERE a.owner_id = ? AND a.status IN ('pending','confirmed')
      AND (a.appointment_date > ? OR (a.appointment_date = ? AND a.end_time >= ?)) ORDER BY a.appointment_date, a.start_time",
    [$uid, $today, $today, date('H:i:s')]
);
$next = $upcoming[0] ?? null;
$completed = (int) val("SELECT COUNT(*) FROM appointments WHERE owner_id = ? AND status = 'completed'", [$uid]);
$alerts = rows(
    'SELECT x.*, p.name AS pet_name, p.species, p.photo AS pet_photo FROM (' . latest_vaccines_sql() . ') x
     JOIN pets p ON p.id = x.pet_id WHERE p.owner_id = ? AND x.next_due_date IS NOT NULL AND x.next_due_date <= ?
     ORDER BY x.next_due_date',
    [$uid, date('Y-m-d', strtotime('+' . REMINDER_WINDOW_DAYS . ' days'))]
);
$recentRecords = rows(
    'SELECT m.*, p.name AS pet_name, p.species, p.photo AS pet_photo, v.name AS vet_name FROM medical_records m
     JOIN pets p ON p.id = m.pet_id LEFT JOIN vets v ON v.id = m.vet_id WHERE p.owner_id = ? ORDER BY m.visit_date DESC, m.id DESC LIMIT 4',
    [$uid]
);
$notes = recent_notifications($uid, 5);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<section class="welcome" data-reveal>
    <div>
        <span class="date-chip"><?= icon('calendar-days') ?><?= e(date('l, F j')) ?></span>
        <h1><?= e(greeting()) ?>, <?= e($user['first_name']) ?>! <span aria-hidden="true">👋</span></h1>
        <p>
            <?php if ($pets): ?>
                You have <?= plural(count($upcoming), 'upcoming visit') ?><?= $alerts ? ' and ' . plural(count($alerts), 'vaccine reminder') : '' ?>. Here's everything happening with <?= e(implode(', ', array_slice(array_column($pets, 'name'), 0, 3))) ?>.
            <?php else: ?>
                Welcome to your pet portal. Start by adding your first pet — it only takes a minute.
            <?php endif; ?>
        </p>
        <div class="welcome-actions">
            <a class="btn btn-white" href="<?= e(url('owner/book.php')) ?>"><?= icon('calendar-plus') ?>Book a visit</a>
            <a class="btn btn-glass" href="<?= e(url('owner/pet-form.php')) ?>"><?= icon('plus') ?>Add a pet</a>
        </div>
    </div>
    <?php if ($pets): ?>
        <div class="welcome-pets"><?php foreach (array_slice($pets, 0, 3) as $p): ?><?= pet_photo($p) ?><?php endforeach; ?></div>
    <?php endif; ?>
</section>

<div class="kpi-grid">
    <?= kpi_card('My pets', (string) count($pets), 'paw-print', 'teal', 'Profiles &amp; health records', 'owner/pets.php') ?>
    <?= kpi_card('Upcoming visits', (string) count($upcoming), 'calendar-check', 'blue', $next ? 'Next: ' . e(fmt_date($next['appointment_date'], 'M j')) : 'Nothing scheduled', 'owner/appointments.php') ?>
    <?= kpi_card('Vaccines due', (string) count($alerts), 'syringe', count($alerts) ? 'coral' : 'green', count($alerts) ? 'Due within ' . REMINDER_WINDOW_DAYS . ' days or overdue' : 'All up to date', 'owner/pets.php') ?>
    <?= kpi_card('Visits completed', (string) $completed, 'circle-check', 'violet', 'Since you joined ' . e(fmt_date($user['created_at'], 'M Y'))) ?>
</div>

<?php if (!$pets): ?>
    <div class="card"><?= empty_state('paw-print', 'Add your first pet', 'Create a profile with a photo, breed and health details so our vets can give the best care.', '<a class="btn btn-primary" href="' . e(url('owner/pet-form.php?first=1')) . '">' . icon('plus') . 'Add a pet</a>') ?></div>
<?php else: ?>
<div class="grid cols-12">
    <div class="span-8 stack">
        <div class="card" data-reveal>
            <div class="card-head"><h2><?= icon('calendar-check') ?>Next appointment</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('owner/appointments.php')) ?>">All appointments <?= icon('arrow-right') ?></a></div>
            <?php if ($next): ?>
                <div class="next-appt">
                    <?= date_block($next['appointment_date']) ?>
                    <?= pet_photo($next, 'size-md') ?>
                    <div class="appt-main">
                        <h3><?= e($next['service_name']) ?> for <?= e($next['pet_name']) ?> <?= status_badge($next['status']) ?></h3>
                        <div class="appt-meta">
                            <span><?= icon('clock') ?><?= e(fmt_time($next['start_time'])) ?> – <?= e(fmt_time($next['end_time'])) ?></span>
                            <span><?= icon('stethoscope') ?><?= e($next['vet_name'] ?? 'Assigned on arrival') ?></span>
                            <span><?= icon('hash') ?><?= e($next['reference']) ?></span>
                        </div>
                        <div class="mt-1"><span class="countdown"><?= icon('hourglass') ?><?= e(relative_day($next['appointment_date'])) ?></span></div>
                    </div>
                    <div class="appt-actions"><a class="btn btn-outline btn-sm" href="<?= e(url('owner/ics.php?id=' . $next['id'])) ?>"><?= icon('calendar-plus') ?>Add to calendar</a></div>
                </div>
            <?php else: ?>
                <?= empty_state('calendar-plus', 'No upcoming visits', 'Book a check-up, vaccination or grooming session in a few taps.', '<a class="btn btn-primary btn-sm" href="' . e(url('owner/book.php')) . '">' . icon('calendar-plus') . 'Book now</a>') ?>
            <?php endif; ?>
        </div>

        <div class="card" data-reveal>
            <div class="card-head"><h2><?= icon('paw-print') ?>My pets</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('owner/pets.php')) ?>">Manage <?= icon('arrow-right') ?></a></div>
            <div class="mini-pets">
                <?php foreach ($pets as $p):
                    $st = pet_health_status((int) $p['id']);
                    // Compact chip: the Health reminders panel carries the full detail.
                    [$chipLabel, $chipIcon, $chipColor] = match ($st['key']) {
                        'overdue' => ['Overdue', 'triangle-alert', 'red'],
                        'due'     => [$st['label'], 'bell-ring', 'amber'],
                        'none'    => ['No vaccines yet', 'shield-plus', 'gray'],
                        default   => ['Up to date', 'shield-check', 'green'],
                    }; ?>
                    <a class="mini-pet" href="<?= e(url('owner/pet.php?id=' . $p['id'])) ?>">
                        <?= pet_photo($p) ?>
                        <div class="mini-pet-body"><strong><?= e($p['name']) ?></strong><small><?= e($p['breed'] ?: $p['species']) ?> · <?= e(pet_age($p['birthdate'])) ?></small><span class="badge badge-<?= e($chipColor) ?>"><?= icon($chipIcon) ?><?= e($chipLabel) ?></span></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card" data-reveal>
            <div class="card-head"><h2><?= icon('file-heart') ?>Recent visit notes</h2></div>
            <?php if ($recentRecords): ?>
                <div class="list">
                    <?php foreach ($recentRecords as $r): ?>
                        <a class="list-item" href="<?= e(url('owner/pet.php?id=' . $r['pet_id'] . '#records')) ?>">
                            <?= pet_photo($r, 'size-sm') ?>
                            <div class="li-main"><div class="li-title"><?= e($r['diagnosis']) ?></div><div class="li-sub"><?= e($r['pet_name']) ?> · <?= e(fmt_date($r['visit_date'])) ?> · <?= e($r['vet_name'] ?? 'Clinic vet') ?></div></div>
                            <?php if ($r['prescription']): ?><span class="badge badge-violet"><?= icon('pill') ?>Rx</span><?php endif; ?>
                            <?= icon('chevron-right', 'faint') ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('file-heart', 'No visit notes yet', 'After each visit, your vet\'s notes and prescriptions appear here.') ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="span-4 stack">
        <div class="card" data-reveal>
            <div class="card-head"><h2><?= icon('bell-ring') ?>Health reminders</h2></div>
            <?php if ($alerts): ?>
                <div class="list">
                    <?php foreach ($alerts as $al): $st = vaccine_status($al['next_due_date']); ?>
                        <div class="list-item">
                            <?= pet_photo($al, 'size-sm') ?>
                            <div class="li-main"><div class="li-title"><?= e($al['pet_name']) ?> · <?= e($al['vaccine_name']) ?></div><div class="li-sub"><span class="text-<?= $st['key'] === 'overdue' ? 'red' : 'amber' ?> fw-700"><?= e($st['label']) ?></span> · <?= e(fmt_date($al['next_due_date'], 'M j')) ?></div></div>
                            <a class="btn btn-soft btn-xs" href="<?= e(url('owner/book.php?pet=' . $al['pet_id'] . '&service=2')) ?>">Book</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('shield-check', 'All caught up!', 'No vaccines are due in the next ' . REMINDER_WINDOW_DAYS . ' days.') ?>
            <?php endif; ?>
        </div>

        <div class="card" data-reveal>
            <div class="card-head"><h2><?= icon('bell') ?>Latest updates</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('notifications.php')) ?>">View all</a></div>
            <?php if ($notes): ?>
                <div class="activity">
                    <?php foreach ($notes as $n): ?>
                        <a class="activity-item" href="<?= e(url($n['link'] ?: 'notifications.php')) ?>">
                            <span class="activity-icon"><?= icon(notification_icon($n['type'])) ?></span>
                            <div><p><?= e($n['title']) ?></p><time><?= e(time_ago($n['created_at'])) ?></time></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?= empty_state('bell', 'No updates yet') ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
