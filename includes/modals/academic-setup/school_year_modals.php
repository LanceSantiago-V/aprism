<!-- ==========================================================
     CREATE SCHOOL YEAR
========================================================== -->

<div class="modal fade" id="createSchoolYearModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form class="modal-content modal-content-custom">

            <!-- Header -->

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box primary">

                        <i data-lucide="calendar-plus"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom">

                            Create School Year

                        </h5>

                        <p class="modal-subtitle-custom">

                            Academic Calendar Configuration

                        </p>

                    </div>

                </div>

                <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

                    <i data-lucide="x"></i>

                </button>

            </div>

            <!-- Body -->

            <div class="modal-body-custom">

                <div class="modal-row">

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            School Year

                        </label>

                        <input type="text" class="form-control-custom" placeholder="e.g. 2026–2027">

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Status

                        </label>

                        <select class="form-control-custom">

                            <option selected>

                                Inactive

                            </option>

                            <option>

                                Active

                            </option>

                        </select>

                    </div>

                </div>

                <div class="modal-row">

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Start Date

                        </label>

                        <input type="date" class="form-control-custom">

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            End Date

                        </label>

                        <input type="date" class="form-control-custom">

                    </div>

                </div>

            </div>

            <!-- Footer -->

            <div class="modal-footer-custom">

                <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

                    Cancel

                </button>

                <button type="submit" class="modal-btn-action">

                    Save School Year

                </button>

            </div>

        </form>

    </div>

</div>



<!-- ==========================================================
     EDIT SCHOOL YEAR
========================================================== -->

<div class="modal fade" id="editSchoolYearModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form class="modal-content modal-content-custom">

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box primary">

                        <i data-lucide="square-pen"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom">

                            Edit School Year

                        </h5>

                        <p class="modal-subtitle-custom">

                            Update Academic Calendar

                        </p>

                    </div>

                </div>

                <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

                    <i data-lucide="x"></i>

                </button>

            </div>

            <div class="modal-body-custom">

                <input type="hidden" name="school_year_id">

                <div class="modal-row">

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            School Year

                        </label>

                        <input type="text" class="form-control-custom">

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Status

                        </label>

                        <select class="form-control-custom">

                            <option>

                                Active

                            </option>

                            <option>

                                Inactive

                            </option>

                        </select>

                    </div>

                </div>

                <div class="modal-row">

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Start Date

                        </label>

                        <input type="date" class="form-control-custom">

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            End Date

                        </label>

                        <input type="date" class="form-control-custom">

                    </div>

                </div>

            </div>

            <div class="modal-footer-custom">

                <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

                    Cancel

                </button>

                <button type="submit" class="modal-btn-action">

                    Save Changes

                </button>

            </div>

        </form>

    </div>

</div>



<!-- ==========================================================
     ACTIVATE SCHOOL YEAR
========================================================== -->

<div class="modal fade" id="activateSchoolYearModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content modal-content-custom">

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box success">

                        <i data-lucide="circle-check-big"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom">

                            Activate School Year

                        </h5>

                    </div>

                </div>

            </div>

            <div class="modal-body-custom text-center">

                <p>

                    Activating this School Year will make it the current
                    operational period used by APRISM.

                </p>

            </div>

            <div class="modal-footer-custom">

                <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

                    Cancel

                </button>

                <button type="button" class="modal-btn-action">

                    Activate

                </button>

            </div>

        </div>

    </div>

</div>



<!-- ==========================================================
     ARCHIVE SCHOOL YEAR
========================================================== -->

<div class="modal fade" id="archiveSchoolYearModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content modal-content-custom">

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box danger">

                        <i data-lucide="archive"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom">

                            Archive School Year

                        </h5>

                    </div>

                </div>

            </div>

            <div class="modal-body-custom text-center">

                <p>

                    Archived School Years remain available for historical
                    reports and analytics but cannot be used for new
                    academic operations.

                </p>

            </div>

            <div class="modal-footer-custom">

                <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

                    Cancel

                </button>

                <button type="button" class="modal-btn-danger">

                    Archive

                </button>

            </div>

        </div>

    </div>

</div>