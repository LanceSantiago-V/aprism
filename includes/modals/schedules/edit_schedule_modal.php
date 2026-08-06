<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <form class="modal-content modal-content-custom">

            <!-- ==========================================================
                 HEADER
            =========================================================== -->

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box primary">

                        <i data-lucide="square-pen"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom">

                            Edit Schedule Record

                        </h5>

                        <p class="modal-subtitle-custom">

                            Update APRISM Schedule Information

                        </p>

                    </div>

                </div>

                <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal">

                    <i data-lucide="x"></i>

                </button>

            </div>

            <!-- ==========================================================
                 BODY
            =========================================================== -->

            <div class="modal-body-custom">

                <input type="hidden" name="schedule_id">

                <!-- Subject / Section -->

                <div class="modal-row">

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Subject

                        </label>

                        <select class="form-control-custom">

                            <option>Capstone Project 2</option>

                        </select>

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Section

                        </label>

                        <select class="form-control-custom">

                            <option>BSIT-401</option>

                        </select>

                    </div>

                </div>

                <!-- Teacher / Room -->

                <div class="modal-row">

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Teacher

                        </label>

                        <select class="form-control-custom">

                            <option>Prof. Alejandro Diaz</option>

                        </select>

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Room

                        </label>

                        <input type="text" class="form-control-custom" value="Laboratory 304">

                    </div>

                </div>

                <!-- Day / Start / End -->

                <div class="modal-row modal-row-3">

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Day

                        </label>

                        <select class="form-control-custom">

                            <option>Monday</option>

                        </select>

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Start Time

                        </label>

                        <input type="time" class="form-control-custom" value="13:00">

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            End Time

                        </label>

                        <input type="time" class="form-control-custom" value="16:00">

                    </div>

                </div>

                <!-- School Year / Term -->

                <div class="modal-row">

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            School Year

                        </label>

                        <select class="form-control-custom">

                            <option>2026–2027</option>

                        </select>

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Academic Term

                        </label>

                        <select class="form-control-custom">

                            <option>First Semester</option>

                        </select>

                    </div>

                </div>

                <!-- Status -->

                <div class="form-group-custom">

                    <label class="form-label-custom">

                        Status

                    </label>

                    <select class="form-control-custom">

                        <option selected>

                            Active

                        </option>

                        <option>

                            Archived

                        </option>

                    </select>

                </div>

                <!-- Notes -->

                <div class="form-group-custom">

                    <label class="form-label-custom">

                        Administrative Notes

                    </label>

                    <textarea rows="4"
                        class="form-control-custom">Teacher reassigned due to administrative schedule revision.</textarea>

                </div>

            </div>

            <!-- ==========================================================
                 FOOTER
            =========================================================== -->

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