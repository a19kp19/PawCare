<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();

if (is_post()) {
    verify_csrf();
    $id = int_param('id');
    if (input('action') === 'status' && in_array(input('status'), ['new', 'read', 'resolved'], true)) {
        update('contact_messages', ['status' => input('status')], 'id = :id', ['id' => $id]);
        flash('success', 'Message marked as ' . input('status') . '.');
    } elseif (input('action') === 'delete') {
        q('DELETE FROM contact_messages WHERE id = ?', [$id]);
        flash('success', 'Message deleted.');
    }
    redirect_back('admin/messages.php');
}

$filter = in_array(param('status'), ['new', 'read', 'resolved'], true) ? param('status') : '';
$counts = ['' => 0, 'new' => 0, 'read' => 0, 'resolved' => 0];
foreach (rows('SELECT status, COUNT(*) AS c FROM contact_messages GROUP BY status') as $r) {
    $counts[$r['status']] = (int) $r['c'];
    $counts[''] += (int) $r['c'];
}
$total = $counts[$filter];
$p = paginate($total, 10);
$list = rows('SELECT * FROM contact_messages' . ($filter ? ' WHERE status = ?' : '') . " ORDER BY status = 'new' DESC, created_at DESC LIMIT {$p['per']} OFFSET {$p['offset']}", $filter ? [$filter] : []);
$badge = ['new' => 'coral', 'read' => 'blue', 'resolved' => 'green'];

$pageTitle = 'Messages';
$activeNav = 'messages';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>Messages</span></div>
        <h1>Website inquiries</h1>
        <p>Messages sent through the contact form on the homepage.</p>
    </div>
</div>
<div class="card">
    <div class="status-tabs"><div class="tabs">
        <?php foreach (['' => 'All', 'new' => 'New', 'read' => 'Read', 'resolved' => 'Resolved'] as $k => $label): ?>
            <a class="tab<?= $filter === $k ? ' active' : '' ?>" href="?<?= $k ? 'status=' . $k : '' ?>"><?= $label ?> <span class="count"><?= $counts[$k] ?></span></a>
        <?php endforeach; ?>
    </div></div>
    <div class="mt-2">
        <?php foreach ($list as $m): ?>
            <article class="message-item<?= $m['status'] === 'new' ? ' is-new' : '' ?>">
                <?= avatar($m['name']) ?>
                <div class="li-main">
                    <div class="message-head"><strong><?= e($m['name']) ?></strong><span class="badge badge-<?= $badge[$m['status']] ?>"><?= e(ucfirst($m['status'])) ?></span><span class="badge badge-outline"><?= e($m['subject']) ?></span><span class="muted text-xs"><?= e(time_ago($m['created_at'])) ?></span></div>
                    <div class="text-sm muted"><?= e($m['email']) ?><?= $m['phone'] ? ' · ' . e($m['phone']) : '' ?></div>
                    <p><?= nl2br(e($m['message'])) ?></p>
                    <div class="flex gap-1 wrap">
                        <a class="btn btn-primary btn-xs" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . $m['subject']) ?>"><?= icon('reply') ?>Reply by email</a>
                        <?php foreach (['read' => ['mail-open', 'Mark read'], 'resolved' => ['circle-check', 'Resolve'], 'new' => ['mail', 'Mark unread']] as $st => [$ic, $label]): if ($st === $m['status']) continue; ?>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-outline btn-xs" name="status" value="<?= $st ?>" type="submit"><?= icon($ic) ?><?= $label ?></button></form>
                        <?php endforeach; ?>
                        <form method="post" data-confirm="Delete this message from <?= e($m['name']) ?>?" data-confirm-ok="Delete"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-ghost btn-xs" type="submit"><?= icon('trash-2') ?>Delete</button></form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if (!$list): ?><?= empty_state('inbox', 'Inbox zero!', 'New messages from the website contact form will appear here.') ?><?php endif; ?>
    </div>
    <?= pagination_links($p) ?>
</div>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
