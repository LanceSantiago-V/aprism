import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import path from "node:path";
import test from "node:test";
import { fileURLToPath } from "node:url";

const repositoryRoot = path.resolve(
  path.dirname(fileURLToPath(import.meta.url)),
  "..",
);
const attendanceScriptPath = path.join(
  repositoryRoot,
  "assets",
  "js",
  "pages",
  "teacher-attendance.js",
);
const scanEndpointPath = path.join(
  repositoryRoot,
  "actions",
  "teacher",
  "record_attendance_scan.php",
);
const attendanceHelperPath = path.join(
  repositoryRoot,
  "includes",
  "helper",
  "attendance_helper.php",
);
const source = await readFile(attendanceScriptPath, "utf8");
const scanEndpointSource = await readFile(scanEndpointPath, "utf8");
const attendanceHelperSource = await readFile(attendanceHelperPath, "utf8");

const section = (startMarker, endMarker) => {
  const start = source.indexOf(startMarker);
  assert.notEqual(start, -1, `Missing ${startMarker}`);
  const end = source.indexOf(endMarker, start + startMarker.length);
  assert.notEqual(end, -1, `Missing boundary after ${startMarker}`);
  return source.slice(start, end);
};

const requestBody = (scriptSection, description) => {
  const match = scriptSection.match(/body:\s*JSON\.stringify\(\{([\s\S]*?)\}\),/);
  assert.ok(match, `Missing JSON request body for ${description}`);
  return match[1];
};

test("offline IndexedDB writes settle only after the transaction commits or aborts", () => {
  const transaction = section("const offlineTransaction =", "const queuedScans =");

  assert.match(
    transaction,
    /transaction\.oncomplete\s*=\s*\(\)\s*=>\s*\{[\s\S]*?resolve\(/,
    "A successful request must not report a queued scan before its transaction commits.",
  );
  assert.match(
    transaction,
    /transaction\.onerror\s*=\s*\(\)\s*=>\s*\{[\s\S]*?fail\(/,
    "IndexedDB transaction errors must reject the queue operation.",
  );
  assert.match(
    transaction,
    /transaction\.onabort\s*=\s*\(\)\s*=>\s*\{[\s\S]*?fail\(/,
    "IndexedDB transaction aborts must reject the queue operation.",
  );
});

test("only nonempty roster tag attributes become assignment snapshots", () => {
  const rosterSnapshot = section("const enrollmentIdsByTag = new Map();", "const enrollmentIdForTag =");

  assert.match(
    rosterSnapshot,
    /const rawTagCode\s*=\s*row\.dataset\.attendanceTagCode\?\.trim\(\);[\s\S]*?if\s*\(rawTagCode\s*===\s*["']{2}\)\s*\{[\s\S]*?return;[\s\S]*?\}[\s\S]*?Number\(rawTagCode\)/,
    "An empty data-attendance-tag-code attribute must be ignored before Number() can turn it into valid AprilTag ID 0.",
  );
});

test("offline records preserve assignment context but never submit a device timestamp", () => {
  const enqueue = section("const enqueueOfflineScan =", "const removeOfflineScan =");

  assert.match(
    enqueue,
    /studentClassEnrollmentId\s*[, :]/,
    "A queued scan needs its roster assignment snapshot for later server revalidation.",
  );
  assert.match(
    enqueue,
    /\bcapturedAtDevice\b/,
    "Local capture time must be explicitly labelled as device time.",
  );
  assert.doesNotMatch(
    enqueue,
    /checked_in_at\s*:/,
    "The client must not claim a server check-in timestamp while queueing.",
  );
});

test("queue deduplication is atomic across sessions, classes, tags, and enrollment snapshots", () => {
  const enqueue = section("const enqueueOfflineScan =", "const removeOfflineScan =");

  assert.match(
    enqueue,
    /offlineTransaction\(\s*["']readwrite["'][\s\S]*?store\.getAll\(\)[\s\S]*?request\.result\.find\(/,
    "Duplicate detection and insertion must share one serialized IndexedDB readwrite transaction.",
  );
  for (const key of ["sessionId", "operationalClassId", "tagCode", "studentClassEnrollmentId"]) {
    assert.ok(enqueue.includes(key), `Queue identity must include ${key}.`);
  }
  assert.match(
    enqueue,
    /queued\.state\s*===\s*["']pending["'][\s\S]*?queued\.canRetry\s*===\s*true/,
    "An existing pending or retryable item should be reused instead of duplicated.",
  );
  assert.doesNotMatch(
    enqueue,
    /await\s+queuedScans\(\)/,
    "A separate read transaction would leave a cross-tab duplicate-insert race.",
  );
});

test("malformed or missing participant evidence is review-only and cannot reach the server", () => {
  const enqueue = section("const enqueueOfflineScan =", "const removeOfflineScan =");
  const directScan = section("const recordScan =", "const handleDetections =");
  const queuedSync = section("const syncOfflineScans =", "const recordScan =");

  assert.match(enqueue, /Number\.isInteger\(Number\(studentClassEnrollmentId\)\)[\s\S]*?studentClassEnrollmentId:\s*assignmentSnapshotAvailable[\s\S]*?:\s*null/);
  assert.match(enqueue, /failureCode:\s*["']assignment_snapshot_unavailable["'][\s\S]*?canRetry:\s*false/);
  assert.match(
    directScan,
    /localRecord\?\.state\s*===\s*["']failed["'][\s\S]*?localRecord\.canRetry\s*!==\s*true[\s\S]*?return;/,
  );
  assert.match(
    queuedSync,
    /!Number\.isInteger\(Number\(record\.studentClassEnrollmentId\)\)[\s\S]*?markOfflineScanFailed\([\s\S]*?continue;/,
  );
});

test("only the exact confirmed queue item is removed", () => {
  const removal = section("const removeSyncedOfflineScan =", "const retryFailedScan =");

  assert.match(removal, /removeOfflineScan\(id\)/);
  assert.doesNotMatch(removal, /queuedScans|tagCode|studentClassEnrollmentId/);
  assert.match(
    source,
    /removeSyncedOfflineScan\(localRecord\)/,
    "Direct scan confirmation must identify the exact local queue record it may clear.",
  );
});

test("a scan is not sent unless the local queue transaction committed", () => {
  const directScan = section("const recordScan =", "const handleDetections =");
  const queueWrite = directScan.indexOf("localRecord = await enqueueOfflineScan(");
  const queueWriteFailure = directScan.indexOf("This browser could not confirm that the scan was saved locally.");
  const fetchRequest = directScan.indexOf("const response = await fetch(scanEndpoint");

  assert.ok(queueWrite >= 0 && queueWriteFailure > queueWrite && fetchRequest > queueWriteFailure);
  assert.match(
    directScan.slice(queueWriteFailure, fetchRequest),
    /return;/,
    "Queue storage failure must leave before network submission.",
  );
  assert.match(
    directScan,
    /offlineSyncing\)[\s\S]*?offlineSyncRequested\s*=\s*true[\s\S]*?return;/,
    "A direct scan arriving during reconnect/manual sync must stay queued for the coalesced next pass.",
  );
});

test("direct and queued submissions of one record are coordinated", () => {
  const queuedSync = section("const syncOfflineScans =", "const recordScan =");
  const directScan = section("const recordScan =", "const handleDetections =");

  assert.match(queuedSync, /if\s*\(directSubmissionIds\.has\(Number\(record\.id\)\)\)\s*\{\s*continue;/);
  assert.match(directScan, /directSubmissionIds\.add\(Number\(localRecord\.id\)\)/);
  assert.match(directScan, /directSubmissionIds\.delete\(Number\(localRecord\.id\)\)/);
  assert.match(source, /if\s*\(offlineSyncing\)\s*\{\s*offlineSyncRequested\s*=\s*true/);
});

test("direct scans and queued replays send the same assignment snapshot", () => {
  const directScan = section("const recordScan =", "const handleDetections =");
  const queuedSync = section("const syncOfflineScans =", "const recordScan =");

  for (const [name, requestPath] of [
    ["direct scan", directScan],
    ["queued replay", queuedSync],
  ]) {
    const payload = requestBody(requestPath, name);
    assert.match(
      payload,
      /student_class_enrollment_id\s*:\s*(?:Number\()?\s*(?:studentClassEnrollmentId|record\.studentClassEnrollmentId)/,
      `${name} must bind the scan to the assignment snapshot that APRISM revalidates.`,
    );
    assert.doesNotMatch(
      payload,
      /(?:capturedAtDevice|queuedAt|checked_in_at)\s*:/,
      `${name} must not submit a device or queue timestamp as an attendance timestamp.`,
    );
  }
});

test("sync distinguishes idempotent, retryable, and review-required outcomes", () => {
  const queuedSync = section("const syncOfflineScans =", "const recordScan =");

  assert.match(
    queuedSync,
    /payload\.error\?\.code\s*===\s*["']already_recorded["'][\s\S]*?await removeSyncedOfflineScan\(record\)/,
    "An idempotent response must clear the local duplicate rather than retry it.",
  );
  assert.match(
    queuedSync,
    /!Number\.isInteger\(Number\(record\.studentClassEnrollmentId\)\)[\s\S]*?markOfflineScanFailed\(/,
    "Legacy queue records without an assignment snapshot must stay visible for review and must not sync.",
  );
  assert.match(
    queuedSync,
    /await markOfflineScanFailed\([\s\S]*?errorMessage/,
    "A server rejection must remain recoverable and visible for review.",
  );
  assert.match(
    queuedSync,
    /if\s*\(responseNeedsRetry\(response, payload\)\)[\s\S]*?await markOfflineScanPending\(/,
    "Retryable authentication or server responses must remain pending rather than be treated as permanent failures.",
  );
  assert.match(
    queuedSync,
    /catch\s*\(error\)\s*\{[\s\S]*?break;/,
    "A transport failure must leave the current and later records pending for a later retry.",
  );
  assert.match(
    queuedSync,
    /errorCode\s*===\s*["']session_closed["'][\s\S]*?lockScannerForClosedSession\(\)/,
    "A stale open scanner must stop camera capture when APRISM reports that the session closed.",
  );
});

test("closed sessions still return canonical already-recorded retries before blocking new scans", () => {
  const existingLookup = scanEndpointSource.indexOf("$existingStmt = $pdo->prepare");
  const existingResponse = scanEndpointSource.indexOf("'already_recorded'", existingLookup);
  const closedSessionCheck = scanEndpointSource.indexOf("(string) $session['status'] !== 'Open'");
  const insert = scanEndpointSource.indexOf("INSERT INTO student_attendance_records");

  assert.notEqual(existingLookup, -1, "The endpoint must lock and inspect the canonical attendance record.");
  assert.notEqual(existingResponse, -1, "The endpoint must expose the canonical already-recorded response.");
  assert.notEqual(closedSessionCheck, -1, "The endpoint must block new scans for a closed session.");
  assert.notEqual(insert, -1, "The endpoint must retain the guarded attendance insert.");
  assert.ok(
    existingLookup < existingResponse && existingResponse < closedSessionCheck,
    "An idempotent retry must be resolved before a closed session rejects new scans.",
  );
  assert.ok(
    closedSessionCheck < insert,
    "The closed-session check must remain before the write path.",
  );
});

test("server scan validation retains Teacher, active class, tag, participant, CSRF, and policy boundaries", () => {
  assert.match(scanEndpointSource, /validateCsrfToken\(/, "Every scan mutation must pass CSRF validation.");
  assert.match(
    scanEndpointSource,
    /aprismResolveAttendanceScanSession\(\s*\$pdo\s*,\s*\$sessionId\s*,\s*\(int\)\s*\$_SESSION\['user_id'\]\s*\)/,
    "The endpoint must resolve the session for the authenticated Teacher.",
  );
  assert.match(
    attendanceHelperSource,
    /oc\.teacher_id\s+AS\s+current_teacher_id/i,
    "The session resolver must check current class ownership.",
  );
  assert.match(attendanceHelperSource, /oc\.status\s*=\s*'Active'/i, "The class must still be active.");
  assert.match(attendanceHelperSource, /active_sy\.status\s*=\s*'Active'/i, "The class must remain in the active School Year.");
  assert.match(scanEndpointSource, /at\.tag_family\s*=\s*'tagStandard52h13'/i, "Only the frozen AprilTag family is accepted.");
  assert.match(scanEndpointSource, /at\.current_student_id\s+IS\s+NOT\s+NULL/i, "Current global tag ownership must be resolved server-side.");
  assert.match(scanEndpointSource, /sce\.operational_class_id\s*=\s*\?/i, "The current class participant must be revalidated server-side.");
  assert.match(scanEndpointSource, /sce\.status\s*=\s*'Active'/i, "The class enrollment must still be active.");
  assert.match(scanEndpointSource, /student_class_enrollment_id'\]\s*!==\s*\$expectedEnrollmentId/i, "The submitted roster snapshot is consistency evidence, not authorization.");
  assert.match(scanEndpointSource, /aprismAttendancePolicy\(\$pdo\)/, "Configured attendance policy must stay dynamic.");
});

test("a tag reassigned after capture cannot be attributed to its new owner", () => {
  const currentOwner = scanEndpointSource.indexOf("$participantStmt->execute([");
  const snapshotComparison = scanEndpointSource.indexOf("$participant['student_class_enrollment_id'] !== $expectedEnrollmentId");
  const staleResponse = scanEndpointSource.indexOf("'scan_context_changed'");
  const insert = scanEndpointSource.indexOf("INSERT INTO student_attendance_records");

  assert.ok(currentOwner >= 0 && snapshotComparison > currentOwner);
  assert.ok(staleResponse > snapshotComparison && insert > staleResponse);
  assert.match(
    scanEndpointSource.slice(snapshotComparison, insert),
    /attendanceScanRespond\([\s\S]*?409[\s\S]*?scan_context_changed/,
    "The server must reject the stale assignment before entering the insert path.",
  );
});

test("permanent validation responses are review states, while transport/server uncertainty stays pending", () => {
  const retryPolicy = section("const responseNeedsRetry =", "const setCameraStatus =");
  const queuedSync = section("const syncOfflineScans =", "const recordScan =");

  assert.match(retryPolicy, /status\s*>=\s*500/);
  assert.match(retryPolicy, /\[408,\s*425,\s*429\]/);
  assert.doesNotMatch(retryPolicy, /\b422\b/, "Permanent validation responses must not auto-retry.");
  const manualRetryPolicy = section("const responseCanBeManuallyRetried =", "const setCameraStatus =");
  assert.doesNotMatch(manualRetryPolicy, /scan_context_changed/, "Changed participant identity requires review rather than retry.");
  assert.match(queuedSync, /if\s*\(responseNeedsRetry\(response, payload\)\)[\s\S]*?markOfflineScanPending\(/);
  assert.match(queuedSync, /await markOfflineScanFailed\([\s\S]*?responseCanBeManuallyRetried\(errorCode\)/);
  assert.match(queuedSync, /catch\s*\(error\)\s*\{[\s\S]*?await markOfflineScanPending\([\s\S]*?break;/);
});

test("server clock alone determines the stored check-in time and attendance status", () => {
  assert.match(scanEndpointSource, /new DateTimeImmutable\('now'\)/, "The server clock supplies the validation instant.");
  assert.match(scanEndpointSource, /\$checkedInAt\s*=\s*\$now->format\(/, "The stored check-in timestamp comes from server time.");
  assert.match(scanEndpointSource, /'checked_in_at_source'\s*=>\s*'server'/, "Responses label the recorded timestamp source.");
  assert.doesNotMatch(
    scanEndpointSource,
    /\$request\s*\[\s*['"](?:capturedAtDevice|queuedAt|checked_in_at|scan_timestamp|timestamp)['"]\s*\]/i,
    "The server must not accept a client/device timestamp for attendance decisions.",
  );
});
