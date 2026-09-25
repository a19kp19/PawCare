<?php
/** GET action=count → unread count. POST action=read (id) | read_all. */
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$uid = (int) $user['id'];

if (is_post()) {
    verify_csrf();
    if (input('action') === 'read') {
        q('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [int_param('id'), $uid]);
    } elseif (input('action') === 'read_all') {
        q('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$uid]);
    }
    json_response(['ok' => true, 'unread' => unread_count($uid)]);
}
$latest = row('SELECT title FROM notifications WHERE user_id = ? AND is_read = 0 AND created_at >= ? ORDER BY id DESC LIMIT 1', [$uid, date('Y-m-d H:i:s', time() - 60)]);
json_response(['ok' => true, 'unread' => unread_count($uid), 'latest' => $latest['title'] ?? null]);
