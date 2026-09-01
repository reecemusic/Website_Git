document.querySelectorAll("#year").forEach(function (el) {
  el.textContent = new Date().getFullYear();
});

const menuButtons = document.querySelectorAll('.menu-toggle');

menuButtons.forEach(function (button) {
  const nav = button.parentElement.querySelector('.nav');

  if (!nav) return;

  button.addEventListener('click', function () {
    const isOpen = nav.classList.toggle('is-open');
    button.setAttribute('aria-expanded', String(isOpen));
  });

  nav.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () {
      nav.classList.remove('is-open');
      button.setAttribute('aria-expanded', 'false');
    });
  });
});

const lightbox = document.getElementById('art-lightbox');

if (lightbox) {
  const lightboxImage = document.getElementById('lightbox-image');
  const lightboxTitle = document.getElementById('lightbox-title');
  const lightboxPrice = document.getElementById('lightbox-price');
  const closeButton = lightbox.querySelector('.lightbox-close');
  const backButton = lightbox.querySelector('.lightbox-back');
  const backdrop = lightbox.querySelector('.lightbox-backdrop');

  const closeLightbox = function () {
    lightbox.classList.remove('is-open');
    lightbox.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('lightbox-open');
  };

  document.querySelectorAll('.artwork-item').forEach(function (item) {
    item.addEventListener('click', function () {
      const img = item.querySelector('img');
      const title = item.dataset.title || item.querySelector('h3')?.textContent || 'Artwork';
      const price = item.dataset.price || item.querySelector('.price')?.textContent || '$10';

      if (!img) return;

      lightboxImage.src = img.src;
      lightboxImage.alt = img.alt;
      lightboxTitle.textContent = title;
      lightboxPrice.textContent = price;
      lightbox.classList.add('is-open');
      lightbox.setAttribute('aria-hidden', 'false');
      document.body.classList.add('lightbox-open');
    });
  });

  closeButton.addEventListener('click', closeLightbox);
  backButton.addEventListener('click', closeLightbox);
  backdrop.addEventListener('click', closeLightbox);

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && lightbox.classList.contains('is-open')) {
      closeLightbox();
    }
  });
}

const projectItems = document.querySelectorAll('.project-item');

projectItems.forEach(function (item) {
  const toggle = item.querySelector('.project-toggle');
  if (!toggle) return;

  toggle.addEventListener('click', function () {
    const isOpen = item.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', String(isOpen));
  });
});

const projectJumpButtons = document.querySelectorAll('.project-jump');

projectJumpButtons.forEach(function (button) {
  button.addEventListener('click', function () {
    const targetId = button.dataset.target;
    const target = document.getElementById(targetId);

    if (!target) return;

    projectJumpButtons.forEach(function (item) {
      item.classList.toggle('is-active', item === button);
    });

    projectItems.forEach(function (item) {
      const toggle = item.querySelector('.project-toggle');
      const shouldOpen = item === target;
      item.classList.toggle('is-open', shouldOpen);
      if (toggle) {
        toggle.setAttribute('aria-expanded', String(shouldOpen));
      }
    });

    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
});
