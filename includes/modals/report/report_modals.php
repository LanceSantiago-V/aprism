<!-- ==========================================================
     REPORT MODALS
     APRISM
=========================================================== -->

<!-- ==========================================================
     GENERATING REPORT
=========================================================== -->

<div class="modal fade" id="generateReportModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static"
    data-bs-keyboard="false">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content report-modal">

            <div class="modal-body report-loading-body">

                <div class="report-loading-spinner">

                    <div class="spinner-border text-primary"></div>

                </div>

                <h4>

                    Generating Report...

                </h4>

                <p>

                    APRISM is compiling records based on the selected
                    report configuration.

                </p>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
     EXPORT PDF
=========================================================== -->

<div class="modal fade" id="exportPdfModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content report-modal">

            <div class="modal-header">

                <h5 class="modal-title">

                    Export PDF

                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <p>

                    Export the current report preview as a PDF document?

                </p>

            </div>

            <div class="modal-footer">

                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">

                    Cancel

                </button>

                <button type="button" class="btn btn-danger" id="confirmPdfExport">

                    Export PDF

                </button>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
     EXPORT EXCEL
=========================================================== -->

<div class="modal fade" id="exportExcelModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content report-modal">

            <div class="modal-header">

                <h5 class="modal-title">

                    Export Excel

                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <p>

                    Export the current report preview as an Excel workbook?

                </p>

            </div>

            <div class="modal-footer">

                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">

                    Cancel

                </button>

                <button type="button" class="btn btn-success" id="confirmExcelExport">

                    Export Excel

                </button>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
     EXPORT SUCCESS
=========================================================== -->

<div class="modal fade" id="reportSuccessModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content report-modal">

            <div class="modal-body report-result-body">

                <div class="report-result-icon success">

                    <i data-lucide="check-circle"></i>

                </div>

                <h4>

                    Export Complete

                </h4>

                <p>

                    Your report has been successfully exported.

                </p>

                <button class="btn btn-primary" data-bs-dismiss="modal">

                    Done

                </button>

            </div>

        </div>

    </div>

</div>

<!-- ==========================================================
     REPORT ERROR
=========================================================== -->

<div class="modal fade" id="reportErrorModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content report-modal">

            <div class="modal-body report-result-body">

                <div class="report-result-icon error">

                    <i data-lucide="triangle-alert"></i>

                </div>

                <h4>

                    Unable to Generate Report

                </h4>

                <p>

                    No records were found for the selected filters.

                </p>

                <button class="btn btn-primary" data-bs-dismiss="modal">

                    Close

                </button>

            </div>

        </div>

    </div>

</div>