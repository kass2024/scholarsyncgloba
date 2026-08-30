/**
 * Smart autofill — extract document text in the browser (no server Tesseract/Imagick).
 */
(function (global) {
  "use strict";

  const MAX_PDF_PAGES = 4;
  const MAX_TEXT_LEN = 20000;
  const PDF_OCR_SCALE = 2.8;
  let libsLoaded = false;
  let loadPromise = null;
  let ocrWorker = null;

  function normalizeText(text, keepLines) {
    var raw = String(text || "").replace(/\r\n/g, "\n").replace(/\r/g, "\n").trim();
    if (!keepLines) {
      return raw.replace(/\s+/g, " ").trim().slice(0, MAX_TEXT_LEN);
    }
    return raw
      .split("\n")
      .map(function (line) {
        return line.replace(/[ \t]+/g, " ").trim();
      })
      .filter(Boolean)
      .join("\n")
      .slice(0, MAX_TEXT_LEN);
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      if (document.querySelector('script[data-smart-autofill-src="' + src + '"]')) {
        resolve();
        return;
      }
      var s = document.createElement("script");
      s.src = src;
      s.async = true;
      s.dataset.smartAutofillSrc = src;
      s.onload = function () { resolve(); };
      s.onerror = function () { reject(new Error("Failed to load script: " + src)); };
      document.head.appendChild(s);
    });
  }

  function ensureLibs() {
    if (libsLoaded) {
      return Promise.resolve();
    }
    if (loadPromise) {
      return loadPromise;
    }
    loadPromise = (async function () {
      await loadScript("https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js");
      await loadScript("https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.min.js");
      await loadScript("https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js");
      if (global.pdfjsLib && global.pdfjsLib.GlobalWorkerOptions) {
        global.pdfjsLib.GlobalWorkerOptions.workerSrc =
          "https://cdn.jsdelivr.net/npm/pdfjs-dist@3.11.174/build/pdf.worker.min.js";
      }
      libsLoaded = true;
    })();
    return loadPromise;
  }

  async function getOcrWorker() {
    if (ocrWorker) {
      return ocrWorker;
    }
    ocrWorker = await Tesseract.createWorker("eng", 1, {
      logger: function () {},
      workerPath: "https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/worker.min.js",
      corePath: "https://cdn.jsdelivr.net/npm/tesseract.js-core@5/tesseract-core-simd-lstm.wasm.js",
    });
    try {
      await ocrWorker.setParameters({
        tessedit_pageseg_mode: "6",
        preserve_interword_spaces: "1",
      });
    } catch (err) {
      /* optional */
    }
    return ocrWorker;
  }

  function enhanceCanvasForOcr(canvas) {
    var ctx = canvas.getContext("2d");
    if (!ctx) {
      return;
    }
    var w = canvas.width;
    var h = canvas.height;
    var imageData = ctx.getImageData(0, 0, w, h);
    var d = imageData.data;
    for (var i = 0; i < d.length; i += 4) {
      var gray = 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
      var boosted = gray < 140 ? Math.max(0, gray - 25) : Math.min(255, gray + 35);
      var v = boosted > 165 ? 255 : boosted < 95 ? 0 : boosted;
      d[i] = d[i + 1] = d[i + 2] = v;
      d[i + 3] = 255;
    }
    ctx.putImageData(imageData, 0, 0);
  }

  async function ocrBlob(blob) {
    var worker = await getOcrWorker();
    var result = await worker.recognize(blob);
    return normalizeText(result.data && result.data.text ? result.data.text : "", true);
  }

  async function extractDocx(file) {
    var zip = await JSZip.loadAsync(await file.arrayBuffer());
    var entry = zip.file("word/document.xml");
    if (!entry) {
      throw new Error("DOCX has no document body");
    }
    var xml = await entry.async("string");
    return normalizeText(xml.replace(/<[^>]+>/g, " "), true);
  }

  async function extractImage(file) {
    return ocrBlob(file);
  }

  async function renderPageToBlob(page) {
    var viewport = page.getViewport({ scale: PDF_OCR_SCALE });
    var canvas = document.createElement("canvas");
    var ctx = canvas.getContext("2d");
    if (!ctx) {
      throw new Error("Canvas not supported");
    }
    canvas.width = viewport.width;
    canvas.height = viewport.height;
    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    await page.render({ canvasContext: ctx, viewport: viewport }).promise;
    enhanceCanvasForOcr(canvas);
    return new Promise(function (resolve) {
      canvas.toBlob(function (blob) { resolve(blob); }, "image/png", 0.95);
    });
  }

  async function extractPdf(file) {
    if (!global.pdfjsLib) {
      throw new Error("PDF.js not loaded");
    }
    var data = new Uint8Array(await file.arrayBuffer());
    var doc = await global.pdfjsLib.getDocument({ data: data }).promise;
    var parts = [];
    var pageCount = Math.min(doc.numPages, MAX_PDF_PAGES);
    var totalEmbedded = 0;

    for (var p = 1; p <= pageCount; p++) {
      var page = await doc.getPage(p);
      var embedded = "";
      try {
        var textContent = await page.getTextContent();
        embedded = textContent.items
          .map(function (it) {
            return it.str || "";
          })
          .join(" ");
      } catch (err) {
        embedded = "";
      }
      embedded = normalizeText(embedded, true);
      totalEmbedded += embedded.length;

      var blob = await renderPageToBlob(page);
      if (blob) {
        var ocrText = await ocrBlob(blob);
        if (ocrText) {
          if (embedded.length >= 30) {
            parts.push(embedded);
            parts.push(ocrText);
          } else {
            parts.push(ocrText);
          }
        } else if (embedded.length >= 20) {
          parts.push(embedded);
        }
      } else if (embedded.length >= 20) {
        parts.push(embedded);
      }
    }

    var merged = normalizeText(parts.join("\n\n"), true);
    if (merged.length < 80 && totalEmbedded < 80) {
      /* second pass: OCR-only at higher scale for stubborn scans */
      for (var p2 = 1; p2 <= Math.min(pageCount, 2); p2++) {
        var page2 = await doc.getPage(p2);
        var blob2 = await renderPageToBlob(page2);
        if (blob2) {
          var retry = await ocrBlob(blob2);
          if (retry) {
            parts.push(retry);
          }
        }
      }
      merged = normalizeText(parts.join("\n\n"), true);
    }

    return merged;
  }

  async function extractFile(file, onProgress) {
    var ext = (file.name.split(".").pop() || "").toLowerCase();
    if (typeof onProgress === "function") {
      onProgress(file.name);
    }
    if (ext === "docx") {
      return extractDocx(file);
    }
    if (ext === "pdf") {
      return extractPdf(file);
    }
    if (["jpg", "jpeg", "png", "webp", "bmp", "tif", "tiff"].indexOf(ext) !== -1) {
      return extractImage(file);
    }
    throw new Error("Unsupported file type: " + ext);
  }

  async function extractAll(files, onProgress) {
    await ensureLibs();
    var texts = await Promise.all(
      files.map(function (file, i) {
        return extractFile(file, function (name) {
          if (typeof onProgress === "function") {
            onProgress(name, i + 1, files.length);
          }
        }).catch(function (err) {
          console.warn("Smart autofill client extract failed:", file.name, err);
          return "";
        });
      })
    );
    try {
      if (ocrWorker) {
        await ocrWorker.terminate();
        ocrWorker = null;
      }
    } catch (e) {
      ocrWorker = null;
    }
    return texts;
  }

  global.SmartAutofillClientExtract = {
    ensureLibs: ensureLibs,
    extractFile: extractFile,
    extractAll: extractAll
  };
})(window);
