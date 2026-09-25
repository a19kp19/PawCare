<?php
/** Staff: list a client's pets (for the new-appointment form). */
require __DIR__ . '/../includes/bootstrap.php';
require_staff();
json_response(['ok' => true, 'pets' => rows('SELECT id, name, species, breed FROM pets WHERE owner_id = ? ORDER BY name', [int_param('owner_id')])]);
