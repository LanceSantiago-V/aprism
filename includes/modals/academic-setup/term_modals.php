<!-- ==========================================================
     CREATE ACADEMIC PERIOD
=========================================================== -->

<div class="modal fade" id="createTermModal" tabindex="-1" aria-labelledby="createTermModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form class="modal-content modal-content-custom" method="POST" action="<?= htmlspecialchars(
            APP_URL . '/actions/academic_head/create_academic_period.php'
        ) ?>">

            <!-- ==================================================
                 HEADER
            =================================================== -->

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box primary">

                        <i data-lucide="book-open"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom" id="createTermModalLabel">
                            Create Academic Term
                        </h5>

                        <p class="modal-subtitle-custom">
                            Academic Period Information
                        </p>

                    </div>

                </div>

                <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal" aria-label="Close">

                    <i data-lucide="x"></i>

                </button>

            </div>


            <!-- ==================================================
                 BODY
            =================================================== -->

            <div class="modal-body-custom">

                <!-- ==================================================
                     SCHOOL YEAR
                =================================================== -->

                <div class="form-group-custom">

                    <label class="form-label-custom" for="createAcademicPeriodSchoolYear">
                        School Year
                    </label>

                    <select class="form-control-custom" id="createAcademicPeriodSchoolYear" name="school_year_id"
                        required>

                        <option value="" selected disabled>
                            Select School Year
                        </option>

                        <?php foreach ($schoolYears as $schoolYear): ?>

                            <?php if ($schoolYear['status'] !== 'Archived'): ?>

                                <option value="<?= (int) $schoolYear['school_year_id'] ?>">
                                    <?= htmlspecialchars(
                                        $schoolYear['school_year']
                                    ) ?>
                                </option>

                            <?php endif; ?>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- ==================================================
                     ACADEMIC LEVEL
                =================================================== -->

                <div class="form-group-custom">

                    <label class="form-label-custom" for="createAcademicPeriodLevel">
                        Academic Level
                    </label>

                    <select class="form-control-custom" id="createAcademicPeriodLevel" name="academic_level" required>

                        <option value="" selected disabled>
                            Select Academic Level
                        </option>

                        <option value="College">
                            College
                        </option>

                        <option value="Senior High School">
                            Senior High School
                        </option>

                    </select>

                </div>


                <!-- ==================================================
                     SEMESTER
                     INITIAL STATE:
                     BLANK + DISABLED
                     ENABLED AFTER ACADEMIC LEVEL IS SELECTED
                =================================================== -->

                <div class="form-group-custom" id="createAcademicSemesterGroup">

                    <label class="form-label-custom" for="createAcademicSemester">
                        Semester
                    </label>

                    <select class="form-control-custom" id="createAcademicSemester" disabled>

                        <option value="" selected disabled></option>

                    </select>

                    <input type="hidden" name="semester" id="createAcademicSemesterHidden" value="" disabled>

                </div>


                <!-- ==================================================
                     ACADEMIC PERIOD
                =================================================== -->

                <div class="form-group-custom">

                    <label class="form-label-custom" for="createAcademicPeriodName">
                        Academic Period
                    </label>

                    <select class="form-control-custom" id="createAcademicPeriodName" name="period_name" required
                        disabled>

                        <option value="" selected disabled>
                            Select Academic Level First
                        </option>

                    </select>

                </div>


                <!-- ==================================================
                     DATES
                =================================================== -->

                <div class="modal-row">

                    <!-- START DATE -->

                    <div class="form-group-custom">

                        <label class="form-label-custom" for="createAcademicPeriodStartDate">
                            Start Date
                        </label>

                        <input type="date" class="form-control-custom" id="createAcademicPeriodStartDate"
                            name="start_date" min="1000-01-01" max="9999-12-31" required>

                    </div>


                    <!-- END DATE -->

                    <div class="form-group-custom">

                        <label class="form-label-custom" for="createAcademicPeriodEndDate">
                            End Date
                        </label>

                        <input type="date" class="form-control-custom" id="createAcademicPeriodEndDate" name="end_date"
                            min="1000-01-01" max="9999-12-31" required>

                    </div>

                </div>

            </div>


            <!-- ==================================================
                 FOOTER
            =================================================== -->

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
     EDIT ACADEMIC PERIOD
=========================================================== -->

<div class="modal fade" id="editTermModal" tabindex="-1" aria-labelledby="editTermModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form class="modal-content modal-content-custom" method="POST" action="<?= htmlspecialchars(
            APP_URL . '/actions/academic_head/update_academic_period.php'
        ) ?>">

            <!-- ==================================================
                 HEADER
            =================================================== -->

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box primary">

                        <i data-lucide="square-pen"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom" id="editTermModalLabel">
                            Edit Academic Term
                        </h5>

                        <p class="modal-subtitle-custom">
                            Update Academic Period
                        </p>

                    </div>

                </div>

                <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal" aria-label="Close">

                    <i data-lucide="x"></i>

                </button>

            </div>


            <!-- ==================================================
                 BODY
            =================================================== -->

            <div class="modal-body-custom">

                <!-- ==================================================
                     ACADEMIC PERIOD ID
                =================================================== -->

                <input type="hidden" name="academic_period_id" id="editAcademicPeriodId">


                <!-- ==================================================
                     SCHOOL YEAR
                =================================================== -->

                <div class="form-group-custom">

                    <label class="form-label-custom" for="editAcademicPeriodSchoolYear">
                        School Year
                    </label>

                    <select class="form-control-custom" id="editAcademicPeriodSchoolYear" name="school_year_id"
                        required>

                        <option value="" selected disabled>
                            Select School Year
                        </option>

                        <?php foreach ($schoolYears as $schoolYear): ?>

                            <?php if ($schoolYear['status'] !== 'Archived'): ?>

                                <option value="<?= (int) $schoolYear['school_year_id'] ?>">
                                    <?= htmlspecialchars(
                                        $schoolYear['school_year']
                                    ) ?>
                                </option>

                            <?php endif; ?>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- ==================================================
                     ACADEMIC LEVEL
                =================================================== -->

                <div class="form-group-custom">

                    <label class="form-label-custom" for="editAcademicPeriodLevel">
                        Academic Level
                    </label>

                    <select class="form-control-custom" id="editAcademicPeriodLevel" name="academic_level" required>

                        <option value="" selected disabled>
                            Select Academic Level
                        </option>

                        <option value="College">
                            College
                        </option>

                        <option value="Senior High School">
                            Senior High School
                        </option>

                    </select>

                </div>


                <!-- ==================================================
                     SEMESTER
                     INITIAL STATE:
                     BLANK + DISABLED
                     ENABLED AFTER ACADEMIC LEVEL IS SELECTED
                =================================================== -->

                <div class="form-group-custom" id="editAcademicSemesterGroup">

                    <label class="form-label-custom" for="editAcademicSemester">
                        Semester
                    </label>

                    <select class="form-control-custom" id="editAcademicSemester" disabled>

                        <option value="" selected disabled></option>

                    </select>

                    <input type="hidden" name="semester" id="editAcademicSemesterHidden" value="" disabled>

                </div>


                <!-- ==================================================
                     ACADEMIC PERIOD
                =================================================== -->

                <div class="form-group-custom">

                    <label class="form-label-custom" for="editAcademicPeriodName">
                        Academic Period
                    </label>

                    <select class="form-control-custom" id="editAcademicPeriodName" name="period_name" required
                        disabled>

                        <option value="" selected disabled>
                            Select Academic Level First
                        </option>

                    </select>

                </div>


                <!-- ==================================================
                     DATES
                =================================================== -->

                <div class="modal-row">

                    <!-- START DATE -->

                    <div class="form-group-custom">

                        <label class="form-label-custom" for="editAcademicPeriodStartDate">
                            Start Date
                        </label>

                        <input type="date" class="form-control-custom" id="editAcademicPeriodStartDate"
                            name="start_date" min="1000-01-01" max="9999-12-31" required>

                    </div>


                    <!-- END DATE -->

                    <div class="form-group-custom">

                        <label class="form-label-custom" for="editAcademicPeriodEndDate">
                            End Date
                        </label>

                        <input type="date" class="form-control-custom" id="editAcademicPeriodEndDate" name="end_date"
                            min="1000-01-01" max="9999-12-31" required>

                    </div>

                </div>

            </div>


            <!-- ==================================================
                 FOOTER
            =================================================== -->

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
     ARCHIVE ACADEMIC PERIOD
     NOT YET CONNECTED
=========================================================== -->

<div class="modal fade" id="archiveTermModal" tabindex="-1" aria-labelledby="archiveTermModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content modal-content-custom">

            <div class="modal-header-custom">

                <div class="d-flex align-items-center">

                    <div class="modal-header-icon-box danger">

                        <i data-lucide="archive"></i>

                    </div>

                    <div class="modal-header-title-wrapper">

                        <h5 class="modal-title-custom" id="archiveTermModalLabel">
                            Archive Academic Term
                        </h5>

                    </div>

                </div>

                <button type="button" class="modal-close-icon-btn" data-bs-dismiss="modal" aria-label="Close">

                    <i data-lucide="x"></i>

                </button>

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



<script>

    document.addEventListener(
        'DOMContentLoaded',
        function () {


            /*
            |--------------------------------------------------------------------------
            | CREATE MODAL
            |--------------------------------------------------------------------------
            */

            const createTermModal =
                document.getElementById(
                    'createTermModal'
                );


            const createTermForm =
                createTermModal
                    ? createTermModal.querySelector('form')
                    : null;


            const createSchoolYearSelect =
                document.getElementById(
                    'createAcademicPeriodSchoolYear'
                );


            const createAcademicLevelSelect =
                document.getElementById(
                    'createAcademicPeriodLevel'
                );


            const createSemesterGroup =
                document.getElementById(
                    'createAcademicSemesterGroup'
                );


            const createSemesterSelect =
                document.getElementById(
                    'createAcademicSemester'
                );


            const createSemesterHidden =
                document.getElementById(
                    'createAcademicSemesterHidden'
                );


            const createPeriodSelect =
                document.getElementById(
                    'createAcademicPeriodName'
                );


            const createStartDateInput =
                document.getElementById(
                    'createAcademicPeriodStartDate'
                );


            const createEndDateInput =
                document.getElementById(
                    'createAcademicPeriodEndDate'
                );



            /*
            |--------------------------------------------------------------------------
            | EDIT MODAL
            |--------------------------------------------------------------------------
            */

            const editTermModal =
                document.getElementById(
                    'editTermModal'
                );


            const editTermForm =
                editTermModal
                    ? editTermModal.querySelector('form')
                    : null;


            const editAcademicPeriodId =
                document.getElementById(
                    'editAcademicPeriodId'
                );


            const editSchoolYearSelect =
                document.getElementById(
                    'editAcademicPeriodSchoolYear'
                );


            const editAcademicLevelSelect =
                document.getElementById(
                    'editAcademicPeriodLevel'
                );


            const editSemesterGroup =
                document.getElementById(
                    'editAcademicSemesterGroup'
                );


            const editSemesterSelect =
                document.getElementById(
                    'editAcademicSemester'
                );


            const editSemesterHidden =
                document.getElementById(
                    'editAcademicSemesterHidden'
                );


            const editPeriodSelect =
                document.getElementById(
                    'editAcademicPeriodName'
                );


            const editStartDateInput =
                document.getElementById(
                    'editAcademicPeriodStartDate'
                );


            const editEndDateInput =
                document.getElementById(
                    'editAcademicPeriodEndDate'
                );



            /*
            |--------------------------------------------------------------------------
            | PERIOD OPTIONS
            |--------------------------------------------------------------------------
            */

            const collegePeriods = [

                'Prelim',
                'Midterm',
                'Pre-Final',
                'Final'

            ];


            const seniorHighSchoolPeriods = {

                'First Semester': [
                    'Quarter 1',
                    'Quarter 2'
                ],

                'Second Semester': [
                    'Quarter 3',
                    'Quarter 4'
                ]

            };


            /*
            |--------------------------------------------------------------------------
            | SEMESTER OPTIONS
            |--------------------------------------------------------------------------
            */

            const academicSemesters = [

                'First Semester',
                'Second Semester'

            ];



            /*
            |--------------------------------------------------------------------------
            | CREATE: POPULATE SEMESTER OPTIONS
            |--------------------------------------------------------------------------
            */

            function populateCreateSemesterOptions() {

                if (!createSemesterSelect) {
                    return;
                }


                createSemesterSelect.innerHTML = '';


                const placeholder =
                    document.createElement('option');


                placeholder.value = '';
                placeholder.textContent = 'Select Academic Semester';
                placeholder.disabled = true;
                placeholder.selected = true;


                createSemesterSelect.appendChild(
                    placeholder
                );


                academicSemesters.forEach(
                    function (semester) {

                        const option =
                            document.createElement(
                                'option'
                            );


                        option.value =
                            semester;


                        option.textContent =
                            semester;


                        createSemesterSelect.appendChild(
                            option
                        );

                    }
                );

            }



            /*
            |--------------------------------------------------------------------------
            | EDIT: POPULATE SEMESTER OPTIONS
            |--------------------------------------------------------------------------
            */

            function populateEditSemesterOptions(
                selectedSemester = ''
            ) {

                if (!editSemesterSelect) {
                    return;
                }


                editSemesterSelect.innerHTML = '';


                const placeholder =
                    document.createElement('option');


                placeholder.value = '';
                placeholder.textContent = 'Select Academic Semester';
                placeholder.disabled = true;
                placeholder.selected = selectedSemester === '';


                editSemesterSelect.appendChild(
                    placeholder
                );


                academicSemesters.forEach(
                    function (semester) {

                        const option =
                            document.createElement(
                                'option'
                            );


                        option.value =
                            semester;


                        option.textContent =
                            semester;


                        if (
                            semester === selectedSemester
                        ) {

                            option.selected =
                                true;

                        }


                        editSemesterSelect.appendChild(
                            option
                        );

                    }
                );

            }



            /*
            |--------------------------------------------------------------------------
            | CREATE: APPLY SEMESTER VALUE
            |--------------------------------------------------------------------------
            */

            function updateCreateSemesterValue() {

                if (
                    !createAcademicLevelSelect ||
                    !createSemesterSelect ||
                    !createSemesterHidden
                ) {
                    return;
                }


                const academicLevel =
                    createAcademicLevelSelect.value;


                if (
                    academicLevel === 'College' ||
                    academicLevel === 'Senior High School'
                ) {

                    createSemesterHidden.value =
                        createSemesterSelect.value;

                    createSemesterHidden.disabled =
                        createSemesterSelect.value === '';

                    return;

                }


                createSemesterSelect.value =
                    '';

                createSemesterHidden.value =
                    '';

                createSemesterHidden.disabled =
                    true;

            }



            /*
            |--------------------------------------------------------------------------
            | EDIT: APPLY SEMESTER VALUE
            |--------------------------------------------------------------------------
            */

            function updateEditSemesterValue() {

                if (
                    !editAcademicLevelSelect ||
                    !editSemesterSelect ||
                    !editSemesterHidden
                ) {
                    return;
                }


                const academicLevel =
                    editAcademicLevelSelect.value;


                if (
                    academicLevel === 'College' ||
                    academicLevel === 'Senior High School'
                ) {

                    editSemesterHidden.value =
                        editSemesterSelect.value;

                    editSemesterHidden.disabled =
                        editSemesterSelect.value === '';

                    return;

                }


                editSemesterSelect.value =
                    '';

                editSemesterHidden.value =
                    '';

                editSemesterHidden.disabled =
                    true;

            }



            /*
            |--------------------------------------------------------------------------
            | CREATE: RESET PERIOD SELECT
            |--------------------------------------------------------------------------
            */

            function resetCreatePeriodSelect() {

                if (!createPeriodSelect) {
                    return;
                }


                createPeriodSelect.innerHTML =
                    '';


                const placeholder =
                    document.createElement(
                        'option'
                    );


                placeholder.value =
                    '';


                placeholder.textContent =
                    'Select Academic Level First';


                placeholder.disabled =
                    true;


                placeholder.selected =
                    true;


                createPeriodSelect.appendChild(
                    placeholder
                );


                createPeriodSelect.disabled =
                    true;

            }



            /*
            |--------------------------------------------------------------------------
            | EDIT: RESET PERIOD SELECT
            |--------------------------------------------------------------------------
            */

            function resetEditPeriodSelect() {

                if (!editPeriodSelect) {
                    return;
                }


                editPeriodSelect.innerHTML =
                    '';


                const placeholder =
                    document.createElement(
                        'option'
                    );


                placeholder.value =
                    '';


                placeholder.textContent =
                    'Select Academic Level First';


                placeholder.disabled =
                    true;


                placeholder.selected =
                    true;


                editPeriodSelect.appendChild(
                    placeholder
                );


                editPeriodSelect.disabled =
                    true;

            }



            /*
            |--------------------------------------------------------------------------
            | CREATE: SEMESTER FIELD
            |--------------------------------------------------------------------------
            */

            function updateCreateSemesterField() {

                if (
                    !createAcademicLevelSelect ||
                    !createSemesterGroup ||
                    !createSemesterSelect ||
                    !createSemesterHidden
                ) {
                    return;
                }


                const academicLevel =
                    createAcademicLevelSelect.value;


                createSemesterGroup.style.display =
                    'block';


                if (
                    academicLevel === 'College' ||
                    academicLevel === 'Senior High School'
                ) {

                    populateCreateSemesterOptions();


                    createSemesterSelect.disabled =
                        false;


                    createSemesterSelect.required =
                        true;


                    createSemesterHidden.disabled =
                        createSemesterSelect.value === '';


                    createSemesterHidden.value =
                        createSemesterSelect.value;

                    return;

                }


                createSemesterSelect.innerHTML =
                    '';


                const placeholder =
                    document.createElement(
                        'option'
                    );


                placeholder.value =
                    '';


                placeholder.textContent =
                    '';


                placeholder.disabled =
                    true;


                placeholder.selected =
                    true;


                createSemesterSelect.appendChild(
                    placeholder
                );


                createSemesterSelect.value =
                    '';

                createSemesterSelect.disabled =
                    true;

                createSemesterSelect.required =
                    false;


                createSemesterHidden.value =
                    '';

                createSemesterHidden.disabled =
                    true;

            }



            /*
            |--------------------------------------------------------------------------
            | EDIT: SEMESTER FIELD
            |--------------------------------------------------------------------------
            */

            function updateEditSemesterField() {

                if (
                    !editAcademicLevelSelect ||
                    !editSemesterGroup ||
                    !editSemesterSelect ||
                    !editSemesterHidden
                ) {
                    return;
                }


                const academicLevel =
                    editAcademicLevelSelect.value;


                editSemesterGroup.style.display =
                    'block';


                if (
                    academicLevel === 'College' ||
                    academicLevel === 'Senior High School'
                ) {

                    populateEditSemesterOptions();


                    editSemesterSelect.disabled =
                        false;


                    editSemesterSelect.required =
                        true;


                    editSemesterHidden.disabled =
                        editSemesterSelect.value === '';


                    editSemesterHidden.value =
                        editSemesterSelect.value;

                    return;

                }


                editSemesterSelect.innerHTML =
                    '';


                const placeholder =
                    document.createElement(
                        'option'
                    );


                placeholder.value =
                    '';


                placeholder.textContent =
                    '';


                placeholder.disabled =
                    true;


                placeholder.selected =
                    true;


                editSemesterSelect.appendChild(
                    placeholder
                );


                editSemesterSelect.value =
                    '';

                editSemesterSelect.disabled =
                    true;

                editSemesterSelect.required =
                    false;


                editSemesterHidden.value =
                    '';

                editSemesterHidden.disabled =
                    true;

            }



            /*
            |--------------------------------------------------------------------------
            | CREATE: POPULATE PERIODS
            |--------------------------------------------------------------------------
            */

            function populateCreateAcademicPeriods() {

                if (!createPeriodSelect) {
                    return;
                }


                const academicLevel =
                    createAcademicLevelSelect.value;


                if (
                    academicLevel === 'College'
                ) {

                    createPeriodSelect.innerHTML =
                        '';


                    const placeholder =
                        document.createElement(
                            'option'
                        );


                    placeholder.value =
                        '';


                    placeholder.textContent =
                        'Select Academic Period';


                    placeholder.disabled =
                        true;


                    placeholder.selected =
                        true;


                    createPeriodSelect.appendChild(
                        placeholder
                    );


                    collegePeriods.forEach(
                        function (period) {

                            const option =
                                document.createElement(
                                    'option'
                                );


                            option.value =
                                period;


                            option.textContent =
                                period;


                            createPeriodSelect.appendChild(
                                option
                            );

                        }
                    );


                    createPeriodSelect.disabled =
                        false;


                    return;

                }


                if (
                    academicLevel === 'Senior High School'
                ) {

                    resetCreatePeriodForSemester();

                    return;

                }


                resetCreatePeriodSelect();

            }



            /*
            |--------------------------------------------------------------------------
            | CREATE: POPULATE SHS PERIODS BY SEMESTER
            |--------------------------------------------------------------------------
            */

            function populateCreateSeniorHighSchoolPeriods() {

                if (
                    !createSemesterSelect ||
                    !createPeriodSelect
                ) {
                    return;
                }


                const semester =
                    createSemesterSelect.value;


                const periods =
                    seniorHighSchoolPeriods[semester] || [];


                createPeriodSelect.innerHTML =
                    '';


                if (
                    periods.length === 0
                ) {

                    resetCreatePeriodForSemester();

                    return;

                }


                const placeholder =
                    document.createElement(
                        'option'
                    );


                placeholder.value =
                    '';


                placeholder.textContent =
                    'Select Academic Period';


                placeholder.disabled =
                    true;


                placeholder.selected =
                    true;


                createPeriodSelect.appendChild(
                    placeholder
                );


                periods.forEach(
                    function (period) {

                        const option =
                            document.createElement(
                                'option'
                            );


                        option.value =
                            period;


                        option.textContent =
                            period;


                        createPeriodSelect.appendChild(
                            option
                        );

                    }
                );


                createPeriodSelect.disabled =
                    false;

            }



            /*
            |--------------------------------------------------------------------------
            | EDIT: POPULATE PERIODS
            |--------------------------------------------------------------------------
            */

            function populateEditAcademicPeriods(
                selectedPeriod = ''
            ) {

                if (!editPeriodSelect) {
                    return;
                }


                const academicLevel =
                    editAcademicLevelSelect.value;


                if (
                    academicLevel === 'College'
                ) {

                    editPeriodSelect.innerHTML =
                        '';


                    const placeholder =
                        document.createElement(
                            'option'
                        );


                    placeholder.value =
                        '';


                    placeholder.textContent =
                        'Select Academic Period';


                    placeholder.disabled =
                        true;


                    placeholder.selected =
                        true;


                    editPeriodSelect.appendChild(
                        placeholder
                    );


                    collegePeriods.forEach(
                        function (period) {

                            const option =
                                document.createElement(
                                    'option'
                                );


                            option.value =
                                period;


                            option.textContent =
                                period;


                            if (
                                period === selectedPeriod
                            ) {

                                option.selected =
                                    true;

                            }


                            editPeriodSelect.appendChild(
                                option
                            );

                        }
                    );


                    editPeriodSelect.disabled =
                        false;


                    return;

                }


                if (
                    academicLevel === 'Senior High School'
                ) {

                    resetEditPeriodForSemester();

                    return;

                }


                resetEditPeriodSelect();

            }



            /*
            |--------------------------------------------------------------------------
            | EDIT: POPULATE SHS PERIODS BY SEMESTER
            |--------------------------------------------------------------------------
            */

            function populateEditSeniorHighSchoolPeriods(
                selectedPeriod = ''
            ) {

                if (
                    !editSemesterSelect ||
                    !editPeriodSelect
                ) {
                    return;
                }


                const semester =
                    editSemesterSelect.value;


                const periods =
                    seniorHighSchoolPeriods[semester] || [];


                editPeriodSelect.innerHTML =
                    '';


                if (
                    periods.length === 0
                ) {

                    resetEditPeriodForSemester();

                    return;

                }


                const placeholder =
                    document.createElement(
                        'option'
                    );


                placeholder.value =
                    '';


                placeholder.textContent =
                    'Select Academic Period';


                placeholder.disabled =
                    true;


                placeholder.selected =
                    true;


                editPeriodSelect.appendChild(
                    placeholder
                );


                periods.forEach(
                    function (period) {

                        const option =
                            document.createElement(
                                'option'
                            );


                        option.value =
                            period;


                        option.textContent =
                            period;


                        if (
                            period === selectedPeriod
                        ) {

                            option.selected =
                                true;

                        }


                        editPeriodSelect.appendChild(
                            option
                        );

                    }
                );


                editPeriodSelect.disabled =
                    false;

            }



            /*
            |--------------------------------------------------------------------------
            | CREATE: RESET PERIOD FOR SEMESTER
            |--------------------------------------------------------------------------
            */

            function resetCreatePeriodForSemester() {

                if (!createPeriodSelect) {
                    return;
                }


                createPeriodSelect.innerHTML =
                    '';


                const placeholder =
                    document.createElement(
                        'option'
                    );


                placeholder.value =
                    '';


                placeholder.textContent =
                    'Select Academic Semester';


                placeholder.disabled =
                    true;


                placeholder.selected =
                    true;


                createPeriodSelect.appendChild(
                    placeholder
                );


                createPeriodSelect.disabled =
                    true;

            }



            /*
            |--------------------------------------------------------------------------
            | EDIT: RESET PERIOD FOR SEMESTER
            |--------------------------------------------------------------------------
            */

            function resetEditPeriodForSemester() {

                if (!editPeriodSelect) {
                    return;
                }


                editPeriodSelect.innerHTML =
                    '';


                const placeholder =
                    document.createElement(
                        'option'
                    );


                placeholder.value =
                    '';


                placeholder.textContent =
                    'Select Academic Semester';


                placeholder.disabled =
                    true;


                placeholder.selected =
                    true;


                editPeriodSelect.appendChild(
                    placeholder
                );


                editPeriodSelect.disabled =
                    true;

            }



            /*
            |--------------------------------------------------------------------------
            | CREATE: RESET FORM
            |--------------------------------------------------------------------------
            */

            function resetCreateAcademicPeriodForm() {

                if (!createTermForm) {
                    return;
                }


                createTermForm.reset();


                /*
                 * School Year
                 */

                if (
                    createSchoolYearSelect
                ) {

                    createSchoolYearSelect.value =
                        '';

                }


                /*
                 * Academic Level
                 */

                if (
                    createAcademicLevelSelect
                ) {

                    createAcademicLevelSelect.value =
                        '';

                }


                /*
                 * Semester
                 *
                 * Initial state:
                 * blank + disabled.
                 * Semester options are populated only
                 * after an Academic Level is selected.
                 */

                if (
                    createSemesterGroup
                ) {

                    createSemesterGroup.style.display =
                        'block';

                }


                if (
                    createSemesterSelect
                ) {

                    createSemesterSelect.innerHTML =
                        '';


                    const placeholder =
                        document.createElement(
                            'option'
                        );


                    placeholder.value =
                        '';


                    placeholder.textContent =
                        '';


                    placeholder.disabled =
                        true;


                    placeholder.selected =
                        true;


                    createSemesterSelect.appendChild(
                        placeholder
                    );


                    createSemesterSelect.value =
                        '';

                    createSemesterSelect.disabled =
                        true;

                    createSemesterSelect.required =
                        false;

                }


                if (
                    createSemesterHidden
                ) {

                    createSemesterHidden.value =
                        '';

                    createSemesterHidden.disabled =
                        true;

                }


                /*
                 * Academic Period
                 */

                resetCreatePeriodSelect();


                /*
                 * Dates
                 */

                if (
                    createStartDateInput
                ) {

                    createStartDateInput.value =
                        '';

                }


                if (
                    createEndDateInput
                ) {

                    createEndDateInput.value =
                        '';

                }

            }



            /*
            |--------------------------------------------------------------------------
            | CREATE: ACADEMIC LEVEL CHANGE
            |--------------------------------------------------------------------------
            */

            if (
                createAcademicLevelSelect
            ) {

                createAcademicLevelSelect.addEventListener(
                    'change',
                    function () {

                        if (
                            createSemesterSelect
                        ) {

                            createSemesterSelect.value =
                                '';

                        }


                        if (
                            createSemesterHidden
                        ) {

                            createSemesterHidden.value =
                                '';

                        }


                        updateCreateSemesterField();

                        populateCreateAcademicPeriods();

                    }
                );

            }



            /*
            |--------------------------------------------------------------------------
            | CREATE: SEMESTER CHANGE
            |--------------------------------------------------------------------------
            */

            if (
                createSemesterSelect
            ) {

                createSemesterSelect.addEventListener(
                    'change',
                    function () {

                        updateCreateSemesterValue();


                        if (
                            createAcademicLevelSelect &&
                            createAcademicLevelSelect.value === 'Senior High School'
                        ) {

                            populateCreateSeniorHighSchoolPeriods();

                        }

                    }
                );

            }



            /*
            |--------------------------------------------------------------------------
            | CREATE: RESET ON OPEN
            |--------------------------------------------------------------------------
            */

            if (
                createTermModal
            ) {

                createTermModal.addEventListener(
                    'show.bs.modal',
                    function () {

                        resetCreateAcademicPeriodForm();

                    }
                );


                createTermModal.addEventListener(
                    'hidden.bs.modal',
                    function () {

                        resetCreateAcademicPeriodForm();

                    }
                );

            }



            /*
            |--------------------------------------------------------------------------
            | EDIT: RESET FORM
            |--------------------------------------------------------------------------
            */

            function resetEditAcademicPeriodForm() {

                if (!editTermForm) {
                    return;
                }


                editTermForm.reset();


                if (
                    editAcademicPeriodId
                ) {

                    editAcademicPeriodId.value =
                        '';

                }


                if (
                    editSchoolYearSelect
                ) {

                    editSchoolYearSelect.value =
                        '';

                }


                if (
                    editAcademicLevelSelect
                ) {

                    editAcademicLevelSelect.value =
                        '';

                }


                /*
                 * Semester
                 *
                 * Initial state:
                 * blank + disabled.
                 * Semester options are populated only
                 * after an Academic Level is selected.
                 */

                if (
                    editSemesterGroup
                ) {

                    editSemesterGroup.style.display =
                        'block';

                }


                if (
                    editSemesterSelect
                ) {

                    editSemesterSelect.innerHTML =
                        '';


                    const placeholder =
                        document.createElement(
                            'option'
                        );


                    placeholder.value =
                        '';


                    placeholder.textContent =
                        '';


                    placeholder.disabled =
                        true;


                    placeholder.selected =
                        true;


                    editSemesterSelect.appendChild(
                        placeholder
                    );


                    editSemesterSelect.value =
                        '';

                    editSemesterSelect.disabled =
                        true;

                    editSemesterSelect.required =
                        false;

                }


                if (
                    editSemesterHidden
                ) {

                    editSemesterHidden.value =
                        '';

                    editSemesterHidden.disabled =
                        true;

                }


                resetEditPeriodSelect();


                if (
                    editStartDateInput
                ) {

                    editStartDateInput.value =
                        '';

                }


                if (
                    editEndDateInput
                ) {

                    editEndDateInput.value =
                        '';

                }

            }



            /*
            |--------------------------------------------------------------------------
            | EDIT: ACADEMIC LEVEL CHANGE
            |--------------------------------------------------------------------------
            */

            if (
                editAcademicLevelSelect
            ) {

                editAcademicLevelSelect.addEventListener(
                    'change',
                    function () {

                        if (
                            editSemesterSelect
                        ) {

                            editSemesterSelect.value =
                                '';

                        }


                        if (
                            editSemesterHidden
                        ) {

                            editSemesterHidden.value =
                                '';

                        }


                        updateEditSemesterField();

                        populateEditAcademicPeriods();

                    }
                );

            }



            /*
            |--------------------------------------------------------------------------
            | EDIT: SEMESTER CHANGE
            |--------------------------------------------------------------------------
            */

            if (
                editSemesterSelect
            ) {

                editSemesterSelect.addEventListener(
                    'change',
                    function () {

                        updateEditSemesterValue();


                        if (
                            editAcademicLevelSelect &&
                            editAcademicLevelSelect.value === 'Senior High School'
                        ) {

                            populateEditSeniorHighSchoolPeriods();

                        }

                    }
                );

            }



            /*
            |--------------------------------------------------------------------------
            | EDIT: LOAD PERIOD DATA
            |--------------------------------------------------------------------------
            |
            | The Edit button will provide the existing values through
            | data attributes:
            |
            | data-term-id
            | data-school-year-id
            | data-academic-level
            | data-semester
            | data-period-name
            | data-start-date
            | data-end-date
            |--------------------------------------------------------------------------
            */

            if (
                editTermModal
            ) {

                editTermModal.addEventListener(
                    'show.bs.modal',
                    function (event) {

                        const editButton =
                            event.relatedTarget;


                        if (
                            !editButton
                        ) {

                            return;

                        }


                        const termId =
                            editButton.dataset.termId ||
                            '';


                        const schoolYearId =
                            editButton.dataset.schoolYearId ||
                            '';


                        const academicLevel =
                            editButton.dataset.academicLevel ||
                            '';


                        const semester =
                            editButton.dataset.semester ||
                            '';


                        const periodName =
                            editButton.dataset.periodName ||
                            '';


                        const startDate =
                            editButton.dataset.startDate ||
                            '';


                        const endDate =
                            editButton.dataset.endDate ||
                            '';


                        /*
                         * Academic Period ID
                         */

                        if (
                            editAcademicPeriodId
                        ) {

                            editAcademicPeriodId.value =
                                termId;

                        }


                        /*
                         * School Year
                         */

                        if (
                            editSchoolYearSelect
                        ) {

                            editSchoolYearSelect.value =
                                schoolYearId;

                        }


                        /*
                         * Academic Level
                         */

                        if (
                            editAcademicLevelSelect
                        ) {

                            editAcademicLevelSelect.value =
                                academicLevel;

                        }


                        /*
                         * Semester + Academic Period
                         */

                        if (
                            academicLevel === 'College'
                        ) {

                            editSemesterGroup.style.display =
                                'block';


                            populateEditSemesterOptions(
                                semester
                            );


                            editSemesterSelect.disabled =
                                false;


                            editSemesterSelect.required =
                                true;


                            editSemesterHidden.value =
                                semester;


                            editSemesterHidden.disabled =
                                false;


                            populateEditAcademicPeriods(
                                periodName
                            );

                        } else if (
                            academicLevel === 'Senior High School'
                        ) {

                            editSemesterGroup.style.display =
                                'block';


                            populateEditSemesterOptions(
                                semester
                            );


                            editSemesterSelect.disabled =
                                false;


                            editSemesterSelect.required =
                                true;


                            editSemesterHidden.value =
                                semester;


                            editSemesterHidden.disabled =
                                false;


                            populateEditSeniorHighSchoolPeriods(
                                periodName
                            );

                        } else {

                            editSemesterGroup.style.display =
                                'block';


                            editSemesterSelect.innerHTML =
                                '';


                            const placeholder =
                                document.createElement(
                                    'option'
                                );


                            placeholder.value =
                                '';


                            placeholder.textContent =
                                '';


                            placeholder.disabled =
                                true;


                            placeholder.selected =
                                true;


                            editSemesterSelect.appendChild(
                                placeholder
                            );


                            editSemesterSelect.value =
                                '';


                            editSemesterSelect.disabled =
                                true;


                            editSemesterSelect.required =
                                false;


                            editSemesterHidden.value =
                                '';

                            editSemesterHidden.disabled =
                                true;


                            resetEditPeriodSelect();

                        }


                        /*
                         * Dates
                         */

                        if (
                            editStartDateInput
                        ) {

                            editStartDateInput.value =
                                startDate;

                        }


                        if (
                            editEndDateInput
                        ) {

                            editEndDateInput.value =
                                endDate;

                        }

                    }
                );


                editTermModal.addEventListener(
                    'hidden.bs.modal',
                    function () {

                        resetEditAcademicPeriodForm();

                    }
                );

            }



            /*
            |--------------------------------------------------------------------------
            | INITIAL STATE
            |--------------------------------------------------------------------------
            */

            resetCreateAcademicPeriodForm();

            resetEditAcademicPeriodForm();

        }

    );

</script>