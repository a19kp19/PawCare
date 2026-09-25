<?php
require __DIR__ . '/../includes/bootstrap.php';
$me = require_staff();

$q = param('q');
$species = array_key_exists(param('species'), SPECIES) ? param('species') : '';
$view = param('view') === 'grid' ? 'grid' : 'table';
$where = [];
$params = [];
if ($q !== '') {
    $where[] = "(p.name LIKE ? OR p.breed LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$speciesCounts = rows('SELECT species, COUNT(*) AS c FROM pets GROUP BY species ORDER BY c DESC');
if ($species) {
    $where[] = 'p.species = ?';
    $params[] = $species;
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$total = (int) val('SELECT COUNT(*) FROM pets p JOIN users u ON u.id = p.owner_id' . $whereSql, $params);
$p = paginate($total, $view === 'grid' ? 12 : 15);
$list = rows(
    "SELECT p.*, u.first_name AS owner_first, u.last_name AS owner_last, u.phone AS owner_phone,
            (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.pet_id = p.id AND a.status = 'completed') AS last_visit,
            (SELECT COUNT(*) FROM appointments a WHERE a.pet_id = p.id AND a.status = 'completed') AS visits
     FROM pets p JOIN users u ON u.id = p.owner_id $whereSql ORDER BY p.name LIMIT {$p['per']} OFFSET {$p['offset']}",
    $params
);

$pageTitle = 'Patients';
$activeNav = 'patients';
include __DIR__ . '/../includes/layout/app_header.php';
?>
<div class="page-head">
    <div>
        <div class="crumbs"><a href="<?= e(url('admin/')) ?>">Dashboard</a><?= icon('chevron-right') ?><span>Patients</span></div>
        <h1>Patients</h1>
        <p><?= plural((int) array_sum(array_column($speciesCounts, 'c')), 'registered pet') ?> with digital health records.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('admin/export.php?type=patients')) ?>"><?= icon('download') ?>Export CSV</a>
        <a class="btn btn-primary" href="<?= e(url('admin/patient-form.php')) ?>"><?= icon('plus') ?>Add patient</a>
    </div>
</div>
<div class="card">
    <div class="status-tabs">
        <div class="tabs">
            <a class="tab<?= $species === '' ? ' active' : '' ?>" href="<?= e(qs(['species' => null, 'page' => null])) ?: '?' ?>">All species</a>
            <?php foreach ($speciesCounts as $sc): ?>
                <a class="tab<?= $species === $sc['species'] ? ' active' : '' ?>" href="<?= e(qs(['species' => $sc['species'], 'page' => null])) ?>"><?= icon(species_icon($sc['species'])) ?><?= e($sc['species']) ?> <span class="count"><?= (int) $sc['c'] ?></span></a>
            <?php endforeach; ?>
        </div>
    </div>
    <form class="filters" method="get">
        <?php if ($species): ?><input type="hidden" name="species" value="<?= e($species) ?>"><?php endif; ?>
        <input type="hidden" name="view" value="<?= e($view) ?>">
        <div class="input-group grow"><?= icon('search') ?><input class="input" type="search" name="q" value="<?= e($q) ?>" placeholder="Search by pet name, breed or owner"></div>
        <button class="btn btn-soft btn-sm" type="submit"><?= icon('search') ?>Search</button>
        <div class="view-toggle" role="group" aria-label="View">
            <a class="<?= $view === 'table' ? 'active' : '' ?>" href="<?= e(qs(['view' => 'table', 'page' => null])) ?>" title="Table view" aria-label="Table view"><?= icon('list') ?></a>
            <a class="<?= $view === 'grid' ? 'active' : '' ?>" href="<?= e(qs(['view' => 'grid', 'page' => null])) ?>" title="Card view" aria-label="Card view"><?= icon('layout-grid') ?></a>
        </div>
    </form>
    <?php if (!$list): ?>
        <?= empty_state('paw-print', 'No patients found', 'Try another search, or register a new patient.', '<a class="btn btn-primary btn-sm" href="' . e(url('admin/patient-form.php')) . '">' . icon('plus') . 'Add patient</a>') ?>
    <?php elseif ($view === 'grid'): ?>
        <div class="card-body"><div class="pets-grid"><?php foreach ($list as $pet): ?><?= pet_card($pet, true) ?><?php endforeach; ?></div></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Patient</th><th>Owner</th><th>Age</th><th>Weight</th><th>Last visit</th><th>Vaccines</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($list as $pet): ?>
                    <tr>
                        <td><a class="cell-main" href="<?= e(url('admin/patient.php?id=' . $pet['id'])) ?>"><?= pet_photo($pet) ?><span><span class="cell-title"><?= e($pet['name']) ?> <?= sex_icon($pet['sex']) ?></span><span class="cell-sub"><?= e($pet['species']) ?> · <?= e($pet['breed'] ?: 'Breed not set') ?></span></span></a></td>
                        <td><a class="cell-title" href="<?= e(url('admin/client.php?id=' . $pet['owner_id'])) ?>"><?= e($pet['owner_first'] . ' ' . $pet['owner_last']) ?></a><span class="cell-sub"><?= e($pet['owner_phone'] ?: '—') ?></span></td>
                        <td><?= e(pet_age($pet['birthdate'])) ?></td>
                        <td class="tabular"><?= $pet['weight_kg'] !== null ? e((float) $pet['weight_kg']) . ' kg' : '—' ?></td>
                        <td><span class="cell-title"><?= e(fmt_date($pet['last_visit'])) ?></span><span class="cell-sub"><?= plural((int) $pet['visits'], 'visit') ?></span></td>
                        <td><?= pet_status_chip(pet_health_status((int) $pet['id'])) ?></td>
                        <td><div class="row-actions">
                            <a class="btn btn-soft btn-xs" href="<?= e(url('admin/appointment-new.php?pet=' . $pet['id'])) ?>"><?= icon('calendar-plus') ?>Book</a>
                            <a class="btn btn-ghost btn-icon btn-xs" href="<?= e(url('admin/patient.php?id=' . $pet['id'])) ?>" aria-label="Open health record" title="Open health record"><?= icon('file-heart') ?></a>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <?= pagination_links($p) ?>
</div>
<?php include __DIR__ . '/../includes/layout/app_footer.php'; ?>
