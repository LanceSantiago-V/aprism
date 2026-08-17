<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| School Year Modals
|--------------------------------------------------------------------------
|
| Academic Head → Academic Setup → School Year
|
| Includes:
| - Create School Year
| - Edit School Year
| - Activate School Year
| - Archive School Year
|
| Backend:
| handlers/academic_setup/school_year_handler.php
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

if (!function_exists('schoolYearModalValue')) {

    function schoolYearModalValue(mixed $value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


/*
|--------------------------------------------------------------------------
| Existing modal data
|--------------------------------------------------------------------------
*/

$editSchoolYear =
    $editSchoolYear ?? null;

$activateSchoolYear =
    $activateSchoolYear ?? null;

$archiveSchoolYear =
    $archiveSchoolYear ?? null;


/*
|--------------------------------------------------------------------------
| EDIT DATA
|--------------------------------------------------------------------------
*/

$editSchoolYearId =
    $editSchoolYear['school_year_id'] ?? '';

$editSchoolYearName =
    $editSchoolYear['school_year'] ?? '';

$editStartDate =
    $editSchoolYear['start_date'] ?? '';

$editEndDate =
    $editSchoolYear['end_date'] ?? '';


/*
|--------------------------------------------------------------------------
| ACTIVATE DATA
|--------------------------------------------------------------------------
*/

$activateSchoolYearId =
    $activateSchoolYear['school_year_id'] ?? '';

$activateSchoolYearName =
    $activateSchoolYear['school_year'] ?? '';


/*
|--------------------------------------------------------------------------
| ARCHIVE DATA
|--------------------------------------------------------------------------
*/

$archiveSchoolYearId =
    $archiveSchoolYear['school_year_id'] ?? '';

$archiveSchoolYearName =
    $archiveSchoolYear['school_year'] ?? '';

?>


<style>
    /* ==========================================================================
       OVERLAY
       ========================================================================== */

    .aprism-sy-modal {

        position: fixed !important;

        inset: 0 !important;

        width: 100vw !important;
        height: 100vh !important;

        z-index: 99999 !important;

        display: none !important;

        align-items: center !important;
        justify-content: center !important;

        padding: 20px !important;

        box-sizing: border-box !important;

        background: rgba(19, 39, 61, 0.42);

        backdrop-filter: blur(3px);

    }


    .aprism-sy-modal.is-open {

        display: flex !important;

    }


    /* ==========================================================================
       MODAL CARD
       ========================================================================== */

    .aprism-sy-modal-card {

        position: relative !important;

        z-index: 1 !important;

        display: block !important;

        visibility: visible !important;

        opacity: 1 !important;

        width: min(500px, calc(100vw - 40px)) !important;

        max-width: 500px !important;

        max-height: calc(100vh - 40px);

        overflow: hidden;

        margin: 0 auto !important;

        background: #ffffff !important;

        border: 1px solid #dce5ee;

        border-radius: 21px;

        box-shadow:
            0 25px 65px rgba(15, 35, 58, 0.20);

    }


    .aprism-sy-modal.is-open .aprism-sy-modal-card {

        display: block !important;

        visibility: visible !important;

        opacity: 1 !important;

        transform: none !important;

    }


    /* ==========================================================================
       CONFIRMATION MODALS
       ========================================================================== */

    #activateSchoolYearModal .aprism-sy-modal-card,
    #archiveSchoolYearModal .aprism-sy-modal-card {

        width: min(470px, calc(100vw - 40px)) !important;

        max-width: 470px !important;

    }


    /* ==========================================================================
       HEADER
       ========================================================================== */

    .aprism-sy-modal-header {

        display: flex;

        align-items: center;

        gap: 13px;

        padding: 19px 23px 16px;

        border-bottom: 1px solid #e7edf3;

    }


    .aprism-sy-modal-icon {

        width: 40px;
        height: 40px;

        flex: 0 0 40px;

        display: flex;

        align-items: center;
        justify-content: center;

        border-radius: 11px;

        background: #edf6ff;

        color: #0068b5;

    }


    .aprism-sy-modal-icon.success {

        background: #ebfaf3;

        color: #08a66b;

    }


    .aprism-sy-modal-icon.danger {

        background: #fff0f1;

        color: #e53649;

    }


    .aprism-sy-modal-icon svg {

        width: 20px;
        height: 20px;

        stroke-width: 2.2;

    }


    .aprism-sy-modal-heading {

        min-width: 0;

        flex: 1;

    }


    .aprism-sy-modal-title {

        margin: 0;

        color: #063763;

        font-size: 20px;

        line-height: 1.2;

        font-weight: 800;

        letter-spacing: -0.02em;

    }


    .aprism-sy-modal-subtitle {

        margin: 3px 0 0;

        color: #91a3b8;

        font-size: 10.5px;

        line-height: 1.2;

        font-weight: 800;

        letter-spacing: 0.04em;

        text-transform: uppercase;

    }


    .aprism-sy-modal-close {

        width: 35px;
        height: 35px;

        flex: 0 0 35px;

        display: flex;

        align-items: center;
        justify-content: center;

        border: 0;

        border-radius: 50%;

        background: #f3f7fb;

        color: #70859b;

        cursor: pointer;

    }


    .aprism-sy-modal-close:hover {

        background: #e9f0f7;

    }


    .aprism-sy-modal-close svg {

        width: 17px;
        height: 17px;

    }


    /* ==========================================================================
       BODY
       ========================================================================== */

    .aprism-sy-modal-body {

        padding: 19px 23px 20px;

        overflow-y: auto;

    }


    /* ==========================================================================
       FORM FIELDS
       ========================================================================== */

    .aprism-sy-field {

        margin-bottom: 15px;

    }


    .aprism-sy-field:last-child {

        margin-bottom: 0;

    }


    .aprism-sy-label {

        display: block;

        margin-bottom: 6px;

        color: #8ca0b5;

        font-size: 10.5px;

        line-height: 1.2;

        font-weight: 800;

        letter-spacing: 0.06em;

        text-transform: uppercase;

    }


    .aprism-sy-control {

        width: 100%;

        height: 44px;

        padding: 0 13px;

        box-sizing: border-box;

        border: 1px solid #d8e3ee;

        border-radius: 12px;

        outline: none;

        background: #fbfdff;

        color: #173b60;

        font-family: inherit;

        font-size: 14px;

        font-weight: 600;

        transition:
            border-color 0.15s ease,
            box-shadow 0.15s ease,
            background 0.15s ease;

    }


    .aprism-sy-control:focus {

        border-color: #0068b5;

        background: #ffffff;

        box-shadow:
            0 0 0 3px rgba(0, 104, 181, 0.09);

    }


    /* ==========================================================================
       READ-ONLY SCHOOL YEAR
       ========================================================================== */

    .aprism-sy-control[readonly] {

        cursor: default;

        background: #f7fafd;

        color: #173b60;

    }


    .aprism-sy-control[readonly]:focus {

        border-color: #d8e3ee;

        background: #f7fafd;

        box-shadow: none;

    }


    /* ==========================================================================
       DATE VALIDATION STATE
       ========================================================================== */

    .aprism-sy-control.is-invalid {

        border-color: #e53649;

        background: #fffafb;

        box-shadow:
            0 0 0 3px rgba(229, 54, 73, 0.08);

    }


    .aprism-sy-control.is-valid {

        border-color: #10a96f;

    }


    /* ==========================================================================
       HELPERS
       ========================================================================== */

    .aprism-sy-helper {

        margin-top: 5px;

        color: #92a4b8;

        font-size: 11.5px;

        line-height: 1.4;

    }


    .aprism-sy-suggestion {

        margin-top: 5px;

        color: #91a3b8;

        font-size: 11.5px;

        line-height: 1.4;

    }


    .aprism-sy-suggestion strong {

        color: #0068b5;

        font-weight: 800;

    }


    .aprism-sy-suggestion[hidden] {

        display: none !important;

    }


    /* ==========================================================================
       WARNING
       ========================================================================== */

    .aprism-sy-warning {

        display: none;

        margin-top: 7px;

        padding: 7px 9px;

        border-radius: 8px;

        background: #fff8e8;

        color: #936700;

        font-size: 11px;

        line-height: 1.4;

        font-weight: 600;

    }


    .aprism-sy-warning.is-visible {

        display: block;

    }


    .aprism-sy-warning.error {

        background: #fff0f1;

        color: #b42336;

    }


    /* ==========================================================================
       DATE GRID
       ========================================================================== */

    .aprism-sy-date-grid {

        display: grid;

        grid-template-columns:
            minmax(0, 1fr) minmax(0, 1fr);

        gap: 13px;

    }


    /* ==========================================================================
       DATE INPUT
       ========================================================================== */

    .aprism-sy-date-control {

        position: relative;

    }


    .aprism-sy-date-control .aprism-sy-control {

        padding-right: 40px;

    }


    .aprism-sy-date-control input[type="date"]::-webkit-calendar-picker-indicator {

        opacity: 0;

        position: absolute;

        right: 0;
        top: 0;

        width: 100%;
        height: 100%;

        cursor: pointer;

    }


    .aprism-sy-date-icon {

        position: absolute;

        right: 13px;

        top: 50%;

        transform:
            translateY(-50%);

        pointer-events: none;

        color: #173b60;

    }


    .aprism-sy-date-icon svg {

        width: 17px;
        height: 17px;

    }


    /* ==========================================================================
       CONFIRMATION CONTENT
       ========================================================================== */

    .aprism-sy-confirm-content {

        min-width: 0;

    }


    .aprism-sy-confirm-title {

        margin: 0 0 7px;

        color: #173b60;

        font-size: 16px;

        line-height: 1.35;

        font-weight: 800;

    }


    .aprism-sy-confirm-title strong {

        color: #063763;

        font-weight: 800;

    }


    .aprism-sy-confirm-text {

        margin: 0;

        color: #718399;

        font-size: 13px;

        line-height: 1.55;

    }


    .aprism-sy-confirm-text strong {

        color: #173b60;

        font-weight: 800;

    }


    /* ==========================================================================
       FOOTER
       ========================================================================== */

    .aprism-sy-modal-footer {

        display: flex;

        align-items: center;

        justify-content: flex-end;

        gap: 9px;

        padding: 14px 23px;

        border-top: 1px solid #e7edf3;

        background: #ffffff;

    }


    .aprism-sy-button {

        height: 41px;

        min-width: 106px;

        display: inline-flex;

        align-items: center;
        justify-content: center;

        gap: 6px;

        padding: 0 17px;

        border-radius: 10px;

        font-family: inherit;

        font-size: 12.5px;

        font-weight: 800;

        cursor: pointer;

        transition:
            transform 0.12s ease,
            box-shadow 0.12s ease,
            background 0.12s ease;

    }


    .aprism-sy-button:hover {

        transform:
            translateY(-1px);

    }


    .aprism-sy-button:disabled {

        opacity: 0.55;

        cursor: not-allowed;

        transform: none !important;

        box-shadow: none !important;

    }


    .aprism-sy-button svg {

        width: 15px;
        height: 15px;

        stroke-width: 2.5;

    }


    .aprism-sy-button-secondary {

        border: 1px solid #d8e3ee;

        background: #f7fafd;

        color: #52677d;

    }


    .aprism-sy-button-secondary:hover {

        background: #eef4f9;

    }


    .aprism-sy-button-primary {

        border: 1px solid #0068b5;

        background: #0068b5;

        color: #ffffff;

        box-shadow:
            0 5px 12px rgba(0, 104, 181, 0.17);

    }


    .aprism-sy-button-primary:hover {

        background: #005b9f;

    }


    .aprism-sy-button-success {

        border: 1px solid #10a96f;

        background: #10a96f;

        color: #ffffff;

        box-shadow:
            0 5px 12px rgba(16, 169, 111, 0.16);

    }


    .aprism-sy-button-danger {

        border: 1px solid #e53649;

        background: #e53649;

        color: #ffffff;

        box-shadow:
            0 5px 12px rgba(229, 54, 73, 0.15);

    }


    /* ==========================================================================
       RESPONSIVE
       ========================================================================== */

    @media (max-width: 600px) {

        .aprism-sy-modal {

            padding: 12px !important;

        }


        .aprism-sy-modal-card,
        #activateSchoolYearModal .aprism-sy-modal-card,
        #archiveSchoolYearModal .aprism-sy-modal-card {

            width: 100% !important;

            max-width: 100% !important;

            border-radius: 19px;

        }


        .aprism-sy-date-grid {

            grid-template-columns: 1fr;

        }


        .aprism-sy-modal-header {

            padding-left: 19px;
            padding-right: 19px;

        }


        .aprism-sy-modal-body {

            padding-left: 19px;
            padding-right: 19px;

        }


        .aprism-sy-modal-footer {

            padding-left: 19px;
            padding-right: 19px;

        }

    }
</style>


<!-- ==========================================================================
     CREATE SCHOOL YEAR
     ========================================================================== -->

<div class="aprism-sy-modal" id="createSchoolYearModal" aria-hidden="true">

    <div class="aprism-sy-modal-card" role="dialog" aria-modal="true" aria-labelledby="createSchoolYearModalTitle">

        <form method="POST" action="<?= htmlspecialchars(
            defined('APP_URL')
            ? APP_URL . '/handlers/academic_setup/school_year_handler.php'
            : '../handlers/academic_setup/school_year_handler.php',
            ENT_QUOTES,
            'UTF-8'
        ) ?>">

            <input type="hidden" name="action" value="create">


            <div class="aprism-sy-modal-header">

                <div class="aprism-sy-modal-icon">

                    <i data-lucide="calendar-plus"></i>

                </div>


                <div class="aprism-sy-modal-heading">

                    <h2 class="aprism-sy-modal-title" id="createSchoolYearModalTitle">
                        Create School Year
                    </h2>

                    <p class="aprism-sy-modal-subtitle">
                        Academic Calendar Configuration
                    </p>

                </div>


                <button type="button" class="aprism-sy-modal-close" data-school-year-close aria-label="Close">

                    <i data-lucide="x"></i>

                </button>

            </div>


            <div class="aprism-sy-modal-body">

                <!-- SCHOOL YEAR -->

                <div class="aprism-sy-field">

                    <label class="aprism-sy-label" for="createSchoolYear">
                        School Year
                    </label>


                    <input type="text" class="aprism-sy-control" id="createSchoolYear" name="school_year"
                        placeholder="YYYY-YYYY" maxlength="9" autocomplete="off" readonly required>


                    <div class="aprism-sy-helper">

                        Automatically follows the Start Date.

                    </div>

                </div>


                <!-- STATUS -->

                <div class="aprism-sy-field">

                    <label class="aprism-sy-label" for="createSchoolYearStatus">
                        Status
                    </label>


                    <input type="text" class="aprism-sy-control" id="createSchoolYearStatus" value="Inactive" readonly>


                    <div class="aprism-sy-helper">

                        New School Years are created as Inactive.
                        Activation is a separate controlled action.

                    </div>

                </div>


                <!-- DATES -->

                <div class="aprism-sy-date-grid">


                    <!-- START DATE -->

                    <div class="aprism-sy-field">

                        <label class="aprism-sy-label" for="createStartDate">
                            Start Date
                        </label>


                        <div class="aprism-sy-date-control">

                            <input type="date" class="aprism-sy-control" id="createStartDate" name="start_date"
                                required>


                            <span class="aprism-sy-date-icon" aria-hidden="true">

                                <i data-lucide="calendar"></i>

                            </span>

                        </div>

                    </div>


                    <!-- END DATE -->

                    <div class="aprism-sy-field">

                        <label class="aprism-sy-label" for="createEndDate">
                            End Date
                        </label>


                        <div class="aprism-sy-date-control">

                            <input type="date" class="aprism-sy-control" id="createEndDate" name="end_date" required>


                            <span class="aprism-sy-date-icon" aria-hidden="true">

                                <i data-lucide="calendar"></i>

                            </span>

                        </div>


                        <div class="aprism-sy-suggestion" id="createEndDateSuggestion" hidden>

                            Suggested End Date year:
                            <strong id="createSuggestedEndYear">
                                —
                            </strong>

                        </div>


                        <div class="aprism-sy-warning" id="createSchoolYearWarning"></div>

                    </div>

                </div>

            </div>


            <div class="aprism-sy-modal-footer">

                <button type="button" class="aprism-sy-button aprism-sy-button-secondary" data-school-year-close>
                    Cancel
                </button>


                <button type="submit" class="aprism-sy-button aprism-sy-button-primary">

                    <i data-lucide="check"></i>

                    Save School Year

                </button>

            </div>

        </form>

    </div>

</div>


<!-- ==========================================================================
     EDIT SCHOOL YEAR
     ========================================================================== -->

<div class="aprism-sy-modal" id="editSchoolYearModal" aria-hidden="true">

    <div class="aprism-sy-modal-card" role="dialog" aria-modal="true" aria-labelledby="editSchoolYearModalTitle">

        <form method="POST" action="<?= htmlspecialchars(
            defined('APP_URL')
            ? APP_URL . '/handlers/academic_setup/school_year_handler.php'
            : '../handlers/academic_setup/school_year_handler.php',
            ENT_QUOTES,
            'UTF-8'
        ) ?>">

            <input type="hidden" name="action" value="update">


            <input type="hidden" name="school_year_id" id="editSchoolYearId"
                value="<?= schoolYearModalValue($editSchoolYearId) ?>">


            <div class="aprism-sy-modal-header">

                <div class="aprism-sy-modal-icon">

                    <i data-lucide="calendar"></i>

                </div>


                <div class="aprism-sy-modal-heading">

                    <h2 class="aprism-sy-modal-title" id="editSchoolYearModalTitle">
                        Edit School Year
                    </h2>

                    <p class="aprism-sy-modal-subtitle">
                        Update Academic Calendar
                    </p>

                </div>


                <button type="button" class="aprism-sy-modal-close" data-school-year-close aria-label="Close">

                    <i data-lucide="x"></i>

                </button>

            </div>


            <div class="aprism-sy-modal-body">

                <!-- SCHOOL YEAR -->

                <div class="aprism-sy-field">

                    <label class="aprism-sy-label" for="editSchoolYear">
                        School Year
                    </label>


                    <input type="text" class="aprism-sy-control" id="editSchoolYear" name="school_year"
                        value="<?= schoolYearModalValue($editSchoolYearName) ?>" maxlength="9" autocomplete="off"
                        readonly required>


                    <div class="aprism-sy-helper">
                        Automatically follows the Start Date.
                    </div>

                </div>


                <!-- DATES -->

                <div class="aprism-sy-date-grid">


                    <!-- START DATE -->

                    <div class="aprism-sy-field">

                        <label class="aprism-sy-label" for="editStartDate">
                            Start Date
                        </label>


                        <div class="aprism-sy-date-control">

                            <input type="date" class="aprism-sy-control" id="editStartDate" name="start_date"
                                value="<?= schoolYearModalValue($editStartDate) ?>" required>


                            <span class="aprism-sy-date-icon" aria-hidden="true">

                                <i data-lucide="calendar"></i>

                            </span>

                        </div>

                    </div>


                    <!-- END DATE -->

                    <div class="aprism-sy-field">

                        <label class="aprism-sy-label" for="editEndDate">
                            End Date
                        </label>


                        <div class="aprism-sy-date-control">

                            <input type="date" class="aprism-sy-control" id="editEndDate" name="end_date"
                                value="<?= schoolYearModalValue($editEndDate) ?>" required>


                            <span class="aprism-sy-date-icon" aria-hidden="true">

                                <i data-lucide="calendar"></i>

                            </span>

                        </div>


                        <div class="aprism-sy-suggestion" id="editEndDateSuggestion" hidden>

                            Suggested End Date year:
                            <strong id="editSuggestedEndYear">
                                —
                            </strong>

                        </div>


                        <div class="aprism-sy-warning" id="editSchoolYearWarning"></div>

                    </div>

                </div>

            </div>


            <div class="aprism-sy-modal-footer">

                <button type="button" class="aprism-sy-button aprism-sy-button-secondary" data-school-year-close>
                    Cancel
                </button>


                <button type="submit" class="aprism-sy-button aprism-sy-button-primary">

                    <i data-lucide="save"></i>

                    Save Changes

                </button>

            </div>

        </form>

    </div>

</div>


<!-- ==========================================================================
     ACTIVATE SCHOOL YEAR
     ========================================================================== -->

<div class="aprism-sy-modal" id="activateSchoolYearModal" aria-hidden="true">

    <div class="aprism-sy-modal-card" role="dialog" aria-modal="true">

        <form method="POST" action="<?= htmlspecialchars(
            defined('APP_URL')
            ? APP_URL . '/handlers/academic_setup/school_year_handler.php'
            : '../handlers/academic_setup/school_year_handler.php',
            ENT_QUOTES,
            'UTF-8'
        ) ?>">

            <input type="hidden" name="action" value="activate">


            <input type="hidden" name="school_year_id" id="activateSchoolYearId"
                value="<?= schoolYearModalValue($activateSchoolYearId) ?>">


            <div class="aprism-sy-modal-header">

                <div class="aprism-sy-modal-icon success">

                    <i data-lucide="calendar-check"></i>

                </div>


                <div class="aprism-sy-modal-heading">

                    <h2 class="aprism-sy-modal-title">
                        Activate School Year
                    </h2>

                    <p class="aprism-sy-modal-subtitle">
                        Set Current Operational Period
                    </p>

                </div>


                <button type="button" class="aprism-sy-modal-close" data-school-year-close aria-label="Close">

                    <i data-lucide="x"></i>

                </button>

            </div>


            <div class="aprism-sy-modal-body">

                <div class="aprism-sy-confirm-content">

                    <h3 class="aprism-sy-confirm-title">

                        Activate
                        <strong id="activateSchoolYearName">
                            <?= schoolYearModalValue($activateSchoolYearName) ?>
                        </strong>?

                    </h3>


                    <p class="aprism-sy-confirm-text">

                        This will make the selected School Year
                        the current institutional academic context used
                        by APRISM. The School Year's <strong>Start Date
                            must already have been reached</strong>, and if
                        another School Year is currently Active, that
                        School Year's <strong>End Date must already have
                            been reached</strong>. Once the replacement is
                        activated, the previous Active School Year will
                        be archived. Its Academic Periods and historical
                        records will remain preserved.

                    </p>

                </div>

            </div>


            <div class="aprism-sy-modal-footer">

                <button type="button" class="aprism-sy-button aprism-sy-button-secondary" data-school-year-close>
                    Cancel
                </button>


                <button type="submit" class="aprism-sy-button aprism-sy-button-success">

                    <i data-lucide="check"></i>

                    Activate

                </button>

            </div>

        </form>

    </div>

</div>


<!-- ==========================================================================
     ARCHIVE SCHOOL YEAR
     ========================================================================== -->

<div class="aprism-sy-modal" id="archiveSchoolYearModal" aria-hidden="true">

    <div class="aprism-sy-modal-card" role="dialog" aria-modal="true">

        <form method="POST" action="<?= htmlspecialchars(
            defined('APP_URL')
            ? APP_URL . '/handlers/academic_setup/school_year_handler.php'
            : '../handlers/academic_setup/school_year_handler.php',
            ENT_QUOTES,
            'UTF-8'
        ) ?>">

            <input type="hidden" name="action" value="archive">


            <input type="hidden" name="school_year_id" id="archiveSchoolYearId"
                value="<?= schoolYearModalValue($archiveSchoolYearId) ?>">


            <div class="aprism-sy-modal-header">

                <div class="aprism-sy-modal-icon danger">

                    <i data-lucide="archive"></i>

                </div>


                <div class="aprism-sy-modal-heading">

                    <h2 class="aprism-sy-modal-title">
                        Archive School Year
                    </h2>

                    <p class="aprism-sy-modal-subtitle">
                        Preserve Academic History
                    </p>

                </div>


                <button type="button" class="aprism-sy-modal-close" data-school-year-close aria-label="Close">

                    <i data-lucide="x"></i>

                </button>

            </div>


            <div class="aprism-sy-modal-body">

                <div class="aprism-sy-confirm-content">

                    <h3 class="aprism-sy-confirm-title">

                        Archive
                        <strong id="archiveSchoolYearName">
                            <?= schoolYearModalValue($archiveSchoolYearName) ?>
                        </strong>?

                    </h3>


                    <p class="aprism-sy-confirm-text">

                        This School Year will remain available
                        for historical records and reports, but
                        will no longer be available for new
                        academic operations.

                    </p>

                </div>

            </div>


            <div class="aprism-sy-modal-footer">

                <button type="button" class="aprism-sy-button aprism-sy-button-secondary" data-school-year-close>
                    Cancel
                </button>


                <button type="submit" class="aprism-sy-button aprism-sy-button-danger">

                    <i data-lucide="archive"></i>

                    Archive

                </button>

            </div>

        </form>

    </div>

</div>


<script>

    (function () {

        /*
        |--------------------------------------------------------------------------
        | DATE PARSER
        |--------------------------------------------------------------------------
        */

        function parseDateParts(value) {

            if (!value) {

                return null;

            }


            const parts = value.split('-');


            if (parts.length !== 3) {

                return null;

            }


            const year = Number(parts[0]);
            const month = Number(parts[1]);
            const day = Number(parts[2]);


            if (
                !year ||
                !month ||
                !day
            ) {

                return null;

            }


            return {
                year: year,
                month: month,
                day: day
            };

        }


        /*
        |--------------------------------------------------------------------------
        | DATE ORDER VALIDATION
        |--------------------------------------------------------------------------
        |
        | School Year dates must satisfy:
        |
        | Start Date <= End Date
        |
        | This is only a client-side convenience check.
        | The backend remains the final authority.
        |
        */

        function validateSchoolYearDates(
            startInput,
            endInput,
            warningElement,
            submitButton = null
        ) {

            if (!startInput || !endInput) {

                return true;

            }


            const startValue =
                startInput.value;

            const endValue =
                endInput.value;


            /*
            |--------------------------------------------------------------------------
            | CLEAR STATE WHEN ONE DATE IS NOT YET PROVIDED
            |--------------------------------------------------------------------------
            */

            if (
                !startValue ||
                !endValue
            ) {

                startInput.classList.remove(
                    'is-invalid',
                    'is-valid'
                );

                endInput.classList.remove(
                    'is-invalid',
                    'is-valid'
                );


                if (warningElement) {

                    warningElement.textContent = '';

                    warningElement.classList.remove(
                        'is-visible',
                        'error'
                    );

                }


                if (submitButton) {

                    submitButton.disabled =
                        false;

                }


                return true;

            }


            const startParts =
                parseDateParts(
                    startValue
                );


            const endParts =
                parseDateParts(
                    endValue
                );


            if (
                !startParts ||
                !endParts
            ) {

                return true;

            }


            const startDate =
                new Date(
                    startParts.year,
                    startParts.month - 1,
                    startParts.day
                );


            const endDate =
                new Date(
                    endParts.year,
                    endParts.month - 1,
                    endParts.day
                );


            if (startDate > endDate) {

                startInput.classList.add(
                    'is-invalid'
                );

                startInput.classList.remove(
                    'is-valid'
                );


                endInput.classList.add(
                    'is-invalid'
                );

                endInput.classList.remove(
                    'is-valid'
                );


                if (warningElement) {

                    warningElement.textContent =
                        'Start Date must be before or equal to End Date.';

                    warningElement.classList.add(
                        'is-visible',
                        'error'
                    );

                }


                if (submitButton) {

                    submitButton.disabled =
                        true;

                }


                return false;

            }


            startInput.classList.remove(
                'is-invalid'
            );


            endInput.classList.remove(
                'is-invalid'
            );


            startInput.classList.add(
                'is-valid'
            );


            endInput.classList.add(
                'is-valid'
            );


            if (warningElement) {

                /*
                 * Do not clear a non-date warning here.
                 * updateSchoolYearWarning() is responsible for
                 * the informational multi-year warning.
                 */

                if (
                    warningElement.classList.contains(
                        'error'
                    )
                ) {

                    warningElement.textContent = '';

                    warningElement.classList.remove(
                        'is-visible',
                        'error'
                    );

                }

            }


            if (submitButton) {

                submitButton.disabled =
                    false;

            }


            return true;

        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE SCHOOL YEAR
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | Start Date:
        | 2026-06-01
        |
        | School Year:
        | 2026-2027
        |
        | For CREATE:
        | End Date may automatically receive the next-year value.
        |
        | For EDIT:
        | updateEndDateYear is FALSE so the existing End Date is
        | never silently modified.
        |
        */

        function updateSchoolYear(
            startInput,
            schoolYearInput,
            endInput,
            suggestedYearElement,
            warningElement,
            updateEndDateYear,
            submitButton = null
        ) {

            if (
                !startInput ||
                !schoolYearInput
            ) {

                return;

            }


            const startParts =
                parseDateParts(
                    startInput.value
                );


            /*
            |--------------------------------------------------------------------------
            | NO START DATE
            |--------------------------------------------------------------------------
            */

            if (!startParts) {

                schoolYearInput.value = '';


                if (suggestedYearElement) {

                    suggestedYearElement.textContent = '—';


                    const suggestionContainer =
                        suggestedYearElement.closest(
                            '.aprism-sy-suggestion'
                        );


                    if (suggestionContainer) {

                        suggestionContainer.hidden = true;

                    }

                }


                if (warningElement) {

                    warningElement.textContent = '';

                    warningElement.classList.remove(
                        'is-visible',
                        'error'
                    );

                }


                if (submitButton) {

                    submitButton.disabled =
                        false;

                }


                return;

            }


            const startYear =
                startParts.year;


            const nextYear =
                startYear + 1;


            /*
            |--------------------------------------------------------------------------
            | SCHOOL YEAR
            |--------------------------------------------------------------------------
            */

            schoolYearInput.value =
                startYear +
                '-' +
                nextYear;


            /*
            |--------------------------------------------------------------------------
            | SUGGESTED END YEAR
            |--------------------------------------------------------------------------
            */

            if (suggestedYearElement) {

                suggestedYearElement.textContent =
                    nextYear;


                const suggestionContainer =
                    suggestedYearElement.closest(
                        '.aprism-sy-suggestion'
                    );


                if (suggestionContainer) {

                    suggestionContainer.hidden =
                        false;

                }

            }


            /*
            |--------------------------------------------------------------------------
            | CHANGE END DATE YEAR ONLY WHEN EXPLICITLY ALLOWED
            |--------------------------------------------------------------------------
            |
            | CREATE = true
            | EDIT   = false
            |
            */

            if (
                updateEndDateYear &&
                endInput &&
                endInput.value
            ) {

                const endParts =
                    parseDateParts(
                        endInput.value
                    );


                if (endParts) {

                    const month =
                        String(
                            endParts.month
                        ).padStart(2, '0');


                    const day =
                        String(
                            endParts.day
                        ).padStart(2, '0');


                    endInput.value =
                        nextYear +
                        '-' +
                        month +
                        '-' +
                        day;

                }

            }


            updateSchoolYearWarning(
                startYear,
                endInput,
                warningElement
            );


            validateSchoolYearDates(
                startInput,
                endInput,
                warningElement,
                submitButton
            );

        }


        /*
        |--------------------------------------------------------------------------
        | WARNING
        |--------------------------------------------------------------------------
        */

        function updateSchoolYearWarning(
            startYear,
            endInput,
            warningElement
        ) {

            if (!warningElement) {

                return;

            }


            /*
            * Do not overwrite a hard date-order error.
            */

            if (
                warningElement.classList.contains(
                    'error'
                )
            ) {

                return;

            }


            if (
                !startYear ||
                !endInput ||
                !endInput.value
            ) {

                warningElement.textContent = '';

                warningElement.classList.remove(
                    'is-visible'
                );

                return;

            }


            const endParts =
                parseDateParts(
                    endInput.value
                );


            if (!endParts) {

                warningElement.textContent = '';

                warningElement.classList.remove(
                    'is-visible'
                );

                return;

            }


            const yearDifference =
                endParts.year -
                startYear;


            if (yearDifference > 1) {

                warningElement.textContent =
                    'This academic period spans more than one year. Please verify the dates if this is intentional.';

                warningElement.classList.add(
                    'is-visible'
                );

            } else {

                warningElement.textContent = '';

                warningElement.classList.remove(
                    'is-visible'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | RESET CREATE MODAL
        |--------------------------------------------------------------------------
        */

        function resetCreateModal() {

            const schoolYear =
                document.getElementById(
                    'createSchoolYear'
                );


            const status =
                document.getElementById(
                    'createSchoolYearStatus'
                );


            const start =
                document.getElementById(
                    'createStartDate'
                );


            const end =
                document.getElementById(
                    'createEndDate'
                );


            const suggested =
                document.getElementById(
                    'createSuggestedEndYear'
                );


            const warning =
                document.getElementById(
                    'createSchoolYearWarning'
                );


            const submitButton =
                document.querySelector(
                    '#createSchoolYearModal button[type="submit"]'
                );


            if (schoolYear) {

                schoolYear.value = '';

            }


            if (status) {

                status.value = 'Inactive';

            }


            if (start) {

                start.value = '';

                start.classList.remove(
                    'is-invalid',
                    'is-valid'
                );

            }


            if (end) {

                end.value = '';

                end.classList.remove(
                    'is-invalid',
                    'is-valid'
                );

            }


            if (suggested) {

                suggested.textContent = '—';


                const suggestionContainer =
                    suggested.closest(
                        '.aprism-sy-suggestion'
                    );


                if (suggestionContainer) {

                    suggestionContainer.hidden = true;

                }

            }


            if (warning) {

                warning.textContent = '';

                warning.classList.remove(
                    'is-visible',
                    'error'
                );

            }


            if (submitButton) {

                submitButton.disabled =
                    false;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | OPEN MODAL
        |--------------------------------------------------------------------------
        */

        function openModal(modal) {

            if (!modal) {

                return;

            }


            modal.classList.add(
                'is-open'
            );


            modal.style.display = 'flex';

            modal.style.visibility = 'visible';

            modal.style.opacity = '1';


            const card =
                modal.querySelector(
                    '.aprism-sy-modal-card'
                );


            if (card) {

                card.style.display = 'block';

                card.style.visibility = 'visible';

                card.style.opacity = '1';

            }


            modal.setAttribute(
                'aria-hidden',
                'false'
            );


            document.body.style.overflow =
                'hidden';


            if (window.lucide) {

                lucide.createIcons();

            }

        }


        /*
        |--------------------------------------------------------------------------
        | CLOSE MODAL
        |--------------------------------------------------------------------------
        */

        function closeModal(modal) {

            if (!modal) {

                return;

            }


            modal.classList.remove(
                'is-open'
            );


            modal.style.display = '';

            modal.style.visibility = '';

            modal.style.opacity = '';


            const card =
                modal.querySelector(
                    '.aprism-sy-modal-card'
                );


            if (card) {

                card.style.display = '';

                card.style.visibility = '';

                card.style.opacity = '';

            }


            modal.setAttribute(
                'aria-hidden',
                'true'
            );


            if (
                !document.querySelector(
                    '.aprism-sy-modal.is-open'
                )
            ) {

                document.body.style.overflow = '';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | OPEN MODAL THROUGH EVENT DELEGATION
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'click',
            function (event) {

                const trigger =
                    event.target.closest(
                        '[data-school-year-modal]'
                    );


                if (!trigger) {

                    return;

                }


                event.preventDefault();


                const modalType =
                    trigger.getAttribute(
                        'data-school-year-modal'
                    );


                if (!modalType) {

                    return;

                }


                const modal =
                    document.getElementById(
                        modalType +
                        'SchoolYearModal'
                    );


                if (!modal) {

                    console.warn(
                        'APRISM: School Year modal not found:',
                        modalType
                    );

                    return;

                }


                const id =
                    trigger.getAttribute(
                        'data-school-year-id'
                    );


                const name =
                    trigger.getAttribute(
                        'data-school-year-name'
                    );


                /*
                |--------------------------------------------------------------------------
                | CREATE
                |--------------------------------------------------------------------------
                */

                if (
                    modalType === 'create'
                ) {

                    resetCreateModal();

                }


                /*
                |--------------------------------------------------------------------------
                | EDIT SCHOOL YEAR
                |--------------------------------------------------------------------------
                */

                if (
                    modalType === 'edit'
                ) {

                    const idInput =
                        document.getElementById(
                            'editSchoolYearId'
                        );


                    const nameInput =
                        document.getElementById(
                            'editSchoolYear'
                        );


                    const startInput =
                        document.getElementById(
                            'editStartDate'
                        );


                    const endInput =
                        document.getElementById(
                            'editEndDate'
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | USE THE EXISTING SCHOOL YEAR PAGE ATTRIBUTES
                    |--------------------------------------------------------------------------
                    */

                    const schoolYearValue =
                        trigger.getAttribute(
                            'data-school-year'
                        );


                    const startDateValue =
                        trigger.getAttribute(
                            'data-start-date'
                        );


                    const endDateValue =
                        trigger.getAttribute(
                            'data-end-date'
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | POPULATE EDIT MODAL
                    |--------------------------------------------------------------------------
                    */

                    if (
                        idInput &&
                        id !== null
                    ) {

                        idInput.value =
                            id;

                    }


                    if (
                        nameInput &&
                        schoolYearValue !== null
                    ) {

                        nameInput.value =
                            schoolYearValue;

                    }


                    if (
                        startInput &&
                        startDateValue !== null
                    ) {

                        startInput.value =
                            startDateValue;

                    }


                    if (
                        endInput &&
                        endDateValue !== null
                    ) {

                        endInput.value =
                            endDateValue;

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | INITIAL EDIT STATE
                    |--------------------------------------------------------------------------
                    |
                    | Do NOT modify the existing End Date merely because
                    | the Edit modal was opened.
                    |
                    */

                    updateSchoolYear(
                        startInput,
                        nameInput,
                        endInput,
                        document.getElementById(
                            'editSuggestedEndYear'
                        ),
                        document.getElementById(
                            'editSchoolYearWarning'
                        ),
                        false,
                        document.querySelector(
                            '#editSchoolYearModal button[type="submit"]'
                        )
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | ACTIVATE
                |--------------------------------------------------------------------------
                */

                if (
                    modalType === 'activate'
                ) {

                    const idInput =
                        document.getElementById(
                            'activateSchoolYearId'
                        );


                    const nameElement =
                        document.getElementById(
                            'activateSchoolYearName'
                        );


                    if (
                        idInput &&
                        id !== null
                    ) {

                        idInput.value =
                            id;

                    }


                    if (
                        nameElement &&
                        name !== null
                    ) {

                        nameElement.textContent =
                            name;

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | ARCHIVE
                |--------------------------------------------------------------------------
                */

                if (
                    modalType === 'archive'
                ) {

                    const idInput =
                        document.getElementById(
                            'archiveSchoolYearId'
                        );


                    const nameElement =
                        document.getElementById(
                            'archiveSchoolYearName'
                        );


                    if (
                        idInput &&
                        id !== null
                    ) {

                        idInput.value =
                            id;

                    }


                    if (
                        nameElement &&
                        name !== null
                    ) {

                        nameElement.textContent =
                            name;

                    }

                }


                openModal(modal);

            },
            false
        );


        /*
        |--------------------------------------------------------------------------
        | BOOTSTRAP MODAL DATA POPULATION
        |--------------------------------------------------------------------------
        |
        | The School Year list already uses Bootstrap's:
        |
        | data-bs-toggle="modal"
        | data-bs-target="#editSchoolYearModal"
        |
        | This handler reads the existing data attributes from
        | the clicked School Year action button.
        |
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'show.bs.modal',
            function (event) {

                const modal =
                    event.target;


                if (
                    !modal ||
                    !modal.classList.contains(
                        'aprism-sy-modal'
                    )
                ) {

                    return;

                }


                const trigger =
                    event.relatedTarget;


                if (!trigger) {

                    return;

                }


                /*
                |--------------------------------------------------------------------------
                | EDIT SCHOOL YEAR
                |--------------------------------------------------------------------------
                */

                if (
                    modal.id ===
                    'editSchoolYearModal'
                ) {

                    const idInput =
                        document.getElementById(
                            'editSchoolYearId'
                        );


                    const nameInput =
                        document.getElementById(
                            'editSchoolYear'
                        );


                    const startInput =
                        document.getElementById(
                            'editStartDate'
                        );


                    const endInput =
                        document.getElementById(
                            'editEndDate'
                        );


                    const schoolYear =
                        trigger.getAttribute(
                            'data-school-year'
                        );


                    const startDate =
                        trigger.getAttribute(
                            'data-start-date'
                        );


                    const endDate =
                        trigger.getAttribute(
                            'data-end-date'
                        );


                    const schoolYearId =
                        trigger.getAttribute(
                            'data-school-year-id'
                        );


                    if (idInput) {

                        idInput.value =
                            schoolYearId || '';

                    }


                    if (nameInput) {

                        nameInput.value =
                            schoolYear || '';

                    }


                    if (startInput) {

                        startInput.value =
                            startDate || '';

                    }


                    if (endInput) {

                        endInput.value =
                            endDate || '';

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | REFRESH DISPLAY ONLY
                    |--------------------------------------------------------------------------
                    |
                    | false = do not automatically modify the saved End Date
                    | merely because the modal was opened.
                    |
                    */

                    updateSchoolYear(
                        startInput,
                        nameInput,
                        endInput,
                        document.getElementById(
                            'editSuggestedEndYear'
                        ),
                        document.getElementById(
                            'editSchoolYearWarning'
                        ),
                        false,
                        document.querySelector(
                            '#editSchoolYearModal button[type="submit"]'
                        )
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | ACTIVATE SCHOOL YEAR
                |--------------------------------------------------------------------------
                */

                if (
                    modal.id ===
                    'activateSchoolYearModal'
                ) {

                    const idInput =
                        document.getElementById(
                            'activateSchoolYearId'
                        );


                    const nameElement =
                        document.getElementById(
                            'activateSchoolYearName'
                        );


                    const schoolYearId =
                        trigger.getAttribute(
                            'data-school-year-id'
                        );


                    const schoolYear =
                        trigger.getAttribute(
                            'data-school-year'
                        );


                    if (idInput) {

                        idInput.value =
                            schoolYearId || '';

                    }


                    if (nameElement) {

                        nameElement.textContent =
                            schoolYear || '';

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | ARCHIVE SCHOOL YEAR
                |--------------------------------------------------------------------------
                */

                if (
                    modal.id ===
                    'archiveSchoolYearModal'
                ) {

                    const idInput =
                        document.getElementById(
                            'archiveSchoolYearId'
                        );


                    const nameElement =
                        document.getElementById(
                            'archiveSchoolYearName'
                        );


                    const schoolYearId =
                        trigger.getAttribute(
                            'data-school-year-id'
                        );


                    const schoolYear =
                        trigger.getAttribute(
                            'data-school-year'
                        );


                    if (idInput) {

                        idInput.value =
                            schoolYearId || '';

                    }


                    if (nameElement) {

                        nameElement.textContent =
                            schoolYear || '';

                    }

                }

            },
            false
        );


        /*
        |--------------------------------------------------------------------------
        | CLOSE BUTTONS
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'click',
            function (event) {

                const closeButton =
                    event.target.closest(
                        '[data-school-year-close]'
                    );


                if (!closeButton) {

                    return;

                }


                event.preventDefault();


                closeModal(
                    closeButton.closest(
                        '.aprism-sy-modal'
                    )
                );

            },
            false
        );


        /*
        |--------------------------------------------------------------------------
        | CLICK OUTSIDE
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'click',
            function (event) {

                if (
                    !event.target.classList.contains(
                        'aprism-sy-modal'
                    )
                ) {

                    return;

                }


                closeModal(
                    event.target
                );

            },
            false
        );


        /*
        |--------------------------------------------------------------------------
        | ESCAPE KEY
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            function (event) {

                if (
                    event.key !== 'Escape'
                ) {

                    return;

                }


                const modal =
                    document.querySelector(
                        '.aprism-sy-modal.is-open'
                    );


                if (!modal) {

                    return;

                }


                closeModal(modal);

            },
            false
        );


        /*
        |--------------------------------------------------------------------------
        | CREATE DATE EVENTS
        |--------------------------------------------------------------------------
        */

        const createStartDate =
            document.getElementById(
                'createStartDate'
            );


        const createEndDate =
            document.getElementById(
                'createEndDate'
            );


        const createSchoolYear =
            document.getElementById(
                'createSchoolYear'
            );


        const createSuggestedEndYear =
            document.getElementById(
                'createSuggestedEndYear'
            );


        const createWarning =
            document.getElementById(
                'createSchoolYearWarning'
            );


        const createSubmitButton =
            document.querySelector(
                '#createSchoolYearModal button[type="submit"]'
            );


        if (
            createStartDate &&
            createSchoolYear
        ) {

            ['change', 'input'].forEach(
                function (eventName) {

                    createStartDate.addEventListener(
                        eventName,
                        function () {

                            updateSchoolYear(
                                createStartDate,
                                createSchoolYear,
                                createEndDate,
                                createSuggestedEndYear,
                                createWarning,
                                true,
                                createSubmitButton
                            );

                        }
                    );

                }
            );

        }


        if (createEndDate) {

            ['change', 'input'].forEach(
                function (eventName) {

                    createEndDate.addEventListener(
                        eventName,
                        function () {

                            const startParts =
                                parseDateParts(
                                    createStartDate
                                        ? createStartDate.value
                                        : ''
                                );


                            updateSchoolYearWarning(
                                startParts
                                    ? startParts.year
                                    : null,
                                createEndDate,
                                createWarning
                            );


                            validateSchoolYearDates(
                                createStartDate,
                                createEndDate,
                                createWarning,
                                createSubmitButton
                            );

                        }
                    );

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | EDIT DATE EVENTS
        |--------------------------------------------------------------------------
        */

        const editStartDate =
            document.getElementById(
                'editStartDate'
            );


        const editEndDate =
            document.getElementById(
                'editEndDate'
            );


        const editSchoolYear =
            document.getElementById(
                'editSchoolYear'
            );


        const editSuggestedEndYear =
            document.getElementById(
                'editSuggestedEndYear'
            );


        const editWarning =
            document.getElementById(
                'editSchoolYearWarning'
            );


        const editSubmitButton =
            document.querySelector(
                '#editSchoolYearModal button[type="submit"]'
            );


        if (
            editStartDate &&
            editSchoolYear
        ) {

            ['change', 'input'].forEach(
                function (eventName) {

                    editStartDate.addEventListener(
                        eventName,
                        function () {

                            /*
                            |--------------------------------------------------------------------------
                            | IMPORTANT:
                            |
                            | false means the existing End Date is NOT
                            | automatically modified when editing.
                            |--------------------------------------------------------------------------
                            */

                            updateSchoolYear(
                                editStartDate,
                                editSchoolYear,
                                editEndDate,
                                editSuggestedEndYear,
                                editWarning,
                                false,
                                editSubmitButton
                            );

                        }
                    );

                }
            );

        }


        if (editEndDate) {

            ['change', 'input'].forEach(
                function (eventName) {

                    editEndDate.addEventListener(
                        eventName,
                        function () {

                            const startParts =
                                parseDateParts(
                                    editStartDate
                                        ? editStartDate.value
                                        : ''
                                );


                            updateSchoolYearWarning(
                                startParts
                                    ? startParts.year
                                    : null,
                                editEndDate,
                                editWarning
                            );


                            validateSchoolYearDates(
                                editStartDate,
                                editEndDate,
                                editWarning,
                                editSubmitButton
                            );

                        }
                    );

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | CREATE FORM VALIDATION
        |--------------------------------------------------------------------------
        */

        const createSchoolYearForm =
            document.querySelector(
                '#createSchoolYearModal form'
            );


        if (createSchoolYearForm) {

            createSchoolYearForm.addEventListener(
                'submit',
                function (event) {

                    const isValid =
                        validateSchoolYearDates(
                            createStartDate,
                            createEndDate,
                            createWarning,
                            createSubmitButton
                        );


                    if (!isValid) {

                        event.preventDefault();

                        event.stopPropagation();

                        return false;

                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | EDIT FORM VALIDATION
        |--------------------------------------------------------------------------
        */

        const editSchoolYearForm =
            document.querySelector(
                '#editSchoolYearModal form'
            );


        if (editSchoolYearForm) {

            editSchoolYearForm.addEventListener(
                'submit',
                function (event) {

                    const isValid =
                        validateSchoolYearDates(
                            editStartDate,
                            editEndDate,
                            editWarning,
                            editSubmitButton
                        );


                    if (!isValid) {

                        event.preventDefault();

                        event.stopPropagation();

                        return false;

                    }

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | INITIAL EDIT STATE
        |--------------------------------------------------------------------------
        */

        if (
            editStartDate &&
            editStartDate.value &&
            editSchoolYear
        ) {

            updateSchoolYear(
                editStartDate,
                editSchoolYear,
                editEndDate,
                editSuggestedEndYear,
                editWarning,
                false,
                editSubmitButton
            );

        } else if (editSuggestedEndYear) {

            const suggestionContainer =
                editSuggestedEndYear.closest(
                    '.aprism-sy-suggestion'
                );


            if (suggestionContainer) {

                suggestionContainer.hidden = true;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | INITIAL CREATE STATE
        |--------------------------------------------------------------------------
        */

        if (
            createStartDate &&
            createStartDate.value &&
            createSchoolYear
        ) {

            updateSchoolYear(
                createStartDate,
                createSchoolYear,
                createEndDate,
                createSuggestedEndYear,
                createWarning,
                true,
                createSubmitButton
            );

        }


        /*
        |--------------------------------------------------------------------------
        | INITIAL LUCIDE RENDER
        |--------------------------------------------------------------------------
        */

        if (window.lucide) {

            lucide.createIcons();

        }

    })();

</script>