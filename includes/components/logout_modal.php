<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content modal-content-custom">

            <div class="modal-body modal-body-custom p-0 text-center">

                <h3 class="modal-title mb-2" id="logoutModalLabel">

                    Confirm Logout

                </h3>

                <p class="logout-message mb-4">

                    Are you sure you want to sign out of <strong>APRISM</strong>?

                </p>

                <div class="d-flex align-items-center justify-content-center gap-3">

                    <button type="button" class="logout-cancel-btn" data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <a href="<?= APP_URL ?>/auth/logout.php" class="logout-confirm-btn text-decoration-none">

                        Logout

                    </a>

                </div>

            </div>

        </div>

    </div>

</div>