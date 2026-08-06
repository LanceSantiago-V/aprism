<!-- ==========================================================
     CREATE TERM
========================================================== -->

<div class="modal fade" id="createTermModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form class="modal-content modal-content-custom">

            <!-- Header -->

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box primary">

                        <i data-lucide="book-open"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom">

                            Create Academic Term

                        </h5>

                        <p class="modal-subtitle-custom">

                            Academic Period Information

                        </p>

                    </div>

                </div>

                <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

                    <i data-lucide="x"></i>

                </button>

            </div>

            <!-- Body -->

            <div class="modal-body-custom">

                <div class="form-group-custom">

                    <label class="form-label-custom">

                        School Year

                    </label>

                    <select class="form-control-custom">

                        <option selected disabled>

                            Select School Year

                        </option>

                    </select>

                </div>

                <div class="form-group-custom">

                    <label class="form-label-custom">

                        Academic Term

                    </label>

                    <select class="form-control-custom">

                        <option selected disabled>

                            Select Term

                        </option>

                        <option>

                            First Semester

                        </option>

                        <option>

                            Second Semester

                        </option>

                        <option>

                            Summer

                        </option>

                    </select>

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

            <!-- Footer -->

            <div class="modal-footer-custom">

                <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

                    Cancel

                </button>

                <button type="submit" class="modal-btn-action">

                    Save Academic Term

                </button>

            </div>

        </form>

    </div>

</div>



<!-- ==========================================================
     EDIT TERM
========================================================== -->

<div class="modal fade" id="editTermModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form class="modal-content modal-content-custom">

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box primary">

                        <i data-lucide="square-pen"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom">

                            Edit Academic Term

                        </h5>

                        <p class="modal-subtitle-custom">

                            Update Academic Period

                        </p>

                    </div>

                </div>

                <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

                    <i data-lucide="x"></i>

                </button>

            </div>

            <div class="modal-body-custom">

                <input type="hidden" name="term_id">

                <div class="form-group-custom">

                    <label class="form-label-custom">

                        School Year

                    </label>

                    <select class="form-control-custom">

                        <option>

                            2026–2027

                        </option>

                    </select>

                </div>

                <div class="form-group-custom">

                    <label class="form-label-custom">

                        Academic Term

                    </label>

                    <select class="form-control-custom">

                        <option>

                            First Semester

                        </option>

                        <option>

                            Second Semester

                        </option>

                        <option>

                            Summer

                        </option>

                    </select>

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
     ARCHIVE TERM
========================================================== -->

<div class="modal fade" id="archiveTermModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content modal-content-custom">

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box danger">

                        <i data-lucide="archive"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom">

                            Archive Academic Term

                        </h5>

                    </div>

                </div>

            </div>

            <div class="modal-body-custom text-center">

                <p>

                    Archived academic terms remain available for
                    historical attendance, grades, monitoring,
                    and institutional reports.

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