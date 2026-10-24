/**
 * KSV ACADEMY - Main JavaScript Controller
 * Light/Dark Mode, Modals, Mobile Drawer, Course Filters, FAQ Accordion & Form Handlers
 */

document.addEventListener('DOMContentLoaded', () => {
  initThemeToggle();
  initStickyHeader();
  initNavDropdowns();
  initMobileDrawer();
  initCourseTabs();
  initFaqAccordion();
  initModals();
  initGalleryLightbox();
  initScrollToTop();
  initStatsCounter();
  initScrollAnimations();
  initActiveNav();
  initReloadPromoModal();
  initPromoSlider();
  initTopBarTicker();
});


/* ==========================================================================
   1. Light / Dark Mode Toggle with localStorage
   ========================================================================== */
function initThemeToggle() {
  const themeToggleBtn = document.getElementById('themeToggleBtn');
  const htmlEl = document.documentElement;

  // Retrieve saved preference or default to light
  const savedTheme = localStorage.getItem('ksv_theme') || 'light';
  htmlEl.setAttribute('data-theme', savedTheme);

  if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', () => {
      const currentTheme = htmlEl.getAttribute('data-theme') || 'light';
      const newTheme = currentTheme === 'light' ? 'dark' : 'light';
      htmlEl.setAttribute('data-theme', newTheme);
      localStorage.setItem('ksv_theme', newTheme);
    });
  }
}

/* ==========================================================================
   2. Sticky Header Scroll Shadow
   ========================================================================== */
function initStickyHeader() {
  const header = document.getElementById('siteHeader');
  if (!header) return;

  const onScroll = () => {
    if (window.scrollY > 40) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  };

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}

/* ==========================================================================
   3. Mobile Drawer Navigation
   ========================================================================== */
function initMobileDrawer() {
  const toggleBtn = document.getElementById('mobileMenuToggle');
  const drawer = document.getElementById('mobileDrawer');
  const overlay = document.getElementById('drawerOverlay');
  const closeBtn = document.getElementById('drawerCloseBtn');

  if (!toggleBtn || !drawer || !overlay) return;

  const openDrawer = () => {
    drawer.classList.add('active');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  };

  const closeDrawer = () => {
    drawer.classList.remove('active');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  };

  toggleBtn.addEventListener('click', openDrawer);
  if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
  overlay.addEventListener('click', closeDrawer);

  // Close when clicking an anchor link inside drawer
  const drawerLinks = drawer.querySelectorAll('a');
  drawerLinks.forEach(link => {
    link.addEventListener('click', () => {
      closeDrawer();
    });
  });
}

/* ==========================================================================
   3.1 Navigation Dropdowns (Courses Submenu)
   ========================================================================== */
function initNavDropdowns() {
  const dropdownParents = document.querySelectorAll('.has-dropdown');
  if (!dropdownParents.length) return;

  dropdownParents.forEach(parent => {
    const arrow = parent.querySelector('.dropdown-arrow');

    // Smooth hover behavior
    parent.addEventListener('mouseenter', () => {
      parent.classList.add('dropdown-open');
    });

    parent.addEventListener('mouseleave', () => {
      parent.classList.remove('dropdown-open');
    });

    // Touch and click support for dropdown chevron
    if (arrow) {
      arrow.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        parent.classList.toggle('dropdown-open');
      });
    }
  });

  // Close dropdown on outside click
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.has-dropdown')) {
      dropdownParents.forEach(p => p.classList.remove('dropdown-open'));
    }
  });

  // Close dropdown when clicking any item inside it
  document.querySelectorAll('.dropdown-link-item, .dropdown-footer-link').forEach(link => {
    link.addEventListener('click', () => {
      dropdownParents.forEach(p => p.classList.remove('dropdown-open'));
    });
  });
}

/* ==========================================================================
   4. Course Tab Filtering & Submenu Deep-Linking
   ========================================================================== */
function initCourseTabs() {
  const tabButtons = document.querySelectorAll('.course-tab-btn');
  const courseCards = document.querySelectorAll('.course-card');

  function applyFilter(filter, shouldScroll = false) {
    if (!filter) filter = 'all';

    let matched = false;
    tabButtons.forEach(btn => {
      if (btn.getAttribute('data-filter') === filter) {
        btn.classList.add('active');
        matched = true;
      } else {
        btn.classList.remove('active');
      }
    });

    // If filter doesn't match any tab, fallback to 'all'
    if (!matched && tabButtons.length) {
      filter = 'all';
      const allBtn = document.querySelector('.course-tab-btn[data-filter="all"]');
      if (allBtn) allBtn.classList.add('active');
    }

    if (courseCards.length) {
      courseCards.forEach(card => {
        const cardCategory = card.getAttribute('data-category');
        if (filter === 'all' || cardCategory === filter) {
          card.style.display = 'flex';
        } else {
          card.style.display = 'none';
        }
      });
    }

    if (shouldScroll) {
      const targetSec = document.getElementById('coursesSection') || document.querySelector('.courses-tab-nav');
      if (targetSec) {
        const headerOffset = 100;
        const elemPos = targetSec.getBoundingClientRect().top + window.pageYOffset - headerOffset;
        window.scrollTo({ top: elemPos, behavior: 'smooth' });
      }
    }
  }

  // 1. Tab buttons click listener
  if (tabButtons.length) {
    tabButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const filter = btn.getAttribute('data-filter');
        applyFilter(filter, false);
        if (window.history && window.history.replaceState) {
          const url = new URL(window.location);
          url.searchParams.set('filter', filter);
          window.history.replaceState(null, '', url);
        }
      });
    });
  }

  // 2. Check URL query parameters or hash on initial load
  const urlParams = new URLSearchParams(window.location.search);
  const paramFilter = urlParams.get('filter');
  const hashFilter = window.location.hash ? window.location.hash.replace('#', '') : null;
  const initialFilter = paramFilter || hashFilter;

  if (initialFilter && (tabButtons.length || courseCards.length)) {
    // Delay slightly to ensure layout and fonts are rendered before smooth scroll
    setTimeout(() => {
      applyFilter(initialFilter, true);
    }, 120);
  }

  // 3. Global Submenu Links Listener
  document.querySelectorAll('[data-course-filter]').forEach(link => {
    link.addEventListener('click', (e) => {
      const filter = link.getAttribute('data-course-filter');
      const isCoursesPage = window.location.pathname.includes('courses') || !!document.getElementById('coursesSection');

      if (isCoursesPage) {
        e.preventDefault();
        applyFilter(filter, true);
        if (window.history && window.history.pushState) {
          const url = new URL(window.location);
          url.searchParams.set('filter', filter);
          window.history.pushState(null, '', url);
        }
        // Close mobile drawer if open
        const drawer = document.getElementById('mobileDrawer');
        const overlay = document.getElementById('drawerOverlay');
        if (drawer && drawer.classList.contains('active')) {
          drawer.classList.remove('active');
          if (overlay) overlay.classList.remove('active');
        }
      }
    });
  });

  // 4. Handle browser Back/Forward buttons
  window.addEventListener('popstate', () => {
    const params = new URLSearchParams(window.location.search);
    const f = params.get('filter') || 'all';
    applyFilter(f, true);
  });
}

/* ==========================================================================
   5. Interactive FAQ Accordion
   ========================================================================== */
function initFaqAccordion() {
  const faqItems = document.querySelectorAll('.faq-item');
  if (!faqItems.length) return;

  faqItems.forEach(item => {
    const header = item.querySelector('.faq-header');
    if (!header) return;

    header.addEventListener('click', () => {
      const isActive = item.classList.contains('active');
      // Close others
      faqItems.forEach(other => other.classList.remove('active'));

      if (!isActive) {
        item.classList.add('active');
      }
    });
  });
}

/* ==========================================================================
   6. Modals System
   ========================================================================== */
function initModals() {
  const triggers = document.querySelectorAll('[data-modal]');
  const closers = document.querySelectorAll('[data-close="modal"]');
  const backdrops = document.querySelectorAll('.modal-backdrop');

  triggers.forEach(trigger => {
    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      const modalId = trigger.getAttribute('data-modal');
      const targetModal = document.getElementById(modalId);
      if (targetModal) {
        targetModal.classList.add('active');
        document.body.style.overflow = 'hidden';
      }
    });
  });

  closers.forEach(closer => {
    closer.addEventListener('click', () => {
      backdrops.forEach(b => b.classList.remove('active'));
      document.body.style.overflow = '';
    });
  });

  backdrops.forEach(backdrop => {
    backdrop.addEventListener('click', (e) => {
      if (e.target === backdrop) {
        backdrop.classList.remove('active');
        document.body.style.overflow = '';
      }
    });
  });

  // ESC key listener
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      backdrops.forEach(b => b.classList.remove('active'));
      document.body.style.overflow = '';
    }
  });
}

/* ==========================================================================
   7. Gallery Lightbox Viewer with Zoom-In & Left-Right Navigation
   ========================================================================== */
function initGalleryLightbox() {
  const lightbox = document.getElementById('imageLightboxModal');
  const targetImg = document.getElementById('lightboxTargetImage');
  const captionEl = document.getElementById('lightboxCaption');
  const counterEl = document.getElementById('lightboxCounter');
  const prevBtn = document.getElementById('lightboxPrevBtn');
  const nextBtn = document.getElementById('lightboxNextBtn');

  if (!lightbox || !targetImg) return;

  let currentItems = [];
  let currentIndex = 0;

  function refreshItems() {
    currentItems = Array.from(document.querySelectorAll('.gallery-card[data-src]'));
  }

  function showImage(index) {
    if (!currentItems.length) return;
    if (index < 0) index = currentItems.length - 1;
    if (index >= currentItems.length) index = 0;
    currentIndex = index;

    const item = currentItems[currentIndex];
    const src = item.getAttribute('data-src');
    const caption = item.getAttribute('data-caption') || '';

    // Trigger smooth zoomin animation
    targetImg.classList.remove('animating-in');
    void targetImg.offsetWidth; // Force CSS reflow
    targetImg.src = src;
    targetImg.classList.add('animating-in');

    if (captionEl) captionEl.textContent = caption;
    if (counterEl) counterEl.textContent = `${currentIndex + 1} / ${currentItems.length}`;
  }

  // Bind gallery cards
  document.addEventListener('click', (e) => {
    const card = e.target.closest('.gallery-card[data-src]');
    if (!card) return;

    refreshItems();
    const clickedIdx = currentItems.indexOf(card);
    if (clickedIdx !== -1) {
      showImage(clickedIdx);
      lightbox.classList.add('active');
      document.body.style.overflow = 'hidden';
    }
  });

  // Prev / Next Buttons
  if (prevBtn) {
    prevBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      showImage(currentIndex - 1);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      showImage(currentIndex + 1);
    });
  }

  // Keyboard navigation
  document.addEventListener('keydown', (e) => {
    if (!lightbox.classList.contains('active')) return;
    if (e.key === 'ArrowLeft') {
      e.preventDefault();
      showImage(currentIndex - 1);
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      showImage(currentIndex + 1);
    }
  });

  // Touch Swipe navigation on mobile
  let touchStartX = 0;
  let touchEndX = 0;

  lightbox.addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].screenX;
  }, { passive: true });

  lightbox.addEventListener('touchend', (e) => {
    touchEndX = e.changedTouches[0].screenX;
    const diff = touchEndX - touchStartX;
    if (Math.abs(diff) > 50) {
      if (diff > 0) {
        showImage(currentIndex - 1);
      } else {
        showImage(currentIndex + 1);
      }
    }
  }, { passive: true });
}

/* ==========================================================================
   8. Scroll To Top Button
   ========================================================================== */
function initScrollToTop() {
  const btn = document.getElementById('scrollToTopBtn');
  if (!btn) return;

  window.addEventListener('scroll', () => {
    if (window.scrollY > 300) {
      btn.classList.add('visible');
    } else {
      btn.classList.remove('visible');
    }
  }, { passive: true });

  btn.addEventListener('click', () => {
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });
}

/* ==========================================================================
   9. Animated Stats Counter
   ========================================================================== */
function initStatsCounter() {
  const counters = document.querySelectorAll('.stat-number[data-target]');
  if (!counters.length) return;

  let hasAnimated = false;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting && !hasAnimated) {
        hasAnimated = true;
        counters.forEach(counter => {
          const target = parseInt(counter.getAttribute('data-target'), 10);
          const prefix = counter.getAttribute('data-prefix') || '';
          const suffix = counter.getAttribute('data-suffix') || '';
          let current = 0;
          const step = Math.max(1, Math.floor(target / 40));

          const timer = setInterval(() => {
            current += step;
            if (current >= target) {
              current = target;
              clearInterval(timer);
            }
            counter.textContent = `${prefix}${current}${suffix}`;
          }, 30);
        });
      }
    });
  }, { threshold: 0.3 });

  const statsSection = document.querySelector('.hero-stats-row') || counters[0];
  if (statsSection) observer.observe(statsSection);
}

/* ==========================================================================
   10. Interactive Form Handlers (Demo Class & Contact)
   ========================================================================== */
function escapeHtml(str) {
  if (!str) return '';
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function handleDemoSubmit(e) {
  e.preventDefault();
  const form = document.getElementById('demoClassForm');
  const successState = document.getElementById('demoSuccessState');
  const submitBtn = document.getElementById('demoSubmitBtn');
  const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';

  const name = document.getElementById('demoStudentName')?.value.trim();
  const phone = document.getElementById('demoPhone')?.value.trim();
  const course = document.getElementById('demoClass')?.value;

  if (!name || !phone || !course) {
    alert('Please complete all required fields.');
    return;
  }

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Reserving Seat...';
  }

  const formData = new FormData(form);
  formData.append('ajax', '1');

  fetch('/submit-form', {
    method: 'POST',
    body: formData,
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      if (form) form.style.display = 'none';
      if (successState) {
        successState.style.display = 'block';
        const textEl = document.getElementById('demoSuccessText');
        if (textEl) {
          textEl.innerHTML = `Thank you, <strong>${escapeHtml(name)}</strong>! Your 3-day demo registration for <strong>${escapeHtml(course)}</strong> has been received. Our counselor will contact you at <strong>+91 ${escapeHtml(phone)}</strong>.`;
        }
      }
      form.reset();
    } else {
      alert(data.message || 'Error booking demo class. Please check your details.');
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origBtnHtml;
      }
    }
  })
  .catch(err => {
    console.error('Demo form error:', err);
    form.submit();
  });
}

function handleContactSubmit(e) {
  e.preventDefault();
  const form = document.getElementById('contactForm');
  const alertBox = document.getElementById('contactSuccessAlert');
  const submitBtn = document.getElementById('contactSubmitBtn');
  const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';

  const name = document.getElementById('contactName')?.value.trim();
  const phone = document.getElementById('contactPhone')?.value.trim();

  if (!name || !phone) {
    alert('Please enter your name and phone number.');
    return;
  }

  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
  }

  const formData = new FormData(form);
  formData.append('ajax', '1');

  fetch('/submit-form', {
    method: 'POST',
    body: formData,
    headers: {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(res => res.json())
  .then(data => {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = origBtnHtml;
    }
    if (data.success) {
      if (alertBox) {
        alertBox.style.display = 'block';
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      form.reset();
    } else {
      alert(data.message || 'Error submitting your inquiry. Please try again.');
    }
  })
  .catch(err => {
    console.error('Contact form error:', err);
    form.submit();
  });
}

/* ==========================================================================
   11. Scroll Entrance Animations (IntersectionObserver)
   ========================================================================== */
function initScrollAnimations() {
  const animatedElements = document.querySelectorAll('.reveal-on-scroll');
  if (!animatedElements.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-revealed');
        observer.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.12,
    rootMargin: '0px 0px -40px 0px'
  });

  animatedElements.forEach(el => observer.observe(el));
}

/* ==========================================================================
   12. Active Menu Synchronization (Desktop & Mobile Drawer)
   ========================================================================== */
function initActiveNav() {
  const rawPath = window.location.pathname;
  const currentPath = rawPath.replace(/\/+$/, '') || '/';
  
  const desktopLinks = document.querySelectorAll('.desktop-navigation .nav-link');
  const drawerLinks = document.querySelectorAll('.drawer-menu-list a');

  const highlightNavGroup = (links) => {
    let matched = false;
    links.forEach(link => {
      const href = link.getAttribute('href');
      if (!href) return;
      
      const linkPath = href.split('#')[0].split('?')[0].replace(/\/+$/, '') || '/';
      const isHome = (currentPath === '' || currentPath === '/' || currentPath.endsWith('/index.php'));
      const isLinkHome = (linkPath === '' || linkPath === '/' || linkPath.endsWith('/index.php'));

      let active = false;
      if (isHome && isLinkHome) {
        active = true;
      } else if (!isHome && !isLinkHome && (currentPath === linkPath || currentPath.startsWith(linkPath + '/'))) {
        active = true;
      }

      if (active) {
        link.classList.add('active');
        matched = true;
      } else {
        link.classList.remove('active');
      }
    });

    if (!matched && (currentPath === '/' || currentPath === '')) {
      links.forEach(link => {
        const href = link.getAttribute('href');
        if (href === '/' || href === '') link.classList.add('active');
      });
    }
  };

  if (desktopLinks.length) highlightNavGroup(desktopLinks);
  if (drawerLinks.length) highlightNavGroup(drawerLinks);
}

/* ==========================================================================
   13. Reload Promo Popup Modal
   ========================================================================== */
function initReloadPromoModal() {
  const promoModal = document.getElementById('reloadPromoModal');
  if (!promoModal) return;

  // Automatically trigger modal popup on page load/reload after smooth entrance delay
  setTimeout(() => {
    // Only open if no other modal is currently active
    const anyActiveModal = document.querySelector('.modal-backdrop.active');
    if (!anyActiveModal) {
      promoModal.classList.add('active');
      document.body.style.overflow = 'hidden';
      if (typeof window.restartPromoAutoplay === 'function') {
        window.restartPromoAutoplay();
      }
    }
  }, 650);

  // Close handlers to stop autoplay
  const closeBtns = promoModal.querySelectorAll('[data-close="modal"], .promo-close-btn');
  closeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      promoModal.classList.remove('active');
      document.body.style.overflow = '';
      if (typeof window.stopPromoAutoplay === 'function') {
        window.stopPromoAutoplay();
      }
    });
  });
}

/* ==========================================================================
   14. Popup Slider (Automatic Slideshow with Controls & Touch Swipe)
   ========================================================================== */
function initPromoSlider() {
  const sliderContainer = document.getElementById('promoSlider');
  if (!sliderContainer) return;

  const track = document.getElementById('promoSlidesTrack');
  const slides = sliderContainer.querySelectorAll('.promo-slide');
  const dots = sliderContainer.querySelectorAll('.promo-dot');
  const prevBtn = document.getElementById('promoPrevBtn');
  const nextBtn = document.getElementById('promoNextBtn');

  if (!track || slides.length <= 1) return;

  let currentIndex = 0;
  const totalSlides = slides.length;
  let autoplayTimer = null;

  const showSlide = (index) => {
    if (index < 0) {
      index = totalSlides - 1;
    } else if (index >= totalSlides) {
      index = 0;
    }
    currentIndex = index;

    // Apply exact percentage translation based on slide index (immune to scaling/timing)
    track.style.transform = `translateX(-${currentIndex * 100}%)`;

    // Toggle active state for slides (for focus and active scaling)
    slides.forEach((slide, i) => {
      if (i === currentIndex) {
        slide.classList.add('active');
      } else {
        slide.classList.remove('active');
      }
    });

    // Update pagination dots
    dots.forEach((dot, i) => {
      if (i === currentIndex) {
        dot.classList.add('active');
      } else {
        dot.classList.remove('active');
      }
    });
  };

  window.addEventListener('resize', () => {
    if (sliderContainer.offsetParent !== null) {
      showSlide(currentIndex);
    }
  });

  const nextSlide = () => showSlide(currentIndex + 1);
  const prevSlide = () => showSlide(currentIndex - 1);

  if (nextBtn) {
    nextBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      nextSlide();
      restartAutoplay();
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      prevSlide();
      restartAutoplay();
    });
  }

  dots.forEach(dot => {
    dot.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      const targetIndex = parseInt(dot.getAttribute('data-slide'), 10);
      if (!isNaN(targetIndex)) {
        showSlide(targetIndex);
        restartAutoplay();
      }
    });
  });

  // Touch Swipe for mobile devices
  let touchStartX = 0;
  let touchEndX = 0;
  let touchStartY = 0;
  let touchEndY = 0;

  sliderContainer.addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].screenX;
    touchStartY = e.changedTouches[0].screenY;
  }, { passive: true });

  sliderContainer.addEventListener('touchend', (e) => {
    touchEndX = e.changedTouches[0].screenX;
    touchEndY = e.changedTouches[0].screenY;
    const diffX = touchStartX - touchEndX;
    const diffY = touchStartY - touchEndY;
    if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 40) {
      if (diffX > 0) {
        nextSlide();
      } else {
        prevSlide();
      }
      restartAutoplay();
    }
  }, { passive: true });

  // Autoplay functionality with smooth pause on hover
  const startAutoplay = () => {
    stopAutoplay();
    autoplayTimer = setInterval(nextSlide, 3200);
  };

  const stopAutoplay = () => {
    if (autoplayTimer) {
      clearInterval(autoplayTimer);
      autoplayTimer = null;
    }
  };

  const restartAutoplay = () => {
    stopAutoplay();
    startAutoplay();
  };

  window.restartPromoAutoplay = restartAutoplay;
  window.stopPromoAutoplay = stopAutoplay;

  sliderContainer.addEventListener('mouseenter', stopAutoplay);
  sliderContainer.addEventListener('mouseleave', startAutoplay);

  // Initialize
  showSlide(0);
  startAutoplay();
}

/* ==========================================================================
   14. Top Announcement Bar Vertical Slide-Down Rotator
   ========================================================================== */
function initTopBarTicker() {
  const wrapper = document.getElementById('topbarTicker');
  if (!wrapper) return;
  const slides = wrapper.querySelectorAll('.ticker-slide');
  if (slides.length <= 1) return;

  let currentIndex = 0;
  let isPaused = false;

  wrapper.addEventListener('mouseenter', () => { isPaused = true; });
  wrapper.addEventListener('mouseleave', () => { isPaused = false; });

  setInterval(() => {
    if (isPaused) return;

    const currentSlide = slides[currentIndex];
    currentIndex = (currentIndex + 1) % slides.length;
    const nextSlide = slides[currentIndex];

    // Current slide transitions out downwards
    currentSlide.classList.remove('slide-active');
    currentSlide.classList.add('slide-out-down');

    // Next slide transitions in downwards into place
    nextSlide.classList.remove('slide-out-down');
    nextSlide.classList.add('slide-active');

    // After animation finishes, reset the exited slide back to top without transition
    setTimeout(() => {
      currentSlide.style.transition = 'none';
      currentSlide.classList.remove('slide-out-down');
      // Trigger reflow so transition: none takes effect immediately
      void currentSlide.offsetHeight;
      currentSlide.style.transition = '';
    }, 500);
  }, 3200);
}


