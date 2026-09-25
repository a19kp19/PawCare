<?php
/** Staff global search: patients, clients and booking references. */
require __DIR__ . '/../includes/bootstrap.php';
require_staff();
$q = trim((string) param('q'));
if (strlen($q) < 2) {
    json_response(['ok' => true, 'pets' => [], 'clients' => [], 'appointments' => []]);
}
$like = '%' . $q . '%';
$pets = rows(
    "SELECT p.id, p.name, p.species, p.breed, p.photo, u.first_name, u.last_name FROM pets p JOIN users u ON u.id = p.owner_id
     WHERE p.name LIKE ? OR p.breed LIKE ? ORDER BY p.name LIMIT 5",
    [$like, $like]
);
$clients = rows(
    "SELECT id, first_name, last_name, email, phone FROM users WHERE role = 'owner'
     AND (CONCAT(first_name, ' ', last_name) LIKE ? OR email LIKE ? OR phone LIKE ?) ORDER BY last_name LIMIT 5",
    [$like, $like, $like]
);
$appts = rows(appointment_sql() . ' WHERE a.reference LIKE ? ORDER BY a.appointment_date DESC LIMIT 5', [$like]);
json_response([
    'ok' => true,
    'pets' => array_map(fn ($p) => [
        'url' => url('admin/patient.php?id=' . $p['id']), 'title' => $p['name'], 'sub' => $p['species'] . ($p['breed'] ? ' · ' . $p['breed'] : '') . ' · ' . $p['first_name'] . ' ' . $p['last_name'],
        'thumb' => pet_photo($p, 'size-sm'),
    ], $pets),
    'clients' => array_map(fn ($c) => [
        'url' => url('admin/client.php?id=' . $c['id']), 'title' => $c['first_name'] . ' ' . $c['last_name'], 'sub' => $c['email'] . ($c['phone'] ? ' · ' . $c['phone'] : ''),
        'thumb' => avatar($c['first_name'] . ' ' . $c['last_name']),
    ], $clients),
    'appointments' => array_map(fn ($a) => [
        'url' => url('admin/appointment.php?id=' . $a['id']), 'title' => $a['reference'] . ' · ' . $a['pet_name'], 'sub' => $a['service_name'] . ' · ' . appt_when($a),
        'thumb' => '<span class="notif-icon">' . icon('calendar-check') . '</span>', 'badge' => status_badge($a['status']),
    ], $appts),
]);
