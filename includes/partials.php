<?php
/**
 * Reusable UI fragments shared by several pages.
 */

const SERVICE_CATEGORIES = ['Wellness', 'Medical', 'Diagnostics', 'Surgery', 'Dental', 'Grooming'];

const SERVICE_ICON_CHOICES = ['stethoscope', 'syringe', 'shield-check', 'microchip', 'heart-pulse', 'sparkles', 'flask-conical', 'scan-line', 'activity', 'hospital', 'bandage', 'smile', 'scissors', 'bath', 'pill', 'bone', 'thermometer', 'droplets', 'dna', 'paw-print'];

function category_class(string $category): string
{
    return 'cat-' . strtolower(preg_replace('/[^a-z]/i', '', $category));
}

/** Chart.js container; the config is read by assets/js/app.js. */
function chart_box(array $config, string $class = ''): string
{
    return '<div class="chart-box ' . e($class) . '" data-chart><canvas role="img" aria-label="' . e($config['aria'] ?? 'Chart') . '"></canvas>'
        . '<script type="application/json">' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . '</script></div>';
}

/** Service card used on the homepage and the services page. */
function service_card(array $s, int $delay = 0): string
{
    $cat = category_class($s['category']);
    return '<article class="service-card ' . $cat . '" data-category="' . e(strtolower($s['category'])) . '" data-reveal style="--d:' . ($delay * 0.06) . 's">'
        . '<div class="svc-icon">' . icon($s['icon']) . '</div>'
        . '<div class="svc-cat">' . e($s['category']) . '</div>'
        . '<h3>' . e($s['name']) . '</h3>'
        . '<p>' . e($s['description']) . '</p>'
        . '<div class="svc-meta"><span class="svc-price"><small>from</small>' . money($s['price']) . '</span>'
        . '<span class="svc-duration">' . icon('clock') . (int) $s['duration_minutes'] . ' min</span></div>'
        . '<a class="svc-book" href="' . e(url('owner/book.php?service=' . $s['id'])) . '">Book this service ' . icon('arrow-right') . '</a>'
        . '</article>';
}

function sex_icon(string $sex): string
{
    if ($sex === 'Male') {
        return '<span class="sex-male" title="Male">' . icon('mars') . '</span>';
    }
    if ($sex === 'Female') {
        return '<span class="sex-female" title="Female">' . icon('venus') . '</span>';
    }
    return '';
}

/** Pet card for grids (owner "My pets"). */
function pet_card(array $pet, bool $staff = false): string
{
    $status = pet_health_status((int) $pet['id']);
    $profile = $staff ? 'admin/patient.php?id=' . $pet['id'] : 'owner/pet.php?id=' . $pet['id'];
    $book = $staff ? 'admin/appointment-new.php?pet=' . $pet['id'] : 'owner/book.php?pet=' . $pet['id'];
    $facts = '<span class="fact">' . icon('cake') . e(pet_age($pet['birthdate'])) . '</span>';
    if ($pet['weight_kg'] !== null) {
        $facts .= '<span class="fact">' . icon('weight') . e(rtrim(rtrim(number_format((float) $pet['weight_kg'], 2), '0'), '.')) . ' kg</span>';
    }
    $facts .= '<span class="fact">' . icon(species_icon($pet['species'])) . e($pet['species']) . '</span>';
    return '<article class="pet-card">'
        . '<a class="pet-card-media" href="' . e(url($profile)) . '">' . pet_photo($pet) . pet_status_chip($status) . '</a>'
        . '<div class="pet-card-body">'
        . '<h3>' . e($pet['name']) . ' ' . sex_icon($pet['sex']) . '</h3>'
        . '<div class="pet-breed">' . e($pet['breed'] ?: $pet['species']) . '</div>'
        . '<div class="pet-facts">' . $facts . '</div>'
        . '<div class="pet-card-actions"><a class="btn btn-outline btn-sm" href="' . e(url($profile)) . '">' . icon('file-heart') . 'Health record</a>'
        . '<a class="btn btn-soft btn-sm" href="' . e(url($book)) . '">' . icon('calendar-plus') . 'Book</a></div>'
        . '</div></article>';
}

function date_block(string $date, string $extra = ''): string
{
    $ts = strtotime($date);
    return '<div class="date-block ' . e($extra) . '"><small>' . date('M', $ts) . '</small><strong>' . date('j', $ts) . '</strong><small>' . date('D', $ts) . '</small></div>';
}

/** Short text like "Mon, Sep 28 · 9:30 AM". */
function appt_when(array $a): string
{
    return fmt_date($a['appointment_date'], 'D, M j') . ' · ' . fmt_time($a['start_time']);
}

function kpi_card(string $label, string $value, string $iconName, string $tone, string $foot = '', ?string $href = null, string $extra = ''): string
{
    $tag = $href ? 'a' : 'div';
    return '<' . $tag . ' class="kpi tone-' . e($tone) . '"' . ($href ? ' href="' . e(url($href)) . '"' : '') . ' data-reveal>'
        . '<div class="kpi-top"><span class="kpi-label">' . e($label) . '</span><span class="kpi-icon">' . icon($iconName) . '</span></div>'
        . '<div class="kpi-value">' . $value . '</div>'
        . ($foot ? '<div class="kpi-foot">' . $foot . '</div>' : '')
        . $extra
        . '<svg class="kpi-bg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . ICONS['paw-print'] . '</svg>'
        . '</' . $tag . '>';
}
