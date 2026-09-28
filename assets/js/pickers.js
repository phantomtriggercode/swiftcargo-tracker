/**
 * Opens the browser's own date and time picker as soon as the field is
 * clicked, rather than only when the small icon at its edge is hit.
 *
 * Left alone, a date or time input puts the caret in whichever segment was
 * clicked and waits for typing, which is fiddly and easy to get wrong.
 * Calling showPicker() turns the whole field into one target: click it and
 * the calendar, or the hour and minute list, appears.
 *
 * Progressive enhancement throughout. showPicker() is only called where the
 * browser has it, and where it does not the field carries on behaving
 * exactly as it did before, so nothing is lost on an older browser. The
 * call is wrapped because it throws if the browser decides it was not
 * triggered by a real user gesture.
 */
(function () {
  'use strict';

  var SELECTOR = 'input[type="date"], input[type="time"], input[type="datetime-local"], input[type="month"], input[type="week"]';

  function openPicker(input) {
    if (input.disabled || input.readOnly || typeof input.showPicker !== 'function') {
      return;
    }
    try {
      input.showPicker();
    } catch (e) {
      // Some browsers refuse outside a trusted gesture, or on a field that
      // is not visible yet. Typing still works, so there is nothing to do.
    }
  }

  // Delegated, so fields added to the page later are covered too.
  document.addEventListener('click', function (event) {
    var input = event.target.closest ? event.target.closest(SELECTOR) : null;
    if (input) {
      openPicker(input);
    }
  });

  // Opening on keyboard focus as well would trap someone tabbing through the
  // form, so this only reacts to a real click. A keyboard user can still open
  // the picker the standard way, with the down arrow or space.
  document.addEventListener('focus', function (event) {
    var input = event.target.closest ? event.target.closest(SELECTOR) : null;
    if (input && input.dataset.pickerAutoOpen === 'true') {
      openPicker(input);
    }
  }, true);
})();
