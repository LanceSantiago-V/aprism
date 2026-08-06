<!-- Bootstrap -->
<script src="<?= APP_URL ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js">
</script>

<!-- Lucide -->
<script src="<?= APP_URL ?>/assets/vendor/lucide/lucide.min.js">
</script>

<script>
    window.APP_URL = "<?= APP_URL ?>";
</script>

<!-- Shared JS -->
<script src="<?= APP_URL ?>/assets/js/technical-admin.js">
</script>
<script>
document.addEventListener("DOMContentLoaded", () => {

    if (window.lucide) {
        console.time("Lucide");
        lucide.createIcons();
        console.timeEnd("Lucide");
    }

});
</script>