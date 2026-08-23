(function () {
  var form = document.getElementById('request-form');
  if (!form) return;

  var panels = Array.prototype.slice.call(document.querySelectorAll('.wizard-panel'));
  var nodes = Array.prototype.slice.call(document.querySelectorAll('.wizard-step-node'));
  var totalSteps = panels.length;
  var current = 1;

  function showStep(n) {
    current = n;
    panels.forEach(function (panel) {
      panel.hidden = parseInt(panel.dataset.step, 10) !== n;
    });
    nodes.forEach(function (node) {
      var step = parseInt(node.dataset.step, 10);
      node.classList.toggle('active', step === n);
      node.classList.toggle('done', step < n);
    });
    if (n === totalSteps) {
      populateReview();
    }
    var wizardTop = document.getElementById('wizard-steps');
    if (wizardTop) wizardTop.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function currentPanelValid(panel) {
    var fields = panel.querySelectorAll('input, select, textarea');
    for (var i = 0; i < fields.length; i++) {
      var field = fields[i];
      if (field.offsetParent === null) continue; // skip hidden fields (e.g. land method when not Land)
      if (!field.checkValidity()) {
        field.reportValidity();
        return false;
      }
    }
    return true;
  }

  document.querySelectorAll('.wizard-next').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = btn.closest('.wizard-panel');
      if (!currentPanelValid(panel)) return;
      var next = parseInt(panel.dataset.step, 10) + 1;
      if (next <= totalSteps) showStep(next);
    });
  });

  document.querySelectorAll('.wizard-back').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = btn.closest('.wizard-panel');
      var prev = parseInt(panel.dataset.step, 10) - 1;
      if (prev >= 1) showStep(prev);
    });
  });

  nodes.forEach(function (node) {
    node.addEventListener('click', function () {
      if (node.classList.contains('done') || node.classList.contains('active')) {
        showStep(parseInt(node.dataset.step, 10));
      }
    });
  });

  function fieldValue(id) {
    var el = document.getElementById(id);
    if (!el) return '';
    if (el.tagName === 'SELECT') return el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : '';
    return el.value;
  }

  function row(label, value) {
    if (!value) return '';
    var div = document.createElement('div');
    div.innerHTML = '<div class="meta-label">' + label + '</div><div class="meta-value"></div>';
    div.querySelector('.meta-value').textContent = value;
    return div.outerHTML.replace('<div>', '<div class="meta-box">').replace(/^<div>/, '<div class="meta-box">');
  }

  function populateReview() {
    var target = document.getElementById('review-summary');
    if (!target) return;

    var shippingMethod = fieldValue('shipping_method');
    var landGroup = document.getElementById('land-method-group');
    var landLine = (shippingMethod === 'Land' && landGroup && landGroup.style.display !== 'none')
      ? shippingMethod + ' — ' + fieldValue('land_method')
      : shippingMethod;

    var insuredBox = document.getElementById('insured');
    var insuranceLine = insuredBox && insuredBox.checked
      ? 'Insured ($' + (parseFloat(fieldValue('insurance_value')) || 0).toFixed(2) + ' declared value)'
      : 'Not insured';

    var parts = [
      row('Name', fieldValue('full_name')),
      row('Email', fieldValue('email')),
      row('Phone', fieldValue('phone')),
      row('Pickup Location', fieldValue('ship_from')),
      row('Delivery Destination', fieldValue('ship_to')),
      row('Preferred Date', fieldValue('preferred_date')),
      row('Preferred Time', fieldValue('preferred_time')),
      row('Pickup Method', fieldValue('pickup_method')),
      row('Package', fieldValue('package_description')),
      row('Weight', fieldValue('weight_kg') + ' kg'),
      row('Dimensions', fieldValue('dimensions')),
      row('Packaging Type', fieldValue('packaging_type')),
      row('Shipping Method', landLine),
      row('Service Type', fieldValue('service_type')),
      row('Insurance', insuranceLine),
    ];

    target.innerHTML = parts.join('');
  }

  showStep(1);
})();
