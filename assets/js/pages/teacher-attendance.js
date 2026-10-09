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
  const placeholderTitle = placeholder.querySelector("strong");
  const placeholderMessage = placeholder.querySelector("span");
  const startButton = scanner.querySelector("[data-attendance-start-camera]");
  const stopButton = scanner.querySelector("[data-attendance-stop-camera]");
  const cameraStatus = scanner.querySelector("[data-attendance-camera-status]");
  const resultPanel = scanner.querySelector("[data-attendance-scan-result]");
  const scanFeedback = scanner.querySelector("[data-attendance-scan-feedback]");
  const sessionId = Number(scanner.dataset.sessionId);
  const operationalClassId = Number(scanner.dataset.operationalClassId);
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
  const offlineSyncButton = scanner.querySelector(
    "[data-attendance-offline-sync]",
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
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error || new Error("Local storage could not open."));
    });

  const offlineTransaction = async (mode, action) => {
    if (!offlineDatabase) {
      offlineDatabase = await offlineDb();
    }
    return new Promise((resolve, reject) => {
      const transaction = offlineDatabase.transaction(offlineStoreName, mode);
      const store = transaction.objectStore(offlineStoreName);
      const request = action(store);
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error || new Error("Local queue operation failed."));
      transaction.onerror = () => reject(transaction.error || new Error("Local queue operation failed."));
    });
  };

  const queuedScans = async () => {
    const records = await offlineTransaction("readonly", (store) => store.getAll());
    return records.filter((record) => Number(record.sessionId) === sessionId);
  };

  const renderOfflineState = async () => {
    if (!offlineState || !offlineSummary || !offlineDetail || !offlineSyncButton) {
      return;
    }
    try {
      const records = await queuedScans();
      const failed = records.filter((record) => record.state === "failed");
      const pendingCount = records.length - failed.length;
      const firstFailure = failed.find((record) => record.failureMessage);
      const isOffline = !navigator.onLine;
      offlineState.hidden = records.length === 0 && !isOffline;
      offlineState.dataset.state = failed.length > 0 ? "warning" : pendingCount > 0 ? "pending" : isOffline ? "offline" : "clear";
      offlineSummary.textContent = failed.length > 0
        ? `${pendingCount} pending · ${failed.length} needs review`
        : pendingCount > 0
          ? `${pendingCount} scan${pendingCount === 1 ? "" : "s"} waiting to sync`
          : isOffline
            ? "Offline · no scans queued"
            : "No scans waiting to sync";
      offlineDetail.textContent = failed.length > 0
        ? `APRISM did not record these scans.${firstFailure ? ` First reason: ${firstFailure.failureMessage}` : ""} If the session is still open, use the matching Mark Manually control below when appropriate.`
        : pendingCount > 0
          ? "Queued scans will be revalidated by APRISM when a connection is available."
          : isOffline
            ? "Scans can be queued on this device and will be revalidated by APRISM after connection returns."
            : "";
      offlineSyncButton.hidden = pendingCount === 0 || !navigator.onLine;
      offlineSyncButton.disabled = offlineSyncing || pendingCount === 0 || !navigator.onLine;
    } catch (error) {
      offlineState.hidden = false;
      offlineState.dataset.state = "unavailable";
      offlineSummary.textContent = "Offline queue unavailable";
      offlineDetail.textContent = "This browser cannot safely save scans while disconnected.";
      offlineSyncButton.hidden = true;
      offlineSyncButton.disabled = true;
    }
  };

  const enqueueOfflineScan = async (tagCode) => {
    const existing = await queuedScans();
    if (existing.some((record) => Number(record.tagCode) === tagCode && record.state !== "failed")) {
      return false;
    }
    await offlineTransaction("readwrite", (store) =>
      store.add({
        sessionId,
        operationalClassId,
        tagCode,
        queuedAt: new Date().toISOString(),
        state: "pending",
      }),
    );
    await renderOfflineState();
    return true;
  };

  const removeOfflineScan = async (id) => {
    await offlineTransaction("readwrite", (store) => store.delete(id));
  };

  const markOfflineScanFailed = async (record, message) => {
    await offlineTransaction("readwrite", (store) =>
      store.put({ ...record, state: "failed", failureMessage: message }),
    );
  };

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
      return;
    }

    const statusCell = row.querySelector("[data-attendance-status]");
    const recordedCell = row.querySelector("[data-attendance-recorded]");
    const sourceCell = row.querySelector("[data-attendance-source]");
    const actionCell = row.querySelector("[data-attendance-action]");
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

  const syncOfflineScans = async () => {
    if (offlineSyncing || !navigator.onLine) {
      await renderOfflineState();
      return;
    }
    offlineSyncing = true;
    await renderOfflineState();
    try {
      const records = await queuedScans();
      for (const record of records) {
        if (record.state === "failed") {
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
              csrf_token: csrfToken,
            }),
          });
          const payload = await response.json();
          if (payload.ok) {
            updateRosterRow(payload.data);
            updateAttendanceMetrics(payload.data.status);
            await removeOfflineScan(record.id);
            continue;
          }
          if (payload.error?.code === "already_recorded") {
            if (payload.data) {
              updateRosterRow(payload.data);
            }
            await removeOfflineScan(record.id);
            continue;
          }
          await markOfflineScanFailed(
            record,
            payload.error?.message || "APRISM rejected the queued scan.",
          );
        } catch (error) {
          // Keep unsent records pending; a later online event may retry them.
          break;
        }
      }
    } finally {
      offlineSyncing = false;
      await renderOfflineState();
    }
  };

  const recordScan = async (tagCode, batch = null, appearance = null) => {
    if (!scannerActive || pending.has(tagCode)) {
      return;
    }
    pending.add(tagCode);

    try {
      const response = await fetch(scanEndpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({
          attendance_session_id: sessionId,
          tag_code: tagCode,
          csrf_token: csrfToken,
        }),
      });
      const payload = await response.json();

      if (payload.ok) {
        const data = payload.data;
        cooldowns.set(tagCode, Date.now() + scanCooldownMs);
        updateRosterRow(data);
        updateAttendanceMetrics(data.status);
        if (batch !== null) {
          batch.results.push({
            title: `${data.student_name} · ${data.status}`,
            text: `${data.student_number} · Recorded ${data.checked_in_at_display || data.checked_in_at}`,
            type: "success",
          });
          renderMultiTagBatch(batch);
          return;
        }
        setLatestResult(
          `${data.student_name} · ${data.status}`,
          `${data.student_number} · Recorded ${data.checked_in_at_display || data.checked_in_at}`,
          "success",
        );
        return;
      }

      if (payload.error?.code === "already_recorded") {
        cooldowns.set(tagCode, Date.now() + scanCooldownMs);
        const data = payload.data;
        const context = data?.student_name
          ? `${data.student_name} · ${data.student_number} · ${data.status} · Recorded ${data.checked_in_at || "previously"}`
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

      if (batch !== null) {
        batch.results.push({
          title: `Tag #${tagCode}`,
          text: payload.error?.message || "The scan was rejected.",
          type: "error",
          signature: `rejected:${payload.error?.code || "scan_rejected"}:${payload.error?.message || "The scan was rejected."}`,
        });
        renderMultiTagBatch(batch);
        return;
      }
      cooldowns.set(tagCode, Date.now() + scanCooldownMs);
      const errorMessage = payload.error?.message || "The scan was rejected.";
      const errorSignature = `rejected:${payload.error?.code || "scan_rejected"}:${errorMessage}`;
      setLatestResult("Scan not recorded", errorMessage, "error");
      notifyNormalScanOutcome(errorSignature, errorMessage, appearance);
    } catch (error) {
      let queued = false;
      try {
        queued = await enqueueOfflineScan(tagCode);
      } catch (queueError) {
        queued = false;
      }
      if (batch !== null) {
        batch.results.push({
          title: `Tag #${tagCode}`,
          text: queued
            ? "Saved locally and waiting for APRISM validation."
            : "The scan could not reach APRISM.",
          type: queued ? "neutral" : "error",
          signature: queued ? undefined : "connection_error",
        });
        renderMultiTagBatch(batch);
        return;
      }
      setLatestResult(
        queued ? "Scan queued locally" : "Connection problem",
        queued
          ? "APRISM will validate this scan when a connection is available."
          : "The scan could not reach APRISM.",
        queued ? "neutral" : "error",
      );
      showToast(
        "Attendance scan",
        queued
          ? "Scan saved locally for validated sync."
          : "The scan could not reach APRISM.",
        "warning",
      );
    } finally {
      pending.delete(tagCode);
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

  const startCamera = async () => {
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

  startButton.addEventListener("click", () => {
    void startCamera();
  });
  stopButton.addEventListener("click", stopCamera);
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
