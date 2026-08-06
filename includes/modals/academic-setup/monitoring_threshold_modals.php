<!-- ==========================================================
     CREATE MONITORING THRESHOLD
========================================================== -->

<div class="modal fade" id="createThresholdModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form class="modal-content modal-content-custom">

            <!-- Header -->

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box primary">

                        <i data-lucide="activity"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom">

                            Create Monitoring Threshold

                        </h5>

                        <p class="modal-subtitle-custom">

                            Student Monitoring Rule

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

                        Threshold Name

                    </label>

                    <input type="text" class="form-control-custom" placeholder="e.g. Attendance Risk">

                </div>

                <div class="form-group-custom">

                    <label class="form-label-custom">

                        Monitoring Category

                    </label>

                    <select class="form-control-custom">

                        <option selected disabled>

                            Select Category

                        </option>

                        <option>

                            Attendance

                        </option>

                        <option>

                            Academic Performance

                        </option>

                        <option>

                            Combined Risk

                        </option>

                    </select>

                </div>

                <div class="modal-row">

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Warning Level

                        </label>

                        <input type="number" class="form-control-custom" placeholder="Example: 80">

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Critical Level

                        </label>

                        <input type="number" class="form-control-custom" placeholder="Example: 70">

                    </div>

                </div>

                <div class="form-group-custom">

                    <label class="form-label-custom">

                        Description

                    </label>

                    <textarea rows="4" class="form-control-custom"
                        placeholder="Describe how this monitoring threshold will be used."></textarea>

                </div>

            </div>

            <!-- Footer -->

            <div class="modal-footer-custom">

                <button type="button" class="modal-btn-dismiss" data-bs-dismiss="modal">

                    Cancel

                </button>

                <button type="submit" class="modal-btn-action">

                    Save Threshold

                </button>

            </div>

        </form>

    </div>

</div>



<!-- ==========================================================
     EDIT MONITORING THRESHOLD
========================================================== -->

<div class="modal fade" id="editThresholdModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form class="modal-content modal-content-custom">

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box primary">

                        <i data-lucide="square-pen"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom">

                            Edit Monitoring Threshold

                        </h5>

                        <p class="modal-subtitle-custom">

                            Update Monitoring Rule

                        </p>

                    </div>

                </div>

                <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

                    <i data-lucide="x"></i>

                </button>

            </div>

            <div class="modal-body-custom">

                <input type="hidden" name="threshold_id">

                <div class="form-group-custom">

                    <label class="form-label-custom">

                        Threshold Name

                    </label>

                    <input type="text" class="form-control-custom">

                </div>

                <div class="form-group-custom">

                    <label class="form-label-custom">

                        Monitoring Category

                    </label>

                    <select class="form-control-custom">

                        <option>

                            Attendance

                        </option>

                        <option>

                            Academic Performance

                        </option>

                        <option>

                            Combined Risk

                        </option>

                    </select>

                </div>

                <div class="modal-row">

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Warning Level

                        </label>

                        <input type="number" class="form-control-custom">

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Critical Level

                        </label>

                        <input type="number" class="form-control-custom">

                    </div>

                </div>

                <div class="form-group-custom">

                    <label class="form-label-custom">

                        Description

                    </label>

                    <textarea rows="4" class="form-control-custom"></textarea>

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
     ARCHIVE MONITORING THRESHOLD
========================================================== -->

<div class="modal fade" id="archiveThresholdModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content modal-content-custom">

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box danger">

                        <i data-lucide="archive"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom">

                            Archive Monitoring Threshold

                        </h5>

                    </div>

                </div>

            </div>

            <div class="modal-body-custom text-center">

                <p>

                    Archived monitoring thresholds remain available for
                    historical reference but will no longer be used when
                    evaluating new student monitoring records.

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