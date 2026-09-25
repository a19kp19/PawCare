<?php
$footerServices = rows('SELECT id, name FROM services WHERE is_active = 1 ORDER BY sort_order, name LIMIT 6');
?>
</main>
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <a class="brand" href="<?= e(url()) ?>"><?= logo_mark() ?><span><?= e(CLINIC_SHORT_NAME) ?><small>Veterinary Clinic</small></span></a>
                <p class="footer-about">Caring for dogs, cats and small pets since <?= (int) CLINIC_SINCE ?>. Book online, get reminders and keep every health record in one place.</p>
                <div class="socials">
                    <a href="<?= e(CLINIC_FACEBOOK) ?>" aria-label="Facebook"><?= icon('facebook') ?></a>
                    <a href="<?= e(CLINIC_INSTAGRAM) ?>" aria-label="Instagram"><?= icon('instagram') ?></a>
                    <a href="mailto:<?= e(CLINIC_EMAIL) ?>" aria-label="Email us"><?= icon('mail') ?></a>
                </div>
            </div>
            <div>
                <h4>Explore</h4>
                <ul class="footer-links">
                    <li><a href="<?= e(url()) ?>">Home</a></li>
                    <li><a href="<?= e(url('services.php')) ?>">Services &amp; prices</a></li>
                    <li><a href="<?= e(url()) ?>#team">Our veterinarians</a></li>
                    <li><a href="<?= e(url()) ?>#tools">Pet age calculator</a></li>
                    <li><a href="<?= e(url()) ?>#faq">FAQ</a></li>
                    <li><a href="<?= e(url('register.php')) ?>">Create an account</a></li>
                </ul>
            </div>
            <div>
                <h4>Popular services</h4>
                <ul class="footer-links">
                    <?php foreach ($footerServices as $fs): ?>
                        <li><a href="<?= e(url('owner/book.php?service=' . $fs['id'])) ?>"><?= e($fs['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h4>Visit us</h4>
                <ul class="footer-links footer-contact">
                    <li><?= icon('map-pin') ?><span><?= e(CLINIC_ADDRESS) ?></span></li>
                    <li><?= icon('phone') ?><a href="tel:<?= e(preg_replace('/\D+/', '', CLINIC_PHONE)) ?>"><?= e(CLINIC_PHONE) ?></a></li>
                    <li><?= icon('mail') ?><a href="mailto:<?= e(CLINIC_EMAIL) ?>"><?= e(CLINIC_EMAIL) ?></a></li>
                    <?php foreach (clinic_hours_summary() as $h): ?>
                        <li><?= icon('clock') ?><span><?= e($h['days']) ?>: <?= e($h['hours']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> <?= e(CLINIC_NAME) ?>. All rights reserved.</span>
            <span>Pet owner? <a href="<?= e(url('register.php')) ?>">Create a free account</a> · Clinic staff? <a href="<?= e(url('login.php')) ?>">Staff login</a></span>
        </div>
    </div>
</footer>
<button class="to-top" type="button" data-to-top aria-label="Back to top"><?= icon('arrow-up') ?></button>
<?php include __DIR__ . '/js_boot.php'; ?>
<script src="<?= e(asset('assets/js/core.js')) ?>"></script>
<script src="<?= e(asset('assets/js/site.js')) ?>"></script>
</body>
</html>
