/*
 * APRISM browser detector boundary.
 *
 * This intentionally exposes only grayscale image input and AprilTag ID/
 * geometry output. Pose estimation and alternate tag families are excluded.
 */

#include <stdio.h>
#include <stdint.h>
#include <stdlib.h>
#include <string.h>

#include "apriltag.h"
#include "tagStandard52h13.h"
#include "common/image_u8.h"
#include "common/zarray.h"

#ifdef __EMSCRIPTEN__
#include <emscripten/emscripten.h>
#else
#define EMSCRIPTEN_KEEPALIVE
#endif

#define APRISM_MAX_DETECTIONS 64
#define APRISM_RESULT_BYTES 16384

static apriltag_detector_t *aprism_detector = NULL;
static apriltag_family_t *aprism_family = NULL;
static uint8_t *aprism_image_buffer = NULL;
static int aprism_width = 0;
static int aprism_height = 0;
static int aprism_stride = 0;
static char aprism_result[APRISM_RESULT_BYTES];

EMSCRIPTEN_KEEPALIVE
int aprism_detector_init(void)
{
    if (aprism_detector != NULL) {
        return 0;
    }

    aprism_family = tagStandard52h13_create();
    aprism_detector = apriltag_detector_create();

    if (aprism_family == NULL || aprism_detector == NULL) {
        if (aprism_detector != NULL) {
            apriltag_detector_destroy(aprism_detector);
            aprism_detector = NULL;
        }
        if (aprism_family != NULL) {
            tagStandard52h13_destroy(aprism_family);
            aprism_family = NULL;
        }
        return -1;
    }

    apriltag_detector_add_family_bits(aprism_detector, aprism_family, 1);
    aprism_detector->quad_decimate = 2.0;
    aprism_detector->quad_sigma = 0.0;
    aprism_detector->nthreads = 1;
    aprism_detector->refine_edges = 1;
    aprism_detector->debug = 0;

    return 0;
}

EMSCRIPTEN_KEEPALIVE
void aprism_detector_destroy(void)
{
    if (aprism_detector != NULL) {
        apriltag_detector_destroy(aprism_detector);
        aprism_detector = NULL;
    }
    if (aprism_family != NULL) {
        tagStandard52h13_destroy(aprism_family);
        aprism_family = NULL;
    }
    free(aprism_image_buffer);
    aprism_image_buffer = NULL;
    aprism_width = 0;
    aprism_height = 0;
    aprism_stride = 0;
}

EMSCRIPTEN_KEEPALIVE
uint8_t *aprism_detector_set_image(int width, int height, int stride)
{
    if (width <= 0 || height <= 0 || stride < width) {
        return NULL;
    }

    size_t required = (size_t) height * (size_t) stride;
    if (aprism_image_buffer == NULL ||
        aprism_width != width ||
        aprism_height != height ||
        aprism_stride != stride) {
        uint8_t *next_buffer = (uint8_t *) realloc(aprism_image_buffer, required);
        if (next_buffer == NULL) {
            return NULL;
        }
        aprism_image_buffer = next_buffer;
        aprism_width = width;
        aprism_height = height;
        aprism_stride = stride;
    }

    return aprism_image_buffer;
}

EMSCRIPTEN_KEEPALIVE
const char *aprism_detector_detect(void)
{
    if (aprism_detector == NULL ||
        aprism_family == NULL ||
        aprism_image_buffer == NULL) {
        return "{\"error\":\"detector_not_ready\"}";
    }

    image_u8_t image = {
        .width = aprism_width,
        .height = aprism_height,
        .stride = aprism_stride,
        .buf = aprism_image_buffer,
    };
    zarray_t *detections = apriltag_detector_detect(aprism_detector, &image);
    int count = zarray_size(detections);
    if (count > APRISM_MAX_DETECTIONS) {
        count = APRISM_MAX_DETECTIONS;
    }

    int offset = snprintf(aprism_result, sizeof(aprism_result), "[");
    for (int i = 0; i < count && offset > 0 && offset < APRISM_RESULT_BYTES; i++) {
        apriltag_detection_t *detection = NULL;
        zarray_get(detections, i, &detection);
        offset += snprintf(
            aprism_result + offset,
            sizeof(aprism_result) - (size_t) offset,
            "%s{\"id\":%d,\"center\":[%.2f,%.2f],\"corners\":[[%.2f,%.2f],[%.2f,%.2f],[%.2f,%.2f],[%.2f,%.2f]]}",
            i == 0 ? "" : ",",
            detection->id,
            detection->c[0], detection->c[1],
            detection->p[0][0], detection->p[0][1],
            detection->p[1][0], detection->p[1][1],
            detection->p[2][0], detection->p[2][1],
            detection->p[3][0], detection->p[3][1]
        );
    }
    if (offset >= 0 && offset < APRISM_RESULT_BYTES) {
        snprintf(aprism_result + offset, sizeof(aprism_result) - (size_t) offset, "]");
    }

    apriltag_detections_destroy(detections);
    return aprism_result;
}
