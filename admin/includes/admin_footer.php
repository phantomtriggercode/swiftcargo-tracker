  </main>
</div>

<div class="row-actions-menu" id="row-actions-portal" hidden></div>
<script>
  (function () {
    var portal = document.getElementById('row-actions-portal');
    if (!portal) return;
    var openBtn = null;

    function closeMenu() {
      portal.hidden = true;
      portal.innerHTML = '';
      if (openBtn) {
        openBtn.setAttribute('aria-expanded', 'false');
        openBtn = null;
      }
    }

    function openMenu(btn) {
      var template = btn.parentElement.querySelector('.row-actions-source');
      if (!template) return;

      portal.innerHTML = '';
      portal.appendChild(template.content.cloneNode(true));
      portal.hidden = false;
      openBtn = btn;
      btn.setAttribute('aria-expanded', 'true');

      var rect = btn.getBoundingClientRect();
      var menuWidth = portal.offsetWidth || 170;
      var left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8);
      left = Math.max(8, left);
      var top = rect.bottom + 6;
      var menuHeight = portal.offsetHeight || 260;
      if (top + menuHeight > window.innerHeight && rect.top - menuHeight - 6 > 0) {
        top = rect.top - menuHeight - 6;
      }
      portal.style.left = left + 'px';
      portal.style.top = top + 'px';
    }

    document.addEventListener('click', function (e) {
      var btn = e.target.closest('.row-actions-btn');
      if (btn) {
        var wasOpenForThisBtn = openBtn === btn && !portal.hidden;
        closeMenu();
        if (!wasOpenForThisBtn) openMenu(btn);
        return;
      }
      if (!e.target.closest('#row-actions-portal')) {
        closeMenu();
      }
    });

    window.addEventListener('scroll', closeMenu, true);
    window.addEventListener('resize', closeMenu);
  })();
</script>
</body>
</html>
