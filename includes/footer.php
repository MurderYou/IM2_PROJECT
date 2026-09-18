<?php
/**
 * includes/footer.php
 * Shared page footer — renders the toast container, loads the
 * Bootstrap JS bundle, and initialises shared behaviours:
 *   1. Dark / light theme toggle (persisted to localStorage)
 *   2. Mobile sidebar drawer (hamburger toggle)
 *   3. Toast notification system (populated from flash messages)
 *
 * All page-specific <script> blocks should appear BEFORE this include.
 */
?>

<!-- Mobile sidebar toggle (hamburger — visible only on mobile) -->
<button type="button" id="mobileMenuToggle" class="mobile-menu-toggle" aria-label="Toggle menu">
  <i class="bi bi-list"></i>
</button>

<!-- Toast container (populated by showToast() and flash-toasts below) -->
<div id="toastContainer" class="toast-container"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
  'use strict';

  /* ==============================
   * 1. DARK / LIGHT THEME
   * ============================== */
  var THEME_KEY = 'sems-theme';
  var body = document.body;

  function applyTheme(theme) {
    if (theme === 'dark') {
      body.classList.add('dark');
    } else {
      body.classList.remove('dark');
    }
    localStorage.setItem(THEME_KEY, theme);
  }

  // Initialise from stored preference, or system preference as fallback
  var stored = localStorage.getItem(THEME_KEY);
  if (stored) {
    applyTheme(stored);
  } else {
    applyTheme(
      window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
    );
  }

  // Toggle button (expect an element with id="themeToggle")
  var toggleBtn = document.getElementById('themeToggle');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      var isDark = body.classList.contains('dark');
      applyTheme(isDark ? 'light' : 'dark');
    });
  }

  /* ==============================
   * 2. MOBILE SIDEBAR DRAWER
   * ============================== */
  var sidebar = document.querySelector('.sidebar');
  var mobileToggle = document.getElementById('mobileMenuToggle');

    if (sidebar && mobileToggle) {
    mobileToggle.addEventListener('click', function () {
      body.classList.toggle('sidebar-open');
      // Swap icon: list (menu) when closed, x when open
      var icon = mobileToggle.querySelector('i');
      if (body.classList.contains('sidebar-open')) {
        icon.className = 'bi bi-x-lg';
      } else {
        icon.className = 'bi bi-list';
      }
    });
  }

  // Close sidebar when clicking the overlay or navigating (mobile)
  document.addEventListener('click', function (e) {
    if (!sidebar) return;
    var inSidebar = sidebar.contains(e.target);
    var inToggle = mobileToggle && mobileToggle.contains(e.target);
    if (!inSidebar && !inToggle && body.classList.contains('sidebar-open')) {
      body.classList.remove('sidebar-open');
    }
  });

  /* ==============================
   * 3. TOAST SYSTEM
   * ============================== */
  window.showToast = function (message, type, duration) {
    type = type || 'info';
    duration = duration || 4000;

    var container = document.getElementById('toastContainer');
    if (!container) return;

    var toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.setAttribute('role', 'alert');
    toast.innerHTML =
      '<button type="button" class="toast-close" aria-label="Close">&times;</button>'
      + '<span class="toast-message">' + message + '</span>';
    container.appendChild(toast);

    // Slide in
    requestAnimationFrame(function () {
      toast.classList.add('show');
    });

    // Auto-dismiss
    var timer = setTimeout(function () {
      toast.classList.remove('show');
      setTimeout(function () {
        if (toast.parentNode) toast.remove();
      }, 300);
    }, duration);

    // Manual close
    var closeEl = toast.querySelector('.toast-close');
    if (closeEl) {
      closeEl.addEventListener('click', function () {
        clearTimeout(timer);
        toast.classList.remove('show');
        setTimeout(function () {
          if (toast.parentNode) toast.remove();
        }, 300);
      });
    }
  };

  /* ==============================
   * 4. FLASH → TOAST (auto-convert)
   * ============================== */
  var flashEl = document.getElementById('flashMessages');
  if (flashEl) {
    var messages = JSON.parse(flashEl.getAttribute('data-messages') || '[]');
    messages.forEach(function (m) {
      showToast(m.message, m.type, m.duration || 5000);
    });
    // Clean up the invisible bridge element
        flashEl.parentNode.removeChild(flashEl);
  }

  /* ==============================
   * 5. TABLE SORTING (vanilla JS)
   * ============================== */
  function sortTable(table, colIndex, ascending) {
    var tbody = table.querySelector('tbody');
    var rows = Array.from(tbody.querySelectorAll('tr'));
    var type = table.dataset.sortType || 'mixed';

    rows.sort(function (a, b) {
      var aText = a.cells[colIndex].textContent.trim();
      var bText = b.cells[colIndex].textContent.trim();

      var aNum, bNum;
      if (type === 'number' || (/[\d.,]/.test(aText) && /[\d.,]/.test(bText))) {
        aNum = parseFloat(aText.replace(/[^\d.-]/g, '')) || 0;
        bNum = parseFloat(bText.replace(/[^\d.-]/g, '')) || 0;
        return ascending ? aNum - bNum : bNum - aNum;
      }
      return ascending
        ? aText.localeCompare(bText)
        : bText.localeCompare(aText);
    });

    rows.forEach(function (row) {
      tbody.appendChild(row);
    });
  }

    // Attach click handlers to sortable headers
  document.querySelectorAll('th.sortable').forEach(function (th) {
    th.addEventListener('click', function () {
      var table = th.closest('table');
      var colIndex = Array.prototype.indexOf.call(th.parentNode.children, th);
      var ascending = !th.classList.contains('sort-asc');

      // Reset all other sortable headers in this table
      table.querySelectorAll('th.sortable').forEach(function (other) {
        other.classList.remove('sort-asc', 'sort-desc');
      });

      th.classList.add(ascending ? 'sort-asc' : 'sort-desc');
      sortTable(table, colIndex, ascending);
    });
  });

  /* ==============================
   * 6. CSV EXPORT (vanilla JS)
   * ============================== */
  window.exportTableToCSV = function (tableId, filename) {
    var table = document.getElementById(tableId);
    if (!table) return;

    var rows = table.querySelectorAll('tr');
    var csv = [];
    rows.forEach(function (row) {
      var cells = row.querySelectorAll('th, td');
      var values = [];
      cells.forEach(function (cell) {
        values.push('"' + cell.textContent.trim().replace(/"/g, '""') + '"');
      });
      csv.push(values.join(','));
    });

    var csvContent = csv.join('\n');
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = (filename || 'export') + '.csv';
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };
})();
</script>