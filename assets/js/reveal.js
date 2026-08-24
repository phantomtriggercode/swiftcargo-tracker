/**
 * Scroll-reveal: adds .is-visible to any [data-reveal] element once it
 * scrolls into view. What that transition actually looks like (fade,
 * fade-up, scale-in, slide-in, or none) is decided per active template
 * by style.css, keyed off the html[data-animation="..."] attribute — this
 * script only ever toggles the one class.
 */
(function () {
  var targets = document.querySelectorAll('[data-reveal]');
  if (!targets.length) return;

  if (document.documentElement.getAttribute('data-animation') === 'none' || !('IntersectionObserver' in window)) {
    targets.forEach(function (el) { el.classList.add('is-visible'); });
    return;
  }

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

  targets.forEach(function (el) { observer.observe(el); });
})();
