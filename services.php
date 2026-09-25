<?php
require __DIR__ . '/includes/bootstrap.php';

$services = rows('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order, name');
$counts = [];
foreach ($services as $s) {
    $counts[$s['category']] = ($counts[$s['category']] ?? 0) + 1;
}
$pageTitle = 'Services & Prices';
$pageDescription = 'Veterinary services and starting prices at ' . CLINIC_NAME . ': check-ups, vaccinations, laboratory, X-ray, surgery, dental and grooming.';
$activeNav = 'services';
include __DIR__ . '/includes/layout/site_header.php';
?>
<section class="page-hero">
    <img src="<?= e(url('assets/img/services-banner.jpg')) ?>" alt="">
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb"><a href="<?= e(url()) ?>">Home</a><?= icon('chevron-right') ?><span>Services &amp; prices</span></nav>
        <h1>Services &amp; prices</h1>
        <p>Everything your pet needs under one roof — with honest, published starting prices. Book any service online in under a minute.</p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="svc-toolbar" data-service-filter="#all-services" data-empty="#no-services">
            <div class="filter-chips">
                <button class="chip active" type="button" data-filter="all"><?= icon('layout-grid') ?>All <span class="muted">(<?= count($services) ?>)</span></button>
                <?php foreach ($counts as $cat => $n): ?>
                    <button class="chip" type="button" data-filter="<?= e(strtolower($cat)) ?>"><?= e($cat) ?> <span class="muted">(<?= $n ?>)</span></button>
                <?php endforeach; ?>
            </div>
            <div class="input-group svc-search">
                <?= icon('search') ?>
                <input class="input" type="search" placeholder="Search services…" aria-label="Search services" data-service-search>
            </div>
        </div>
        <div class="svc-list" id="all-services">
            <?php foreach ($services as $i => $s): ?><?= service_card($s, $i % 3) ?><?php endforeach; ?>
        </div>
        <div class="no-results" id="no-services" hidden><?= icon('search') ?> No services match your search. Try another word or <a class="fw-700" href="<?= e(url()) ?>#contact">ask us</a>.</div>

        <div class="pricing-note" data-reveal>
            <?= icon('info') ?>
            <div><strong>About our prices</strong><br>Prices shown are starting rates for a typical small-to-medium pet. Final costs can vary with your pet's size, medicines used and additional tests — we always explain the costs before any treatment.</div>
        </div>

        <div class="feature-strip">
            <div class="fs-media" data-reveal><img src="<?= e(url('assets/img/grooming.jpg')) ?>" alt="A groomer trimming a Yorkshire terrier" loading="lazy"></div>
            <div data-reveal style="--d:.1s">
                <span class="kicker"><?= icon('scissors') ?>Spa &amp; grooming</span>
                <h2 class="section-title">Spa days that leave tails wagging</h2>
                <p class="muted">Our groomers work right next to our vets, so any skin, ear or dental concern spotted during a groom gets checked the same day.</p>
                <ul class="check-list">
                    <li><span class="ck"><?= icon('check') ?></span><div><strong>Gentle, low-stress handling</strong><span>Breaks, treats and calm music for nervous pets.</span></div></li>
                    <li><span class="ck"><?= icon('check') ?></span><div><strong>Vet-approved products</strong><span>Hypoallergenic shampoos for sensitive skin.</span></div></li>
                    <li><span class="ck"><?= icon('check') ?></span><div><strong>Health check included</strong><span>Skin, coat, ears and nails checked every visit.</span></div></li>
                </ul>
                <a class="btn btn-primary btn-lg" href="<?= e(url('owner/book.php?service=13')) ?>"><?= icon('calendar-plus') ?>Book a grooming session</a>
            </div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/includes/layout/site_footer.php'; ?>
