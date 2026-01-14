</div>

<footer class="footer mt-5 py-3">
    <div class="container text-center">
        <small class="text-muted">
            Wrapping System Dashboard © 2026
        </small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="<?= base_url('public/assets/js/dashboard.js') ?>"></script>

<?php if ($page == 'monitoring'): ?>
<script>
$(document).ready(function() {
    Dashboard.initMonitoring();
});
</script>
<?php endif; ?>

<?php if ($page == 'testing'): ?>
<script>
$(document).ready(function() {
    Dashboard.initTesting();
});
</script>
<?php endif; ?>

</body>
</html>
