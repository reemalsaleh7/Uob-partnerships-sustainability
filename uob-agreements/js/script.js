// js/script.js
document.addEventListener('DOMContentLoaded', () => {
  // Tooltip (اختياري)
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  tooltipTriggerList.map(el => new bootstrap.Tooltip(el))
});