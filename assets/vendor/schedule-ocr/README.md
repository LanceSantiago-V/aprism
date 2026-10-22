# Locally packaged schedule OCR dependencies

These browser assets are served by APRISM. Timetable files stay in the browser during extraction.

| Package | Version | Files | License |
| --- | --- | --- | --- |
| Tesseract.js | 5.1.1 | `tesseract/tesseract.min.js`, `tesseract/worker.min.js` | Apache-2.0; see `tesseract/LICENSE.md` |
| Tesseract Core | 5.0.0 | `tesseract/core/` | Apache-2.0; see `tesseract/core/LICENSE` |
| English trained data | `@tesseract.js-data/eng` 1.0.0 | `tesseract/lang/eng.traineddata.gz` (best-int model) | MIT, as distributed by the language-data package |
| PDF.js | 4.10.38 | `pdfjs/pdf.min.mjs`, `pdfjs/pdf.worker.min.mjs` | Apache-2.0; see `pdfjs/LICENSE` |

Upstream sources: [Tesseract.js](https://github.com/naptha/tesseract.js), [Tesseract Core](https://github.com/naptha/tesseract.js-core), [English language data](https://github.com/naptha/tessdata), and [PDF.js](https://github.com/mozilla/pdf.js). Keep the Tesseract core variants together: the worker chooses among them based on browser support.
