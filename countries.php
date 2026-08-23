<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$countries = get_countries_list();

$activeNav = 'countries';
$pageTitle = 'Countries We Ship To';
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding-bottom:60px;">
  <div class="container">
    <h1 style="font-size:34px;"><?= h(get_setting('countries_title', 'Countries We Ship To')) ?></h1>
    <p class="lead"><?= h(get_setting('countries_intro')) ?></p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="form-group" style="max-width:420px;margin:0 auto 32px;">
      <input type="text" id="country-search" placeholder="Search a country…" autocomplete="off">
    </div>
    <div class="countries-grid" id="countries-grid">
      <?php foreach ($countries as $country): ?>
        <div class="country-chip"><?= h($country) ?></div>
      <?php endforeach; ?>
    </div>
    <p id="no-results" style="display:none;text-align:center;color:var(--muted);margin-top:24px;">No countries match your search.</p>
  </div>
</section>

<script>
  (function () {
    var input = document.getElementById('country-search');
    var chips = document.querySelectorAll('.country-chip');
    var noResults = document.getElementById('no-results');
    input.addEventListener('input', function () {
      var q = input.value.trim().toLowerCase();
      var visible = 0;
      chips.forEach(function (chip) {
        var match = chip.textContent.toLowerCase().indexOf(q) !== -1;
        chip.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      noResults.style.display = visible === 0 ? 'block' : 'none';
    });
  })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
