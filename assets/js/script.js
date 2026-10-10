// CCMS Interactive Scripts & Form Validation
document.addEventListener('DOMContentLoaded', function () {
  
  // Toggle "show password" checkboxes -> input[type=password]
  document.querySelectorAll('[data-toggle-password]').forEach(function (chk) {
    chk.addEventListener('change', function () {
      var target = document.querySelector(chk.getAttribute('data-toggle-password'));
      if (target) target.type = chk.checked ? 'text' : 'password';
    });
  });

  // Confirm before any delete form submits
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // Auto-dismiss standard alerts after 6s (except persistent auth/error alerts)
  document.querySelectorAll('.alert:not(.alert-permanent)').forEach(function (a) {
    setTimeout(function () {
      var alert = bootstrap.Alert.getOrCreateInstance(a);
      if (alert) alert.close();
    }, 6000);
  });

  // -------------------------------------------------------------
  // MOBILE SIDEBAR DRAWER & BACKDROP LOGIC
  // -------------------------------------------------------------
  var sidebar = document.getElementById('ccmsSidebar') || document.querySelector('.ccms-sidebar');
  var sidebarBackdrop = document.getElementById('sidebarBackdrop');
  var sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
  var sidebarCloseBtn = document.getElementById('sidebarCloseBtn');

  function openSidebar() {
    if (sidebar) sidebar.classList.add('show');
    if (sidebarBackdrop) sidebarBackdrop.classList.add('show');
    document.body.classList.add('sidebar-open');
  }

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('show');
    if (sidebarBackdrop) sidebarBackdrop.classList.remove('show');
    document.body.classList.remove('sidebar-open');
  }

  if (sidebarToggleBtn) {
    sidebarToggleBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (sidebar && sidebar.classList.contains('show')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });
  }

  if (sidebarCloseBtn) {
    sidebarCloseBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      closeSidebar();
    });
  }

  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', closeSidebar);
  }

  // Close sidebar on link click on mobile screens
  if (sidebar) {
    sidebar.querySelectorAll('.nav-link').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.innerWidth < 992) {
          closeSidebar();
        }
      });
    });
  }

  // Close sidebar on Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && sidebar && sidebar.classList.contains('show')) {
      closeSidebar();
    }
  });

  // -------------------------------------------------------------
  // 1. POPUP TOAST NOTIFICATIONS
  // -------------------------------------------------------------
  window.showToast = function (title, message, type) {
    type = type || 'success';
    var container = document.getElementById('toastContainer');
    if (!container) return;

    var iconClass = 'bi-check-circle-fill text-success';
    var toastClass = 'ccms-toast-success';
    if (type === 'danger' || type === 'error') {
      iconClass = 'bi-x-circle-fill text-danger';
      toastClass = 'ccms-toast-danger';
    } else if (type === 'warning') {
      iconClass = 'bi-exclamation-triangle-fill text-warning';
      toastClass = 'ccms-toast-warning';
    } else if (type === 'info') {
      iconClass = 'bi-info-circle-fill text-info';
      toastClass = 'ccms-toast-info';
    }

    var toastId = 'toast-' + Date.now();
    var html = `
      <div id="${toastId}" class="toast ccms-toast ${toastClass} show fade mb-2" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-transparent border-0 text-white d-flex align-items-center justify-content-between pb-0">
          <div class="d-flex align-items-center gap-2">
            <i class="bi ${iconClass} fs-5"></i>
            <strong class="me-auto text-light fw-bold">${title}</strong>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body small text-slate-300 pt-2 pb-3">
          ${message}
        </div>
      </div>
    `;

    container.insertAdjacentHTML('beforeend', html);
    var elem = document.getElementById(toastId);
    var bsToast = new bootstrap.Toast(elem, { delay: 6000 });
    bsToast.show();

    elem.addEventListener('hidden.bs.toast', function () {
      elem.remove();
    });
  };

  // Check if session flash toast is present
  if (window.SESSION_TOAST && window.SESSION_TOAST.message) {
    var t = window.SESSION_TOAST;
    showToast(t.title || 'Task Completed', t.message, t.type || 'success');
  }

  // -------------------------------------------------------------
  // 2. NAVBAR NOTIFICATION DROPDOWN & LIVE SYSTEM ACTIVITIES
  // -------------------------------------------------------------
  function loadNavbarNotifications() {
    var notifList = document.getElementById('notificationList');
    var notifBadge = document.getElementById('notif-badge');
    if (!notifList) return;

    fetch('/ccms/notifications_api.php?action=fetch')
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (!data.success) return;

        // Update unread badge count
        if (data.unread_count > 0) {
          notifBadge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
          notifBadge.classList.remove('d-none');
        } else {
          notifBadge.classList.add('d-none');
        }

        if (!data.notifications || data.notifications.length === 0) {
          notifList.innerHTML = '<div class="text-center py-4 text-muted small"><i class="bi bi-bell-slash fs-4 d-block mb-1 text-secondary"></i>No recent notifications.</div>';
          return;
        }

        var html = '';
        data.notifications.forEach(function (n) {
          var icon = 'bi-check-circle-fill text-success';
          if (n.type === 'danger') icon = 'bi-x-circle-fill text-danger';
          else if (n.type === 'warning') icon = 'bi-exclamation-triangle-fill text-warning';
          else if (n.type === 'info') icon = 'bi-info-circle-fill text-info';

          var unreadClass = parseInt(n.is_read) === 0 ? 'unread' : '';
          var unreadDot = parseInt(n.is_read) === 0 ? '<span class="notif-badge-dot ms-auto"></span>' : '';
          var link = n.link ? n.link : '#';

          html += `
            <a href="${link}" class="notif-item ${unreadClass}">
              <div class="d-flex align-items-start gap-2">
                <i class="bi ${icon} fs-5 mt-1"></i>
                <div class="flex-grow-1">
                  <div class="d-flex align-items-center justify-content-between">
                    <span class="fw-semibold small text-light">${n.title}</span>
                    ${unreadDot}
                  </div>
                  <div class="small text-secondary text-wrap" style="font-size: 0.8rem; margin-top:2px;">${n.message}</div>
                  <div class="text-muted" style="font-size: 0.7rem; margin-top:4px;"><i class="bi bi-clock me-1"></i>${n.created_at}</div>
                </div>
              </div>
            </a>
          `;
        });
        notifList.innerHTML = html;
      })
      .catch(function (err) {
        console.error('Error fetching notifications:', err);
      });
  }

  loadNavbarNotifications();

  var markAllReadBtn = document.getElementById('markAllReadBtn');
  if (markAllReadBtn) {
    markAllReadBtn.addEventListener('click', function (e) {
      e.preventDefault();
      fetch('/ccms/notifications_api.php?action=mark_read', { method: 'POST' })
        .then(function (res) { return res.json(); })
        .then(function () {
          loadNavbarNotifications();
          showToast('Notifications Updated', 'All notifications marked as read.', 'info');
        });
    });
  }

  // Poll for new notifications every 30s
  setInterval(loadNavbarNotifications, 30000);

  // -------------------------------------------------------------
  // 3. PREVENT NEGATIVE NUMBERS (-) IN BUDGET & NUMERIC INPUTS
  // -------------------------------------------------------------
  function setupNegativePrevention() {
    var selector = 'input[type="number"], input[min], [data-no-negative], input[name*="budget"], input[name*="amount"], input[name*="price"], input[name*="quantity"]';
    
    document.querySelectorAll(selector).forEach(function (input) {
      input.addEventListener('keydown', function (e) {
        if (e.key === '-' || e.key === 'e' || e.key === 'E' || e.key === '+') {
          var minVal = input.getAttribute('min');
          if (minVal === null || parseFloat(minVal) >= 0 || input.name.indexOf('budget') !== -1 || input.name.indexOf('amount') !== -1) {
            e.preventDefault();
          }
        }
      });

      input.addEventListener('input', function () {
        var minVal = input.getAttribute('min');
        if (minVal === null || parseFloat(minVal) >= 0 || input.name.indexOf('budget') !== -1 || input.name.indexOf('amount') !== -1) {
          if (input.value && (input.value.indexOf('-') !== -1 || input.value.indexOf('e') !== -1 || input.value.indexOf('E') !== -1)) {
            input.value = input.value.replace(/[-eE]/g, '');
          }
          if (parseFloat(input.value) < 0) {
            input.value = Math.abs(parseFloat(input.value));
          }
        }
      });
    });
  }

  setupNegativePrevention();

  var addRowBtn = document.getElementById('addRow');
  if (addRowBtn) {
    addRowBtn.addEventListener('click', function () {
      setTimeout(setupNegativePrevention, 100);
    });
  }

  // -------------------------------------------------------------
  // 4. LIVE EMAIL, PHONE & SRI LANKAN NIC VALIDATION HANDLERS
  // -------------------------------------------------------------
  var emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
  var phoneRegex = /^\+?[0-9\s\-\(\)]{9,15}$/;
  var oldNicRegex = /^[0-9]{9}[vVxX]$/;
  var newNicRegex = /^[0-9]{12}$/;

  function setFieldError(field, errorMsg) {
    field.classList.add('is-invalid');
    field.classList.remove('is-valid');
    var parent = field.parentElement;
    var feedback = parent.querySelector('.invalid-feedback');
    if (!feedback) {
      feedback = document.createElement('div');
      feedback.className = 'invalid-feedback';
      parent.appendChild(feedback);
    }
    feedback.textContent = errorMsg;

    var validFeedback = parent.querySelector('.valid-feedback');
    if (validFeedback) validFeedback.remove();
  }

  function setFieldValid(field, successMsg) {
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
    var parent = field.parentElement;
    var invalidFeedback = parent.querySelector('.invalid-feedback');
    if (invalidFeedback) invalidFeedback.remove();

    var feedback = parent.querySelector('.valid-feedback');
    if (!feedback) {
      feedback = document.createElement('div');
      feedback.className = 'valid-feedback text-success small mt-1 font-monospace';
      parent.appendChild(feedback);
    }
    feedback.textContent = successMsg;
  }

  function clearFieldError(field) {
    field.classList.remove('is-invalid');
    field.classList.remove('is-valid');
    var parent = field.parentElement;
    var feedback = parent.querySelector('.invalid-feedback');
    if (feedback) feedback.remove();
    var validFeedback = parent.querySelector('.valid-feedback');
    if (validFeedback) validFeedback.remove();
  }

  function validateEmailInput(field) {
    var val = field.value.trim();
    if (val === '') {
      if (field.hasAttribute('required')) {
        setFieldError(field, 'Email address is required.');
        return false;
      }
      clearFieldError(field);
      return true;
    }
    if (!emailRegex.test(val)) {
      setFieldError(field, 'Please enter a valid email address (e.g. name@example.com).');
      return false;
    }
    setFieldValid(field, '✓ Valid email address');
    return true;
  }

  function validatePhoneInput(field) {
    var val = field.value.trim();
    if (val === '') {
      if (field.hasAttribute('required')) {
        setFieldError(field, 'Phone number is required.');
        return false;
      }
      clearFieldError(field);
      return true;
    }
    var clean = val.replace(/[\s\-\(\)\.]/g, '');
    var lkLocalRegex = /^0[0-9]{9}$/;
    var lkIntlRegex = /^(?:\+94|94)[0-9]{9}$/;
    var intlRegex = /^\+[1-9][0-9]{8,14}$/;

    if (!lkLocalRegex.test(clean) && !lkIntlRegex.test(clean) && !intlRegex.test(clean)) {
      setFieldError(field, 'Please enter a valid phone number (e.g. 0712345678 or +94712345678).');
      return false;
    }
    setFieldValid(field, '✓ Valid phone number');
    return true;
  }

  function validateNicInput(field) {
    var val = field.value.trim().toUpperCase();
    field.value = val;

    if (val === '') {
      if (field.hasAttribute('required')) {
        setFieldError(field, 'NIC number is required.');
        return false;
      }
      clearFieldError(field);
      return true;
    }

    var isOld = oldNicRegex.test(val);
    var isNew = newNicRegex.test(val);

    if (!isOld && !isNew) {
      setFieldError(field, 'Invalid Sri Lankan NIC. Must be 9 digits + V/X (Old) or 12 digits (New). E.g. 951234567V or 199512345678.');
      return false;
    }

    // Parse Sri Lankan NIC Date of Birth & Gender
    var year = '';
    var days = 0;
    if (isOld) {
      year = '19' + val.substring(0, 2);
      days = parseInt(val.substring(2, 5), 10);
    } else {
      year = val.substring(0, 4);
      days = parseInt(val.substring(4, 7), 10);
    }

    var gender = 'Male';
    if (days > 500) {
      gender = 'Female';
      days -= 500;
    }

    if (days < 1 || days > 366) {
      setFieldError(field, 'Invalid Sri Lankan NIC: day of year component out of valid range.');
      return false;
    }

    var formatName = isOld ? 'Old (9 Digits + V/X)' : 'New (12 Digits)';
    setFieldValid(field, `✓ Valid Sri Lankan NIC [${formatName}] - Gender: ${gender}`);

    // Optionally auto-populate gender and date of birth if fields exist and are empty
    var form = field.closest('form');
    if (form) {
      var genderField = form.querySelector('select[name="gender"]');
      if (genderField && (!genderField.value || genderField.value === '')) {
        genderField.value = gender;
        genderField.dispatchEvent(new Event('change'));
      }
      var dobField = form.querySelector('input[name="date_of_birth"]');
      if (dobField && !dobField.value) {
        var startYearDate = new Date(parseInt(year, 10), 0, 1);
        var dobDate = new Date(startYearDate.getTime() + (days - 1) * 86400000);
        var yyyy = dobDate.getFullYear();
        var mm = String(dobDate.getMonth() + 1).padStart(2, '0');
        var dd = String(dobDate.getDate()).padStart(2, '0');
        dobField.value = `${yyyy}-${mm}-${dd}`;
        dobField.dispatchEvent(new Event('change'));
      }
    }

    return true;
  }

  // Bind live blur & input validation on email, phone & NIC inputs
  document.querySelectorAll('input[type="email"], input[name="email"]').forEach(function (field) {
    field.addEventListener('blur', function () { validateEmailInput(field); });
    field.addEventListener('input', function () { if (field.classList.contains('is-invalid')) validateEmailInput(field); });
  });

  document.querySelectorAll('input[type="tel"], input[name="phone"]').forEach(function (field) {
    field.addEventListener('blur', function () { validatePhoneInput(field); });
    field.addEventListener('input', function () { if (field.classList.contains('is-invalid')) validatePhoneInput(field); });
  });

  document.querySelectorAll('input[name="nic_number"]').forEach(function (field) {
    field.addEventListener('blur', function () { validateNicInput(field); });
    field.addEventListener('input', function () { validateNicInput(field); });
  });

  // Validate on form submit
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var isValid = true;
      form.querySelectorAll('input[type="email"], input[name="email"]').forEach(function (field) {
        if (!validateEmailInput(field)) isValid = false;
      });
      form.querySelectorAll('input[type="tel"], input[name="phone"]').forEach(function (field) {
        if (!validatePhoneInput(field)) isValid = false;
      });
      form.querySelectorAll('input[name="nic_number"]').forEach(function (field) {
        if (!validateNicInput(field)) isValid = false;
      });

      if (!isValid) {
        e.preventDefault();
        var firstInvalid = form.querySelector('.is-invalid');
        if (firstInvalid) firstInvalid.focus();
      }
    });
  });

  // -------------------------------------------------------------
  // 5. INSTANT LIVE TABLE SEARCH FILTERING
  // -------------------------------------------------------------
  window.filterTable = function (query, tableSelector) {
    query = (query || '').toLowerCase().trim();
    tableSelector = tableSelector || 'table';
    var table = document.querySelector(tableSelector);
    if (!table) return;

    var tbody = table.querySelector('tbody');
    if (!tbody) return;

    var rows = tbody.querySelectorAll('tr');
    var visibleCount = 0;

    rows.forEach(function (row) {
      if (row.classList.contains('no-results-row')) return;

      var text = row.textContent.toLowerCase();
      if (text.indexOf(query) !== -1) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });

    var noResultsRow = tbody.querySelector('.no-results-row');
    if (visibleCount === 0 && rows.length > 0) {
      if (!noResultsRow) {
        var colCount = table.querySelectorAll('thead th').length || 6;
        noResultsRow = document.createElement('tr');
        noResultsRow.className = 'no-results-row';
        noResultsRow.innerHTML = `<td colspan="${colCount}" class="text-center text-muted py-4"><i class="bi bi-search me-1"></i> No matching records found.</td>`;
        tbody.appendChild(noResultsRow);
      } else {
        noResultsRow.style.display = '';
      }
    } else if (noResultsRow) {
      noResultsRow.style.display = 'none';
    }
  };

});
