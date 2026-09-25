<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_owner();
$pet = row('SELECT * FROM pets WHERE id = ? AND owner_id = ?', [int_param('id'), $user['id']]);
if (!$pet) {
    abort(404, 'Pet not found');
}
$isStaff = false;
$pageTitle = $pet['name'] . "'s health record";
$activeNav = 'pets';
$useCharts = true;
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="crumbs"><a href="<?= e(url('owner/')) ?>">Dashboard</a><?= icon('chevron-right') ?><a href="<?= e(url('owner/pets.php')) ?>">My pets</a><?= icon('chevron-right') ?><span><?= e($pet['name']) ?></span></div>
<?php include __DIR__ . '/../includes/partials/pet_profile.php'; ?>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
