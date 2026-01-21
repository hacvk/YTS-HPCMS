// PDF.js viewer configuration for production
// Set worker path for PDF.js
if (typeof window.pdfjsLib !== 'undefined') {
  window.pdfjsLib.GlobalWorkerOptions.workerSrc = '../build/pdf.worker.mjs';
}
