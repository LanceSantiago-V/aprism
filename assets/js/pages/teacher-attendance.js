(() => {
  "use strict";

  const scanner = document.querySelector("[data-attendance-scanner]");
  if (!scanner) {
    return;
  }

  const video = scanner.querySelector("[data-attendance-video]");
  const canvas = scanner.querySelector("[data-attendance-canvas]");
  const videoFrame = scanner.querySelector(".teacher-attendance-video-frame");
  const placeholder = scanner.querySelector(
    "[data-attendance-camera-placeholder]",
  );
  const placeholderTitle = placeholder?.querySelector("strong");
  const placeholderMessage = placeholder?.querySelector("span");
  const startButton = scanner.querySelector("[data-attendance-start-camera]");
  const stopButton = scanner.querySelector("[data-attendance-stop-camera]");
  const cameraStatus = scanner.querySelector("[data-attendance-camera-status]");
  const resultPanel = scanner.querySelector("[data-attendance-scan-result]");
  const scanFeedback = scanner.querySelector("[data-attendance-scan-feedback]");
  const sessionId = Number(scanner.dataset.sessionId);
  const operationalClassId = Number(scanner.dataset.operationalClassId);
  let sessionIsOpen = scanner.dataset.sessionOpen !== "false";
  const csrfToken = scanner.dataset.csrfToken;
  const workerUrl = scanner.dataset.workerUrl;
  const decoderUrl = scanner.dataset.decoderUrl;
  const wasmUrl = scanner.dataset.wasmUrl;
  const scanEndpoint = scanner.dataset.scanEndpoint;
  const offlineState = scanner.querySelector("[data-attendance-offline-state]");
  const offlineSummary = scanner.querySelector(
    "[data-attendance-offline-summary]",
  );
  const offlineDetail = scanner.querySelector(
    "[data-attendance-offline-detail]",
  );
  const offlineList = scanner.querySelector("[data-attendance-offline-list]");
  const offlineSyncButton = scanner.querySelector(
    "[data-attendance-offline-sync]",
  );
  const closeAttendanceButton = document.querySelector(
    "[data-attendance-close-submit]",
  );
  const closeAttendanceWarning = document.querySelector(
    "[data-attendance-close-warning]",
  );
  const scanCooldownMs = 3500;
  const tagPresenceReleaseMs = 2000;
  const scanIntervalMs = 180;

  let stream = null;
  let worker = null;
  let frameTimer = null;
  let lastFrameAt = 0;
  let frameInFlight = false;
  let scannerActive = false;
  let cameraStartPending = false;
  const cooldowns = new Map();
  const tagPresence = new Map();
  const pending = new Set();
  let activeMultiTagBatch = null;
  let offlineDatabase = null;
  let offlineSyncing = false;
  let offlineSyncRequested = false;
  let offlineSyncMessage = "";
  const directSubmissionIds = new Set();

  const enrollmentIdsByTag = new Map();
  document
    .querySelectorAll("tr[data-attendance-row][data-attendance-tag-code]")
    .forEach((row) => {
      const rawTagCode = row.dataset.attendanceTagCode?.trim();
      if (rawTagCode === "") {
        return;
      }
      const tagCode = Number(rawTagCode);
      const enrollmentId = Number(row.dataset.attendanceRow);
      if (
        !Number.isInteger(tagCode) ||
        tagCode < 0 ||
        tagCode > 48713 ||
        !Number.isInteger(enrollmentId) ||
        enrollmentId < 1
      ) {
        return;
      }
      const enrollmentIds = enrollmentIdsByTag.get(tagCode) || [];
      enrollmentIds.push(enrollmentId);
      enrollmentIdsByTag.set(tagCode, enrollmentIds);
    });

  const enrollmentIdForTag = (tagCode) => {
    const enrollmentIds = enrollmentIdsByTag.get(tagCode) || [];
    return enrollmentIds.length === 1 ? enrollmentIds[0] : null;
  };

  const offlineDatabaseName = "aprism-attendance-scans";
  const offlineStoreName = "queued-scans";

  const offlineDb = () =>
    new Promise((resolve, reject) => {
      if (!window.indexedDB) {
        reject(new Error("IndexedDB is unavailable."));
        return;
      }
      const request = window.indexedDB.open(offlineDatabaseName, 1);
      request.onupgradeneeded = () => {
        const database = request.result;
        if (!database.objectStoreNames.contains(offlineStoreName)) {
          database.createObjectStore(offlineStoreName, {
            keyPath: "id",
            autoIncrement: true,
          });
        }
      };
      request.onsuccess = () => {
        const database = request.result;
        database.onversionchange = () => {
          database.close();
          if (offlineDatabase === database) {
            offlineDatabase = null;
          }
        };
        resolve(database);
      };
      request.onerror = () => reject(request.error || new Error("Local storage could not open."));
    });

  const offlineTransaction = async (mode, action) => {
    if (!offlineDatabase) {
      offlineDatabase = await offlineDb();
    }
    return new Promise((resolve, reject) => {
      let transaction;
      let request;
      let requestResult;
      let requestError = null;
      let actionResult;
      let hasActionResult = false;
      let settled = false;
      const setActionResult = (value) => {
        actionResult = value;
        hasActionResult = true;
      };
      const fail = (error) => {
        if (!settled) {
          settled = true;
          reject(error || new Error("Local queue operation failed."));
        }
      };

      try {
        transaction = offlineDatabase.transaction(offlineStoreName, mode);
        request = action(
          transaction.objectStore(offlineStoreName),
          transaction,
          setActionResult,
        );
      } catch (error) {
        try {
          transaction?.abort();
        } catch (abortError) {
          // The transaction may already have completed or aborted.
        }
        fail(error);
        return;
      }

      request.onsuccess = () => {
        requestResult = request.result;
      };
      request.onerror = () => {
        requestError = request.error || new Error("Local queue operation failed.");
      };
      transaction.oncomplete = () => {
        if (!settled) {
          settled = true;
          resolve(hasActionResult ? actionResult : requestResult);
        }
      };
      transaction.onerror = () => {
        fail(transaction.error || requestError);
      };
      transaction.onabort = () => {
        fail(transaction.error || requestError || new Error("Local queue operation was not saved."));
      };
    });
  };

  const queuedScans = async () => {
    const records = await offlineTransaction("readonly", (store) => store.getAll());
    return records.filter((record) => Number(record.sessionId) === sessionId);
  };

  const renderOfflineState = async () => {
    if (
      !offlineState ||
      !offlineSummary ||
      !offlineDetail ||
      !offlineSyncButton ||
      !offlineList
    ) {
      return;
    }
    try {
      const records = [];
      for (const record of await queuedScans()) {
        if (
          record.state !== "failed" &&
          (!Number.isInteger(Number(record.studentClassEnrollmentId)) ||
            Number(record.studentClassEnrollmentId) < 1)
        ) {
          const failedRecord = {
            ...record,
            state: "failed",
            failureCode: "assignment_snapshot_missing",
            failureMessage:
              "This saved scan has no class-participant snapshot. APRISM will not assign it automatically; review it manually.",
            canRetry: false,
          };
          await markOfflineScanFailed(
            record,
            failedRecord.failureMessage,
            failedRecord.failureCode,
            false,
          );
          records.push(failedRecord);
          continue;
        }
        records.push(record);
      }
      const failed = records.filter((record) => record.state === "failed");
      const retryableFailedCount = failed.filter((record) => record.canRetry === true).length;
      const pendingCount = records.filter((record) => record.state === "pending").length;
      const closeBlockingCount = pendingCount + retryableFailedCount;
      const isOffline = !navigator.onLine;
      offlineState.hidden = records.length === 0 && !isOffline;
      offlineState.dataset.state = failed.length > 0
        ? "warning"
        : offlineSyncing
          ? "syncing"
          : pendingCount > 0
            ? isOffline
              ? "offline"
              : "pending"
            : isOffline
              ? "offline"
              : "clear";
      offlineSummary.textContent = offlineSyncing
        ? `Syncing ${pendingCount} scan${pendingCount === 1 ? "" : "s"}…`
        : failed.length > 0 && pendingCount > 0
          ? `${pendingCount} pending · ${failed.length} need review`
          : failed.length > 0
            ? `${failed.length} scan${failed.length === 1 ? "" : "s"} need review`
            : pendingCount > 0
              ? isOffline
                ? `${pendingCount} scan${pendingCount === 1 ? "" : "s"} saved on this device`
                : `${pendingCount} scan${pendingCount === 1 ? "" : "s"} pending sync`
              : isOffline
                ? "Offline · no scans queued"
                : "No scans waiting to sync";
      offlineDetail.textContent = offlineSyncMessage
        ? offlineSyncMessage
        : failed.length > 0
          ? sessionIsOpen
            ? "APRISM did not record scans marked for review. Each item stays on this device; recheck after resolving the issue or recording attendance manually."
            : "APRISM did not record these scans. Recheck can confirm attendance already recorded before closure; this session will not accept new attendance."
          : pendingCount > 0
            ? sessionIsOpen
              ? "Reconnect or sync manually when service returns. The device-reported scan time is informational; APRISM sets the attendance status and recorded time using its server clock when it accepts the scan."
              : "This session is closed. APRISM can confirm scans already recorded, but it will reject new attendance. The device-reported time is informational."
            : isOffline
              ? sessionIsOpen
                ? "This already-open scanner can save scans here if IndexedDB is available. Reopening the page while offline is not supported."
                : "This session is closed. Saved scans remain on this device; APRISM can only confirm records that were already saved."
              : "";

      offlineList.replaceChildren();
      offlineList.hidden = records.length === 0;
      records
        .sort((left, right) => Number(left.id) - Number(right.id))
        .forEach((record) => {
          const item = document.createElement("li");
          const title = document.createElement("strong");
          const detail = document.createElement("span");
          title.textContent = `Tag #${record.tagCode} · ${record.state === "failed" ? "Needs review" : "Pending sync"}`;
          const timeText = record.capturedAtDevice
            ? `Device-reported scan time: ${record.capturedAtDevice}. `
            : "Device-reported scan time unavailable. ";
          detail.textContent = record.state === "failed"
            ? `${timeText}${record.failureMessage || "APRISM did not record this scan."}`
            : `${timeText}${sessionIsOpen
              ? "APRISM will use server time when it validates this scan."
              : "APRISM can confirm an existing record; this closed session will not accept new attendance."}`;
          item.append(title, detail);
          if (record.state === "failed" && record.canRetry === true) {
            const retryButton = document.createElement("button");
            retryButton.type = "button";
            retryButton.className = "teacher-attendance-offline-sync";
            retryButton.textContent = navigator.onLine
              ? sessionIsOpen
                ? "Recheck or retry"
                : "Check server record"
              : sessionIsOpen
                ? "Recheck when online"
                : "Check when online";
            retryButton.disabled = !navigator.onLine;
            retryButton.addEventListener("click", () => {
              void retryFailedScan(record);
            });
            item.append(retryButton);
          }
          offlineList.append(item);
        });

      offlineSyncButton.hidden = pendingCount === 0;
      offlineSyncButton.disabled = offlineSyncing || pendingCount === 0 || !navigator.onLine;
      offlineSyncButton.textContent = offlineSyncing
        ? "Syncing scans…"
        : !navigator.onLine
          ? sessionIsOpen
            ? "Sync when online"
            : "Check when online"
          : offlineSyncMessage
            ? sessionIsOpen
              ? "Retry sync"
              : "Check saved records"
            : sessionIsOpen
              ? "Sync queued scans"
              : "Check saved records";
      if (closeAttendanceButton) {
        closeAttendanceButton.disabled = closeBlockingCount > 0 || offlineSyncing;
      }
      if (closeAttendanceWarning) {
        closeAttendanceWarning.hidden = records.length === 0;
        closeAttendanceWarning.textContent = closeBlockingCount > 0
          ? `Sync or retry ${closeBlockingCount} saved scan${closeBlockingCount === 1 ? "" : "s"} before closing this session. Closing prevents APRISM from accepting them.`
          : failed.length > 0
            ? `${failed.length} scan${failed.length === 1 ? "" : "s"} were not recorded and need review. Resolve or manually record them before closing.`
            : "";
      }
    } catch (error) {
      offlineState.hidden = false;
      offlineState.dataset.state = "unavailable";
      offlineSummary.textContent = "Offline queue unavailable";
      offlineDetail.textContent = "This browser could not read its local queue. Scans cannot be confirmed as saved here.";
      offlineList.replaceChildren();
      offlineList.hidden = true;
      offlineSyncButton.hidden = true;
      offlineSyncButton.disabled = true;
      if (closeAttendanceButton) {
        closeAttendanceButton.disabled = true;
      }
      if (closeAttendanceWarning) {
        closeAttendanceWarning.hidden = false;
        closeAttendanceWarning.textContent = "APRISM could not verify this device’s pending scans. Keep this scanner open and resolve the queue storage problem before closing.";
      }
    }
  };

  const enqueueOfflineScan = async (tagCode, studentClassEnrollmentId, capturedAtDevice) => {
    const assignmentSnapshotAvailable =
      Number.isInteger(Number(studentClassEnrollmentId)) &&
      Number(studentClassEnrollmentId) > 0;
    const record = {
      sessionId,
      operationalClassId,
      studentClassEnrollmentId: assignmentSnapshotAvailable
        ? Number(studentClassEnrollmentId)
        : null,
      tagCode,
      capturedAtDevice,
      queuedAt: new Date().toISOString(),
      state: assignmentSnapshotAvailable ? "pending" : "failed",
      ...(assignmentSnapshotAvailable
        ? {}
        : {
            failureCode: "assignment_snapshot_unavailable",
            failureMessage:
              "No matching class participant was present in the loaded roster. APRISM will not assign this scan automatically; review it manually.",
            canRetry: false,
          }),
    };
    const savedRecord = await offlineTransaction(
      "readwrite",
      (store, transaction, setResult) => {
        const request = store.getAll();
        request.addEventListener(
          "success",
          () => {
            const sameQueueKey = (queued) =>
              Number(queued.sessionId) === sessionId &&
              Number(queued.operationalClassId) === operationalClassId &&
              Number(queued.tagCode) === tagCode &&
              (assignmentSnapshotAvailable
                ? Number(queued.studentClassEnrollmentId) ===
                  Number(studentClassEnrollmentId)
                : queued.studentClassEnrollmentId == null);
            const duplicate = request.result.find((queued) => {
              if (!sameQueueKey(queued)) return false;
              return queued.state === "pending" ||
                (queued.state === "failed" && queued.canRetry === true) ||
                (!assignmentSnapshotAvailable &&
                  queued.state === "failed" &&
                  queued.failureCode === "assignment_snapshot_unavailable");
            });
            if (duplicate) {
              if (duplicate.state === "failed" && duplicate.canRetry === true) {
                const {
                  failureCode,
                  failureMessage,
                  canRetry,
                  ...pendingRecord
                } = duplicate;
                const refreshedRecord = {
                  ...pendingRecord,
                  capturedAtDevice,
                  queuedAt: new Date().toISOString(),
                  state: "pending",
                  lastSyncError: "",
                };
                store.put(refreshedRecord);
                setResult(refreshedRecord);
                return;
              }
              setResult(duplicate);
              return;
            }
            const addRequest = store.add(record);
            addRequest.addEventListener(
              "success",
              () => setResult({ ...record, id: addRequest.result }),
              { once: true },
            );
          },
          { once: true },
        );
        return request;
      },
    );
    offlineSyncMessage = "";
    await renderOfflineState();
    return savedRecord;
  };

  const removeOfflineScan = async (id) => {
    await offlineTransaction("readwrite", (store) => store.delete(id));
  };

  const updateOfflineScan = async (record, update) => {
    if (!Number.isInteger(Number(record?.id))) {
      return false;
    }
    await offlineTransaction("readwrite", (store) => {
      const request = store.get(Number(record.id));
      request.addEventListener(
        "success",
        () => {
          if (request.result) {
            store.put(update(request.result));
          }
        },
        { once: true },
      );
      return request;
    });
    return true;
  };

  const markOfflineScanFailed = async (record, message, code, canRetry = false) => {
    await updateOfflineScan(record, (current) => ({
        ...current,
        state: "failed",
        failureCode: code || "scan_rejected",
        failureMessage: message,
        canRetry,
      }));
  };

  const markOfflineScanPending = async (record, message = "") => {
    await updateOfflineScan(record, (current) => {
      const {
        failureCode,
        failureMessage,
        canRetry,
        ...pendingRecord
      } = current;
      return {
        ...pendingRecord,
        state: "pending",
        lastSyncError: message,
      };
    });
  };

  const readScanResponse = async (response) => {
    const contentType = response.headers.get("content-type") || "";
    if (!contentType.toLowerCase().includes("application/json")) {
      throw new Error("APRISM returned a response that could not be read as JSON.");
    }
    const payload = await response.json();
    if (!payload || typeof payload !== "object") {
      throw new Error("APRISM returned an invalid response.");
    }
    if (payload.ok !== true && !payload.error && payload.code) {
      payload.error = {
        code: payload.code,
        message: payload.message || "Your sign-in or permissions need attention.",
      };
    }
    return payload;
  };

  const responseNeedsRetry = (response, payload) => {
    const status = Number(response?.status || 0);
    const code = payload?.error?.code || "";
    return (
      [408, 425, 429].includes(status) ||
      status >= 500 ||
      [
        "SESSION_EXPIRED",
        "SESSION_VALIDATION_FAILED",
        "SESSION_REFRESH_FAILED",
        "PASSWORD_CHANGE_REQUIRED",
        "request_not_verified",
      ].includes(code)
    );
  };

  const responseCanBeManuallyRetried = (code) =>
    [
      "attendance_not_open",
      "attendance_threshold_passed",
      "session_unavailable",
      "tag_not_assigned",
      "student_not_in_class",
      "session_closed",
    ].includes(code);

  const setCameraStatus = (message, type = "neutral") => {
    cameraStatus.textContent = message;
    cameraStatus.dataset.state = type;
  };

  const setCameraPlaceholder = (state, title, message) => {
    placeholder.dataset.state = state;
    placeholderTitle.textContent = title;
    placeholderMessage.textContent = message;
    placeholder.hidden = false;
    placeholder.style.display = "grid";
  };

  const resetCameraPlaceholder = () => {
    if (!placeholder || !placeholderTitle || !placeholderMessage) {
      return;
    }
    delete placeholder.dataset.state;
    placeholderTitle.textContent = "Camera ready when you are";
    placeholderMessage.textContent =
      "Start the camera to scan tagStandard52h13 markers.";
    placeholder.hidden = false;
    placeholder.style.display = "grid";
  };

  const reportCameraFailure = (message) => {
    cameraStartPending = false;
    setCameraPlaceholder("error", "Camera could not start", message);
    setCameraStatus(message, "error");
    startButton.disabled = false;
    stopButton.disabled = true;
    showToast("Camera could not start", message, "warning");
  };

  const clearCameraFailureToast = () => {
    const toastContainer = document.getElementById("toastContainer");
    if (!toastContainer) {
      return;
    }
    [...toastContainer.children].forEach((toast) => {
      const title = toast.querySelector(".toast-title")?.textContent?.trim();
      if (title === "Camera could not start" || title === "Camera unavailable") {
        toast.remove();
      }
    });
  };

  const cameraErrorMessage = (error) =>
    error?.name === "NotAllowedError"
      ? "Camera permission was denied. Allow access and try again."
      : error?.name === "NotFoundError"
        ? "No camera was found on this device."
        : error?.name === "NotReadableError"
          ? "The camera could not be accessed. It may be unavailable or already in use."
          : error?.name === "SecurityError"
            ? "Camera access was blocked by the browser or page policy."
            : "The camera failed to start. Check browser permissions and try again.";

  const reportDecoderFailure = (message) => {
    stopCamera();
    setCameraPlaceholder("error", "AprilTag decoder unavailable", message);
    setCameraStatus(message, "error");
    setLatestResult("Decoder unavailable", message, "error");
    showToast("AprilTag decoder", message, "warning");
  };

  const setLatestResult = (title, text, type = "neutral", items = []) => {
    if (scanFeedback) scanFeedback.hidden = false;
    resultPanel.dataset.state = type;
    resultPanel.innerHTML = "";
    const icon = document.createElement("i");
    icon.dataset.lucide =
      type === "success"
        ? "badge-check"
        : type === "error"
          ? "triangle-alert"
          : "scan";
    icon.setAttribute("aria-hidden", "true");
    const strong = document.createElement("strong");
    strong.textContent = title;
    const span = document.createElement("span");
    span.textContent = text;
    resultPanel.append(icon, strong, span);
    if (items.length > 0) {
      const list = document.createElement("ul");
      list.className = "teacher-attendance-scan-result-list";
      items.forEach((item) => {
        const listItem = document.createElement("li");
        const itemTitle = document.createElement("strong");
        itemTitle.textContent = item.title;
        const itemText = document.createElement("span");
        itemText.textContent = item.text;
        listItem.append(itemTitle, itemText);
        list.append(listItem);
      });
      resultPanel.append(list);
    }
    window.lucide?.createIcons();
  };

  const renderMultiTagBatch = (batch) => {
    const complete = batch.results.length >= batch.expected.size;
    const hasError = batch.results.some((result) => result.type === "error");
    const allSuccessful =
      complete && batch.results.every((result) => result.type === "success");
    const firstError = batch.results.find((result) => result.type === "error");
    if (firstError?.signature) {
      notifyNormalScanOutcome(
        firstError.signature,
        firstError.text,
        null,
        batch,
      );
    }
    setLatestResult(
      `${batch.expected.size} tags detected`,
      complete
        ? "Each detected tag was processed independently."
        : `Processing ${batch.expected.size} detected tags…`,
      hasError ? "error" : allSuccessful ? "success" : "neutral",
      batch.results,
    );
  };

  const showToast = (title, message, type = "warning") => {
    if (typeof window.aprismShowToast === "function") {
      const toastContainer = document.getElementById("toastContainer");
      const duplicateActive =
        toastContainer &&
        [...toastContainer.children].some((toast) => {
          const text = toast.textContent || "";
          return text.includes(title) && text.includes(message);
        });
      if (duplicateActive) {
        return;
      }
      window.aprismShowToast(title, message, type);
    }
  };

  const notifyNormalScanOutcome = (
    signature,
    message,
    appearance = null,
    batch = null,
  ) => {
    const notificationState =
      batch?.toastSignatures || appearance?.toastSignatures;
    if (!notificationState || notificationState.has(signature)) {
      return;
    }

    notificationState.add(signature);
    showToast("Attendance scan", message, "warning");
  };

  const updateRosterRow = (data) => {
    const row = document.querySelector(
      `[data-attendance-row="${data.student_class_enrollment_id}"]`,
    );
    if (!row) {
      return false;
    }

    const statusCell = row.querySelector("[data-attendance-status]");
    const recordedCell = row.querySelector("[data-attendance-recorded]");
    const sourceCell = row.querySelector("[data-attendance-source]");
    const actionCell = row.querySelector("[data-attendance-action]");
    const wasRecorded =
      statusCell?.textContent.trim() !== "Not recorded" ||
      Boolean(actionCell?.querySelector(".teacher-attendance-recorded-label"));
    const badgeClass =
      data.status === "Present"
        ? "teacher-badge-success"
        : data.status === "Late"
          ? "teacher-badge-warning"
          : "teacher-badge-danger";

    if (statusCell) {
      statusCell.innerHTML = `<span class="teacher-badge ${badgeClass}"></span>`;
      statusCell.querySelector("span").textContent = data.status;
    }
    if (recordedCell) {
      recordedCell.textContent =
        data.checked_in_at_display || data.checked_in_at;
    }
    if (sourceCell) {
      sourceCell.textContent = data.capture_source;
    }
    if (actionCell) {
      actionCell.textContent = "Recorded";
      actionCell.classList.add("teacher-attendance-recorded-label");
    }
    return wasRecorded;
  };

  const updateAttendanceMetrics = (status) => {
    const metricMap = {
      Present: "present",
      Late: "late",
      Absent: "absent",
    };
    const metricKey = metricMap[status];
    const metric = metricKey
      ? document.querySelector(`[data-attendance-metric="${metricKey}"]`)
      : null;
    const recorded = document.querySelector(
      '[data-attendance-metric="recorded"]',
    );
    const recordedLabel = document.querySelector(
      '[data-attendance-metric="recorded-label"]',
    );
    const unrecordedLabel = document.querySelector(
      '[data-attendance-metric="unrecorded-label"]',
    );
    const progressBar = document.querySelector(
      "[data-attendance-progress-bar]",
    );
    const total = Number(
      document.querySelector(".teacher-attendance-metric-total strong")
        ?.textContent || 0,
    );

    if (metric) {
      metric.textContent = String(Number(metric.textContent || 0) + 1);
    }
    const currentRecorded = Number(
      recorded?.textContent || recordedLabel?.textContent || 0,
    );
    const nextRecorded = currentRecorded + 1;
    if (recorded) {
      recorded.textContent = String(nextRecorded);
    }
    if (recordedLabel) {
      recordedLabel.textContent = String(nextRecorded);
    }
    if (unrecordedLabel) {
      unrecordedLabel.textContent = String(Math.max(0, total - nextRecorded));
    }
    if (progressBar && total > 0) {
      progressBar.style.width = `${Math.min(100, (nextRecorded / total) * 100)}%`;
    }
  };

  const serverRecordedTime = (data) =>
    data?.checked_in_at_source === "server" && data.checked_in_at
      ? `APRISM server time: ${data.checked_in_at}`
      : "APRISM server time unavailable";

  const removeSyncedOfflineScan = async (record) => {
    const id = Number(record?.id);
    if (!Number.isInteger(id) || id < 1) {
      throw new Error("The confirmed scan has no local queue identifier.");
    }
    await removeOfflineScan(id);
  };

  const retryFailedScan = async (record) => {
    if (!navigator.onLine || record.state !== "failed" || record.canRetry !== true) {
      return;
    }
    await markOfflineScanPending(record);
    offlineSyncMessage = "Retrying the selected scan against APRISM…";
    await renderOfflineState();
    await syncOfflineScans();
  };

  const syncOfflineScans = async () => {
    if (offlineSyncing) {
      offlineSyncRequested = true;
      await renderOfflineState();
      return;
    }
    if (!navigator.onLine) {
      await renderOfflineState();
      return;
    }
    offlineSyncing = true;
    offlineSyncMessage = "";
    await renderOfflineState();
    try {
      const records = await queuedScans();
      let syncedCount = 0;
      let alreadyRecordedCount = 0;
      let reviewCount = 0;
      for (const record of records) {
        if (directSubmissionIds.has(Number(record.id))) {
          continue;
        }
        if (record.state === "failed") {
          continue;
        }
        if (
          !Number.isInteger(Number(record.studentClassEnrollmentId)) ||
          Number(record.studentClassEnrollmentId) < 1
        ) {
          await markOfflineScanFailed(
            record,
            "This saved scan has no class-participant snapshot. APRISM will not assign it automatically; review it manually.",
            "assignment_snapshot_missing",
            false,
          );
          reviewCount += 1;
          continue;
        }
        try {
          const response = await fetch(scanEndpoint, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify({
              attendance_session_id: Number(record.sessionId),
              tag_code: Number(record.tagCode),
              student_class_enrollment_id: Number(record.studentClassEnrollmentId),
              csrf_token: csrfToken,
            }),
          });
          const payload = await readScanResponse(response);
          if (response.ok && payload.ok === true && payload.data) {
            const wasRecorded = updateRosterRow(payload.data);
            if (!wasRecorded) {
              updateAttendanceMetrics(payload.data.status);
            }
            try {
              await removeSyncedOfflineScan(record);
            } catch (queueError) {
              offlineSyncMessage = `APRISM recorded this scan as ${payload.data.status}, but this device could not clear its saved retry. It remains queued for safe confirmation.`;
              break;
            }
            syncedCount += 1;
            offlineSyncMessage = `Scan synced to APRISM as ${payload.data.status}. ${serverRecordedTime(payload.data)}.`;
            continue;
          }
          if (payload.error?.code === "already_recorded") {
            if (payload.data) {
              const wasRecorded = updateRosterRow(payload.data);
              if (!wasRecorded) {
                updateAttendanceMetrics(payload.data.status);
              }
            }
            try {
              await removeSyncedOfflineScan(record);
            } catch (queueError) {
              offlineSyncMessage = "APRISM had already recorded this attendance, but this device could not clear its saved retry. It remains queued for safe confirmation.";
              break;
            }
            alreadyRecordedCount += 1;
            offlineSyncMessage = payload.data
              ? `APRISM had already recorded this scan as ${payload.data.status}. ${serverRecordedTime(payload.data)}. The local retry was cleared.`
              : "APRISM had already recorded this attendance. The local retry was cleared.";
            continue;
          }
          const errorCode = payload.error?.code || "scan_rejected";
          if (errorCode === "session_closed") {
            lockScannerForClosedSession();
          }
          const errorMessage = payload.error?.message || "APRISM rejected the queued scan.";
          if (responseNeedsRetry(response, payload)) {
            await markOfflineScanPending(record, errorMessage);
            const recoveryInstruction =
              errorCode === "SESSION_EXPIRED" || errorCode === "PASSWORD_CHANGE_REQUIRED"
                ? "Refresh and sign in again before retrying."
                : errorCode === "request_not_verified"
                  ? "Refresh this scanner page to get a current request token, then retry."
                  : "Reconnect or retry synchronization when service is available.";
            offlineSyncMessage = [
              "APRISM could not confirm this scan. It remains saved on this device.",
              errorMessage,
              recoveryInstruction,
            ].join(" ");
            break;
          }
          await markOfflineScanFailed(
            record,
            errorMessage,
            errorCode,
            responseCanBeManuallyRetried(errorCode),
          );
          reviewCount += 1;
        } catch (error) {
          await markOfflineScanPending(
            record,
            error?.message || "APRISM did not return a confirmable response.",
          );
          offlineSyncMessage =
            "APRISM could not confirm this scan. It remains saved on this device; reconnect and retry synchronization.";
          break;
        }
      }
      if (!offlineSyncMessage && (syncedCount > 0 || alreadyRecordedCount > 0)) {
        const clearedCount = syncedCount + alreadyRecordedCount;
        offlineSyncMessage = `${clearedCount} queued scan${clearedCount === 1 ? "" : "s"} confirmed by APRISM; ${syncedCount} newly synced and ${alreadyRecordedCount} already recorded.`;
      } else if (!offlineSyncMessage && reviewCount > 0) {
        offlineSyncMessage = `${reviewCount} scan${reviewCount === 1 ? "" : "s"} need review. APRISM did not record them; see the details below.`;
      }
    } finally {
      offlineSyncing = false;
      await renderOfflineState();
      if (offlineSyncRequested) {
        offlineSyncRequested = false;
        if (navigator.onLine) {
          void syncOfflineScans();
        }
      }
    }
  };

  const recordScan = async (tagCode, batch = null, appearance = null) => {
    if (!scannerActive || pending.has(tagCode)) {
      return;
    }
    pending.add(tagCode);
    cooldowns.set(tagCode, Date.now() + scanCooldownMs);
    const studentClassEnrollmentId = enrollmentIdForTag(tagCode);
    const capturedAtDevice = new Date().toISOString();
    let localRecord = null;

    try {
      localRecord = await enqueueOfflineScan(
        tagCode,
        studentClassEnrollmentId,
        capturedAtDevice,
      );
    } catch (queueError) {
      offlineSyncMessage =
        "This browser could not confirm that the scan was saved locally. APRISM did not send it; keep the scanner open and resolve the local storage problem before scanning again.";
      await renderOfflineState();
      if (batch !== null) {
        batch.results.push({
          title: `Tag #${tagCode}`,
          text: "The scan was not sent because this browser could not confirm a local save.",
          type: "error",
          signature: "local_queue_write_failed",
        });
        renderMultiTagBatch(batch);
        return;
      }
      setLatestResult("Scan not submitted", offlineSyncMessage, "error");
      showToast("Attendance scan", offlineSyncMessage, "error");
      return;
    }

    if (localRecord?.state === "failed" && localRecord.canRetry !== true) {
      offlineSyncMessage = localRecord.failureMessage ||
        "This scan has no valid class-participant snapshot and cannot be sent automatically.";
      await renderOfflineState();
      if (batch !== null) {
        batch.results.push({
          title: `Tag #${tagCode}`,
          text: "Saved for review. APRISM did not submit this scan without a class-participant snapshot.",
          type: "error",
          signature: localRecord.failureCode || "assignment_snapshot_missing",
        });
        renderMultiTagBatch(batch);
        return;
      }
      setLatestResult("Scan saved for review", offlineSyncMessage, "error");
      showToast("Attendance scan", offlineSyncMessage, "error");
      return;
    }

    if (offlineSyncing) {
      offlineSyncRequested = true;
      offlineSyncMessage =
        "The scan is saved on this device. APRISM will include it in the current synchronization pass.";
      await renderOfflineState();
      if (batch !== null) {
        batch.results.push({
          title: `Tag #${tagCode}`,
          text: "Saved locally. APRISM will include it in the current synchronization pass.",
          type: "neutral",
        });
        renderMultiTagBatch(batch);
        return;
      }
      setLatestResult("Scan saved · sync in progress", offlineSyncMessage, "neutral");
      return;
    }
    directSubmissionIds.add(Number(localRecord.id));

    try {
      const response = await fetch(scanEndpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({
          attendance_session_id: sessionId,
          tag_code: tagCode,
          student_class_enrollment_id: studentClassEnrollmentId,
          csrf_token: csrfToken,
        }),
      });
      const payload = await readScanResponse(response);

      if (response.ok && payload.ok === true && payload.data) {
        const data = payload.data;
        cooldowns.set(tagCode, Date.now() + scanCooldownMs);
        const wasRecorded = updateRosterRow(data);
        if (!wasRecorded) {
          updateAttendanceMetrics(data.status);
        }
        try {
          await removeSyncedOfflineScan(localRecord);
        } catch (queueError) {
          offlineSyncMessage = "APRISM recorded this scan, but this device could not clear its saved retry. It remains queued for safe confirmation.";
        }
        await renderOfflineState();
        const recordedTime = serverRecordedTime(data);
        if (batch !== null) {
          batch.results.push({
            title: `${data.student_name} · ${data.status}`,
            text: `${data.student_number} · ${recordedTime}`,
            type: "success",
          });
          renderMultiTagBatch(batch);
          return;
        }
        setLatestResult(
          `${data.student_name} · ${data.status}`,
          `${data.student_number} · ${recordedTime}`,
          "success",
        );
        return;
      }

      if (payload.error?.code === "already_recorded") {
        cooldowns.set(tagCode, Date.now() + scanCooldownMs);
        const data = payload.data;
        if (data) {
          const wasRecorded = updateRosterRow(data);
          if (!wasRecorded) {
            updateAttendanceMetrics(data.status);
          }
        }
        try {
          await removeSyncedOfflineScan(localRecord);
        } catch (queueError) {
          offlineSyncMessage = "APRISM had already recorded this attendance, but this device could not clear its saved retry. It remains queued for safe confirmation.";
        }
        await renderOfflineState();
        const context = data?.student_name
          ? `${data.student_name} · ${data.student_number} · ${data.status} · ${serverRecordedTime(data)}`
          : payload.error.message;
        if (batch !== null) {
          batch.results.push({
            title: data?.student_name || `Tag #${tagCode}`,
            text: context,
            type: "neutral",
          });
          renderMultiTagBatch(batch);
          return;
        }
        setLatestResult("Already recorded", context, "neutral");
        return;
      }

      if (payload.error?.code === "session_closed") {
        lockScannerForClosedSession();
        await syncOfflineScans();
      }

      if (responseNeedsRetry(response, payload)) {
        const errorMessage = payload.error?.message || "APRISM could not confirm this scan.";
        if (!localRecord) {
          try {
            localRecord = await enqueueOfflineScan(
              tagCode,
              studentClassEnrollmentId,
              capturedAtDevice,
            );
          } catch (queueError) {
            localRecord = null;
          }
        }
        if (localRecord?.state === "pending") {
          await markOfflineScanPending(localRecord, errorMessage);
        }
        const savedLocally = Boolean(localRecord);
        offlineSyncMessage = savedLocally
          ? localRecord.state === "pending"
            ? `${errorMessage} The scan remains saved on this device. Refresh and sign in again if your session expired, then retry sync.`
            : "This scan is saved for review, but it has no matching active class-participant snapshot and cannot sync automatically."
          : errorMessage;
        if (batch !== null) {
          batch.results.push({
            title: `Tag #${tagCode}`,
            text: savedLocally
              ? "APRISM could not confirm the result. The scan remains saved on this device."
              : "APRISM could not confirm the result and this browser could not save the scan.",
            type: savedLocally ? "neutral" : "error",
          });
          await renderOfflineState();
          renderMultiTagBatch(batch);
          return;
        }
        setLatestResult(
          savedLocally ? "Scan saved · sync needed" : "Scan could not be confirmed",
          offlineSyncMessage,
          savedLocally ? "neutral" : "error",
        );
        await renderOfflineState();
        return;
      }

      const errorCode = payload.error?.code || "scan_rejected";
      const errorMessage = payload.error?.message || "The scan was rejected.";
      if (localRecord) {
        const hasAssignmentSnapshot =
          Number.isInteger(Number(localRecord.studentClassEnrollmentId)) &&
          Number(localRecord.studentClassEnrollmentId) > 0;
        await markOfflineScanFailed(
          localRecord,
          errorMessage,
          errorCode,
          hasAssignmentSnapshot && responseCanBeManuallyRetried(errorCode),
        );
        offlineSyncMessage = sessionIsOpen
          ? "APRISM did not record this scan. It remains on this device for recheck or manual review."
          : "APRISM did not record this scan. Check whether attendance was saved before the session closed; new attendance cannot be added now.";
      } else {
        offlineSyncMessage = "APRISM rejected the scan, and this browser could not confirm a local copy was saved.";
      }
      await renderOfflineState();
      if (!sessionIsOpen) {
        await syncOfflineScans();
      }
      if (batch !== null) {
        batch.results.push({
          title: `Tag #${tagCode}`,
          text: errorMessage,
          type: "error",
          signature: `rejected:${errorCode}:${errorMessage}`,
        });
        renderMultiTagBatch(batch);
        return;
      }
      cooldowns.set(tagCode, Date.now() + scanCooldownMs);
      const errorSignature = `rejected:${errorCode}:${errorMessage}`;
      setLatestResult("Scan not recorded", errorMessage, "error");
      notifyNormalScanOutcome(errorSignature, errorMessage, appearance);
    } catch (error) {
      if (!localRecord) {
        try {
          localRecord = await enqueueOfflineScan(
            tagCode,
            studentClassEnrollmentId,
            capturedAtDevice,
          );
        } catch (queueError) {
          localRecord = null;
        }
      }
      if (localRecord?.state === "pending") {
        await markOfflineScanPending(
          localRecord,
          error?.message || "APRISM did not return a confirmable response.",
        );
      }
      const savedLocally = Boolean(localRecord);
      const queuedForSync = localRecord?.state === "pending";
      offlineSyncMessage = savedLocally
        ? queuedForSync
          ? "APRISM did not return a confirmable response. The scan remains saved on this device and can be retried safely."
          : "This scan is saved for review, but it has no matching active class-participant snapshot and cannot sync automatically."
        : "The connection failed and this browser could not confirm that the scan was saved locally.";
      await renderOfflineState();
      if (batch !== null) {
        batch.results.push({
          title: `Tag #${tagCode}`,
          text: queuedForSync
            ? "Saved locally and waiting for APRISM validation."
            : savedLocally
              ? "Saved on this device for manual review; automatic sync is unavailable."
              : "The scan could not reach APRISM, and local storage could not confirm it was saved.",
          type: queuedForSync ? "neutral" : "error",
          signature: queuedForSync ? undefined : savedLocally ? "assignment_snapshot_missing" : "connection_error",
        });
        renderMultiTagBatch(batch);
        return;
      }
      setLatestResult(
        queuedForSync ? "Scan queued locally" : savedLocally ? "Scan saved for review" : "Connection problem",
        queuedForSync
          ? "Scan saved on this device. APRISM will validate it when synchronization succeeds."
          : savedLocally
            ? "This scan has no matching active class-participant snapshot and cannot sync automatically. Review it manually."
            : "The scan could not reach APRISM, and local storage could not confirm it was saved.",
        queuedForSync ? "neutral" : "error",
      );
      showToast(
        "Attendance scan",
        queuedForSync
          ? "Scan saved locally for validated sync."
          : savedLocally
            ? "Scan saved for manual review; automatic sync is unavailable."
            : "The scan could not reach APRISM, and local storage could not confirm it was saved.",
        queuedForSync ? "warning" : "error",
      );
    } finally {
      if (localRecord?.id !== undefined) {
        directSubmissionIds.delete(Number(localRecord.id));
      }
      pending.delete(tagCode);
      if (localRecord?.id !== undefined && navigator.onLine) {
        void syncOfflineScans();
      }
    }
  };

  const handleDetections = (detections) => {
    const uniqueIds = [
      ...new Set(detections.map((detection) => detection.id)),
    ].filter((id) => Number.isInteger(id) && id >= 0 && id <= 48713);
    const visibleIds = new Set(uniqueIds);
    const now = performance.now();

    tagPresence.forEach((presence, id) => {
      if (visibleIds.has(id)) {
        presence.lastSeenAt = now;
        return;
      }

      if (now - presence.lastSeenAt >= tagPresenceReleaseMs) {
        tagPresence.delete(id);
      }
    });

    const cooldownNow = Date.now();
    const eligibleIds = uniqueIds.filter((id) => {
      const presence = tagPresence.get(id) || {
        lastSeenAt: now,
        processed: false,
        toastSignatures: new Set(),
      };
      tagPresence.set(id, presence);
      presence.lastSeenAt = now;

      if (presence.processed || pending.has(id)) {
        return false;
      }

      return !cooldowns.has(id) || cooldowns.get(id) <= cooldownNow;
    });

    eligibleIds.forEach((id) => {
      const presence = tagPresence.get(id);
      if (presence) {
        presence.processed = true;
      }
    });

    if (eligibleIds.length > 1) {
      activeMultiTagBatch = {
        expected: new Set(eligibleIds),
        results: [],
        toastSignatures: new Set(),
      };
      renderMultiTagBatch(activeMultiTagBatch);
      eligibleIds.forEach((id) => {
        void recordScan(id, activeMultiTagBatch, tagPresence.get(id));
      });
      return;
    }

    eligibleIds.forEach((id) => {
      void recordScan(id, null, tagPresence.get(id));
    });
  };

  const processFrame = (timestamp) => {
    if (!scannerActive) {
      return;
    }

    frameTimer = requestAnimationFrame(processFrame);
    if (
      frameInFlight ||
      timestamp - lastFrameAt < scanIntervalMs ||
      video.readyState < HTMLMediaElement.HAVE_CURRENT_DATA
    ) {
      return;
    }
    lastFrameAt = timestamp;
    frameInFlight = true;

    const maxWidth = 640;
    const scale = Math.min(1, maxWidth / video.videoWidth);
    canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
    canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
    const context = canvas.getContext("2d", { willReadFrequently: true });
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
    const grayscale = new Uint8Array(canvas.width * canvas.height);
    for (
      let index = 0, pixel = 0;
      index < pixels.length;
      index += 4, pixel += 1
    ) {
      grayscale[pixel] = Math.round(
        (pixels[index] + pixels[index + 1] + pixels[index + 2]) / 3,
      );
    }
    worker.postMessage(
      {
        type: "frame",
        width: canvas.width,
        height: canvas.height,
        pixels: grayscale.buffer,
      },
      [grayscale.buffer],
    );
  };

  const stopCamera = () => {
    if (!video) {
      return;
    }
    scannerActive = false;
    cameraStartPending = false;
    clearCameraFailureToast();
    frameInFlight = false;
    if (frameTimer !== null) {
      cancelAnimationFrame(frameTimer);
      frameTimer = null;
    }
    if (stream !== null) {
      stream.getTracks().forEach((track) => track.stop());
      stream = null;
    }
    video.srcObject = null;
    if (worker !== null) {
      worker.terminate();
      worker = null;
    }
    tagPresence.clear();
    activeMultiTagBatch = null;
    video.pause();
    video.classList.remove("is-active");
    videoFrame.classList.remove("is-camera-active");
    video.style.display = "none";
    video.srcObject = null;
    video.removeAttribute("src");
    video.load();
    resetCameraPlaceholder();
    startButton.disabled = false;
    stopButton.disabled = true;
    setCameraStatus("Camera is off.");
  };

  const lockScannerForClosedSession = () => {
    scanner.dataset.sessionOpen = "false";
    sessionIsOpen = false;
    if (!video) {
      return;
    }
    stopCamera();
    startButton.disabled = true;
    setCameraStatus("Attendance session closed. Saved scans can be checked below.");
    setCameraPlaceholder(
      "error",
      "Attendance session closed",
      "APRISM can confirm saved scans that were already recorded, but this session cannot accept new attendance.",
    );
  };

  const startCamera = async () => {
    if (!sessionIsOpen) {
      lockScannerForClosedSession();
      return;
    }
    if (cameraStartPending || scannerActive || stream !== null) {
      return;
    }

    cameraStartPending = true;
    startButton.disabled = true;
    setCameraPlaceholder(
      "starting",
      "Starting camera…",
      "Waiting for camera access.",
    );
    setCameraStatus("Requesting camera permission…");

    if (
      !window.isSecureContext &&
      window.location.hostname !== "localhost" &&
      window.location.hostname !== "127.0.0.1"
    ) {
      reportCameraFailure(
        "Camera access requires HTTPS or localhost. Open APRISM over a secure connection and try again.",
      );
      return;
    }
    if (!navigator.mediaDevices?.getUserMedia) {
      reportCameraFailure(
        "This browser does not support the required camera API.",
      );
      return;
    }

    let activeWorker;
    try {
      activeWorker = new Worker(workerUrl);
    } catch (error) {
      reportDecoderFailure("The local decoder could not start.");
      return;
    }
    worker = activeWorker;
    activeWorker.onmessage = (event) => {
      if (worker !== activeWorker) {
        return;
      }
      const message = event.data || {};
      if (message.type === "ready") {
        if (stream === null) {
          return;
        }
        setCameraStatus(
          "Camera is active and continuously detecting AprilTags in view.",
          "success",
        );
        scannerActive = true;
        frameTimer = requestAnimationFrame(processFrame);
      } else if (message.type === "detections") {
        frameInFlight = false;
        handleDetections(message.detections || []);
      } else if (message.type === "error") {
        frameInFlight = false;
        reportDecoderFailure(
          message.message || "The AprilTag decoder could not process this frame.",
        );
      }
    };
    activeWorker.onerror = () => {
      if (worker !== activeWorker) {
        return;
      }
      frameInFlight = false;
      reportDecoderFailure("The local decoder could not start.");
    };

    try {
      try {
        stream = await navigator.mediaDevices.getUserMedia({
          video: {
            facingMode: { ideal: "environment" },
            width: { ideal: 1280 },
            height: { ideal: 720 },
          },
          audio: false,
        });
      } catch (error) {
        stream = await navigator.mediaDevices.getUserMedia({
          video: true,
          audio: false,
        });
      }
    } catch (error) {
      if (worker !== activeWorker || !cameraStartPending) {
        return;
      }
      stopCamera();
      reportCameraFailure(cameraErrorMessage(error));
      return;
    }

    if (worker !== activeWorker || !cameraStartPending) {
      stream?.getTracks().forEach((track) => track.stop());
      stream = null;
      return;
    }

    try {
      video.srcObject = stream;
      await video.play();
    } catch (error) {
      if (worker !== activeWorker || !cameraStartPending) {
        return;
      }
      stopCamera();
      reportCameraFailure(
        error?.name === "NotAllowedError"
          ? "The browser blocked camera preview playback. Check browser permissions and try again."
          : "The camera preview could not start. Try again.",
      );
      return;
    }

    video.classList.add("is-active");
    videoFrame.classList.add("is-camera-active");
    video.style.display = "block";
    placeholder.hidden = true;
    placeholder.style.display = "none";
    stopButton.disabled = false;
    setCameraStatus("Camera acquired. Starting AprilTag detection…");
    try {
      activeWorker.postMessage({ type: "init", decoderUrl, wasmUrl });
    } catch (error) {
      reportDecoderFailure("The local decoder could not start.");
      return;
    }
    cameraStartPending = false;
  };

  startButton?.addEventListener("click", () => {
    void startCamera();
  });
  stopButton?.addEventListener("click", stopCamera);
  offlineSyncButton?.addEventListener("click", () => {
    void syncOfflineScans();
  });
  window.addEventListener("online", () => {
    void syncOfflineScans();
  });
  window.addEventListener("offline", () => {
    void renderOfflineState();
  });
  void renderOfflineState();
  if (navigator.onLine) {
    void syncOfflineScans();
  }
  window.addEventListener("pagehide", stopCamera);
})();
