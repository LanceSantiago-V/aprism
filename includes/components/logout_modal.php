<?php if (!defined('APRISM_LOGOUT_MODAL_RENDERED')): define('APRISM_LOGOUT_MODAL_RENDERED', true); ?>
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content aprism-confirmation-surface aprism-confirmation-surface--standard">

            <div class="modal-body modal-body-custom p-0 text-center">

                <h3 class="modal-title aprism-confirmation-title mb-2" id="logoutModalLabel">

                    Confirm Logout

                </h3>

                <p class="logout-message aprism-confirmation-message mb-4">

                    Are you sure you want to sign out of <strong>APRISM</strong>?

                </p>

                <div class="aprism-confirmation-actions">

                    <button type="button" class="logout-cancel-btn aprism-confirmation-action aprism-confirmation-action--secondary" data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <a href="<?= APP_URL ?>/auth/logout.php" class="logout-confirm-btn aprism-confirmation-action aprism-confirmation-action--danger text-decoration-none">

                        Logout

                    </a>

                </div>

            </div>

        </div>

    </div>

</div>
<?php endif; ?>
