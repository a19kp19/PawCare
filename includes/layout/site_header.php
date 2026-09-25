<?php
/**
 * Public website header. Optional before including: $pageTitle, $pageDescription, $activeNav, $bodyClass.
 */
$siteUser = current_user();
$clinicStatus = clinic_status();
$activeNav = $activeNav ?? '';
$titleText = !empty($pageTitle) ? $pageTitle . ' · ' . CLINIC_NAME : CLINIC_NAME . ' — ' . CLINIC_TAGLINE;
$navLinks = [
    'home'     => ['Home', url()],
    'services' => ['Services', url('services.php')],
    'team'     => ['Our Vets', url() . '#team'],
    'tools'    => ['Pet Tools', url() . '#tools'],
    'faq'      => ['FAQ', url() . '#faq'],
    'contact'  => ['Contact', url() . '#contact'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titleText) ?></title>
<meta name="description" content="<?= e($pageDescription ?? CLINIC_NAME . ' offers check-ups, vaccinations, surgery, dental care and grooming, with online booking and digital pet health records.') ?>">
<meta name="theme-color" content="#0d8a7d">
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="<?= e(url('assets/fonts/plus-jakarta-sans-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
</head>
<body class="site <?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>
<div class="topbar-strip">
    <div class="container">
        <div class="strip-group">
            <span class="strip-item"><span class="status-dot<?= $clinicStatus['open'] ? '' : ' closed' ?>"></span><strong><?= e($clinicStatus['label']) ?></strong><?= e($clinicStatus['detail']) ?></span>
            <span class="strip-item strip-hide"><?= icon('map-pin') ?><?= e(CLINIC_ADDRESS) ?></span>
        </div>
        <div class="strip-group">
            <a class="strip-item strip-hide" href="mailto:<?= e(CLINIC_EMAIL) ?>"><?= icon('mail') ?><?= e(CLINIC_EMAIL) ?></a>
            <a class="strip-item" href="tel:<?= e(preg_replace('/\D+/', '', CLINIC_EMERGENCY)) ?>"><?= icon('siren') ?>24/7 Emergency <strong><?= e(CLINIC_EMERGENCY) ?></strong></a>
        </div>
    </div>
</div>
<header class="site-header" data-header>
    <div class="container nav">
        <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e(CLINIC_NAME) ?> home"><?= logo_mark() ?><span><?= e(CLINIC_SHORT_NAME) ?><small>Veterinary Clinic</small></span></a>
        <nav class="nav-links" data-nav aria-label="Main">
            <?php foreach ($navLinks as $key => [$label, $href]): ?>
                <a href="<?= e($href) ?>"<?= $activeNav === $key ? ' class="active" aria-current="page"' : '' ?>><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="nav-actions">
            <?php if ($siteUser): ?>
                <a class="btn btn-primary" href="<?= e(url(home_for($siteUser))) ?>"><?= icon('layout-dashboard') ?>My dashboard</a>
            <?php else: ?>
                <a class="btn btn-ghost btn-login" href="<?= e(url('login.php')) ?>"><?= icon('log-in') ?>Log in</a>
                <a class="btn btn-primary" href="<?= e(url('owner/book.php')) ?>"><?= icon('calendar-plus') ?>Book a visit</a>
            <?php endif; ?>
            <button class="nav-toggle" type="button" data-nav-toggle aria-label="Toggle menu" aria-expanded="false"><span class="i-menu"><?= icon('menu') ?></span><span class="i-close"><?= icon('x') ?></span></button>
        </div>
    </div>
</header>
<?= render_toasts() ?>
<main id="main">
