(() => {
  /* ---------- Bootstrap modal placement ----------
     Modals inside dashboard cards/tables can inherit a stacking context from
     their container. Keeping them directly under <body> prevents a backdrop
     from covering the page while the dialog is hidden behind it. */
  document.querySelectorAll('.modal').forEach((modal) => {
    if (modal.parentElement !== document.body) document.body.appendChild(modal);
  });

  /* ---------- Authenticated sidebar ---------- */
  const sidebar = document.getElementById('nhreSidebar');
  if (sidebar) {
    const body = document.body;
    const toggle = document.querySelector('.sidebar-toggle');
    const collapse = document.querySelector('.sidebar-collapse');
    const backdrop = document.querySelector('.sidebar-backdrop');
    let sidebarCloseTimer;
    body.classList.add('has-sidebar');

    const setMobileOpen = (open) => {
      if (backdrop && open) {
        window.clearTimeout(sidebarCloseTimer);
        backdrop.hidden = false;
        requestAnimationFrame(() => body.classList.add('sidebar-open'));
      } else {
        body.classList.remove('sidebar-open');
        if (backdrop) sidebarCloseTimer = window.setTimeout(() => { backdrop.hidden = true; }, 260);
      }
      if (toggle) toggle.setAttribute('aria-expanded', String(open));
    };
    const closeMobile = () => setMobileOpen(false);

    if (toggle) toggle.addEventListener('click', () => setMobileOpen(!body.classList.contains('sidebar-open')));
    if (backdrop) backdrop.addEventListener('click', closeMobile);
    if (collapse) collapse.addEventListener('click', () => {
      body.classList.toggle('sidebar-collapsed');
      try { localStorage.setItem('nhre-sidebar-collapsed', body.classList.contains('sidebar-collapsed') ? '1' : '0'); } catch (e) {}
    });
    try { if (localStorage.getItem('nhre-sidebar-collapsed') === '1' && window.innerWidth > 991) body.classList.add('sidebar-collapsed'); } catch (e) {}
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMobile));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMobile(); });
  }

  'use strict';

  /* ---------- Light/dark theme ---------- */
  const THEME_KEY = 'nhre-theme';
  const root = document.documentElement;
  const preferredTheme = () => {
    try {
      const saved = localStorage.getItem(THEME_KEY);
      if (saved === 'light' || saved === 'dark') return saved;
    } catch (err) {}
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  };

  const existingThemeButton = document.getElementById('themeToggle');
  const themeButton = existingThemeButton || document.createElement('button');
  if (!existingThemeButton) {
    themeButton.type = 'button';
    themeButton.id = 'themeToggle';
  }

  const applyTheme = (theme, save = false) => {
    root.dataset.theme = theme;
    root.style.colorScheme = theme;
    const dark = theme === 'dark';
    themeButton.innerHTML = `<i class="fa-solid fa-${dark ? 'sun' : 'moon'}" aria-hidden="true"></i>`;
    themeButton.setAttribute('aria-label', `Switch to ${dark ? 'light' : 'dark'} theme`);
    themeButton.setAttribute('title', `${dark ? 'Light' : 'Dark'} theme`);
    themeButton.setAttribute('aria-pressed', String(dark));
    if (save) {
      try { localStorage.setItem(THEME_KEY, theme); } catch (err) {}
    }
  };

  applyTheme(preferredTheme());
  if (!existingThemeButton) {
    const navActions =
      document.querySelector('.dashboard-nav .container > div:last-child') ||
      document.querySelector('#navMain > .d-flex');
    if (navActions) {
      themeButton.className = 'theme-toggle theme-toggle-nav';
      navActions.appendChild(themeButton);
    } else {
      themeButton.className = 'theme-toggle';
      document.body.appendChild(themeButton);
    }
  }
  themeButton.addEventListener('click', () => {
    applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark', true);
  });

  /* ---------- Ripple effect ---------- */
  document.querySelectorAll('.ripple').forEach((el) => {
    el.addEventListener('click', (ev) => {
      const rect = el.getBoundingClientRect();
      const size = Math.max(rect.width, rect.height);
      const ink = document.createElement('span');
      ink.className = 'ripple-ink';
      ink.style.width = ink.style.height = size + 'px';
      ink.style.left = ev.clientX - rect.left - size / 2 + 'px';
      ink.style.top = ev.clientY - rect.top - size / 2 + 'px';
      el.appendChild(ink);
      setTimeout(() => ink.remove(), 650);
    });
  });

  /* ---------- Navbar scroll state ---------- */
  const nav = document.getElementById('mainNav');
  if (nav) {
    const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 24);
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ---------- Landing nav scrollspy ---------- */
  const spyLinks = document.querySelectorAll('.nav-link-nhre[href^="#"]');
  if (spyLinks.length) {
    const spySections = Array.from(spyLinks)
      .map((a) => document.querySelector(a.getAttribute('href')))
      .filter(Boolean);
    const spy = () => {
      const pos = window.scrollY + 140;
      let current = spySections[0];
      spySections.forEach((sec) => { if (sec.offsetTop <= pos) current = sec; });
      if (!current) return;
      spyLinks.forEach((a) =>
        a.classList.toggle('is-active', a.getAttribute('href') === '#' + current.id)
      );
    };
    window.addEventListener('scroll', spy, { passive: true });
    spy();
  }

  /* ---------- Hero particle network ---------- */
  const particlesCanvas = document.getElementById('nhre-particles');
  if (particlesCanvas && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    const ctx = particlesCanvas.getContext('2d');
    let particles = [];
    let pRaf = 0;
    const DPR = Math.min(window.devicePixelRatio || 1, 2);
    const pSize = () => {
      const rect = particlesCanvas.parentElement.getBoundingClientRect();
      particlesCanvas.width = Math.floor(rect.width * DPR);
      particlesCanvas.height = Math.floor(rect.height * DPR);
      ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
    };
    const spawn = () => {
      const w = particlesCanvas.width / DPR;
      const h = particlesCanvas.height / DPR;
      particles = Array.from({ length: Math.min(64, Math.floor(w / 16)) }, () => ({
        x: Math.random() * w,
        y: Math.random() * h,
        vx: (Math.random() - 0.5) * 0.32,
        vy: (Math.random() - 0.5) * 0.32,
        r: 1 + Math.random() * 2.2,
      }));
    };
    const tick = () => {
      const w = particlesCanvas.width / DPR;
      const h = particlesCanvas.height / DPR;
      const dark = root.dataset.theme === 'dark';
      const line = dark ? '34,211,238' : '6,42,69';
      const dot = dark ? '34,211,238' : '0,166,166';
      ctx.clearRect(0, 0, w, h);
      particles.forEach((p) => {
        p.x += p.vx;
        p.y += p.vy;
        if (p.x < 0 || p.x > w) p.vx *= -1;
        if (p.y < 0 || p.y > h) p.vy *= -1;
      });
      for (let i = 0; i < particles.length; i++) {
        for (let j = i + 1; j < particles.length; j++) {
          const a = particles[i];
          const b = particles[j];
          const dist = Math.hypot(a.x - b.x, a.y - b.y);
          if (dist < 130) {
            ctx.strokeStyle = `rgba(${line},${(0.16 * (1 - dist / 130)).toFixed(3)})`;
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.moveTo(a.x, a.y);
            ctx.lineTo(b.x, b.y);
            ctx.stroke();
          }
        }
      }
      particles.forEach((p) => {
        ctx.fillStyle = `rgba(${dot},0.7)`;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fill();
      });
      pRaf = requestAnimationFrame(tick);
    };
    const pStart = () => { pSize(); spawn(); cancelAnimationFrame(pRaf); pRaf = requestAnimationFrame(tick); };
    window.addEventListener('resize', pStart);
    pStart();
  }

  /* ---------- Close mobile nav on link click ---------- */
  const navCollapse = document.getElementById('navMain');
  if (navCollapse) {
    navCollapse.querySelectorAll('.nav-link').forEach((link) => {
      link.addEventListener('click', () => {
        if (navCollapse.classList.contains('show')) {
          const inst = bootstrap.Collapse.getOrCreateInstance(navCollapse);
          inst.hide();
        }
      });
    });
  }

  /* ---------- Reveal on scroll ---------- */
  const revealEls = document.querySelectorAll('.reveal');
  const reveal = (el) => el.classList.add('revealed');

  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            reveal(entry.target);
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12 }
    );
    revealEls.forEach((el) => io.observe(el));
  } else {
    revealEls.forEach(reveal);
  }

  /* ---------- Animated counters ---------- */
  const counters = document.querySelectorAll('[data-count]');

  const animateCounter = (el) => {
    const target = parseFloat(el.dataset.count);
    const decimals = parseInt(el.dataset.decimal || '0', 10);
    const suffix = el.dataset.suffix || '';
    const duration = 1800;
    const start = performance.now();

    const step = (now) => {
      const p = Math.min((now - start) / duration, 1);
      const eased = 1 - Math.pow(1 - p, 3);
      el.textContent = (target * eased).toFixed(decimals) + suffix;
      if (p < 1) requestAnimationFrame(step);
    };

    requestAnimationFrame(step);
  };

  if ('IntersectionObserver' in window) {
    const cio = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            animateCounter(entry.target);
            cio.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.4 }
    );
    counters.forEach((el) => cio.observe(el));
  } else {
    counters.forEach(animateCounter);
  }

  /* ---------- Strong password meter ---------- */
  const STRONG_RE = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/;
  const passwordField = document.getElementById('password');
  const confirmField = document.getElementById('confirm_password');
  const meterBar = document.querySelector('.password-meter > span');

  const scorePassword = (val) => {
    let score = 0;
    if (val.length >= 8) score++;
    if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++;
    if (/\d/.test(val)) score++;
    if (/[\W_]/.test(val)) score++;
    return score;
  };

  if (passwordField && meterBar && passwordField.dataset.strongPassword !== undefined) {
    passwordField.addEventListener('input', () => {
      const score = scorePassword(passwordField.value);
      meterBar.className = 'level-' + score;
      meterBar.style.width = (score / 4) * 100 + '%';
    });
  }

  /* ---------- Password visibility toggles ---------- */
  document.querySelectorAll('.form-floating input[type="password"]').forEach((input) => {
    const wrap = input.closest('.form-floating');
    if (!wrap) return;
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'password-toggle';
    btn.setAttribute('aria-label', 'Show password');
    btn.setAttribute('tabindex', '0');
    btn.innerHTML = '<i class="fa-regular fa-eye" aria-hidden="true"></i>';
    btn.addEventListener('click', () => {
      const reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      btn.innerHTML = `<i class="fa-${reveal ? 'solid' : 'regular'} fa-eye${reveal ? '-slash' : ''}" aria-hidden="true"></i>`;
      btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    });
    wrap.appendChild(btn);
  });

  /* ---------- Bootstrap custom validation + confirm match ---------- */
  document.querySelectorAll('.needs-validation').forEach((form) => {
    const clearValidity = (input) =>
      input.addEventListener('input', () => input.setCustomValidity(''));

    form.querySelectorAll('input, select').forEach(clearValidity);

    form.addEventListener(
      'submit',
      (ev) => {
        let valid = form.checkValidity();

        if (passwordField && passwordField.dataset.strongPassword !== undefined && passwordField.value !== '') {
          if (!STRONG_RE.test(passwordField.value)) {
            passwordField.setCustomValidity(
              'Use 8+ characters with uppercase, lowercase, number, and symbol.'
            );
            valid = false;
          }
        }

        if (confirmField && passwordField && confirmField.value !== passwordField.value) {
          confirmField.setCustomValidity('Passwords must match.');
          valid = false;
        }

        if (!valid) {
          ev.preventDefault();
          ev.stopPropagation();
          form.querySelector(':invalid')?.focus();
        }

        form.classList.add('was-validated');
      },
      false
    );
  });

  /* ---------- Submit loading state ---------- */
  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      if (form.checkValidity()) {
        form.querySelectorAll('button[type="submit"]').forEach((btn) => {
          btn.classList.add('is-loading');
          btn.disabled = true;
        });
      }
    });
  });

  /* ---------- Notification bell + overlay ---------- */
  const bell = document.getElementById('notificationBell');
  if (bell) {
    const wrap = document.getElementById('notificationWrap');
    const badge = document.getElementById('notificationBadge');
    const overlay = document.getElementById('notificationOverlay');
    const list = document.getElementById('notificationList');
    const markAll = document.getElementById('markAllRead');
    const csrfInput = document.getElementById('notificationCsrf');
    const API_URL = 'auth/notifications_api.php';

    const escapeHtml = (value) =>
      String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
      }[c]));

    const timeAgo = (datetime) => {
      const date = new Date(String(datetime).replace(' ', 'T'));
      if (isNaN(date.getTime())) return datetime;
      const diff = Math.max(0, (Date.now() - date.getTime()) / 1000);
      if (diff < 60) return 'just now';
      if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
      if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
      return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    };

    const updateBadge = (count) => {
      if (badge) {
        if (count > 0) {
          badge.textContent = count > 99 ? '99+' : String(count);
          badge.hidden = false;
          badge.style.display = 'inline-flex';
        } else {
          badge.textContent = '';
          badge.hidden = true;
          badge.style.display = 'none';
        }
      }
      document.querySelectorAll('.sidebar-unread').forEach((sb) => {
        if (count > 0) {
          sb.textContent = count > 99 ? '99+' : String(count);
          sb.hidden = false;
        } else {
          sb.remove();
        }
      });
    };

    const NOTIF_ICONS = {
      appointments: 'calendar-check',
      laboratory: 'flask-vial',
      prescriptions: 'prescription-bottle-medical',
      records: 'file-medical',
      access: 'user-shield',
      blood: 'droplet',
      security: 'lock',
      general: 'bell',
    };

    const notifCategory = (type) => {
      const t = String(type || '').toLowerCase();
      if (t.includes('appoint')) return 'appointments';
      if (t.includes('laborat') || t.includes('lab')) return 'laboratory';
      if (t.includes('prescript')) return 'prescriptions';
      if (t.includes('record') || t.includes('medical')) return 'records';
      if (t.includes('access')) return 'access';
      if (t.includes('blood')) return 'blood';
      if (t.includes('security')) return 'security';
      return 'general';
    };

    const renderList = (notifications) => {
      if (!list) return;
      if (!notifications.length) {
        list.innerHTML = '<div class="notification-empty">You\u2019re all caught up.</div>';
        return;
      }
      list.innerHTML = notifications
        .map((n) => {
          const key = notifCategory(n.notification_type);
          return `
            <a href="${escapeHtml(n.url || 'notifications.php')}" class="notification-item${n.is_read ? '' : ' notification-item-unread'}">
              <div class="d-flex gap-2 align-items-start">
                <span class="notif-type-icon t-${key}" aria-hidden="true"><i class="fa-solid fa-${NOTIF_ICONS[key]}"></i></span>
                <div class="flex-grow-1">
                  <div class="notification-item-title">${escapeHtml(n.title)}</div>
                  <div class="notification-item-msg">${escapeHtml(n.message)}</div>
                  <div class="notification-item-time">${escapeHtml(n.notification_type)} \u2022 ${timeAgo(n.created_at)}</div>
                </div>
              </div>
            </a>`;
        })
        .join('');
    };

    const loadNotifications = async () => {
      try {
        const res = await fetch(API_URL, {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        if (!res.ok) return;
        const data = await res.json();
        if (!data.ok) return;
        updateBadge(data.unread);
        renderList(data.notifications);
      } catch (err) {}
    };

    const markAllRead = async () => {
      if (!csrfInput) return;
      try {
        const res = await fetch(API_URL, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
          body: '_csrf=' + encodeURIComponent(csrfInput.value),
        });
        if (res.ok) updateBadge(0);
        loadNotifications();
      } catch (err) {}
    };

    bell.addEventListener('click', () => {
      const willOpen = overlay.hidden;
      overlay.hidden = !willOpen;
      bell.setAttribute('aria-expanded', String(willOpen));
      if (willOpen) {
        loadNotifications();
        markAllRead();
      }
    });

  if (markAll) {
      markAll.addEventListener('click', (ev) => {
        ev.stopPropagation();
        markAllRead();
      });
    }

    document.addEventListener('click', (ev) => {
      if (!overlay.hidden && wrap && !wrap.contains(ev.target)) {
        overlay.hidden = true;
        bell.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape' && !overlay.hidden) {
        overlay.hidden = true;
        bell.setAttribute('aria-expanded', 'false');
      }
    });

    loadNotifications();
    setInterval(loadNotifications, 30000);
  }

  /* ---------- Toast helper ---------- */
  window.nhreToast = (message, error = false) => {
    let toast = document.querySelector('.nhre-toast');
    if (!toast) {
      toast = document.createElement('div');
      toast.className = 'nhre-toast';
      toast.setAttribute('role', 'status');
      toast.setAttribute('aria-live', 'polite');
      document.body.appendChild(toast);
    }
    toast.classList.toggle('is-error', error);
    toast.innerHTML = `<i class="fa-solid fa-circle-${error ? 'xmark' : 'check'}" aria-hidden="true"></i><span>${message}</span>`;
    toast.classList.add('show');
    window.clearTimeout(window.__nhreToastTimer);
    window.__nhreToastTimer = window.setTimeout(() => toast.classList.remove('show'), 3200);
  };

  /* ---------- Smooth page transitions ---------- */
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('a[href]').forEach((link) => {
      link.addEventListener('click', (event) => {
        const href = link.getAttribute('href');
        const isInternal = href && !href.startsWith('#') && !href.startsWith('http') && !href.startsWith('mailto:') && !href.startsWith('tel:');
        if (!isInternal || link.target === '_blank' || link.hasAttribute('download') || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        event.preventDefault();
        document.body.classList.add('page-leaving');
        window.setTimeout(() => { window.location.href = href; }, 180);
      });
    });
  }

  /* ---------- Reset transition state when restored from bfcache ---------- */
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      document.body.classList.remove('page-leaving');
    }
  });
})();
