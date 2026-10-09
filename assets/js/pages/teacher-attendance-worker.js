let detector = null;
let imagePointer = 0;
let imageWidth = 0;
let imageHeight = 0;
let imageStride = 0;

self.onmessage = async (event) => {
  const message = event.data || {};

  try {
    if (message.type === "init") {
      importScripts(message.decoderUrl);
      detector = await self.AprismAprilTagModule({
        locateFile: (path) => (path.endsWith(".wasm") ? message.wasmUrl : path),
      });
      if (detector._aprism_detector_init() !== 0) {
        throw new Error("The AprilTag decoder could not initialize.");
      }
      self.postMessage({ type: "ready" });
      return;
    }

    if (message.type !== "frame" || detector === null) {
      return;
    }

    if (
      imageWidth !== message.width ||
      imageHeight !== message.height ||
      imageStride !== message.width ||
      imagePointer === 0
    ) {
      imageWidth = message.width;
      imageHeight = message.height;
      imageStride = message.width;
      imagePointer = detector._aprism_detector_set_image(
        imageWidth,
        imageHeight,
        imageStride,
      );
    }

    detector.HEAPU8.set(new Uint8Array(message.pixels), imagePointer);
    const result = JSON.parse(
      detector.UTF8ToString(detector._aprism_detector_detect()),
    );
    const detections = result
      .filter((detection) => Number.isInteger(detection.id))
      .filter((detection) => detection.id >= 0 && detection.id <= 48713)
      .map((detection) => ({
        id: detection.id,
        center: detection.center,
        corners: detection.corners,
      }));

    self.postMessage({ type: "detections", detections });
  } catch (error) {
    self.postMessage({
      type: "error",
      message:
        error instanceof Error ? error.message : "Decoder processing failed.",
    });
  }
};

self.addEventListener("close", () => {
  detector?._aprism_detector_destroy();
});
