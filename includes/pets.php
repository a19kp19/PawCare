<?php
/**
 * Pet profile helpers: species, photos, ages, vaccine status and uploads.
 */

const SPECIES = [
    'Dog'     => 'dog',
    'Cat'     => 'cat',
    'Rabbit'  => 'rabbit',
    'Bird'    => 'bird',
    'Hamster' => 'squirrel',
    'Turtle'  => 'turtle',
    'Other'   => 'paw-print',
];

const BREEDS = [
    'Dog'     => ['Aspin (Asong Pinoy)', 'Shih Tzu', 'Pomeranian', 'Golden Retriever', 'Labrador Retriever', 'Beagle', 'Chihuahua', 'Dachshund', 'Siberian Husky', 'Poodle', 'Pug', 'French Bulldog', 'German Shepherd', 'Corgi', 'Maltese', 'Mixed breed'],
    'Cat'     => ['Puspin (Pusang Pinoy)', 'Persian', 'Siamese', 'British Shorthair', 'Maine Coon', 'Ragdoll', 'Bengal', 'Scottish Fold', 'Domestic Shorthair', 'Mixed breed'],
    'Rabbit'  => ['Holland Lop', 'Netherland Dwarf', 'Lionhead', 'Mini Rex', 'Mixed breed'],
    'Bird'    => ['Lovebird', 'Budgerigar', 'Cockatiel', 'African Grey', 'Amazon Parrot', 'Conure'],
    'Hamster' => ['Syrian', 'Winter White Dwarf', 'Roborovski', 'Campbell Dwarf'],
    'Turtle'  => ['Red-eared Slider', 'Box Turtle', 'Musk Turtle'],
    'Other'   => [],
];

const VACCINES = [
    'Dog'    => ['5-in-1 (DHPPiL)', 'Anti-Rabies', 'Kennel Cough (Bordetella)', 'Canine Coronavirus', 'Leptospirosis'],
    'Cat'    => ['4-in-1 (FVRCP + Chlamydia)', 'Anti-Rabies', 'Feline Leukemia (FeLV)'],
    'Rabbit' => ['RHDV1/RHDV2', 'Myxomatosis'],
];

function species_icon(string $species): string
{
    return SPECIES[$species] ?? 'paw-print';
}

function pet_photo(array $pet, string $class = ''): string
{
    $name = $pet['pet_name'] ?? ($pet['name'] ?? 'Pet');
    $photo = $pet['pet_photo'] ?? ($pet['photo'] ?? null);
    $species = $pet['species'] ?? 'Other';
    if ($photo && is_file(__DIR__ . '/../' . $photo)) {
        return '<img class="pet-photo ' . e($class) . '" src="' . e(url($photo)) . '" alt="' . e($name) . '" loading="lazy">';
    }
    return '<span class="pet-photo pet-fallback species-' . e(strtolower($species)) . ' ' . e($class) . '" role="img" aria-label="' . e($name) . '">'
        . icon(species_icon($species)) . '</span>';
}

function vet_photo(array $vet, string $class = ''): string
{
    $name = $vet['vet_name'] ?? ($vet['name'] ?? 'Veterinarian');
    $photo = $vet['vet_photo'] ?? ($vet['photo'] ?? null);
    if ($photo && is_file(__DIR__ . '/../' . $photo)) {
        return '<img class="vet-photo ' . e($class) . '" src="' . e(url($photo)) . '" alt="' . e($name) . '" loading="lazy">';
    }
    return avatar(preg_replace('/^Dr\.?\s+/i', '', $name), 'vet-photo ' . $class);
}

function pet_age(?string $birthdate): string
{
    if (!$birthdate) {
        return 'Age unknown';
    }
    $b = new DateTime($birthdate);
    $now = new DateTime('today');
    if ($b > $now) {
        return 'Newborn';
    }
    $d = $b->diff($now);
    if ($d->y >= 1) {
        return $d->y . ' yr' . ($d->y > 1 ? 's' : '') . ($d->m ? ' ' . $d->m . ' mo' : '');
    }
    if ($d->m >= 1) {
        return $d->m . ' month' . ($d->m > 1 ? 's' : '');
    }
    return max(1, (int) floor($d->days / 7)) . ' weeks';
}

function vaccine_status(?string $nextDue): array
{
    if (!$nextDue) {
        return ['key' => 'done', 'label' => 'No booster needed', 'color' => 'gray', 'days' => null];
    }
    $days = (int) round((strtotime($nextDue) - strtotime(date('Y-m-d'))) / 86400);
    if ($days < 0) {
        return ['key' => 'overdue', 'label' => 'Overdue by ' . plural(abs($days), 'day'), 'color' => 'red', 'days' => $days];
    }
    if ($days <= REMINDER_WINDOW_DAYS) {
        return ['key' => 'due', 'label' => $days === 0 ? 'Due today' : 'Due in ' . plural($days, 'day'), 'color' => 'amber', 'days' => $days];
    }
    return ['key' => 'ok', 'label' => 'Up to date', 'color' => 'green', 'days' => $days];
}

/** SQL returning only the most recent dose of each vaccine per pet. */
function latest_vaccines_sql(): string
{
    return "SELECT v.* FROM vaccinations v
            JOIN (SELECT pet_id, vaccine_name, MAX(date_given) AS last_given FROM vaccinations GROUP BY pet_id, vaccine_name) lv
              ON lv.pet_id = v.pet_id AND lv.vaccine_name = v.vaccine_name AND lv.last_given = v.date_given";
}

function pet_latest_vaccines(int $petId): array
{
    return rows('SELECT * FROM (' . latest_vaccines_sql() . ') x WHERE x.pet_id = ? ORDER BY x.next_due_date IS NULL, x.next_due_date', [$petId]);
}

/** Worst vaccine status for a pet (overdue > due soon > up to date). */
function pet_health_status(int $petId): array
{
    $rank = ['overdue' => 3, 'due' => 2, 'ok' => 1, 'done' => 0];
    $worst = null;
    foreach (pet_latest_vaccines($petId) as $v) {
        $s = vaccine_status($v['next_due_date']) + ['vaccine' => $v['vaccine_name'], 'due' => $v['next_due_date']];
        if (!$worst || $rank[$s['key']] > $rank[$worst['key']]) {
            $worst = $s;
        }
    }
    return $worst ?: ['key' => 'none', 'label' => 'No vaccine records', 'color' => 'gray', 'days' => null];
}

function pet_status_chip(array $status): string
{
    $labels = ['overdue' => 'Vaccine overdue', 'due' => 'Vaccine due soon', 'ok' => 'Vaccines up to date', 'done' => 'Vaccines complete', 'none' => 'No vaccine records'];
    $icons = ['overdue' => 'triangle-alert', 'due' => 'bell-ring', 'ok' => 'shield-check', 'done' => 'shield-check', 'none' => 'shield-plus'];
    return '<span class="badge badge-' . e($status['color']) . '">' . icon($icons[$status['key']]) . e($labels[$status['key']]) . '</span>';
}

function pet_form_data(): array
{
    return [
        'name'        => input('name'),
        'species'     => input('species'),
        'breed'       => input('breed'),
        'sex'         => input('sex', 'Unknown'),
        'birthdate'   => input('birthdate'),
        'weight_kg'   => input('weight_kg'),
        'color'       => input('color'),
        'is_neutered' => isset($_POST['is_neutered']) ? 1 : 0,
        'allergies'   => input('allergies'),
        'notes'       => input('notes'),
    ];
}

function validate_pet(array $d): array
{
    $errors = [];
    if ($d['name'] === '' || strlen($d['name']) > 60) {
        $errors['name'] = "Please enter your pet's name (up to 60 characters).";
    }
    if (!array_key_exists($d['species'], SPECIES)) {
        $errors['species'] = 'Please choose a species.';
    }
    if (!in_array($d['sex'], ['Male', 'Female', 'Unknown'], true)) {
        $errors['sex'] = 'Please choose a sex.';
    }
    if ($d['birthdate'] !== '') {
        $ts = strtotime($d['birthdate']);
        if (!$ts || $d['birthdate'] > date('Y-m-d') || $ts < strtotime('-40 years')) {
            $errors['birthdate'] = 'Please enter a valid birth date (not in the future).';
        }
    }
    if ($d['weight_kg'] !== '' && (!is_numeric($d['weight_kg']) || $d['weight_kg'] <= 0 || $d['weight_kg'] > 200)) {
        $errors['weight_kg'] = 'Weight must be between 0.01 and 200 kg.';
    }
    if (strlen($d['breed']) > 80 || strlen($d['color']) > 60 || strlen($d['allergies']) > 255) {
        $errors['breed'] = 'One of the fields is too long.';
    }
    return $errors;
}

/** Convert empty optional fields to NULL before saving. */
function normalize_pet(array $d): array
{
    foreach (['breed', 'birthdate', 'weight_kg', 'color', 'allergies', 'notes'] as $k) {
        if ($d[$k] === '') {
            $d[$k] = null;
        }
    }
    return $d;
}

/**
 * Validate and store an uploaded image. Returns ['path' => ?string, 'error' => ?string].
 * Only real images (checked with getimagesize) with random file names are accepted.
 */
function handle_photo_upload(string $field, string $folder = 'pets'): array
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => null, 'error' => null];
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        $tooBig = in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        return ['path' => null, 'error' => $tooBig ? 'That photo is too large.' : 'Upload failed. Please try again.'];
    }
    if ($f['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
        return ['path' => null, 'error' => 'Photos must be smaller than ' . MAX_UPLOAD_MB . ' MB.'];
    }
    $info = @getimagesize($f['tmp_name']);
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!$info || !isset($types[$info['mime']])) {
        return ['path' => null, 'error' => 'Please upload a JPG, PNG, WEBP or GIF image.'];
    }
    $dir = __DIR__ . '/../uploads/' . $folder;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return ['path' => null, 'error' => 'The uploads folder is missing or not writable.'];
    }
    $name = bin2hex(random_bytes(8)) . '.' . $types[$info['mime']];
    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
        return ['path' => null, 'error' => 'Could not save the photo. Check that the uploads folder is writable.'];
    }
    return ['path' => 'uploads/' . $folder . '/' . $name, 'error' => null];
}

function delete_upload(?string $path): void
{
    if ($path && strpos($path, 'uploads/') === 0) {
        $file = __DIR__ . '/../' . $path;
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function pet_records(int $petId): array
{
    return rows(
        'SELECT m.*, v.name AS vet_name, s.name AS service_name
         FROM medical_records m
         LEFT JOIN vets v ON v.id = m.vet_id
         LEFT JOIN appointments a ON a.id = m.appointment_id
         LEFT JOIN services s ON s.id = a.service_id
         WHERE m.pet_id = ? ORDER BY m.visit_date DESC, m.id DESC',
        [$petId]
    );
}

function pet_vaccinations(int $petId): array
{
    return rows(
        'SELECT x.*, v.name AS vet_name FROM vaccinations x LEFT JOIN vets v ON v.id = x.vet_id
         WHERE x.pet_id = ? ORDER BY x.date_given DESC, x.id DESC',
        [$petId]
    );
}
