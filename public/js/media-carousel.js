/**
 * Horizontal scroll-snap carousels with optional native <dialog> lightbox.
 * Root: [data-media-carousel]
 */
(function () {
  'use strict';

  function prefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function scrollOpts(left) {
    return { left: left, behavior: prefersReducedMotion() ? 'auto' : 'smooth' };
  }

  function nearestIndex(viewport, slides) {
    var center = viewport.scrollLeft + viewport.clientWidth / 2;
    var best = 0;
    var bestDist = Infinity;
    for (var i = 0; i < slides.length; i++) {
      var el = slides[i];
      var mid = el.offsetLeft + el.offsetWidth / 2;
      var d = Math.abs(mid - center);
      if (d < bestDist) {
        bestDist = d;
        best = i;
      }
    }
    return best;
  }

  function goToIndex(viewport, slides, i) {
    var slide = slides[i];
    if (!slide) {
      return;
    }
    viewport.scrollTo(scrollOpts(slide.offsetLeft));
  }

  function updateChrome(viewport, slides, tabs, btnPrev, btnNext) {
    var i = nearestIndex(viewport, slides);
    for (var t = 0; t < tabs.length; t++) {
      var sel = t === i;
      tabs[t].setAttribute('aria-selected', sel ? 'true' : 'false');
      tabs[t].tabIndex = sel ? 0 : -1;
    }
    if (btnPrev) {
      btnPrev.disabled = i <= 0;
    }
    if (btnNext) {
      btnNext.disabled = i >= slides.length - 1;
    }
    return i;
  }

  function readSlideMedia(slide) {
    var img = slide.querySelector('img');
    var cap = slide.querySelector('[data-mc-caption]');
    return {
      src: img ? img.getAttribute('src') : '',
      alt: img ? img.getAttribute('alt') || '' : '',
      caption: cap ? cap.textContent.trim() : '',
    };
  }

  function init(root) {
    var viewport = root.querySelector('[data-mc-viewport]');
    var slides = root.querySelectorAll('[data-mc-slide]');
    var tabs = root.querySelectorAll('[data-mc-tab]');
    var btnPrev = root.querySelector('[data-mc-prev]');
    var btnNext = root.querySelector('[data-mc-next]');
    var dialog = root.querySelector('[data-mc-dialog]');
    var dialogImg = dialog ? dialog.querySelector('[data-mc-dialog-img]') : null;
    var dialogCaption = dialog ? dialog.querySelector('[data-mc-dialog-caption]') : null;
    var btnClose = dialog ? dialog.querySelector('[data-mc-close]') : null;
    var dPrev = dialog ? dialog.querySelector('[data-mc-dprev]') : null;
    var dNext = dialog ? dialog.querySelector('[data-mc-dnext]') : null;

    if (!viewport || slides.length === 0) {
      return;
    }

    var slidesArr = Array.prototype.slice.call(slides);
    var tabsArr = Array.prototype.slice.call(tabs);
    var lightboxIndex = 0;
    var returnFocus = null;
    var scrollEndTimer = null;

    function onScrollSettled() {
      updateChrome(viewport, slidesArr, tabsArr, btnPrev, btnNext);
    }

    viewport.addEventListener('scroll', function () {
      window.clearTimeout(scrollEndTimer);
      scrollEndTimer = window.setTimeout(onScrollSettled, 80);
    });

    function currentIdx() {
      return nearestIndex(viewport, slidesArr);
    }

    if (btnPrev) {
      btnPrev.addEventListener('click', function () {
        var i = currentIdx();
        goToIndex(viewport, slidesArr, Math.max(0, i - 1));
      });
    }
    if (btnNext) {
      btnNext.addEventListener('click', function () {
        var i = currentIdx();
        goToIndex(viewport, slidesArr, Math.min(slidesArr.length - 1, i + 1));
      });
    }

    tabsArr.forEach(function (tab, idx) {
      tab.addEventListener('click', function () {
        goToIndex(viewport, slidesArr, idx);
        window.setTimeout(onScrollSettled, prefersReducedMotion() ? 0 : 320);
      });
    });

    viewport.addEventListener('keydown', function (ev) {
      if (ev.key === 'ArrowLeft') {
        ev.preventDefault();
        goToIndex(viewport, slidesArr, Math.max(0, currentIdx() - 1));
      } else if (ev.key === 'ArrowRight') {
        ev.preventDefault();
        goToIndex(viewport, slidesArr, Math.min(slidesArr.length - 1, currentIdx() + 1));
      }
    });

    var tablist = root.querySelector('[role="tablist"]');
    if (tablist) {
      tablist.addEventListener('keydown', function (ev) {
        if (ev.key !== 'ArrowLeft' && ev.key !== 'ArrowRight') {
          return;
        }
        var focusIdx = tabsArr.indexOf(document.activeElement);
        if (focusIdx < 0) {
          return;
        }
        ev.preventDefault();
        var next = ev.key === 'ArrowLeft' ? focusIdx - 1 : focusIdx + 1;
        if (next < 0) {
          next = tabsArr.length - 1;
        }
        if (next >= tabsArr.length) {
          next = 0;
        }
        goToIndex(viewport, slidesArr, next);
        tabsArr[next].focus();
      });
    }

    function syncDialog() {
      if (!dialog || !dialogImg) {
        return;
      }
      var slide = slidesArr[lightboxIndex];
      if (!slide) {
        return;
      }
      var m = readSlideMedia(slide);
      dialogImg.setAttribute('src', m.src);
      dialogImg.setAttribute('alt', m.alt);
      if (dialogCaption) {
        if (m.caption) {
          dialogCaption.textContent = m.caption;
          dialogCaption.hidden = false;
        } else {
          dialogCaption.textContent = '';
          dialogCaption.hidden = true;
        }
      }
      if (dPrev) {
        dPrev.disabled = lightboxIndex <= 0;
      }
      if (dNext) {
        dNext.disabled = lightboxIndex >= slidesArr.length - 1;
      }
    }

    function openLightbox(idx) {
      if (!dialog || !dialog.showModal) {
        return;
      }
      lightboxIndex = Math.max(0, Math.min(slidesArr.length - 1, idx));
      returnFocus = document.activeElement;
      syncDialog();
      dialog.showModal();
      if (btnClose) {
        btnClose.focus();
      }
    }

    if (dialog) {
      dialog.addEventListener('close', function () {
        if (returnFocus && typeof returnFocus.focus === 'function') {
          returnFocus.focus();
        }
        returnFocus = null;
      });

      dialog.addEventListener('keydown', function (ev) {
        if (ev.key === 'ArrowLeft' && dPrev && !dPrev.disabled) {
          ev.preventDefault();
          lightboxIndex -= 1;
          syncDialog();
        } else if (ev.key === 'ArrowRight' && dNext && !dNext.disabled) {
          ev.preventDefault();
          lightboxIndex += 1;
          syncDialog();
        }
      });
    }

    if (btnClose) {
      btnClose.addEventListener('click', function () {
        if (dialog) {
          dialog.close();
        }
      });
    }

    if (dPrev) {
      dPrev.addEventListener('click', function () {
        if (lightboxIndex > 0) {
          lightboxIndex -= 1;
          syncDialog();
        }
      });
    }
    if (dNext) {
      dNext.addEventListener('click', function () {
        if (lightboxIndex < slidesArr.length - 1) {
          lightboxIndex += 1;
          syncDialog();
        }
      });
    }

    slidesArr.forEach(function (slide, idx) {
      var opens = slide.querySelectorAll('[data-mc-open]');
      opens.forEach(function (btn) {
        btn.addEventListener('click', function () {
          openLightbox(idx);
        });
      });
      var img = slide.querySelector('.media-carousel__img');
      if (img) {
        img.addEventListener('click', function () {
          openLightbox(idx);
        });
        img.style.cursor = 'zoom-in';
      }
    });

    updateChrome(viewport, slidesArr, tabsArr, btnPrev, btnNext);
  }

  function boot() {
    document.querySelectorAll('[data-media-carousel]').forEach(function (root) {
      init(root);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
