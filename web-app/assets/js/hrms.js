/**
 * KSV Academy HRMS - Interactive Client Script
 */

document.addEventListener('DOMContentLoaded', function () {
  // 1. Mobile Sidebar Toggle
  const mobileToggle = document.getElementById('mobileMenuToggle');
  const sidebar = document.getElementById('hrmsSidebar');
  if (mobileToggle && sidebar) {
    mobileToggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });

    document.addEventListener('click', function (e) {
      if (window.innerWidth <= 991 && !sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
        sidebar.classList.remove('open');
      }
    });
  }

  // 2. Login Page Role Tabs & Auto-Fill Prevention
  const roleTabs = document.querySelectorAll('.role-tab-btn');
  const usernameInput = document.getElementById('loginUsername');
  const passwordInput = document.getElementById('loginPassword');
  const roleInput = document.getElementById('selectedRole');
  const roleContextName = document.getElementById('roleContextName');
  const roleContextDesc = document.getElementById('roleContextDesc');
  const roleContextIcon = document.getElementById('roleContextIcon');
  const usernameHint = document.getElementById('usernameHint');
  const submitBtnText = document.getElementById('submitBtnText');

  const roleMeta = {
    'super_admin': {
      name: 'Super Admin Workspace',
      desc: 'Full administrative control over institutional settings, payroll, staff records & reports.',
      icon: 'fa-shield-halved',
      hint: 'Admin ID or Email',
      btn: 'Sign In to Super Admin',
      placeholder: 'Enter admin username or email'
    },
    'staff': {
      name: 'Staff & Faculty Workspace',
      desc: 'Access your teaching schedule, daily biometric logs, student attendance & leave requests.',
      icon: 'fa-chalkboard-user',
      hint: 'Employee Code or Email',
      btn: 'Sign In as Staff / Faculty',
      placeholder: 'Enter employee code (e.g. STF-001)'
    },
    'parent': {
      name: 'Parent & Student Portal',
      desc: 'Track academic progress, report cards, fee records, attendance rate & official circulars.',
      icon: 'fa-user-graduate',
      hint: 'Student ID or Parent Mobile',
      btn: 'Sign In as Parent / Student',
      placeholder: 'Enter student ID or registered mobile'
    }
  };

  function applyRoleUI(role) {
    const meta = roleMeta[role] || roleMeta['super_admin'];
    if (roleContextName) roleContextName.textContent = meta.name;
    if (roleContextDesc) roleContextDesc.textContent = meta.desc;
    if (roleContextIcon) roleContextIcon.innerHTML = `<i class="fa-solid ${meta.icon}"></i>`;
    if (usernameHint) usernameHint.textContent = meta.hint;
    if (submitBtnText) submitBtnText.textContent = meta.btn;
    if (usernameInput) usernameInput.setAttribute('placeholder', meta.placeholder);
  }

  if (roleTabs.length > 0) {
    // Initialize UI for currently active role
    const currentActive = document.querySelector('.role-tab-btn.active');
    if (currentActive) {
      applyRoleUI(currentActive.getAttribute('data-role'));
    }

    roleTabs.forEach(tab => {
      tab.addEventListener('click', function () {
        roleTabs.forEach(t => {
          t.classList.remove('active');
          t.setAttribute('aria-selected', 'false');
        });
        this.classList.add('active');
        this.setAttribute('aria-selected', 'true');

        const role = this.getAttribute('data-role');
        if (roleInput) roleInput.value = role;

        // Apply updated role contextual UI
        applyRoleUI(role);

        // Clear credentials on tab click so it does NOT auto-fill
        if (usernameInput) {
          usernameInput.value = '';
        }
        if (passwordInput) {
          passwordInput.value = '';
        }

        // Catch delayed browser autofill attempts
        setTimeout(function () {
          if (usernameInput) usernameInput.value = '';
          if (passwordInput) passwordInput.value = '';
        }, 50);
        setTimeout(function () {
          if (usernameInput) usernameInput.value = '';
          if (passwordInput) passwordInput.value = '';
        }, 200);
      });
    });

    // Prevent initial browser auto-fill on page load (unless validation error was displayed)
    const hasErrorAlert = document.querySelector('.flash-alert.error');
    if (!hasErrorAlert) {
      setTimeout(function () {
        if (usernameInput && document.activeElement !== usernameInput) {
          usernameInput.value = '';
        }
        if (passwordInput && document.activeElement !== passwordInput) {
          passwordInput.value = '';
        }
      }, 50);
      setTimeout(function () {
        if (usernameInput && document.activeElement !== usernameInput) {
          usernameInput.value = '';
        }
        if (passwordInput && document.activeElement !== passwordInput) {
          passwordInput.value = '';
        }
      }, 250);
    }
  }

  // Quick Demo Chip Fillers
  window.fillDemoCreds = function (user, pass, role) {
    if (usernameInput) usernameInput.value = user;
    if (passwordInput) passwordInput.value = pass;
    if (roleInput) roleInput.value = role;

    // highlight corresponding tab
    roleTabs.forEach(t => {
      if (t.getAttribute('data-role') === role) {
        t.classList.add('active');
      } else {
        t.classList.remove('active');
      }
    });
  };

  // 3. Password Visibility Toggle
  const togglePassBtn = document.getElementById('togglePasswordBtn');
  if (togglePassBtn && passwordInput) {
    togglePassBtn.addEventListener('click', function () {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      const icon = this.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
      }
    });
  }

  // 4. Modal Handlers
  window.openModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('show');
      modal.style.display = 'flex';
    }
  };

  window.closeModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('show');
      modal.style.display = 'none';
    }
  };

  // Close modal when clicking outside card
  document.querySelectorAll('.hrms-modal').forEach(modal => {
    modal.addEventListener('click', function (e) {
      if (e.target === this) {
        this.classList.remove('show');
        this.style.display = 'none';
      }
    });
  });

  // 5. Live Digital Clock for Staff Punch Status
  const liveClockEl = document.getElementById('staffLiveClock');
  if (liveClockEl) {
    function updateClock() {
      const now = new Date();
      liveClockEl.textContent = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
      });
    }
    updateClock();
    setInterval(updateClock, 1000);
  }
});
