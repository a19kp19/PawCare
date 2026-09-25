<?php
/** CSV exports (open in Excel or Google Sheets). */
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();
$type = param('type');
$isDate = fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) === 1;

if ($type === 'appointments') {
    $where = [];
    $params = [];
    if ($isDate(param('from'))) { $where[] = 'a.appointment_date >= ?'; $params[] = param('from'); }
    if ($isDate(param('to'))) { $where[] = 'a.appointment_date <= ?'; $params[] = param('to'); }
    $data = rows(appointment_sql() . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.appointment_date, a.start_time', $params);
    $header = ['Reference', 'Date', 'Start', 'End', 'Status', 'Pet', 'Species', 'Owner', 'Owner email', 'Owner phone', 'Service', 'Category', 'Veterinarian', 'Price', 'Booked by', 'Reason', 'Created'];
    $out = array_map(fn ($a) => [$a['reference'], $a['appointment_date'], substr($a['start_time'], 0, 5), substr($a['end_time'], 0, 5), status_label($a['status']), $a['pet_name'], $a['species'], $a['owner_first'] . ' ' . $a['owner_last'], $a['owner_email'], $a['owner_phone'], $a['service_name'], $a['service_category'], $a['vet_name'], $a['price'], $a['booked_by'], $a['reason'], $a['created_at']], $data);
} elseif ($type === 'patients') {
    $data = rows('SELECT p.*, u.first_name, u.last_name, u.email, u.phone FROM pets p JOIN users u ON u.id = p.owner_id ORDER BY p.name');
    $header = ['ID', 'Name', 'Species', 'Breed', 'Sex', 'Birth date', 'Weight (kg)', 'Spayed/Neutered', 'Allergies', 'Owner', 'Owner email', 'Owner phone', 'Registered'];
    $out = array_map(fn ($p) => [$p['id'], $p['name'], $p['species'], $p['breed'], $p['sex'], $p['birthdate'], $p['weight_kg'], $p['is_neutered'] ? 'Yes' : 'No', $p['allergies'], $p['first_name'] . ' ' . $p['last_name'], $p['email'], $p['phone'], $p['created_at']], $data);
} elseif ($type === 'clients') {
    $data = rows("SELECT u.*, (SELECT COUNT(*) FROM pets p WHERE p.owner_id = u.id) AS pets,
                         (SELECT COUNT(*) FROM appointments a WHERE a.owner_id = u.id AND a.status = 'completed') AS visits,
                         (SELECT COALESCE(SUM(a.price), 0) FROM appointments a WHERE a.owner_id = u.id AND a.status = 'completed') AS spent
                  FROM users u WHERE u.role = 'owner' ORDER BY u.last_name, u.first_name");
    $header = ['ID', 'First name', 'Last name', 'Email', 'Phone', 'Address', 'Pets', 'Completed visits', 'Total spent', 'Joined', 'Active'];
    $out = array_map(fn ($u) => [$u['id'], $u['first_name'], $u['last_name'], $u['email'], $u['phone'], $u['address'], $u['pets'], $u['visits'], $u['spent'], $u['created_at'], $u['is_active'] ? 'Yes' : 'No'], $data);
} else {
    abort(404, 'Unknown export');
}

log_activity((int) $me['id'], full_name($me) . " exported $type (" . count($out) . ' rows)', 'file-down');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . strtolower(CLINIC_SHORT_NAME) . "-$type-" . date('Ymd') . '.csv"');
$fh = fopen('php://output', 'w');
fwrite($fh, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₱ and ñ correctly
fputcsv($fh, $header);
foreach ($out as $line) {
    // prevent spreadsheet formula injection
    fputcsv($fh, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, $line));
}
fclose($fh);
