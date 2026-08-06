<div class="modal fade" id="recordScheduleModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <form class="modal-content modal-content-custom">

            <!-- ==========================================================
                 HEADER
            =========================================================== -->

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box primary">

                        <i data-lucide="calendar-days"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom">

                            Record Schedule

                        </h5>

                        <p class="modal-subtitle-custom">

                            APRISM Schedule Information

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

                <!-- Subject / Section -->

                <div class="modal-row">

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Subject

                        </label>

                        <select class="form-control-custom">

                            <option selected disabled>

                                Select Subject

                            </option>

                        </select>

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Section

                        </label>

                        <select class="form-control-custom">

                            <option selected disabled>

                                Select Section

                            </option>

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

                            <option selected disabled>

                                Select Teacher

                            </option>

                        </select>

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Room

                        </label>

                        <input type="text" class="form-control-custom" placeholder="e.g. Laboratory 304">

                    </div>

                </div>

                <!-- Day / Start / End -->

                <div class="modal-row modal-row-3">

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Day

                        </label>

                        <select class="form-control-custom">

                            <option selected disabled>

                                Select Day

                            </option>

                            <option>Monday</option>
                            <option>Tuesday</option>
                            <option>Wednesday</option>
                            <option>Thursday</option>
                            <option>Friday</option>
                            <option>Saturday</option>

                        </select>

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            Start Time

                        </label>

                        <input type="time" class="form-control-custom">

                    </div>

                    <div class="form-group-custom">

                        <label class="form-label-custom">

                            End Time

                        </label>

                        <input type="time" class="form-control-custom">

                    </div>

                </div>

                <!-- School Year / Term -->

                <div class="modal-row">

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

                                Select Academic Term

                            </option>

                            <option>First Semester</option>
                            <option>Second Semester</option>
                            <option>Summer</option>

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
                        <span class="text-muted">

                            (Optional)

                        </span>

                    </label>

                    <textarea rows="4" class="form-control-custom"
                        placeholder="Optional administrative remarks..."></textarea>

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

                    Record Schedule

                </button>

            </div>

        </form>

    </div>

</div>