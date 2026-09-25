        </main>
    </div>
</div>
<div class="sidebar-backdrop" data-sidebar-backdrop></div>
<?php include __DIR__ . '/js_boot.php'; ?>
<?php if (!empty($useCharts)): ?><script src="<?= e(asset('assets/vendor/chart.umd.js')) ?>"></script><?php endif; ?>
<script src="<?= e(asset('assets/js/core.js')) ?>"></script>
<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
<?php foreach ($extraScripts ?? [] as $script): ?><script src="<?= e(asset($script)) ?>"></script><?php endforeach; ?>
</body>
</html>
