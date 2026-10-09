import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const rawDirectory = process.argv[2];
const modulePath = path.resolve(
  process.argv[3] ?? 'assets/vendor/apriltag/aprism_apriltag_detector.js'
);

if (!rawDirectory) {
  throw new Error('A directory containing APRISM grayscale fixture files is required.');
}

const createModule = (await import(pathToFileURL(modulePath))).default;
const detector = await createModule();

if (detector._aprism_detector_init() !== 0) {
  throw new Error('The WASM AprilTag detector failed to initialize.');
}

const expected = new Map([
  ['tag52_13_00000.raw', 0],
  ['tag52_13_00001.raw', 1],
  ['tag52_13_00002.raw', 2],
  ['tag52_13_12345.raw', 12345],
  ['tag52_13_48713.raw', 48713],
]);

for (const [filename, expectedId] of expected) {
  const buffer = fs.readFileSync(path.join(rawDirectory, filename));
  const headerEnd = buffer.indexOf(0x0a);
  const width = Number(buffer.subarray(0, headerEnd).toString());
  const heightEnd = buffer.indexOf(0x0a, headerEnd + 1);
  const height = Number(buffer.subarray(headerEnd + 1, heightEnd).toString());
  const pixels = buffer.subarray(heightEnd + 1);
  const imagePointer = detector._aprism_detector_set_image(width, height, width);
  detector.HEAPU8.set(pixels, imagePointer);
  const detections = JSON.parse(detector.UTF8ToString(detector._aprism_detector_detect()));
  const ids = detections.map((detection) => detection.id);

  if (!ids.includes(expectedId)) {
    throw new Error(`${filename}: expected ${expectedId}, received ${JSON.stringify(ids)}`);
  }
  console.log(`${filename}: detected ${expectedId}`);
}

const blank = fs.readFileSync(path.join(rawDirectory, 'blank.raw'));
const blankHeaderEnd = blank.indexOf(0x0a);
const blankHeightEnd = blank.indexOf(0x0a, blankHeaderEnd + 1);
const blankWidth = Number(blank.subarray(0, blankHeaderEnd).toString());
const blankHeight = Number(blank.subarray(blankHeaderEnd + 1, blankHeightEnd).toString());
const blankPixels = blank.subarray(blankHeightEnd + 1);
const blankPointer = detector._aprism_detector_set_image(blankWidth, blankHeight, blankWidth);
detector.HEAPU8.set(blankPixels, blankPointer);
const blankDetections = JSON.parse(detector.UTF8ToString(detector._aprism_detector_detect()));

if (blankDetections.length !== 0) {
  throw new Error(`blank.raw: expected no detections, received ${JSON.stringify(blankDetections)}`);
}

console.log('blank.raw: no detections');
detector._aprism_detector_destroy();
