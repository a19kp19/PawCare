<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_owner();
$pets = rows('SELECT * FROM pets WHERE owner_id = ? ORDER BY created_at', [$user['id']]);

$pageTitle = 'My Pets';
$activeNav = 'pets';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('owner/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>My pets</span></div>
        <h1>My pets</h1>
        <p>Profiles, vaccine status and health records for every furry (or feathery) family member.</p>
    </div>
    <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('owner/pet-form.php')) ?>"><?= icon('plus') ?>Add a pet</a></div>
</div>
<div class="pets-grid">
    <?php foreach ($pets as $p): ?><?= pet_card($p) ?><?php endforeach; ?>
    <a class="add-card" href="<?= e(url('owner/pet-form.php')) ?>">
        <span class="add-icon"><?= icon('plus') ?></span>
        <strong>Add a new pet</strong>
        <span class="text-sm">Dogs, cats, rabbits, birds and more</span>
    </a>
</div>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
