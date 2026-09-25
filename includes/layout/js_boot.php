<?php
$__icons = [];
foreach (['success' => 'circle-check', 'error' => 'circle-alert', 'warning' => 'triangle-alert', 'info' => 'info', 'x' => 'x', 'eye' => 'eye', 'eye-off' => 'eye-off', 'chevron-left' => 'chevron-left', 'chevron-right' => 'chevron-right', 'calendar-x' => 'calendar-x', 'calendar-days' => 'calendar-days', 'sun' => 'sun', 'clock' => 'clock', 'image' => 'image'] as $k => $name) {
    $__icons[$k] = icon($name);
}
?>
<script>
window.PC = {
  base: <?= json_encode(base_path(), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>,
  csrf: <?= json_encode(csrf_token()) ?>,
  currency: <?= json_encode(CURRENCY, JSON_UNESCAPED_UNICODE) ?>,
  icons: <?= json_encode($__icons, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>
};
</script>
