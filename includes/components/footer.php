<?php require_once __DIR__ . '/logout_modal.php'; ?>

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
<script src="<?= APP_URL ?>/assets/js/technical-admin.js?v=<?= (int) filemtime(__DIR__ . '/../../assets/js/technical-admin.js') ?>">
</script>

<!-- Local chart runtime used by query-backed APRISM analytics. -->
<script src="<?= APP_URL ?>/assets/vendor/chartjs/chart.umd.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/aprism-charts.js?v=<?= (int) filemtime(__DIR__ . '/../../assets/js/aprism-charts.js') ?>"></script>


<script>
    document.addEventListener("DOMContentLoaded", () => {

        /*
         * ==========================================================
         * LUCIDE ICONS
         * ==========================================================
         */

        if (window.lucide) {

            console.time("Lucide");

            lucide.createIcons();

            console.timeEnd("Lucide");

        }


        /*
         * ==========================================================
         * SIDEBAR AUTO-HIDING SCROLLBAR
         * ==========================================================
         *
         * The sidebar navigation remains scrollable at all times.
         *
         * The scrollbar itself is only made visible while the
         * user is actively scrolling.
         *
         * After 1.5 seconds without scrolling, the scrollbar
         * automatically returns to its hidden state.
         *
         * This applies to the shared sidebar across APRISM.
         */

        const sidebarNavigation =
            document.querySelector(".sidebar-navigation");


        if (sidebarNavigation) {

            let scrollbarTimeout = null;


            sidebarNavigation.addEventListener(
                "scroll",
                () => {

                    /*
                     * Show the scrollbar while scrolling.
                     */
                    sidebarNavigation.classList.add("is-scrolling");


                    /*
                     * Reset the hide timer whenever another
                     * scroll event occurs.
                     */
                    if (scrollbarTimeout) {

                        clearTimeout(scrollbarTimeout);

                    }


                    /*
                     * Hide the scrollbar after 1.5 seconds
                     * of no scrolling.
                     */
                    scrollbarTimeout = setTimeout(() => {

                        sidebarNavigation.classList.remove(
                            "is-scrolling"
                        );

                    }, 1500);

                },
                {
                    passive: true
                }
            );

        }

    });
</script>
