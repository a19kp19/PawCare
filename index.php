<?php
require __DIR__ . '/includes/bootstrap.php';

// ---------------------------------------------------------------- contact form
$contactErrors = [];
if (is_post() && input('form') === 'contact') {
    verify_csrf();
    $c = [
        'name'    => clip(input('name'), 120),
        'email'   => clip(strtolower(input('email')), 120),
        'phone'   => clip(input('phone'), 30),
        'subject' => clip(input('subject'), 150),
        'message' => clip(input('message'), 3000),
    ];
    if (input('website') !== '') {                 // honeypot filled in → silently ignore bots
        redirect('index.php#contact');
    }
    if ($c['name'] === '') $contactErrors['name'] = 'Please tell us your name.';
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL)) $contactErrors['email'] = 'Please enter a valid email address.';
    if ($c['subject'] === '') $contactErrors['subject'] = 'Please choose a topic.';
    if (strlen($c['message']) < 10) $contactErrors['message'] = 'Please write a short message (at least 10 characters).';
    if (!$contactErrors && time() - (int) ($_SESSION['last_contact'] ?? 0) < 30) {
        $contactErrors['message'] = 'You just sent a message. Please wait a few seconds before sending another.';
    }
    if (!$contactErrors) {
        insert('contact_messages', array_merge($c, ['phone' => $c['phone'] !== '' ? $c['phone'] : null]));
        notify_staff('message', 'New website inquiry', $c['name'] . ': ' . $c['subject'], 'admin/messages.php');
        $_SESSION['last_contact'] = time();
        flash('success', 'Thanks, ' . $c['name'] . '! Your message was sent — we usually reply within the day.');
        redirect('index.php#contact');
    }
}

// ---------------------------------------------------------------- page data
$services = rows('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order, name');
$categories = array_values(array_unique(array_column($services, 'category')));
$vets = rows('SELECT * FROM vets WHERE is_active = 1 ORDER BY id');
$onDuty = vets_for_date(date('Y-m-d'));
$checkup = $services[0] ?? null;
$nextSlot = $checkup ? next_available_slot((int) $checkup['duration_minutes']) : null;
$years = (int) date('Y') - CLINIC_SINCE;
$todayIso = (int) date('N');
$petAvatars = ['coco', 'muning', 'kuma', 'mango'];
$testimonials = [
    ['coco', 'Maria Santos', 'Coco the Shih Tzu', 'Booking online took less than a minute and I got a reminder the day before. Dr. Andrea was so gentle that Coco did not even notice her booster shot!'],
    ['muning', 'Juan Dela Cruz', 'Muning the Puspin', 'Muning hates car rides, so the team makes sure we are seen right away. Having her records and vaccine card on my phone makes everything easier.'],
    ['kuma', 'Angela Garcia', 'Kuma the Pomeranian', 'Kuma\'s dental cleaning went perfectly. They updated me the whole time, and the price matched exactly what was posted on the website.'],
    ['kiwi', 'Carlo Aquino', 'Kiwi the Amazon Parrot', 'Finding a vet for a parrot is not easy. Dr. Kristine knew exactly what Kiwi needed — now he greets everyone at the clinic with "Hello doc!"'],
];
$faqs = [
    ['Do I need an account to book an appointment?', 'Yes — creating a free account takes about a minute. It lets you add your pets, book visits, receive reminders and view health records anytime.'],
    ['Do you accept walk-ins?', 'Walk-ins are welcome during clinic hours, but booked appointments are prioritised. For emergencies, call our 24/7 hotline at ' . CLINIC_EMERGENCY . ' right away.'],
    ['How will I know my booking is confirmed?', 'Your request shows as "Pending" as soon as you book. When our staff confirms it you will get a notification in your dashboard, plus a reminder before the visit.'],
    ['Can I cancel or reschedule?', 'You can cancel online up to ' . (CANCEL_NOTICE_MINUTES / 60) . ' hours before your visit from the Appointments page, then book a new time. For last-minute changes, just give us a call.'],
    ['What should I bring on our first visit?', 'Bring any previous vaccine cards or medical records and a list of current medicines. Please keep dogs on a leash and cats in a carrier.'],
    ['What payment methods do you accept?', 'We accept cash, GCash, Maya and major debit/credit cards at the clinic. Prices shown online are starting rates and may vary with your pet\'s size and needs.'],
];

$activeNav = 'home';
include __DIR__ . '/includes/layout/site_header.php';
?>

<!-- ============================== HERO ============================== -->
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow" data-reveal><span class="pill"><?= icon('sparkles') ?>New</span>Online booking &amp; digital health records</span>
            <h1 data-reveal style="--d:.08s">Gentle, expert care for <span class="hl">every paw<svg class="scribble" viewBox="0 0 300 20" preserveAspectRatio="none" aria-hidden="true"><path d="M4 14 C 60 3, 130 3, 185 10 S 268 17, 296 6" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round"/></svg></span></h1>
            <p class="hero-lead" data-reveal style="--d:.16s">From first puppy shots to senior check-ups, our veterinarians treat your pets like family — and with online booking, reminders and health records in your pocket, there's no more waiting in line.</p>
            <div class="hero-ctas" data-reveal style="--d:.24s">
                <a class="btn btn-primary btn-lg" href="<?= e(url('owner/book.php')) ?>"><?= icon('calendar-plus') ?>Book an appointment</a>
                <a class="btn btn-outline btn-lg" href="<?= e(url('services.php')) ?>">Explore services <?= icon('arrow-right') ?></a>
                <span class="hand-note" aria-hidden="true"><svg viewBox="0 0 50 40" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M46 6 C 30 4, 12 12, 8 32"/><path d="M3 24 L 8 33 L 16 27"/></svg>Book in under a minute!</span>
            </div>
            <div class="hero-proof" data-reveal style="--d:.32s">
                <div class="avatar-stack">
                    <?php foreach ($petAvatars as $p): ?><img src="<?= e(url("assets/img/pets/$p.jpg")) ?>" alt="" loading="lazy"><?php endforeach; ?>
                </div>
                <div class="proof-text">
                    <strong>4.9 <span class="stars"><?= str_repeat(icon('star'), 5) ?></span></strong>
                    <span>Loved by 1,200+ pet parents in the community</span>
                </div>
            </div>
        </div>

        <div class="hero-visual" data-reveal style="--d:.1s">
            <div class="hero-ring"></div>
            <div class="hero-blob"></div>
            <?= str_replace('class="icon', 'class="paw p1 icon', icon('paw-print')) ?>
            <?= str_replace('class="icon', 'class="paw p2 icon', icon('paw-print')) ?>
            <?= str_replace('class="icon', 'class="paw p3 icon', icon('paw-print')) ?>
            <div class="hero-photo"><img src="<?= e(url('assets/img/hero-dog.jpg')) ?>" alt="A happy golden retriever looking up" fetchpriority="high"></div>
            <div class="float-card fc-1">
                <span class="fc-icon fc-teal"><?= icon('calendar-check') ?></span>
                <div>
                    <span>Next available check-up</span>
                    <strong><?= $nextSlot ? e(relative_day($nextSlot['date']) === 'Today' || relative_day($nextSlot['date']) === 'Tomorrow' ? relative_day($nextSlot['date']) : fmt_date($nextSlot['date'], 'D, M j')) . ' · ' . e($nextSlot['label']) : 'Call us to book' ?></strong>
                </div>
            </div>
            <div class="float-card fc-2">
                <span class="fc-icon fc-coral"><?= icon('syringe') ?></span>
                <div><strong>Booster reminder sent</strong><span>We track every vaccine for you</span></div>
            </div>
            <div class="float-card fc-3">
                <?php if ($onDuty): ?>
                    <?= vet_photo($onDuty[0]) ?>
                    <div><strong><?= e(preg_replace('/^Dr\.\s*/', 'Dr. ', $onDuty[0]['name'])) ?><?= count($onDuty) > 1 ? ' +' . (count($onDuty) - 1) : '' ?></strong><span>On duty today</span></div>
                <?php else: ?>
                    <span class="fc-icon fc-sun"><?= icon('siren') ?></span>
                    <div><strong>Emergency line open</strong><span>24/7 · <?= e(CLINIC_EMERGENCY) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================== TRUST STRIP ============================== -->
<section class="trust">
    <div class="container">
        <div class="trust-grid" data-reveal>
            <div class="trust-item"><span class="ti-icon"><?= icon('stethoscope') ?></span><div><strong>Licensed veterinarians</strong><span><?= count($vets) ?> vets · <?= $years ?>+ years of care</span></div></div>
            <div class="trust-item"><span class="ti-icon"><?= icon('calendar-check') ?></span><div><strong>Book online 24/7</strong><span>Choose your vet &amp; time slot</span></div></div>
            <div class="trust-item"><span class="ti-icon"><?= icon('file-heart') ?></span><div><strong>Digital health records</strong><span>Visits, vaccines &amp; Rx in one place</span></div></div>
            <div class="trust-item"><span class="ti-icon"><?= icon('siren') ?></span><div><strong>Emergency hotline</strong><span>Call <?= e(CLINIC_EMERGENCY) ?> anytime</span></div></div>
        </div>
    </div>
</section>

<!-- ============================== SERVICES ============================== -->
<section class="section" id="services">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="kicker"><?= icon('paw-print') ?>What we do</span>
            <h2>Complete care, from nose to tail</h2>
            <p>Transparent prices, no surprises. Pick a service and book the time that suits you best.</p>
        </div>
        <div data-service-filter="#home-services" data-empty="#no-home-services">
            <div class="filter-chips" data-reveal>
                <button class="chip active" type="button" data-filter="all"><?= icon('layout-grid') ?>All services</button>
                <?php foreach ($categories as $cat): ?>
                    <button class="chip" type="button" data-filter="<?= e(strtolower($cat)) ?>"><?= e($cat) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="services-grid" id="home-services">
            <?php foreach ($services as $i => $s): ?>
                <?= service_card($s, $i % 4) ?>
            <?php endforeach; ?>
        </div>
        <p class="no-results" id="no-home-services" hidden>No services in this category yet.</p>
        <div class="center-cta" data-reveal><a class="btn btn-outline btn-lg" href="<?= e(url('services.php')) ?>">See full price list <?= icon('arrow-right') ?></a></div>
    </div>
</section>

<!-- ============================== ABOUT ============================== -->
<section class="section section-alt" id="about">
    <div class="container about-grid">
        <div class="about-media" data-reveal>
            <div class="main"><img src="<?= e(url('assets/img/about-vet.jpg')) ?>" alt="A veterinarian gently examining a Pomeranian" loading="lazy"></div>
            <div class="sub"><img src="<?= e(url('assets/img/about-cat.jpg')) ?>" alt="A fluffy white cat during a check-up" loading="lazy"></div>
            <div class="about-badge"><strong><?= $years ?>+</strong><span>years caring<br>for pets</span></div>
        </div>
        <div>
            <div class="section-head left" data-reveal>
                <span class="kicker"><?= icon('heart') ?>Why <?= e(CLINIC_SHORT_NAME) ?></span>
                <h2>A clinic that feels like a second home</h2>
                <p>We opened in <?= (int) CLINIC_SINCE ?> with one goal: make quality vet care calm, clear and convenient. Our team treats thousands of dogs, cats, rabbits and birds every year — and we still greet every patient by name.</p>
            </div>
            <ul class="check-list" data-reveal style="--d:.1s">
                <li><span class="ck"><?= icon('check') ?></span><div><strong>Fear-free handling</strong><span>Gentle techniques, treats and quiet rooms for anxious pets.</span></div></li>
                <li><span class="ck"><?= icon('check') ?></span><div><strong>Transparent pricing</strong><span>Every service and its starting price is listed online.</span></div></li>
                <li><span class="ck"><?= icon('check') ?></span><div><strong>Reminders that actually help</strong><span>We track vaccines and let you know before a booster is due.</span></div></li>
            </ul>
            <div class="stats-row" data-reveal style="--d:.2s">
                <div class="stat"><strong data-count="8500" data-suffix="+">0</strong><span>Pets treated</span></div>
                <div class="stat"><strong data-count="4.9">0</strong><span>Average rating</span></div>
                <div class="stat"><strong data-count="<?= count($vets) ?>">0</strong><span>Veterinarians</span></div>
                <div class="stat"><strong data-count="98" data-suffix="%">0</strong><span>Would recommend</span></div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== HOW IT WORKS ============================== -->
<section class="section" id="how">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="kicker"><?= icon('zap') ?>How it works</span>
            <h2>Skip the queue in three easy steps</h2>
            <p>Your pet's whole health journey, organised in one friendly place.</p>
        </div>
        <div class="steps">
            <div class="step" data-reveal><div class="step-icon"><?= icon('user-plus') ?><span class="step-num">1</span></div><h3>Create your free account</h3><p>Sign up in a minute and add your pets' profiles, photos and health details.</p></div>
            <div class="step" data-reveal style="--d:.12s"><div class="step-icon"><?= icon('calendar-plus') ?><span class="step-num">2</span></div><h3>Pick a service, vet &amp; time</h3><p>See real-time availability and grab the slot that works for you.</p></div>
            <div class="step" data-reveal style="--d:.24s"><div class="step-icon"><?= icon('bell-ring') ?><span class="step-num">3</span></div><h3>Get reminders &amp; records</h3><p>We confirm your visit, remind you before it, and save the vet's notes to your pet's record.</p></div>
        </div>
    </div>
</section>

<!-- ============================== TEAM ============================== -->
<section class="section section-alt" id="team">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="kicker"><?= icon('stethoscope') ?>Meet the team</span>
            <h2>Veterinarians who love what they do</h2>
            <p>Hover a card to learn more. Coloured days show when each vet is in the clinic.</p>
        </div>
        <div class="team-grid">
            <?php foreach ($vets as $i => $v): $days = vet_work_days($v); ?>
                <article class="team-card" data-reveal style="--d:<?= $i * 0.08 ?>s" tabindex="0">
                    <div class="team-photo">
                        <?= vet_photo($v) ?>
                        <div class="team-bio"><?= e($v['bio']) ?></div>
                    </div>
                    <div class="team-info">
                        <h3><?= e($v['name']) ?></h3>
                        <div class="team-role"><?= e($v['title']) ?></div>
                        <div class="team-spec"><?= e($v['specialty']) ?></div>
                        <div class="day-dots" aria-label="Clinic days">
                            <?php for ($d = 1; $d <= 7; $d++): ?>
                                <span class="day-dot<?= in_array($d, $days, true) ? ' on' : '' ?><?= $d === $todayIso ? ' today' : '' ?>" title="<?= e(weekday_name($d)) ?><?= in_array($d, $days, true) ? ' — in clinic' : ' — off' ?>"><?= e(substr(weekday_name($d), 0, 2)) ?></span>
                            <?php endfor; ?>
                        </div>
                        <a class="team-book" href="<?= e(url('owner/book.php?vet=' . $v['id'])) ?>">Book with <?= e(explode(' ', preg_replace('/^Dr\.\s*/', '', $v['name']))[0]) ?> <?= icon('arrow-right') ?></a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== PET TOOLS ============================== -->
<section class="section" id="tools">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="kicker"><?= icon('sparkles') ?>Free pet tools</span>
            <h2>Handy tools for curious pet parents</h2>
            <p>Play with the calculator, then check which vaccines your new puppy or kitten needs.</p>
        </div>
        <div class="tools-grid">
            <div class="tool-card" data-age-calc data-reveal>
                <h3><?= icon('cake') ?>How old is your pet in human years?</h3>
                <p class="tool-sub">Dogs and cats age faster than we do — especially in their first two years.</p>
                <div class="tool-row">
                    <div class="seg" role="group" aria-label="Species">
                        <button type="button" class="active" data-species="dog"><?= icon('dog') ?>Dog</button>
                        <button type="button" data-species="cat"><?= icon('cat') ?>Cat</button>
                    </div>
                    <div class="seg" role="group" aria-label="Dog size" data-size-row>
                        <button type="button" data-size="small">Small</button>
                        <button type="button" class="active" data-size="medium">Medium</button>
                        <button type="button" data-size="large">Large</button>
                    </div>
                </div>
                <div class="range-wrap">
                    <div class="range-label"><label for="age-range">Your pet's age</label><output data-age-out>3 years</output></div>
                    <input class="range" id="age-range" type="range" min="0.5" max="20" step="0.5" value="3" data-age-range>
                </div>
                <div class="age-result">
                    <div class="age-gauge">
                        <svg viewBox="0 0 110 110" aria-hidden="true"><circle class="track" cx="55" cy="55" r="48"/><circle class="bar" cx="55" cy="55" r="48" data-age-bar/></svg>
                        <div class="gauge-num"><div><strong data-age-num>0</strong><span>human yrs</span></div></div>
                    </div>
                    <div class="age-copy">
                        <span class="life-stage"><?= icon('paw-print') ?><span data-age-stage>Adult</span></span>
                        <h4>Care tip for this life stage</h4>
                        <p data-age-tip>Yearly check-ups and boosters keep them thriving.</p>
                    </div>
                </div>
            </div>

            <div class="tool-card dark" data-vax-guide data-reveal style="--d:.1s">
                <h3><?= icon('syringe') ?>First-year vaccine guide</h3>
                <p class="tool-sub">A typical schedule for core vaccines. Your vet will tailor it to your pet.</p>
                <div class="seg" role="group" aria-label="Pet type">
                    <button type="button" class="active" data-vax="puppy"><?= icon('dog') ?>Puppy</button>
                    <button type="button" data-vax="kitten"><?= icon('cat') ?>Kitten</button>
                </div>
                <ol class="vax-plan" data-vax-plan="puppy">
                    <li><strong>6–8 weeks</strong><span>1st 5-in-1 (DHPPiL) + deworming</span></li>
                    <li><strong>9–11 weeks</strong><span>2nd 5-in-1 + Kennel Cough</span></li>
                    <li><strong>12–14 weeks</strong><span>3rd 5-in-1 + Anti-Rabies</span></li>
                    <li><strong>15–17 weeks</strong><span>Final 5-in-1 booster</span></li>
                    <li><strong>Every year</strong><span>5-in-1 and Anti-Rabies boosters</span></li>
                </ol>
                <ol class="vax-plan" data-vax-plan="kitten" hidden>
                    <li><strong>8 weeks</strong><span>1st 4-in-1 (FVRCP + Chlamydia) + deworming</span></li>
                    <li><strong>12 weeks</strong><span>2nd 4-in-1 + optional FeLV</span></li>
                    <li><strong>16 weeks</strong><span>3rd 4-in-1 + Anti-Rabies</span></li>
                    <li><strong>Every year</strong><span>4-in-1 and Anti-Rabies boosters</span></li>
                </ol>
                <a class="btn btn-accent" href="<?= e(url('owner/book.php?service=2')) ?>"><?= icon('calendar-plus') ?>Book a vaccination</a>
            </div>
        </div>
    </div>
</section>

<!-- ============================== TESTIMONIALS ============================== -->
<section class="section section-alt" id="reviews">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="kicker"><?= icon('star') ?>Happy tails</span>
            <h2>What pet parents are saying</h2>
        </div>
        <div class="carousel" data-carousel data-reveal>
            <div class="t-track" data-track>
                <?php foreach ($testimonials as [$photo, $owner, $pet, $quote]): ?>
                    <div class="t-slide">
                        <figure class="t-card">
                            <div class="t-pet"><img src="<?= e(url("assets/img/pets/$photo.jpg")) ?>" alt="<?= e($pet) ?>" loading="lazy"><span class="t-badge"><?= icon('heart') ?></span></div>
                            <div>
                                <span class="stars"><?= str_repeat(icon('star'), 5) ?></span>
                                <blockquote class="t-quote">“<?= e($quote) ?>”</blockquote>
                                <figcaption class="t-author"><?= avatar($owner) ?><span><strong><?= e($owner) ?></strong> · <?= e($pet) ?></span></figcaption>
                            </div>
                        </figure>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="t-controls">
                <button class="t-nav" type="button" data-prev aria-label="Previous review"><?= icon('arrow-left') ?></button>
                <div class="t-dots" data-dots></div>
                <button class="t-nav" type="button" data-next aria-label="Next review"><?= icon('arrow-right') ?></button>
            </div>
        </div>
    </div>
</section>

<!-- ============================== FAQ ============================== -->
<section class="section" id="faq">
    <div class="container faq-grid">
        <div class="faq-aside" data-reveal>
            <span class="kicker"><?= icon('circle-help') ?>FAQ</span>
            <h2 class="section-title">Questions? We've got answers.</h2>
            <p class="muted">Can't find what you are looking for? Send us a message and our front desk will get back to you.</p>
            <div class="help-box">
                <span class="ti-icon"><?= icon('phone-call') ?></span>
                <div><strong>Call the clinic</strong><br><a class="muted" href="tel:<?= e(preg_replace('/\D+/', '', CLINIC_PHONE)) ?>"><?= e(CLINIC_PHONE) ?> · <?= e(CLINIC_MOBILE) ?></a></div>
            </div>
        </div>
        <div data-faq data-reveal style="--d:.1s">
            <?php foreach ($faqs as $i => [$q, $a]): ?>
                <div class="faq-item<?= $i === 0 ? ' open' : '' ?>">
                    <button class="faq-q" type="button" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>"><?= e($q) ?><span class="plus"><?= icon('plus') ?></span></button>
                    <div class="faq-a"><div><p><?= e($a) ?></p></div></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== CTA ============================== -->
<section class="section" style="padding-top:0">
    <div class="container">
        <div class="cta-band" data-reveal>
            <div class="cta-copy">
                <span class="kicker" style="color:#ffd3c6"><?= icon('heart') ?>Join the <?= e(CLINIC_SHORT_NAME) ?> family</span>
                <h2>Ready to give your pet the care they deserve?</h2>
                <p>Create a free account to book visits, track vaccines and keep every health record in your pocket.</p>
                <div class="cta-actions">
                    <a class="btn btn-white btn-lg" href="<?= e(url('register.php')) ?>"><?= icon('user-plus') ?>Create free account</a>
                    <a class="btn btn-glass btn-lg" href="<?= e(url('owner/book.php')) ?>"><?= icon('calendar-plus') ?>Book a visit</a>
                </div>
            </div>
            <div class="cta-media"><img src="<?= e(url('assets/img/cta-pet-parent.jpg')) ?>" alt="A smiling pet owner holding her puppy" loading="lazy"></div>
        </div>
    </div>
</section>

<!-- ============================== CONTACT ============================== -->
<section class="section section-alt" id="contact">
    <div class="container">
        <div class="section-head" data-reveal>
            <span class="kicker"><?= icon('mail') ?>Get in touch</span>
            <h2>We'd love to hear from you</h2>
            <p>Questions about a service, pricing or your pet's health? Send a message or drop by the clinic.</p>
        </div>
        <div class="contact-grid">
            <form class="contact-form-card" method="post" action="<?= e(url()) ?>#contact" novalidate data-reveal>
                <?= csrf_field() ?>
                <input type="hidden" name="form" value="contact">
                <div class="hp-field" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <h3>Send us a message</h3>
                <p class="muted mb-3">We usually reply within the day.</p>
                <div class="form-grid">
                    <div class="field<?= isset($contactErrors['name']) ? ' has-error' : '' ?>">
                        <label for="c-name">Your name <span class="req">*</span></label>
                        <input class="input" id="c-name" name="name" value="<?= e(old('name')) ?>" placeholder="Juan Dela Cruz" required>
                        <?php if (isset($contactErrors['name'])): ?><span class="field-error"><?= e($contactErrors['name']) ?></span><?php endif; ?>
                    </div>
                    <div class="field<?= isset($contactErrors['email']) ? ' has-error' : '' ?>">
                        <label for="c-email">Email <span class="req">*</span></label>
                        <input class="input" id="c-email" type="email" name="email" value="<?= e(old('email')) ?>" placeholder="you@example.com" required>
                        <?php if (isset($contactErrors['email'])): ?><span class="field-error"><?= e($contactErrors['email']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="c-phone">Mobile number</label>
                        <input class="input" id="c-phone" name="phone" value="<?= e(old('phone')) ?>" placeholder="0917 000 0000">
                    </div>
                    <div class="field<?= isset($contactErrors['subject']) ? ' has-error' : '' ?>">
                        <label for="c-subject">Topic <span class="req">*</span></label>
                        <select class="input" id="c-subject" name="subject" required>
                            <option value="">Choose a topic</option>
                            <?php foreach (['Appointments & schedules', 'Prices & services', 'My pet\'s health', 'Grooming', 'Feedback', 'Something else'] as $t): ?>
                                <option<?= selected(old('subject'), $t) ?>><?= e($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($contactErrors['subject'])): ?><span class="field-error"><?= e($contactErrors['subject']) ?></span><?php endif; ?>
                    </div>
                    <div class="field span-2<?= isset($contactErrors['message']) ? ' has-error' : '' ?>">
                        <label for="c-message">Message <span class="req">*</span></label>
                        <textarea class="input" id="c-message" name="message" rows="5" placeholder="How can we help you and your pet?" required><?= e(old('message')) ?></textarea>
                        <?php if (isset($contactErrors['message'])): ?><span class="field-error"><?= e($contactErrors['message']) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="form-actions" style="justify-content:flex-start"><button class="btn btn-primary btn-lg" type="submit"><?= icon('send') ?>Send message</button></div>
            </form>

            <aside class="info-card" data-reveal style="--d:.1s">
                <h3>Visit the clinic</h3>
                <div class="info-row"><span class="ir-icon"><?= icon('map-pin') ?></span><div><small>Address</small><strong><?= e(CLINIC_ADDRESS) ?></strong></div></div>
                <div class="info-row"><span class="ir-icon"><?= icon('phone') ?></span><div><small>Phone</small><a href="tel:<?= e(preg_replace('/\D+/', '', CLINIC_PHONE)) ?>"><?= e(CLINIC_PHONE) ?></a> · <a href="tel:<?= e(preg_replace('/\D+/', '', CLINIC_MOBILE)) ?>"><?= e(CLINIC_MOBILE) ?></a></div></div>
                <div class="info-row"><span class="ir-icon"><?= icon('mail') ?></span><div><small>Email</small><a href="mailto:<?= e(CLINIC_EMAIL) ?>"><?= e(CLINIC_EMAIL) ?></a></div></div>
                <table class="hours-table">
                    <?php foreach (CLINIC_HOURS as $d => $h): ?>
                        <tr class="<?= $d === $todayIso ? 'today' : '' ?>"><td><?= e(weekday_name($d)) ?></td><td><?= $h ? e(fmt_time($h[0]) . ' – ' . fmt_time($h[1])) : 'Closed (emergencies only)' ?></td></tr>
                    <?php endforeach; ?>
                </table>
                <div class="map-frame">
                    <iframe title="Map to <?= e(CLINIC_NAME) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=<?= e(urlencode(CLINIC_MAP_QUERY)) ?>&amp;z=15&amp;output=embed"></iframe>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/layout/site_footer.php'; ?>
