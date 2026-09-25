<?php
/**
 * In-app notifications and the staff activity feed.
 */

const NOTIFICATION_ICONS = [
    'booking'     => 'calendar-plus',
    'appointment' => 'calendar-check',
    'cancelled'   => 'calendar-x',
    'vaccine'     => 'syringe',
    'record'      => 'file-heart',
    'reminder'    => 'bell-ring',
    'message'     => 'mail',
    'account'     => 'user-check',
    'system'      => 'sparkles',
];

function notification_icon(string $type): string
{
    return NOTIFICATION_ICONS[$type] ?? 'bell';
}

function notify(int $userId, string $type, string $title, string $message = '', ?string $link = null): void
{
    insert('notifications', [
        'user_id' => $userId,
        'type'    => $type,
        'title'   => $title,
        'message' => $message,
        'link'    => $link,
    ]);
}

/** Send a notification to every active admin & staff account. */
function notify_staff(string $type, string $title, string $message = '', ?string $link = null, ?int $exceptUserId = null): void
{
    foreach (rows("SELECT id FROM users WHERE role IN ('admin','staff') AND is_active = 1") as $u) {
        if ($exceptUserId !== null && (int) $u['id'] === $exceptUserId) {
            continue;
        }
        notify((int) $u['id'], $type, $title, $message, $link);
    }
}

function log_activity(?int $userId, string $action, string $icon = 'activity', ?string $link = null): void
{
    insert('activity_log', ['user_id' => $userId, 'action' => $action, 'icon' => $icon, 'link' => $link]);
}

function unread_count(int $userId): int
{
    return (int) val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
}

function recent_notifications(int $userId, int $limit = 8): array
{
    $limit = max(1, $limit);
    return rows("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT $limit", [$userId]);
}

/** Tell the pet owner that their appointment changed status. */
function notify_appointment_status(array $a, string $status, string $extra = ''): void
{
    $when = fmt_date($a['appointment_date'], 'D, M j') . ' at ' . fmt_time($a['start_time']);
    $what = $a['pet_name'] . "'s " . $a['service_name'];
    switch ($status) {
        case 'confirmed':
            notify((int) $a['owner_id'], 'appointment', 'Appointment confirmed', "$what on $when is confirmed. See you soon!", 'owner/appointments.php');
            break;
        case 'cancelled':
            notify((int) $a['owner_id'], 'cancelled', 'Appointment cancelled', "$what on $when was cancelled." . ($extra ? " Reason: $extra" : ''), 'owner/appointments.php?tab=cancelled');
            break;
        case 'completed':
            notify((int) $a['owner_id'], 'record', 'Visit summary ready', "Notes from {$a['pet_name']}'s visit are now in the health record.", 'owner/pet.php?id=' . $a['pet_id'] . '#records');
            break;
        case 'no_show':
            notify((int) $a['owner_id'], 'appointment', 'We missed you today', "{$a['pet_name']} missed the $when {$a['service_name']} appointment. Book a new time anytime.", 'owner/book.php?pet=' . $a['pet_id']);
            break;
        case 'rescheduled':
            notify((int) $a['owner_id'], 'appointment', 'Appointment rescheduled', "$what has been moved to $when.", 'owner/appointments.php');
            break;
    }
}
