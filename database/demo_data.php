<?php
/**
 * Demo data generator (used by setup.php).
 *
 * Creates realistic clients, pets, appointments, visit records, vaccinations,
 * notifications and inquiries *relative to today*, so the dashboards always
 * look alive no matter when the project is installed or presented.
 */

function seed_demo_data(): array
{
    mt_srand(20240917);
    $pdo = db();
    $pdo->beginTransaction();

    $today = date('Y-m-d');
    $now = time();
    $day = fn (int $offset): string => date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . $offset . ' days'));
    $openDay = function (int $offset) use ($day): string {
        // nearest open clinic day, moving away from today
        $step = $offset < 0 ? -1 : 1;
        $d = $day($offset);
        while (!clinic_hours_for($d)) {
            $offset += $step;
            $d = $day($offset);
        }
        return $d;
    };
    $rand = fn (float $min, float $max): float => $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    $pick = function (array $weights) {
        $r = mt_rand(1, array_sum($weights));
        foreach ($weights as $k => $w) {
            $r -= $w;
            if ($r <= 0) {
                return $k;
            }
        }
        return array_key_first($weights);
    };

    // ------------------------------------------------------------------ clients
    $hash = password_hash('owner123', PASSWORD_DEFAULT);
    $owners = [
        ['Maria', 'Santos', 'owner@pawcare.test', '0917 555 0142', 'Unit 5B Mabini Residences, Brgy. Kamuning, Quezon City', 420],
        ['Juan', 'Dela Cruz', 'juan.delacruz@mail.test', '0918 555 0199', '45 Kalayaan Ave., Brgy. Pinyahan, Quezon City', 610],
        ['Angela', 'Garcia', 'angela.garcia@mail.test', '0919 555 0123', '12 Maginhawa St., Brgy. Teachers Village, Quezon City', 540],
        ['Mark', 'Villanueva', 'mark.villanueva@mail.test', '0927 555 0175', '8 Scout Rallos St., Brgy. Laging Handa, Quezon City', 700],
        ['Sofia', 'Ramos', 'sofia.ramos@mail.test', '0905 555 0110', '221 Katipunan Ave., Brgy. Loyola Heights, Quezon City', 380],
        ['Carlo', 'Aquino', 'carlo.aquino@mail.test', '0916 555 0188', '3 Tomas Morato Ave., Brgy. South Triangle, Quezon City', 300],
        ['Bea', 'Navarro', 'bea.navarro@mail.test', '0998 555 0131', '77 Timog Ave., Brgy. Sacred Heart, Quezon City', 250],
        ['Luis', 'Fernandez', 'luis.fernandez@mail.test', '0917 555 0164', '19 Panay Ave., Brgy. Paligsahan, Quezon City', 160],
        ['Isabel', 'Cruz', 'isabel.cruz@mail.test', '0920 555 0107', '5 Visayas Ave., Brgy. Vasra, Quezon City', 120],
        ['Daniel', 'Tan', 'daniel.tan@mail.test', '0915 555 0152', '101 Banawe St., Brgy. Lourdes, Quezon City', 480],
        ['Patricia', 'Gomez', 'patricia.gomez@mail.test', '0926 555 0119', '14 Anonas St., Brgy. Quirino 2-A, Quezon City', 90],
        ['Miguel', 'Torres', 'miguel.torres@mail.test', '0935 555 0146', '66 Congressional Ave., Brgy. Bahay Toro, Quezon City', 200],
        ['Camille', 'Flores', 'camille.flores@mail.test', '0947 555 0183', '9 Scout Tuason St., Brgy. Obrero, Quezon City', 12],
        ['Rico', 'Castillo', 'rico.castillo@mail.test', '0908 555 0127', '28 Roosevelt Ave., Brgy. San Antonio, Quezon City', 5],
    ];
    $ownerIds = [];
    $ownerJoined = [];
    $ownerNames = [];
    foreach ($owners as $i => [$first, $last, $email, $phone, $address, $daysAgo]) {
        $joined = strtotime($day(-$daysAgo) . ' 10:15:00');
        $id = insert('users', [
            'role' => 'owner', 'first_name' => $first, 'last_name' => $last, 'email' => $email,
            'phone' => $phone, 'address' => $address, 'password_hash' => $hash,
            'created_at' => date('Y-m-d H:i:s', $joined),
            'last_login_at' => date('Y-m-d H:i:s', $now - mt_rand(2, 20) * 86400),
        ]);
        $ownerIds[$i] = $id;
        $ownerJoined[$id] = $joined;
        $ownerNames[$id] = "$first $last";
    }
    $maria = $ownerIds[0];

    // --------------------------------------------------------------------- pets
    // [owner, name, species, breed, sex, age (years), kg, colour, photo, neutered, allergies, notes]
    $petDefs = [
        [0, 'Coco', 'Dog', 'Shih Tzu', 'Female', 2.4, 5.8, 'White & brown', 'coco', 1, null, 'Loves belly rubs. Gets a little anxious during nail trims.'],
        [0, 'Mochi', 'Cat', 'Puspin (Pusang Pinoy)', 'Male', 4.1, 4.5, 'Orange & white', 'mochi', 1, 'Chicken-based food', 'Indoor cat. Prefers a quiet exam room.'],
        [0, 'Bruno', 'Dog', 'Golden Retriever', 'Male', 0.95, 26.4, 'Golden', 'bruno', 0, null, 'Very energetic puppy, still in obedience training.'],
        [1, 'Buddy', 'Dog', 'Beagle', 'Male', 5.2, 12.4, 'Tricolor', 'buddy', 1, null, null],
        [1, 'Muning', 'Cat', 'Puspin (Pusang Pinoy)', 'Female', 3.0, 3.9, 'Brown tabby', 'muning', 1, null, null],
        [2, 'Kuma', 'Dog', 'Pomeranian', 'Male', 3.5, 3.1, 'Black & tan', 'kuma', 1, null, 'Barks at the stethoscope, but treats help!'],
        [2, 'Snowball', 'Rabbit', 'Holland Lop', 'Female', 1.6, 1.8, 'White', 'snowball', 0, null, null],
        [3, 'Choco', 'Dog', 'Dachshund', 'Male', 7.8, 9.2, 'Chocolate', 'choco', 1, 'Penicillin', 'Senior. Monitor back and joints.'],
        [4, 'Mango', 'Cat', 'Persian', 'Female', 2.1, 4.2, 'Cream', 'mango', 1, null, null],
        [4, 'Kahel', 'Cat', 'Domestic Shorthair', 'Male', 5.5, 5.1, 'Orange tabby', 'kahel', 1, null, null],
        [5, 'Kiwi', 'Bird', 'Amazon Parrot', 'Male', 8.0, 0.45, 'Green & yellow', 'kiwi', 0, null, 'Talks a lot. Greets everyone with "Hello doc!"'],
        [5, 'Nala', 'Dog', 'Labrador Retriever', 'Female', 4.9, 27.5, 'Yellow', null, 1, null, null],
        [6, 'Bella', 'Dog', 'Shih Tzu', 'Female', 6.2, 6.5, 'Cream', 'bella', 1, null, null],
        [7, 'Honey', 'Dog', 'Golden Retriever', 'Female', 0.45, 9.8, 'Light golden', 'honey', 0, null, 'Puppy vaccination series in progress.'],
        [8, 'Simba', 'Cat', 'Puspin (Pusang Pinoy)', 'Male', 0.7, 3.2, 'Orange tabby', 'simba', 0, null, null],
        [8, 'Pepper', 'Rabbit', 'Lionhead', 'Male', 2.3, 1.6, 'Grey', 'pepper', 0, null, null],
        [9, 'Max', 'Dog', 'Mixed breed', 'Male', 9.1, 7.4, 'Grey & white', 'max', 1, null, 'Senior with mild arthritis.'],
        [10, 'Lucky', 'Dog', 'Pomeranian', 'Male', 4.4, 2.9, 'Black & tan', 'lucky', 1, null, null],
        [11, 'Rocky', 'Dog', 'Aspin (Asong Pinoy)', 'Male', 3.8, 14.0, 'Brown', null, 1, null, 'Rescued from the street in 2022.'],
        [11, 'Tiger', 'Cat', 'Puspin (Pusang Pinoy)', 'Male', 6.0, 4.8, 'Grey tabby', null, 1, null, null],
        [12, 'Luna', 'Cat', 'Siamese', 'Female', 1.9, 3.4, 'Seal point', null, 0, null, null],
        [12, 'Oreo', 'Hamster', 'Syrian', 'Male', 1.1, 0.15, 'Black & white', null, 0, null, null],
        [13, 'Brownie', 'Dog', 'Aspin (Asong Pinoy)', 'Female', 2.7, 12.1, 'Brown', null, 1, null, null],
        [13, 'Shelly', 'Turtle', 'Red-eared Slider', 'Unknown', 4.0, 0.6, 'Green', null, 0, null, null],
    ];
    $pets = [];
    foreach ($petDefs as [$o, $name, $species, $breed, $sex, $age, $kg, $colour, $photo, $neutered, $allergies, $notes]) {
        $ownerId = $ownerIds[$o];
        $birth = strtotime('-' . (int) round($age * 365.25) . ' days');
        $created = min($now - 3600, max($ownerJoined[$ownerId] + 1800, $birth + 45 * 86400));
        $id = insert('pets', [
            'owner_id' => $ownerId, 'name' => $name, 'species' => $species, 'breed' => $breed, 'sex' => $sex,
            'birthdate' => date('Y-m-d', $birth), 'weight_kg' => $kg, 'color' => $colour, 'is_neutered' => $neutered,
            'allergies' => $allergies, 'notes' => $notes,
            'photo' => $photo ? "assets/img/pets/$photo.jpg" : null,
            'created_at' => date('Y-m-d H:i:s', $created),
        ]);
        $pets[$name] = ['id' => $id, 'name' => $name, 'owner_id' => $ownerId, 'species' => $species, 'sex' => $sex, 'birth' => $birth, 'kg' => $kg, 'created' => $created];
    }

    // ------------------------------------------------------------ vocabularies
    $services = [];
    foreach (rows('SELECT * FROM services') as $s) {
        $services[(int) $s['id']] = $s;
    }
    $vetNames = [];
    foreach (rows('SELECT id, name FROM vets') as $v) {
        $vetNames[(int) $v['id']] = $v['name'];
    }
    $serviceWeights = [
        'Dog'    => [1 => 20, 2 => 15, 3 => 8, 4 => 2, 5 => 16, 6 => 7, 7 => 6, 8 => 4, 9 => 2, 10 => 2, 11 => 2, 12 => 4, 13 => 9, 14 => 6],
        'Cat'    => [1 => 22, 2 => 16, 3 => 8, 4 => 2, 5 => 18, 6 => 6, 7 => 7, 8 => 3, 9 => 2, 10 => 3, 11 => 1, 12 => 4, 13 => 3, 14 => 3],
        'Rabbit' => [1 => 45, 2 => 6, 5 => 32, 7 => 6, 14 => 11],
        'other'  => [1 => 55, 5 => 40, 7 => 5],
    ];
    $reasons = [
        1 => ['Annual wellness check', 'Routine check-up', 'Check-up before travel', 'Senior wellness exam', 'Weight check'],
        2 => ['Annual booster', 'Booster reminder received', 'Anti-rabies vaccine due', 'Vaccine series'],
        3 => ['Quarterly deworming', 'Flea and tick prevention', 'Saw worms in stool'],
        4 => ['Microchip for travel papers', 'Permanent ID for a new pet'],
        5 => ['Not eating well for 2 days', 'Vomiting since last night', 'Limping on the left hind leg', 'Coughing and sneezing', 'Diarrhea for 2 days', 'Lethargic and hiding', 'Scratching ears a lot', 'Red, watery eyes'],
        6 => ['Itchy skin and hair loss', 'Red spots on the belly', 'Keeps licking paws', 'Hot spot on the back'],
        7 => ['Pre-surgery blood work', 'Follow-up CBC', 'Annual blood panel for senior pet'],
        8 => ['Check for fracture after a fall', 'Chest X-ray for persistent cough', 'Hip evaluation'],
        9 => ['Pregnancy check', 'Abdominal scan'],
        10 => ['Scheduled spay', 'Scheduled neuter'],
        11 => ['Small lump on the side', 'Wound from a scuffle'],
        12 => ['Bad breath and tartar', 'Yearly dental cleaning'],
        13 => ['Monthly grooming', 'Summer haircut, please', 'Full groom before a family event'],
        14 => ['Quick bath and nail trim', 'Nails are too long'],
    ];
    $templates = [
        1 => [['Healthy — routine wellness exam', 'Complete physical exam normal. Discussed diet, exercise and dental care.', null], ['Healthy, slightly overweight', 'Body condition score 6/9. Advised portion control and daily walks.', 'Weight-management diet; recheck in 3 months']],
        3 => [['Routine deworming', 'Dewormer given based on body weight. Flea and tick spot-on applied.', 'Repeat deworming in 3 months']],
        4 => [['Microchip implanted', 'ISO microchip implanted between the shoulder blades and scanned successfully.', null]],
        5 => [
            ['Acute gastroenteritis', 'Subcutaneous fluids and anti-emetic injection given. Advised bland diet for 3 days.', 'Metronidazole ½ tab twice daily for 5 days'],
            ['Otitis externa (ear infection)', 'Ears cleaned. Cytology showed yeast overgrowth.', 'Otic drops, 3 drops twice daily for 10 days'],
            ['Mild upper respiratory infection', 'Nebulization done. Lungs clear on auscultation.', 'Doxycycline once daily for 7 days'],
            ['Soft-tissue sprain, left hind leg', 'Orthopedic exam: no fracture suspected. Cold compress advised.', 'Meloxicam once daily for 5 days; strict rest'],
            ['Conjunctivitis', 'Eyes flushed; fluorescein stain negative for ulcers.', 'Antibiotic eye drops 3x a day for 7 days'],
            ['Tick-borne infection (Ehrlichia) — suspected', 'Blood drawn for CBC and 4Dx test. Treatment started.', 'Doxycycline once daily for 28 days'],
        ],
        6 => [['Allergic dermatitis', 'Skin scraping negative for mites. Medicated bath given.', 'Antihistamine daily for 14 days; medicated shampoo twice a week'], ['Hot spot (pyotraumatic dermatitis)', 'Area clipped and cleaned. E-collar fitted.', 'Topical spray twice daily for 7 days']],
        7 => [['Blood work within normal limits', 'CBC and blood chemistry normal.', null], ['Mild anemia', 'CBC shows a low red cell count. Recheck in 2 weeks.', 'Iron supplement syrup 1 ml daily']],
        8 => [['No fractures seen on X-ray', 'Two-view radiographs of the affected area taken.', 'Rest for 1 week'], ['Mild hip dysplasia', 'Hip radiographs show mild joint laxity.', 'Joint supplement daily; keep weight lean']],
        9 => [['Abdominal ultrasound unremarkable', 'No masses or free fluid seen.', null]],
        10 => [['Routine spay/neuter — uneventful', 'Procedure done under isoflurane anaesthesia. Smooth recovery.', 'Antibiotics for 7 days; pain relief for 3 days; e-collar for 10 days']],
        11 => [['Laceration repair', 'Wound cleaned and sutured under sedation.', 'Antibiotics for 7 days; suture removal in 10 days'], ['Benign lipoma removed', 'Mass excised and sent for histopathology.', 'Pain relief for 3 days']],
        12 => [['Dental tartar, grade 2', 'Ultrasonic scaling and polishing performed.', 'Dental chews daily; brush teeth 3x a week']],
    ];
    $vaccinesFor = ['Dog' => ['5-in-1 (DHPPiL)', 'Anti-Rabies', 'Kennel Cough (Bordetella)'], 'Cat' => ['4-in-1 (FVRCP + Chlamydia)', 'Anti-Rabies'], 'Rabbit' => ['RHDV1/RHDV2']];
    $tempRange = ['Dog' => [38.3, 39.2], 'Cat' => [38.1, 39.2], 'Rabbit' => [38.5, 39.8], 'Bird' => [40.0, 41.5]];

    $counts = ['appointments' => 0, 'records' => 0, 'vaccinations' => 0];
    $petDay = [];
    $vaxGiven = [];

    $addRecord = function (array $pet, string $date, ?int $vetId, ?int $apptId, array $tpl, ?float $kg = null, string $createdAt = '') use (&$counts, $rand, $tempRange) {
        $temp = isset($tempRange[$pet['species']]) ? round($rand(...$tempRange[$pet['species']]), 1) : null;
        insert('medical_records', [
            'pet_id' => $pet['id'], 'appointment_id' => $apptId, 'vet_id' => $vetId, 'visit_date' => $date,
            'weight_kg' => $kg ?? round($pet['kg'] * $rand(0.97, 1.03), 2), 'temperature_c' => $temp,
            'diagnosis' => $tpl[0], 'treatment' => $tpl[1], 'prescription' => $tpl[2],
            'created_by' => 1, 'created_at' => $createdAt ?: $date . ' 17:30:00',
        ]);
        $counts['records']++;
    };
    $addVaccine = function (array $pet, string $vaccine, string $date, ?string $nextDue, ?int $vetId, ?int $apptId = null, ?string $reminded = null) use (&$counts, &$vaxGiven) {
        insert('vaccinations', [
            'pet_id' => $pet['id'], 'vaccine_name' => $vaccine, 'date_given' => $date, 'next_due_date' => $nextDue,
            'batch_no' => strtoupper(substr(md5($pet['id'] . $vaccine . $date), 0, 7)), 'vet_id' => $vetId,
            'appointment_id' => $apptId, 'reminded_at' => $reminded, 'created_at' => $date . ' 16:00:00',
        ]);
        $vaxGiven[$pet['id']][$vaccine] = true;
        $counts['vaccinations']++;
    };

    /** Create one appointment (plus record/vaccine when completed). Returns the row or null. */
    $book = function (array $pet, int $serviceId, string $date, ?string $time = null, string $status = 'auto', array $opt = [])
        use (&$petDay, &$counts, $services, $reasons, $templates, $vaccinesFor, $ownerJoined, $now, $today, $pick, $addRecord, $addVaccine) {
        $svc = $services[$serviceId];
        if (isset($petDay[$pet['id'] . $date])) {
            return null;
        }
        $open = array_values(array_filter(
            available_slots($date, (int) $svc['duration_minutes'], null, ['allow_past' => true])['slots'],
            fn ($s) => $s['available']
        ));
        if (!$open) {
            return null;
        }
        $slot = $open[mt_rand(0, count($open) - 1)];
        foreach ($open as $s) {
            if ($time && $s['time'] === $time) {
                $slot = $s;
            }
        }
        $vetId = $slot['vet_ids'][mt_rand(0, count($slot['vet_ids']) - 1)];
        $start = strtotime("$date {$slot['time']}");
        $end = strtotime("$date {$slot['end']}");
        if ($status === 'auto') {
            if ($end < $now) {
                $r = mt_rand(1, 100);
                $status = $r <= 84 ? 'completed' : ($r <= 93 ? 'cancelled' : 'no_show');
            } elseif ($start <= $now) {
                $status = 'confirmed';
            } else {
                $status = mt_rand(1, 100) <= ($date === $today ? 85 : 62) ? 'confirmed' : 'pending';
            }
        }
        $created = $start - mt_rand(1, 12) * 86400 - mt_rand(0, 30000);
        if ($created > $now) {
            $created = $now - mt_rand(900, 4 * 86400);
        }
        $created = max($created, $ownerJoined[$pet['owner_id']] + 3600, $pet['created'] + 600);
        $created = min($created, $now - 300);
        $updated = $status === 'completed' || $status === 'no_show' ? min($end, $now) : $created;
        $pool = $reasons[$serviceId] ?? ['Check-up'];
        $id = insert('appointments', [
            'reference' => generate_reference(), 'pet_id' => $pet['id'], 'owner_id' => $pet['owner_id'],
            'service_id' => $serviceId, 'vet_id' => $vetId, 'appointment_date' => $date,
            'start_time' => $slot['time'] . ':00', 'end_time' => $slot['end'] . ':00', 'status' => $status,
            'price' => $svc['price'], 'reason' => $opt['reason'] ?? $pool[mt_rand(0, count($pool) - 1)],
            'booked_by' => $opt['booked_by'] ?? (mt_rand(1, 100) <= 78 ? 'owner' : 'staff'),
            'cancel_reason' => $status === 'cancelled' ? ['Pet is feeling better', 'Schedule conflict', 'Will rebook next week'][mt_rand(0, 2)] : null,
            'created_at' => date('Y-m-d H:i:s', $created), 'updated_at' => date('Y-m-d H:i:s', $updated),
        ]);
        $petDay[$pet['id'] . $date] = true;
        $counts['appointments']++;

        if ($status === 'completed') {
            $recordAt = date('Y-m-d H:i:s', min($end + 600, $now));
            if ((int) $svc['is_vaccination'] === 1) {
                $list = $vaccinesFor[$pet['species']] ?? ['Anti-Rabies'];
                $vaccine = $opt['vaccine'] ?? $list[mt_rand(0, min(1, count($list) - 1))];
                $addVaccine($pet, $vaccine, $date, date('Y-m-d', strtotime("$date +1 year")), $vetId, $id);
                $addRecord($pet, $date, $vetId, $id, ["Vaccination — $vaccine", "Administered $vaccine. No adverse reaction after 15 minutes of observation.", null], $opt['kg'] ?? null, $recordAt);
            } elseif ($svc['category'] !== 'Grooming') {
                $pool = $templates[$serviceId] ?? $templates[1];
                $tpl = $opt['record'] ?? $pool[mt_rand(0, count($pool) - 1)];
                $addRecord($pet, $date, $vetId, $id, $tpl, $opt['kg'] ?? null, $recordAt);
            }
        }
        return ['id' => $id, 'status' => $status, 'date' => $date, 'time' => $slot['time'], 'vet_id' => $vetId, 'created' => $created];
    };

    // ------------------------------------------------ Maria's story (demo owner)
    $coco = $pets['Coco'];
    $mochi = $pets['Mochi'];
    $bruno = $pets['Bruno'];

    $cocoBooster = $book($coco, 2, $openDay(2), '09:30', 'confirmed', ['reason' => '5-in-1 booster due', 'booked_by' => 'owner']);
    $brunoGroom = $book($bruno, 13, $openDay(6), '14:00', 'pending', ['reason' => "First full groom — he's a wiggly one!", 'booked_by' => 'owner']);
    $mochiVisit = $book($mochi, 5, $openDay(-9), '10:00', 'completed', [
        'reason' => 'Not eating well for 2 days', 'kg' => 4.5,
        'record' => ['Mild gastritis', 'Abdomen mildly tender on palpation. Anti-emetic injection given; bland diet advised.', 'Famotidine ¼ tab once daily for 5 days; small, frequent meals'],
    ]);
    $book($bruno, 5, $openDay(-18), '15:00', 'completed', [
        'reason' => 'Swallowed part of a sock', 'kg' => 25.9,
        'record' => ['Foreign-body ingestion — resolved', 'Vomiting induced; sock fragment retrieved. Abdomen soft afterwards.', 'Monitor stools for 48 hours'],
    ]);
    $book($bruno, 3, $openDay(-25), '09:00', 'completed', ['reason' => 'Quarterly deworming', 'kg' => 25.1]);
    $book($mochi, 12, $openDay(-33), '13:30', 'cancelled', ['reason' => 'Yearly dental cleaning']);
    $book($coco, 1, $openDay(-40), '10:30', 'completed', ['reason' => 'Routine check-up', 'kg' => 5.8, 'record' => $templates[1][0]]);
    $book($coco, 2, $openDay(-60), '11:00', 'completed', ['reason' => 'Anti-rabies vaccine due', 'vaccine' => 'Anti-Rabies', 'kg' => 5.7]);

    // older history for richer health timelines & weight charts
    foreach ([[-300, 4.9, 'Puppy wellness exam', 'First visit: healthy, playful puppy. Vaccine series started.'], [-244, 10.8, 'Puppy booster visit', 'Final puppy boosters given. Growth on track.'], [-180, 17.2, 'Healthy — growth check', 'Growing well. Discussed large-breed puppy diet.'], [-120, 22.0, 'Healthy — growth check', 'Weight gain on track; joints normal.']] as [$off, $kg, $dx, $tx]) {
        $addRecord($bruno, $openDay($off), 1, null, [$dx, $tx, null], $kg);
    }
    foreach ([[-330, 5.2], [-200, 5.5], [-120, 5.6]] as [$off, $kg]) {
        $addRecord($coco, $openDay($off), [1, 3][mt_rand(0, 1)], null, $templates[1][0], $kg);
    }
    $addRecord($mochi, $openDay(-380), 3, null, ['Healthy — annual exam', 'Good body condition. Advised more play time and a water fountain.', null], 4.9);
    $addRecord($mochi, $openDay(-150), 3, null, ['Vaccination — 4-in-1 (FVRCP + Chlamydia)', 'Annual booster given. No reaction observed.', null], 4.7);

    $addVaccine($coco, '5-in-1 (DHPPiL)', $openDay(-350), date('Y-m-d', strtotime($openDay(-350) . ' +1 year')), 1, null, date('Y-m-d H:i:s', $now - 86400));
    $addVaccine($mochi, 'Anti-Rabies', $openDay(-395), date('Y-m-d', strtotime($openDay(-395) . ' +1 year')), 3, null, date('Y-m-d H:i:s', $now - 2 * 86400));
    $addVaccine($mochi, '4-in-1 (FVRCP + Chlamydia)', $openDay(-150), date('Y-m-d', strtotime($openDay(-150) . ' +1 year')), 3);
    $addVaccine($bruno, '5-in-1 (DHPPiL)', $openDay(-300), $openDay(-272), 1);
    $addVaccine($bruno, '5-in-1 (DHPPiL)', $openDay(-272), $openDay(-244), 1);
    $addVaccine($bruno, '5-in-1 (DHPPiL)', $openDay(-244), date('Y-m-d', strtotime($openDay(-244) . ' +1 year')), 1);
    $addVaccine($bruno, 'Anti-Rabies', $openDay(-244), date('Y-m-d', strtotime($openDay(-244) . ' +1 year')), 1);

    // --------------------------------------------------- everyone else, 90 days back → 3 weeks ahead
    $others = array_values(array_filter($pets, fn ($p) => $p['owner_id'] !== $maria));
    for ($offset = -90; $offset <= 21; $offset++) {
        $date = $day($offset);
        if (!clinic_hours_for($date)) {
            continue;
        }
        if ($offset < 0) {
            $n = mt_rand(3, 6);
        } elseif ($offset === 0) {
            $n = 7;
        } elseif ($offset <= 3) {
            $n = mt_rand(3, 5);
        } elseif ($offset <= 10) {
            $n = mt_rand(1, 4);
        } else {
            $n = mt_rand(0, 2);
        }
        for ($k = 0; $k < $n; $k++) {
            $pet = $others[mt_rand(0, count($others) - 1)];
            if (strtotime($date) < $pet['created']) {
                continue;
            }
            $weights = $serviceWeights[$pet['species']] ?? $serviceWeights['other'];
            $book($pet, (int) $pick($weights), $date);
        }
    }

    // Honey (puppy) booster due in a few days; other pets get a realistic vaccine history
    $addVaccine($pets['Honey'], '5-in-1 (DHPPiL)', $openDay(-51), $openDay(-23), 2);
    $addVaccine($pets['Honey'], '5-in-1 (DHPPiL)', $openDay(-23), $day(5), 2);
    foreach ($others as $pet) {
        foreach (array_slice($vaccinesFor[$pet['species']] ?? [], 0, 2) as $vaccine) {
            if (!empty($vaxGiven[$pet['id']][$vaccine])) {
                continue;
            }
            $given = $openDay(-mt_rand(20, 420));
            if (strtotime($given) < $pet['birth'] + 60 * 86400) {
                continue;
            }
            $addVaccine($pet, $vaccine, $given, date('Y-m-d', strtotime("$given +1 year")), mt_rand(1, 4));
        }
    }

    // keep each pet's current weight in sync with its latest visit
    q('UPDATE pets p JOIN (SELECT m.pet_id, m.weight_kg FROM medical_records m
         JOIN (SELECT pet_id, MAX(visit_date) AS d FROM medical_records WHERE weight_kg IS NOT NULL GROUP BY pet_id) last
           ON last.pet_id = m.pet_id AND last.d = m.visit_date WHERE m.weight_kg IS NOT NULL) w
       ON w.pet_id = p.id SET p.weight_kg = w.weight_kg');

    // ------------------------------------------------------------ notifications
    $at = fn (int $secondsAgo): string => date('Y-m-d H:i:s', $now - $secondsAgo);
    $n = function (int $userId, string $type, string $title, string $msg, ?string $link, bool $read, string $createdAt) {
        insert('notifications', ['user_id' => $userId, 'type' => $type, 'title' => $title, 'message' => $msg, 'link' => $link, 'is_read' => $read ? 1 : 0, 'created_at' => $createdAt]);
    };
    $n($maria, 'system', 'Welcome to PawCare, Maria!', 'Your pet health portal is ready. Add your pets and book visits online anytime.', 'owner/', true, date('Y-m-d H:i:s', $ownerJoined[$maria] + 60));
    if ($mochiVisit) {
        $n($maria, 'record', 'Visit summary ready', "Notes from Mochi's visit are now in the health record.", 'owner/pet.php?id=' . $mochi['id'] . '#records', true, $mochiVisit['date'] . ' 11:15:00');
    }
    $n($maria, 'vaccine', 'Vaccine overdue: Mochi', "Mochi's Anti-Rabies booster is overdue. Book a vaccination visit soon.", 'owner/book.php?pet=' . $mochi['id'] . '&service=2', false, $at(2 * 86400));
    $n($maria, 'vaccine', "Coco's 5-in-1 booster is due soon", 'Her booster is due in about two weeks. Tap to view her vaccine card.', 'owner/pet.php?id=' . $coco['id'] . '#vaccines', false, $at(86400));
    if ($brunoGroom) {
        $n($maria, 'booking', 'Booking request received', "We received Bruno's Full Grooming request for " . fmt_date($brunoGroom['date'], 'D, M j') . ' at ' . fmt_time($brunoGroom['time']) . ". We'll confirm shortly.", 'owner/appointments.php', true, $at(5 * 3600));
    }
    if ($cocoBooster) {
        $n($maria, 'appointment', 'Appointment confirmed', "Coco's Vaccination on " . fmt_date($cocoBooster['date'], 'D, M j') . ' at ' . fmt_time($cocoBooster['time']) . ' is confirmed. See you soon!', 'owner/appointments.php', false, $at(3 * 3600));
    }

    $pending = rows(appointment_sql() . " WHERE a.status = 'pending' AND a.appointment_date >= ? ORDER BY a.created_at DESC LIMIT 6", [$today]);
    foreach ($pending as $i => $a) {
        foreach ([1, 2] as $staffId) {
            $n($staffId, 'booking', 'New booking request', "{$a['owner_first']} {$a['owner_last']} requested {$a['service_name']} for {$a['pet_name']} on " . fmt_date($a['appointment_date'], 'M j') . ' at ' . fmt_time($a['start_time']) . '.', 'admin/appointment.php?id=' . $a['id'], $i > 2, $a['created_at']);
        }
    }

    // ---------------------------------------------------------- website inquiries
    $messages = [
        ['Kristel Mae Uy', 'kristel.uy@mail.test', '0917 555 0190', 'Do you accept walk-ins?', 'Hi! My cat has been sneezing since yesterday. Do you accept walk-ins on Saturdays, or do I need to book online first? Thank you!', 'new', 3 * 3600],
        ['Jerome Santiago', 'jerome.s@mail.test', null, 'Pet boarding', 'Good day! Do you offer boarding for dogs? We will be out of town for four days next month.', 'new', 26 * 3600],
        ['Anne Villareal', 'anne.v@mail.test', '0998 555 0102', 'Price of spay for cats', 'How much is the spay surgery for a 1-year-old cat, and does it include the pre-surgery blood test?', 'read', 4 * 86400],
        ['Paul Lim', 'paul.lim@mail.test', null, 'Thank you!', 'Just wanted to say thank you to Dr. Kristine for taking such good care of our rabbit. You are the best!', 'resolved', 10 * 86400],
    ];
    foreach ($messages as [$name, $email, $phone, $subject, $body, $status, $ago]) {
        insert('contact_messages', ['name' => $name, 'email' => $email, 'phone' => $phone, 'subject' => $subject, 'message' => $body, 'status' => $status, 'created_at' => $at($ago)]);
        if ($status === 'new') {
            foreach ([1, 2] as $staffId) {
                $n($staffId, 'message', 'New website inquiry', "$name: $subject", 'admin/messages.php', false, $at($ago));
            }
        }
    }
    foreach ([12, 13] as $i) {
        $uid = $ownerIds[$i];
        $n(1, 'account', 'New client registered', $ownerNames[$uid] . ' created an account on the website.', 'admin/client.php?id=' . $uid, true, date('Y-m-d H:i:s', $ownerJoined[$uid]));
    }

    // ---------------------------------------------------------------- activity feed
    foreach (rows(appointment_sql() . ' WHERE a.created_at <= NOW() ORDER BY a.created_at DESC LIMIT 12') as $a) {
        $who = $a['booked_by'] === 'staff' ? 2 : null;
        $text = $a['booked_by'] === 'staff'
            ? "Joy Ramirez booked {$a['service_name']} for {$a['pet_name']}"
            : "{$a['owner_first']} {$a['owner_last']} booked {$a['service_name']} for {$a['pet_name']}";
        insert('activity_log', ['user_id' => $who, 'action' => $text, 'icon' => 'calendar-plus', 'link' => 'admin/appointment.php?id=' . $a['id'], 'created_at' => $a['created_at']]);
    }
    foreach (rows(appointment_sql() . " WHERE a.status = 'completed' AND a.appointment_date >= ? ORDER BY a.updated_at DESC LIMIT 6", [$day(-3)]) as $a) {
        insert('activity_log', ['user_id' => 1, 'action' => "{$a['vet_name']} completed {$a['pet_name']}'s {$a['service_name']}", 'icon' => 'circle-check', 'link' => 'admin/appointment.php?id=' . $a['id'], 'created_at' => $a['updated_at']]);
    }
    foreach ([12, 13] as $i) {
        $uid = $ownerIds[$i];
        insert('activity_log', ['user_id' => $uid, 'action' => 'New client registered: ' . $ownerNames[$uid], 'icon' => 'user-plus', 'link' => 'admin/client.php?id=' . $uid, 'created_at' => date('Y-m-d H:i:s', $ownerJoined[$uid])]);
    }

    $pdo->commit();

    return [
        'clients'      => count($ownerIds),
        'pets'         => count($pets),
        'appointments' => $counts['appointments'],
        'records'      => $counts['records'],
        'vaccinations' => $counts['vaccinations'],
    ];
}
