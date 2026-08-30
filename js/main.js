document.querySelectorAll("#year").forEach(function (el) {
  el.textContent = new Date().getFullYear();
});
