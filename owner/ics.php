<?php
/** Download an appointment as a calendar (.ics) file. */
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$a = find_appointment(int_param('id'));
if (!$a || (!is_staff($user) && (int) $a['owner_id'] !== (int) $user['id'])) {
    abort(404, 'Appointment not found');
}
$esc = fn (string $s): string => str_replace(["\\", ',', ';', "\r\n", "\n"], ["\\\\", '\\,', '\\;', '\\n', '\\n'], $s);
$utc = fn (string $date, string $time): string => gmdate('Ymd\THis\Z', strtotime("$date $time"));
$lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//' . CLINIC_SHORT_NAME . '//Appointments//EN',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    'BEGIN:VEVENT',
    'UID:' . $a['reference'] . '@' . strtolower(preg_replace('/\W+/', '', CLINIC_SHORT_NAME)),
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . $utc($a['appointment_date'], $a['start_time']),
    'DTEND:' . $utc($a['appointment_date'], $a['end_time']),
    'SUMMARY:' . $esc($a['pet_name'] . ' – ' . $a['service_name'] . ' at ' . CLINIC_SHORT_NAME),
    'LOCATION:' . $esc(CLINIC_NAME . ', ' . CLINIC_ADDRESS),
    'DESCRIPTION:' . $esc('Reference ' . $a['reference'] . "\nVeterinarian: " . ($a['vet_name'] ?? 'First available') . "\nClinic phone: " . CLINIC_PHONE),
    'STATUS:' . ($a['status'] === 'confirmed' ? 'CONFIRMED' : 'TENTATIVE'),
    'BEGIN:VALARM',
    'TRIGGER:-PT2H',
    'ACTION:DISPLAY',
    'DESCRIPTION:' . $esc('Vet visit for ' . $a['pet_name'] . ' in 2 hours'),
    'END:VALARM',
    'END:VEVENT',
    'END:VCALENDAR',
];
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . CLINIC_SHORT_NAME . '-' . $a['reference'] . '.ics"');
echo implode("\r\n", $lines) . "\r\n";
