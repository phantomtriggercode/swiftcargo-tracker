(function () {
  var rates = window.SHIPPING_RATES;
  var form = document.getElementById('request-form');
  if (!rates || !form) return;

  var weightInput = document.getElementById('weight_kg');
  var shippingMethodSelect = document.getElementById('shipping_method');
  var landMethodGroup = document.getElementById('land-method-group');
  var serviceTypeSelect = document.getElementById('service_type');
  var insuredCheckbox = document.getElementById('insured');
  var insuranceGroup = document.getElementById('insurance-value-group');
  var insuranceValueInput = document.getElementById('insurance_value');
  var amountEl = document.getElementById('calc-amount');

  function toggleLandMethod() {
    var isLand = shippingMethodSelect.value === 'Land';
    landMethodGroup.style.display = isLand ? '' : 'none';
    var landMethodSelect = document.getElementById('land_method');
    if (landMethodSelect) landMethodSelect.required = isLand;
  }

  function toggleInsurance() {
    insuranceGroup.style.display = insuredCheckbox.checked ? '' : 'none';
    if (insuranceValueInput) insuranceValueInput.required = insuredCheckbox.checked;
  }

  function methodMultiplier(method) {
    if (method === 'Air') return parseFloat(rates.air) || 1;
    if (method === 'Sea') return parseFloat(rates.sea) || 1;
    if (method === 'Land') return parseFloat(rates.land) || 1;
    return 1;
  }

  function recalculate() {
    var weight = parseFloat(weightInput.value) || 0;
    var method = shippingMethodSelect.value;
    var service = serviceTypeSelect.value;
    var baseFee = parseFloat(rates.base_fee) || 0;
    var perKg = parseFloat(rates.price_per_kg) || 0;

    var serviceMultiplier = service === 'Express' ? (parseFloat(rates.express) || 1) : 1;
    var estimate = (baseFee + perKg * weight) * methodMultiplier(method) * serviceMultiplier;

    if (insuredCheckbox.checked) {
      var declaredValue = parseFloat(insuranceValueInput.value) || 0;
      estimate += declaredValue * ((parseFloat(rates.insurance_percent) || 0) / 100);
    }

    amountEl.textContent = '$' + estimate.toFixed(2);
  }

  [weightInput, shippingMethodSelect, serviceTypeSelect, insuranceValueInput].forEach(function (el) {
    el.addEventListener('input', recalculate);
    el.addEventListener('change', recalculate);
  });
  insuredCheckbox.addEventListener('change', function () {
    toggleInsurance();
    recalculate();
  });
  shippingMethodSelect.addEventListener('change', function () {
    toggleLandMethod();
    recalculate();
  });

  toggleLandMethod();
  toggleInsurance();
  recalculate();
})();
