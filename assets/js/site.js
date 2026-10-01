/**
 * Motion and interaction for the public site's templates.
 *
 *   [data-carousel]      looping slider (reviews, services, the Corporate
 *                        hero). Slides per view come from the CSS variable
 *                        --per-view, so each template and screen width
 *                        decides. data-fade crossfades instead of sliding.
 *   [data-fade-slider]   background photos that crossfade (Classic hero).
 *   [data-marquee]       endless strips (partners, photos, tickers). The
 *                        movement itself is CSS; this only sets a speed
 *                        that stays the same whatever the strip's length.
 *   [data-count]         figures that count up when they come into view.
 *   [data-tabs]          the Corporate Track / Quote / Contact widget.
 *   [data-spotlight]     cards lit by a glow that follows the pointer.
 *   [data-rotate-words]  a word that cycles through a list.
 *
 * Anyone who has asked their device for reduced motion gets none of the
 * automatic movement: carousels wait for a click, strips stand still and
 * numbers show their final value. Every control still works.
 */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var doc = document.documentElement;

  /* ---------------- Header state on scroll ---------------- */
  var header = document.getElementById('site-header');
  if (header) {
    var lastScrolled = null;
    var onScroll = function () {
      var scrolled = window.scrollY > 12;
      if (scrolled !== lastScrolled) {
        header.classList.toggle('is-scrolled', scrolled);
        doc.classList.toggle('page-scrolled', scrolled);
        lastScrolled = scrolled;
      }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------------- Carousel ---------------- */
  function Carousel(root) {
    this.root = root;
    this.viewport = root.querySelector('.carousel-viewport');
    this.track = root.querySelector('.carousel-track');
    this.originals = Array.prototype.slice.call(this.track.children);
    this.count = this.originals.length;
    this.fade = root.hasAttribute('data-fade');
    this.interval = parseInt(root.getAttribute('data-autoplay'), 10) || 0;
    this.index = 0;
    this.timer = null;
    this.paused = false;      // by the pause button
    this.holding = false;     // pointer or focus inside
    this.dotsWrap = root.querySelector('.carousel-dots');
    this.pauseBtn = root.querySelector('.carousel-pause');
    this.build();
    this.bind();
    this.schedule();
  }

  Carousel.prototype.perView = function () {
    if (this.fade) return 1;
    var v = parseFloat(getComputedStyle(this.root).getPropertyValue('--per-view'));
    return v > 0 ? Math.max(1, Math.round(v)) : 1;
  };

  Carousel.prototype.build = function () {
    var self = this;
    // Remove clones from an earlier build (a resize changed slides per view).
    Array.prototype.slice.call(this.track.querySelectorAll('.is-clone')).forEach(function (c) { c.remove(); });
    this.pv = this.perView();
    this.root.classList.toggle('carousel--static', this.count <= this.pv);

    if (this.fade) {
      this.root.classList.add('carousel--fade');
      this.originals.forEach(function (s, i) { s.classList.toggle('is-active', i === self.index); });
    } else if (this.count > this.pv) {
      // Copies of the first and last few slides at either end, so moving
      // past the last slide shows the first one coming in, and the jump
      // back to the real first slide happens out of sight.
      var head = this.originals.slice(-this.pv).map(function (s) { return self.cloneSlide(s); });
      var tail = this.originals.slice(0, this.pv).map(function (s) { return self.cloneSlide(s); });
      head.forEach(function (c) { self.track.insertBefore(c, self.track.firstChild); });
      tail.forEach(function (c) { self.track.appendChild(c); });
    }

    if (this.dotsWrap) {
      this.dotsWrap.innerHTML = '';
      if (this.count > this.pv || this.fade) {
        this.originals.forEach(function (_, i) {
          var dot = document.createElement('button');
          dot.type = 'button';
          dot.className = 'carousel-dot';
          dot.setAttribute('role', 'tab');
          dot.setAttribute('aria-label', 'Go to ' + (i + 1) + ' of ' + self.count);
          dot.addEventListener('click', function () { self.goTo(i, true); });
          self.dotsWrap.appendChild(dot);
        });
      }
    }
    this.position(false);
  };

  Carousel.prototype.cloneSlide = function (slide) {
    var c = slide.cloneNode(true);
    c.classList.add('is-clone');
    c.setAttribute('aria-hidden', 'true');
    c.removeAttribute('aria-label');
    Array.prototype.slice.call(c.querySelectorAll('a, button, input')).forEach(function (el) { el.setAttribute('tabindex', '-1'); });
    return c;
  };

  Carousel.prototype.position = function (animate) {
    var self = this;
    var real = ((this.index % this.count) + this.count) % this.count;
    if (this.fade) {
      this.originals.forEach(function (s, i) {
        s.classList.toggle('is-active', i === real);
        s.setAttribute('aria-hidden', i === real ? 'false' : 'true');
      });
    } else if (this.count > this.pv) {
      this.track.style.transition = animate && !reduceMotion ? '' : 'none';
      this.track.style.transform = 'translate3d(' + (-(this.index + this.pv) * (100 / this.pv)) + '%,0,0)';
      if (!animate) { void this.track.offsetWidth; this.track.style.transition = ''; }
      this.originals.forEach(function (s, i) {
        var visible = false;
        for (var k = 0; k < self.pv; k++) { if ((real + k) % self.count === i) visible = true; }
        s.setAttribute('aria-hidden', visible ? 'false' : 'true');
        Array.prototype.slice.call(s.querySelectorAll('a, button')).forEach(function (el) {
          if (visible) el.removeAttribute('tabindex'); else el.setAttribute('tabindex', '-1');
        });
      });
    } else {
      this.track.style.transform = '';
    }
    if (this.dotsWrap) {
      Array.prototype.slice.call(this.dotsWrap.children).forEach(function (d, i) {
        d.classList.toggle('is-active', i === real);
        d.setAttribute('aria-selected', i === real ? 'true' : 'false');
      });
    }
  };

  Carousel.prototype.goTo = function (i, fromUser) {
    if (this.count <= this.pv && !this.fade) return;
    this.index = i;
    this.position(true);
    if (fromUser) this.schedule();
  };

  Carousel.prototype.next = function (fromUser) { this.goTo(this.index + 1, fromUser); };
  Carousel.prototype.prev = function (fromUser) { this.goTo(this.index - 1, fromUser); };

  Carousel.prototype.settle = function () {
    // After sliding onto a copy, jump without animation to the real slide
    // it copies. Looks identical, so the loop never visibly rewinds.
    if (this.fade || this.count <= this.pv) return;
    if (this.index >= this.count) { this.index -= this.count; this.position(false); }
    else if (this.index < 0) { this.index += this.count; this.position(false); }
  };

  Carousel.prototype.schedule = function () {
    var self = this;
    window.clearInterval(this.timer);
    this.timer = null;
    if (!this.interval || reduceMotion || this.paused || this.holding || document.hidden) return;
    if (this.count <= this.pv && !this.fade) return;
    if (this.fade && this.count < 2) return;
    this.timer = window.setInterval(function () { self.next(false); }, this.interval);
  };

  Carousel.prototype.bind = function () {
    var self = this;
    var prev = this.root.querySelector('.carousel-prev');
    var next = this.root.querySelector('.carousel-next');
    if (prev) prev.addEventListener('click', function () { self.prev(true); });
    if (next) next.addEventListener('click', function () { self.next(true); });
    this.track.addEventListener('transitionend', function (e) { if (e.target === self.track) self.settle(); });

    if (this.pauseBtn) {
      if (!this.interval || reduceMotion) {
        this.pauseBtn.hidden = true;
      }
      this.pauseBtn.addEventListener('click', function () {
        self.paused = !self.paused;
        self.pauseBtn.setAttribute('aria-pressed', self.paused ? 'true' : 'false');
        self.pauseBtn.setAttribute('aria-label', self.paused ? 'Play' : 'Pause');
        self.root.classList.toggle('is-paused', self.paused);
        self.schedule();
      });
    }

    this.root.addEventListener('mouseenter', function () { self.holding = true; self.schedule(); });
    this.root.addEventListener('mouseleave', function () { self.holding = false; self.schedule(); });
    this.root.addEventListener('focusin', function () { self.holding = true; self.schedule(); });
    this.root.addEventListener('focusout', function (e) {
      if (!self.root.contains(e.relatedTarget)) { self.holding = false; self.schedule(); }
    });
    document.addEventListener('visibilitychange', function () { self.schedule(); });

    this.root.addEventListener('keydown', function (e) {
      if (e.target.closest && e.target.closest('input, textarea')) return;
      if (e.key === 'ArrowLeft') { self.prev(true); }
      if (e.key === 'ArrowRight') { self.next(true); }
    });

    // Swipe on touch screens.
    var startX = null, startY = null;
    this.viewport.addEventListener('touchstart', function (e) {
      startX = e.touches[0].clientX; startY = e.touches[0].clientY;
      self.holding = true; self.schedule();
    }, { passive: true });
    this.viewport.addEventListener('touchend', function (e) {
      if (startX === null) return;
      var dx = e.changedTouches[0].clientX - startX;
      var dy = e.changedTouches[0].clientY - startY;
      if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) { dx < 0 ? self.next(true) : self.prev(true); }
      startX = null;
      self.holding = false; self.schedule();
    });

    var resizeTimer = null;
    window.addEventListener('resize', function () {
      window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(function () {
        if (self.perView() !== self.pv) {
          self.index = ((self.index % self.count) + self.count) % self.count;
          self.build();
          self.schedule();
        }
      }, 150);
    });
  };

  Array.prototype.slice.call(document.querySelectorAll('[data-carousel]')).forEach(function (el) {
    if (el.querySelector('.carousel-track') && el.querySelector('.carousel-track').children.length) {
      new Carousel(el);
    }
  });

  /* ---------------- Crossfading background photos ---------------- */
  Array.prototype.slice.call(document.querySelectorAll('[data-fade-slider]')).forEach(function (wrap) {
    var slides = Array.prototype.slice.call(wrap.children);
    var dotsWrap = wrap.parentNode.querySelector('[data-fade-dots]');
    var dots = dotsWrap ? Array.prototype.slice.call(dotsWrap.children) : [];
    if (slides.length < 2 || reduceMotion) return;
    var i = 0;
    var every = parseInt(wrap.getAttribute('data-interval'), 10) || 6000;
    window.setInterval(function () {
      if (document.hidden) return;
      slides[i].classList.remove('is-active');
      if (dots[i]) dots[i].classList.remove('is-active');
      i = (i + 1) % slides.length;
      slides[i].classList.add('is-active');
      if (dots[i]) dots[i].classList.add('is-active');
    }, every);
  });

  /* ---------------- Marquee speed ---------------- */
  function sizeMarquees() {
    Array.prototype.slice.call(document.querySelectorAll('[data-marquee]')).forEach(function (m) {
      var group = m.querySelector('.marquee-group');
      if (!group) return;
      var speed = parseFloat(getComputedStyle(m).getPropertyValue('--marquee-speed')) || 40; // px per second
      var seconds = Math.max(8, group.scrollWidth / speed);
      m.style.setProperty('--marquee-duration', seconds.toFixed(1) + 's');
      m.classList.add('is-ready');
    });
  }
  sizeMarquees();
  window.addEventListener('load', sizeMarquees);

  /* ---------------- Count-up figures ---------------- */
  var counters = Array.prototype.slice.call(document.querySelectorAll('[data-count]'));
  function runCount(el) {
    var target = parseFloat(el.getAttribute('data-count'));
    var decimals = parseInt(el.getAttribute('data-decimals'), 10) || 0;
    var prefix = el.getAttribute('data-prefix') || '';
    var suffix = el.getAttribute('data-suffix') || '';
    var finalText = el.textContent;
    if (isNaN(target)) return;
    var start = null, duration = 1400;
    function frame(ts) {
      if (start === null) start = ts;
      var t = Math.min(1, (ts - start) / duration);
      var eased = 1 - Math.pow(1 - t, 3);
      el.textContent = prefix + (target * eased).toFixed(decimals) + suffix;
      if (t < 1) window.requestAnimationFrame(frame); else el.textContent = finalText;
    }
    window.requestAnimationFrame(frame);
  }
  if (counters.length && !reduceMotion && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { runCount(entry.target); io.unobserve(entry.target); }
      });
    }, { threshold: 0.4 });
    counters.forEach(function (c) { io.observe(c); });
  }

  /* ---------------- Tabs ---------------- */
  Array.prototype.slice.call(document.querySelectorAll('[data-tabs]')).forEach(function (wrap) {
    var tabs = Array.prototype.slice.call(wrap.querySelectorAll('[role="tab"]'));
    function select(tab, focus) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.setAttribute('tabindex', on ? '0' : '-1');
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.hidden = !on;
      });
      if (focus) tab.focus();
    }
    tabs.forEach(function (tab, i) {
      tab.addEventListener('click', function () { select(tab, false); });
      tab.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { e.preventDefault(); select(tabs[(i + 1) % tabs.length], true); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); select(tabs[(i - 1 + tabs.length) % tabs.length], true); }
      });
    });
  });

  /* ---------------- Spotlight cards ---------------- */
  Array.prototype.slice.call(document.querySelectorAll('[data-spotlight]')).forEach(function (card) {
    card.addEventListener('pointermove', function (e) {
      var r = card.getBoundingClientRect();
      card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
      card.style.setProperty('--my', (e.clientY - r.top) + 'px');
    });
  });

  /* ---------------- Rotating words ---------------- */
  Array.prototype.slice.call(document.querySelectorAll('[data-rotate-words]')).forEach(function (el) {
    var words = el.getAttribute('data-rotate-words').split(',');
    if (words.length < 2 || reduceMotion) return;
    var i = 0;
    window.setInterval(function () {
      if (document.hidden) return;
      el.classList.add('is-out');
      window.setTimeout(function () {
        i = (i + 1) % words.length;
        el.textContent = words[i];
        el.classList.remove('is-out');
      }, 260);
    }, 2400);
  });
})();
