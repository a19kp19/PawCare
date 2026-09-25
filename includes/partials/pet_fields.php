<?php
/**
 * Pet form fields (owner & staff). Expects $values, $errors; optional $ownerOptions (staff only).
 */
$err = fn (string $k): string => isset($errors[$k]) ? '<span class="field-error">' . e($errors[$k]) . '</span>' : '';
$cls = fn (string $k): string => isset($errors[$k]) ? ' has-error' : '';
?>
<div class="form-grid">
    <?php if (isset($ownerOptions)): ?>
        <div class="field span-2<?= $cls('owner_id') ?>">
            <label for="owner_id">Owner <span class="req">*</span></label>
            <select class="input" id="owner_id" name="owner_id" required>
                <option value="">Select the pet's owner</option>
                <?php foreach ($ownerOptions as $o): ?>
                    <option value="<?= (int) $o['id'] ?>"<?= selected($values['owner_id'] ?? '', $o['id']) ?>><?= e($o['last_name'] . ', ' . $o['first_name'] . ' · ' . $o['email']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= $err('owner_id') ?>
        </div>
    <?php endif; ?>

    <div class="field span-2<?= $cls('photo') ?>">
        <span class="label">Photo</span>
        <label class="dropzone" data-dropzone data-max-mb="<?= (int) MAX_UPLOAD_MB ?>">
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Upload a photo">
            <span class="dz-preview" data-dz-preview><?= !empty($values['photo']) ? pet_photo($values) : icon('camera') ?></span>
            <span class="dz-text">
                <strong data-dz-label><?= !empty($values['photo']) ? 'Drop a new photo to replace this one' : 'Drag a photo here or click to upload' ?></strong>
                <span>JPG, PNG, WEBP or GIF · up to <?= (int) MAX_UPLOAD_MB ?> MB</span>
                <span class="btn btn-outline btn-sm dz-btn"><?= icon('upload') ?>Choose photo</span>
            </span>
        </label>
        <?= $err('photo') ?>
    </div>

    <div class="field<?= $cls('name') ?>">
        <label for="name">Pet's name <span class="req">*</span></label>
        <input class="input" id="name" name="name" value="<?= e($values['name']) ?>" maxlength="60" placeholder="e.g. Coco" required>
        <?= $err('name') ?>
    </div>
    <div class="field<?= $cls('birthdate') ?>">
        <label for="birthdate">Birth date</label>
        <input class="input" id="birthdate" type="date" name="birthdate" value="<?= e($values['birthdate']) ?>" max="<?= date('Y-m-d') ?>">
        <?= $err('birthdate') ?: '<span class="field-hint">Not sure? An estimate is fine.</span>' ?>
    </div>

    <div class="field span-2<?= $cls('species') ?>">
        <span class="label">Species <span class="req">*</span></span>
        <div class="choice-row">
            <?php foreach (SPECIES as $sp => $ic): ?>
                <label class="choice"><input type="radio" name="species" value="<?= e($sp) ?>"<?= checked($values['species'] === $sp) ?>><span><?= icon($ic) ?><?= e($sp) ?></span></label>
            <?php endforeach; ?>
        </div>
        <span hidden data-species-select="breed-list" data-name="species" data-breeds="<?= e(json_encode(BREEDS)) ?>"></span>
        <?= $err('species') ?>
    </div>

    <div class="field<?= $cls('breed') ?>">
        <label for="breed">Breed</label>
        <input class="input" id="breed" name="breed" list="breed-list" value="<?= e($values['breed']) ?>" maxlength="80" placeholder="Start typing to see suggestions">
        <datalist id="breed-list"></datalist>
        <?= $err('breed') ?>
    </div>
    <div class="field<?= $cls('sex') ?>">
        <span class="label">Sex</span>
        <div class="choice-row">
            <?php foreach (['Male' => 'mars', 'Female' => 'venus', 'Unknown' => 'circle-help'] as $sx => $ic): ?>
                <label class="choice"><input type="radio" name="sex" value="<?= $sx ?>"<?= checked($values['sex'] === $sx) ?>><span><?= icon($ic) ?><?= $sx ?></span></label>
            <?php endforeach; ?>
        </div>
        <?= $err('sex') ?>
    </div>
    <div class="field<?= $cls('weight_kg') ?>">
        <label for="weight_kg">Weight</label>
        <div class="input-group"><?= icon('weight') ?><input class="input" id="weight_kg" type="number" name="weight_kg" step="0.01" min="0" max="200" value="<?= e($values['weight_kg']) ?>" placeholder="0.00"><span class="input-suffix">kg</span></div>
        <?= $err('weight_kg') ?>
    </div>
    <div class="field">
        <label for="color">Colour / markings</label>
        <input class="input" id="color" name="color" value="<?= e($values['color']) ?>" maxlength="60" placeholder="e.g. White with brown patches">
    </div>
    <div class="field span-2">
        <label class="switch"><input type="checkbox" name="is_neutered" value="1"<?= checked(!empty($values['is_neutered'])) ?>> Spayed / neutered</label>
    </div>
    <div class="field span-2">
        <label for="allergies">Allergies</label>
        <input class="input" id="allergies" name="allergies" value="<?= e($values['allergies']) ?>" maxlength="255" placeholder="Food or medicine allergies (leave blank if none)">
        <span class="field-hint">Shown as a red alert to our vets during every visit.</span>
    </div>
    <div class="field span-2">
        <label for="notes">Notes for the vet</label>
        <textarea class="input" id="notes" name="notes" rows="3" placeholder="Temperament, diet, medical history…"><?= e($values['notes']) ?></textarea>
    </div>
</div>
