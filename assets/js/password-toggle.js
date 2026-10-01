/**
 * Show / hide button for every password box.
 *
 * Adds a small eye button inside each password field. Pressing it shows
 * what has been typed so far, pressing it again hides it. The choice is
 * the typist's alone, field by field, and it is never remembered: every
 * page opens with passwords hidden.
 *
 * Before a form is sent the field is always switched back to a password
 * field, so a password manager still recognises it and offers to save it,
 * and the browser never stores it as ordinary text.
 */
(function () {
  var EYE =
    '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' +
    '<path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ' +
    'd="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/>' +
    '<circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
  var EYE_OFF =
    '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">' +
    '<path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" ' +
    'd="M3 3l18 18M10.6 5.1A10.4 10.4 0 0 1 12 5c6.4 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.1M6.6 6.6C3.9 8.4 2 12 2 12s3.6 7 10 7a9.7 9.7 0 0 0 5.4-1.6M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';

  function setVisible(input, button, visible) {
    // Changing the type moves the caret to the start in some browsers, so
    // its position is put back afterwards and typing carries on in place.
    var start = null, end = null;
    try { start = input.selectionStart; end = input.selectionEnd; } catch (e) { /* not supported */ }

    input.type = visible ? 'text' : 'password';
    button.innerHTML = visible ? EYE_OFF : EYE;
    button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    button.title = visible ? 'Hide password' : 'Show password';

    if (start !== null && document.activeElement === input) {
      try { input.setSelectionRange(start, end); } catch (e) { /* not supported */ }
    }
  }

  function enhance(input) {
    if (input.getAttribute('data-pw-toggle') === 'on') return;
    input.setAttribute('data-pw-toggle', 'on');

    var wrap = document.createElement('span');
    wrap.className = 'pw-field';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'pw-toggle';
    if (input.id) button.setAttribute('aria-controls', input.id);
    wrap.appendChild(button);
    setVisible(input, button, false);

    // mousedown would otherwise take focus away from the field, which
    // closes the keyboard on a phone half way through typing.
    button.addEventListener('mousedown', function (e) { e.preventDefault(); });
    button.addEventListener('click', function () {
      setVisible(input, button, input.type === 'password');
      input.focus();
    });

    if (input.form) {
      input.form.addEventListener('submit', function () {
        setVisible(input, button, false);
      });
    }
  }

  function init() {
    var fields = document.querySelectorAll('input[type="password"]');
    for (var i = 0; i < fields.length; i++) enhance(fields[i]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
