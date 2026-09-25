<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$uid = (int) $me['id'];

if (int_param('open')) {
    $n = row('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [int_param('open'), $uid]);
    if ($n) {
        q('UPDATE notifications SET is_read = 1 WHERE id = ?', [$n['id']]);
        redirect($n['link'] ?: 'notifications.php');
    }
}
if (is_post() && input('action') === 'read_all') {
    verify_csrf();
    q('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$uid]);
    flash('success', 'All notifications marked as read.');
    redirect('notifications.php');
}

$unreadOnly = param('filter') === 'unread';
$total = (int) val('SELECT COUNT(*) FROM notifications WHERE user_id = ?' . ($unreadOnly ? ' AND is_read = 0' : ''), [$uid]);
$p = paginate($total, 15);
$list = rows('SELECT * FROM notifications WHERE user_id = ?' . ($unreadOnly ? ' AND is_read = 0' : '') . " ORDER BY created_at DESC, id DESC LIMIT {$p['per']} OFFSET {$p['offset']}", [$uid]);
$unreadTotal = unread_count($uid);

$pageTitle = 'Notifications';
$activeNav = 'notifications';
include __DIR__ . '/includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <h1>Notifications</h1>
        <p><?= $unreadTotal ? plural($unreadTotal, 'unread notification') : "You're all caught up." ?></p>
    </div>
    <?php if ($unreadTotal): ?>
        <form class="page-actions" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-outline" type="submit"><?= icon('check-check') ?>Mark all as read</button></form>
    <?php endif; ?>
</div>
<div class="card">
    <div class="status-tabs"><div class="tabs">
        <a class="tab<?= $unreadOnly ? '' : ' active' ?>" href="?">All</a>
        <a class="tab<?= $unreadOnly ? ' active' : '' ?>" href="?filter=unread">Unread <span class="count"><?= $unreadTotal ?></span></a>
    </div></div>
    <div class="notif-list mt-2" style="max-height:none">
        <?php foreach ($list as $n): ?>
            <a class="notif-item notif-type-<?= e($n['type']) ?><?= $n['is_read'] ? '' : ' unread' ?>" href="?open=<?= (int) $n['id'] ?>">
                <span class="notif-icon"><?= icon(notification_icon($n['type'])) ?></span>
                <div><strong><?= e($n['title']) ?></strong><p><?= e($n['message']) ?></p><time><?= e(fmt_datetime($n['created_at'])) ?> · <?= e(time_ago($n['created_at'])) ?></time></div>
            </a>
        <?php endforeach; ?>
        <?php if (!$list): ?><?= empty_state('bell', $unreadOnly ? 'No unread notifications' : 'No notifications yet', 'Booking confirmations, reminders and visit summaries will appear here.') ?><?php endif; ?>
    </div>
    <?= pagination_links($p) ?>
</div>
<?php include __DIR__ . '/includes/layout/app_footer.php'; ?>
