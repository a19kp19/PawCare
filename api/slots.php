<?php
/** GET date, service_id, vet_id?, ignore? → available time slots (JSON). */
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$staff = is_staff($user);
$service = row('SELECT * FROM services WHERE id = ? AND is_active = 1', [int_param('service_id')]);
if (!$service) {
    json_response(['ok' => false, 'error' => 'Please choose a service first.'], 422);
}
$date = (string) param('date');
if (!$staff && $date > date('Y-m-d', strtotime('+' . BOOKING_WINDOW_DAYS . ' days'))) {
    json_response(['ok' => true, 'date' => $date, 'closed' => true, 'message' => 'Online booking opens ' . BOOKING_WINDOW_DAYS . ' days ahead.', 'slots' => []]);
}
$res = available_slots($date, (int) $service['duration_minutes'], int_param('vet_id') ?: null, [
    'notice'    => $staff ? 0 : MIN_NOTICE_MINUTES,
    'ignore_id' => $staff ? int_param('ignore') : 0,
]);
json_response([
    'ok'      => true,
    'date'    => $date,
    'closed'  => $res['closed'],
    'message' => $res['message'],
    'slots'   => array_map(fn ($s) => [
        'time' => $s['time'], 'label' => $s['label'], 'period' => $s['period'], 'available' => $s['available'], 'free' => count($s['vet_ids']),
    ], $res['slots']),
]);
