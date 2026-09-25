<?php
/**
 * Scheduling engine: clinic hours, vet availability, time slots and booking.
 */

function t2m(string $time): int
{
    $parts = explode(':', $time);
    return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
}

function m2t(int $minutes): string
{
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function clinic_hours_for(string $date): ?array
{
    $dow = (int) date('N', strtotime($date));
    return CLINIC_HOURS[$dow] ?? null;
}

/** Live open/closed status for the website header. */
function clinic_status(): array
{
    $now = t2m(date('H:i'));
    $hours = clinic_hours_for(date('Y-m-d'));
    if ($hours && $now >= t2m($hours[0]) && $now < t2m($hours[1])) {
        return ['open' => true, 'label' => 'Open now', 'detail' => 'until ' . fmt_time($hours[1])];
    }
    for ($i = 0; $i < 8; $i++) {
        $d = date('Y-m-d', strtotime("+$i day"));
        $h = clinic_hours_for($d);
        if (!$h || ($i === 0 && $now >= t2m($h[0]))) {
            continue;
        }
        $when = $i === 0 ? 'today' : ($i === 1 ? 'tomorrow' : weekday_name((int) date('N', strtotime($d)), true));
        return ['open' => false, 'label' => 'Closed now', 'detail' => 'opens ' . $when . ' ' . fmt_time($h[0])];
    }
    return ['open' => false, 'label' => 'Closed', 'detail' => ''];
}

/** Opening hours grouped for display, e.g. "Mon – Fri: 8:00 AM – 6:00 PM". */
function clinic_hours_summary(): array
{
    $groups = [];
    foreach (CLINIC_HOURS as $day => $h) {
        $label = $h ? fmt_time($h[0]) . ' – ' . fmt_time($h[1]) : 'Closed';
        $last = count($groups) - 1;
        if ($last >= 0 && $groups[$last]['label'] === $label && $groups[$last]['to'] === $day - 1) {
            $groups[$last]['to'] = $day;
        } else {
            $groups[] = ['from' => $day, 'to' => $day, 'label' => $label, 'closed' => !$h];
        }
    }
    $today = (int) date('N');
    return array_map(function ($g) use ($today) {
        $days = $g['from'] === $g['to']
            ? weekday_name($g['from'], true)
            : weekday_name($g['from'], true) . ' – ' . weekday_name($g['to'], true);
        return ['days' => $days, 'hours' => $g['label'], 'closed' => $g['closed'], 'today' => $today >= $g['from'] && $today <= $g['to']];
    }, $groups);
}

function vet_work_days(array $vet): array
{
    return array_values(array_filter(array_map('intval', explode(',', (string) $vet['work_days']))));
}

function vet_works_on(array $vet, string $date): bool
{
    return in_array((int) date('N', strtotime($date)), vet_work_days($vet), true);
}

function vets_for_date(string $date, ?int $vetId = null): array
{
    $vets = $vetId
        ? rows('SELECT * FROM vets WHERE id = ? AND is_active = 1', [$vetId])
        : rows('SELECT * FROM vets WHERE is_active = 1 ORDER BY id');
    return array_values(array_filter($vets, fn ($v) => vet_works_on($v, $date)));
}

/**
 * All time slots for a date with availability.
 * $opts: ignore_id (appointment to ignore, for rescheduling), notice (minutes), allow_past (bool)
 */
function available_slots(string $date, int $duration, ?int $vetId = null, array $opts = []): array
{
    $result = ['date' => $date, 'closed' => false, 'message' => '', 'slots' => []];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
        return ['closed' => true, 'message' => 'Please choose a valid date.'] + $result;
    }
    $hours = clinic_hours_for($date);
    if (!$hours) {
        $result['closed'] = true;
        $result['message'] = 'The clinic is closed on ' . weekday_name((int) date('N', strtotime($date))) . 's.';
        return $result;
    }
    $vets = vets_for_date($date, $vetId);
    if (!$vets) {
        $result['message'] = $vetId ? 'This veterinarian is not on duty that day. Try another date or "Any available vet".' : 'No veterinarians are on duty that day.';
        return $result;
    }

    $params = [$date];
    $sql = "SELECT vet_id, start_time, end_time FROM appointments
            WHERE appointment_date = ? AND status IN ('pending','confirmed','completed') AND vet_id IS NOT NULL";
    if (!empty($opts['ignore_id'])) {
        $sql .= ' AND id <> ?';
        $params[] = (int) $opts['ignore_id'];
    }
    $busy = [];
    foreach (rows($sql, $params) as $a) {
        $busy[(int) $a['vet_id']][] = [t2m($a['start_time']), t2m($a['end_time'])];
    }

    $open = t2m($hours[0]);
    $close = t2m($hours[1]);
    $lunch = LUNCH_BREAK ? [t2m(LUNCH_BREAK[0]), t2m(LUNCH_BREAK[1])] : null;
    $duration = max(15, $duration);
    $today = date('Y-m-d');
    $cutoff = null;
    if (empty($opts['allow_past'])) {
        if ($date < $today) {
            $cutoff = PHP_INT_MAX;
        } elseif ($date === $today) {
            $cutoff = t2m(date('H:i')) + (int) ($opts['notice'] ?? MIN_NOTICE_MINUTES);
        }
    }

    for ($s = $open; $s + $duration <= $close; $s += SLOT_MINUTES) {
        $e = $s + $duration;
        if ($lunch && $s < $lunch[1] && $e > $lunch[0]) {
            continue;
        }
        $past = $cutoff !== null && $s < $cutoff;
        $free = [];
        if (!$past) {
            foreach ($vets as $v) {
                $ok = true;
                foreach ($busy[(int) $v['id']] ?? [] as [$bs, $be]) {
                    if ($s < $be && $e > $bs) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    $free[] = (int) $v['id'];
                }
            }
        }
        $result['slots'][] = [
            'time'      => m2t($s),
            'end'       => m2t($e),
            'label'     => fmt_time(m2t($s)),
            'period'    => $s < 720 ? 'Morning' : 'Afternoon',
            'available' => !$past && $free !== [],
            'vet_ids'   => $free,
        ];
    }
    if (!$result['slots']) {
        $result['message'] = 'No time slots fit this service on that day.';
    } elseif (!array_filter($result['slots'], fn ($s) => $s['available'])) {
        $allPast = $cutoff !== null && !array_filter($result['slots'], fn ($s) => t2m($s['time']) >= $cutoff);
        $result['message'] = !$allPast ? 'Fully booked — please try another day.'
            : ($date < $today ? 'That date has already passed.' : 'No more open times today — please pick another day.');
    }
    return $result;
}

/** First date that still has bookable hours (skips today once its last slot is out of reach). */
function first_bookable_date(int $notice = MIN_NOTICE_MINUTES): string
{
    for ($i = 0; $i < 14; $i++) {
        $date = date('Y-m-d', strtotime("+$i day"));
        $hours = clinic_hours_for($date);
        if (!$hours || ($i === 0 && t2m(date('H:i')) + $notice + SLOT_MINUTES > t2m($hours[1]))) {
            continue;
        }
        return $date;
    }
    return date('Y-m-d');
}

/** Next open slot for a service (used on the homepage hero card). */
function next_available_slot(int $duration, int $daysAhead = 14): ?array
{
    for ($i = 0; $i <= $daysAhead; $i++) {
        $date = date('Y-m-d', strtotime("+$i day"));
        foreach (available_slots($date, $duration)['slots'] as $s) {
            if ($s['available']) {
                return ['date' => $date, 'time' => $s['time'], 'label' => $s['label']];
            }
        }
    }
    return null;
}

/** Pick the vet with the fewest bookings that day (spreads the workload). */
function least_busy_vet(array $vetIds, string $date): ?int
{
    if (!$vetIds) {
        return null;
    }
    $in = implode(',', array_map('intval', $vetIds));
    $counts = [];
    $sql = "SELECT vet_id, COUNT(*) AS c FROM appointments
            WHERE appointment_date = ? AND status IN ('pending','confirmed','completed') AND vet_id IN ($in)
            GROUP BY vet_id";
    foreach (rows($sql, [$date]) as $r) {
        $counts[(int) $r['vet_id']] = (int) $r['c'];
    }
    usort($vetIds, fn ($a, $b) => (($counts[$a] ?? 0) <=> ($counts[$b] ?? 0)) ?: ($a <=> $b));
    return (int) $vetIds[0];
}

function generate_reference(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $ref = 'PC-';
        for ($i = 0; $i < 6; $i++) {
            $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
    } while (val('SELECT 1 FROM appointments WHERE reference = ?', [$ref]));
    return $ref;
}

/**
 * Book an appointment after re-checking the slot inside a transaction
 * (prevents two people grabbing the same vet & time).
 * $d keys: pet_id, owner_id, service_id, vet_id (nullable), date, time (HH:MM), reason, status
 * Returns ['id' => int] on success or ['error' => string].
 */
function book_appointment(array $d, bool $byStaff = false): array
{
    $service = row('SELECT * FROM services WHERE id = ? AND is_active = 1', [(int) $d['service_id']]);
    if (!$service) {
        return ['error' => 'Please choose a valid service.'];
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d['date']) || $d['date'] < date('Y-m-d')) {
        return ['error' => 'Please choose a date from today onwards.'];
    }
    if (!$byStaff && $d['date'] > date('Y-m-d', strtotime('+' . BOOKING_WINDOW_DAYS . ' days'))) {
        return ['error' => 'Appointments can be booked up to ' . BOOKING_WINDOW_DAYS . ' days ahead.'];
    }
    $vetId = !empty($d['vet_id']) ? (int) $d['vet_id'] : null;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('SELECT id FROM appointments WHERE appointment_date = ? FOR UPDATE', [$d['date']]);
        $slots = available_slots($d['date'], (int) $service['duration_minutes'], $vetId, ['notice' => $byStaff ? 0 : MIN_NOTICE_MINUTES]);
        $slot = null;
        foreach ($slots['slots'] as $s) {
            if ($s['time'] === substr((string) $d['time'], 0, 5) && $s['available']) {
                $slot = $s;
                break;
            }
        }
        if (!$slot) {
            $pdo->rollBack();
            return ['error' => 'Sorry — that time is no longer available. Please pick another slot.'];
        }
        $clash = (int) val(
            "SELECT COUNT(*) FROM appointments WHERE pet_id = ? AND appointment_date = ?
             AND status IN ('pending','confirmed') AND start_time < ? AND end_time > ?",
            [(int) $d['pet_id'], $d['date'], $slot['end'] . ':00', $slot['time'] . ':00']
        );
        if ($clash) {
            $pdo->rollBack();
            return ['error' => 'This pet already has an appointment at that time.'];
        }
        $id = insert('appointments', [
            'reference'        => generate_reference(),
            'pet_id'           => (int) $d['pet_id'],
            'owner_id'         => (int) $d['owner_id'],
            'service_id'       => (int) $service['id'],
            'vet_id'           => $vetId ?: least_busy_vet($slot['vet_ids'], $d['date']),
            'appointment_date' => $d['date'],
            'start_time'       => $slot['time'] . ':00',
            'end_time'         => $slot['end'] . ':00',
            'status'           => $d['status'] ?? 'pending',
            'price'            => $service['price'],
            'reason'           => ($d['reason'] ?? '') !== '' ? $d['reason'] : null,
            'booked_by'        => $byStaff ? 'staff' : 'owner',
        ]);
        $pdo->commit();
        return ['id' => $id];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Move an appointment to a new date/time (staff). */
function reschedule_appointment(array $appt, string $date, string $time, ?int $vetId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        q('SELECT id FROM appointments WHERE appointment_date = ? FOR UPDATE', [$date]);
        $duration = (t2m($appt['end_time']) - t2m($appt['start_time'])) ?: 30;
        $slots = available_slots($date, $duration, $vetId, ['ignore_id' => (int) $appt['id'], 'notice' => 0]);
        $slot = null;
        foreach ($slots['slots'] as $s) {
            if ($s['time'] === substr($time, 0, 5) && $s['available']) {
                $slot = $s;
                break;
            }
        }
        if (!$slot) {
            $pdo->rollBack();
            return ['error' => 'That time is not available. Please choose another slot.'];
        }
        update('appointments', [
            'appointment_date' => $date,
            'start_time'       => $slot['time'] . ':00',
            'end_time'         => $slot['end'] . ':00',
            'vet_id'           => $vetId ?: least_busy_vet($slot['vet_ids'], $date),
            'reminded_at'      => null,
        ], 'id = :id', ['id' => (int) $appt['id']]);
        $pdo->commit();
        return ['ok' => true];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function appointment_sql(): string
{
    return "SELECT a.*, p.name AS pet_name, p.species, p.breed, p.photo AS pet_photo, p.allergies,
                   s.name AS service_name, s.category AS service_category, s.icon AS service_icon, s.is_vaccination,
                   v.name AS vet_name, v.photo AS vet_photo,
                   u.first_name AS owner_first, u.last_name AS owner_last, u.email AS owner_email, u.phone AS owner_phone
            FROM appointments a
            JOIN pets p ON p.id = a.pet_id
            JOIN services s ON s.id = a.service_id
            JOIN users u ON u.id = a.owner_id
            LEFT JOIN vets v ON v.id = a.vet_id";
}

function find_appointment(int $id): ?array
{
    return row(appointment_sql() . ' WHERE a.id = ?', [$id]);
}

function appointment_start_ts(array $a): int
{
    return (int) strtotime($a['appointment_date'] . ' ' . $a['start_time']);
}

/** Can the pet owner still cancel this appointment online? */
function owner_can_cancel(array $a): bool
{
    return in_array($a['status'], ['pending', 'confirmed'], true)
        && appointment_start_ts($a) - time() >= CANCEL_NOTICE_MINUTES * 60;
}

/** Staff status change (confirm / cancel / no-show) with notification + activity log. Returns an error or null. */
function change_appointment_status(array $a, string $status, array $actor, string $reason = ''): ?string
{
    $allowed = ['confirmed' => ['pending'], 'cancelled' => ['pending', 'confirmed'], 'no_show' => ['pending', 'confirmed']];
    if (!isset($allowed[$status]) || !in_array($a['status'], $allowed[$status], true)) {
        return 'That status change is not allowed for a ' . strtolower(status_label($a['status'])) . ' appointment.';
    }
    if ($status === 'no_show' && $a['appointment_date'] > date('Y-m-d')) {
        return 'You can only mark a no-show on or after the appointment day.';
    }
    $data = ['status' => $status];
    if ($status === 'cancelled') {
        $data['cancel_reason'] = $reason !== '' ? $reason : 'Cancelled by the clinic';
    }
    update('appointments', $data, 'id = :id', ['id' => (int) $a['id']]);
    notify_appointment_status($a, $status, $data['cancel_reason'] ?? '');
    $verbs = ['confirmed' => ['confirmed', 'calendar-check'], 'cancelled' => ['cancelled', 'calendar-x'], 'no_show' => ['marked a no-show for', 'ban']];
    log_activity((int) $actor['id'], full_name($actor) . ' ' . $verbs[$status][0] . " {$a['pet_name']}'s {$a['service_name']}", $verbs[$status][1], 'admin/appointment.php?id=' . $a['id']);
    return null;
}
