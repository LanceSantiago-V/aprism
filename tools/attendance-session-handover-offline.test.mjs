import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import path from "node:path";
import test from "node:test";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const [scanner, scanEndpoint, attendanceHelper, sessionStart] = await Promise.all([
  readFile(path.join(root, "assets/js/pages/teacher-attendance.js"), "utf8"),
  readFile(path.join(root, "actions/teacher/record_attendance_scan.php"), "utf8"),
  readFile(path.join(root, "includes/helper/attendance_helper.php"), "utf8"),
  readFile(path.join(root, "actions/teacher/start_attendance_session.php"), "utf8"),
]);

test("new sessions capture the authenticated conducting Teacher", () => {
  assert.match(sessionStart, /conducted_by_user_id/);
  assert.match(sessionStart, /\(int\) \$_SESSION\['user_id'\]/);
});

test("scan endpoint resolves both current class ownership and recorded conductor", () => {
  assert.match(scanEndpoint, /aprismResolveAttendanceScanSession\(/);
  assert.match(attendanceHelper, /oc\.teacher_id AS current_teacher_id/);
  assert.match(attendanceHelper, /ats\.conducted_by_user_id/);
  assert.match(attendanceHelper, /responsibility_changed/);
  assert.match(attendanceHelper, /session_conductor_mismatch/);
  assert.match(attendanceHelper, /\$conductorId !== null/);
});

test("responsibility changes preserve queued scans and stop camera submissions", () => {
  const pathStart = scanner.indexOf('if (errorCode === "responsibility_changed" || errorCode === "session_conductor_mismatch")');
  assert.notEqual(pathStart, -1);
  const branch = scanner.slice(pathStart, scanner.indexOf("break;", pathStart) + "break;".length);
  assert.match(branch, /markOfflineScanFailed\(record, errorMessage, errorCode, false\)/);
  assert.match(branch, /lockScannerForResponsibilityChange\(\)/);
  assert.doesNotMatch(branch, /removeOfflineScan|removeSyncedOfflineScan/);
  assert.match(scanner, /responseCanBeManuallyRetried\s*=\s*\(code\)\s*=>\s*\[([\s\S]*?)\]/);
  assert.doesNotMatch(scanner.match(/responseCanBeManuallyRetried\s*=\s*\(code\)\s*=>\s*\[([\s\S]*?)\]/)?.[1] || "", /session_unavailable/);
});

test("direct camera submissions retain and explain rejected post-handover scans", () => {
  const pathStart = scanner.indexOf('if (errorCode === "responsibility_changed" || errorCode === "session_conductor_mismatch")', scanner.indexOf("const recordScan ="));
  assert.notEqual(pathStart, -1);
  const directArea = scanner.slice(pathStart, scanner.indexOf("} catch (error)", pathStart));
  assert.match(directArea, /markOfflineScanFailed\(localRecord, errorMessage, errorCode, false\)/);
  assert.match(directArea, /lockScannerForResponsibilityChange\(\)/);
  assert.match(directArea, /notifyNormalScanOutcome/);
});
