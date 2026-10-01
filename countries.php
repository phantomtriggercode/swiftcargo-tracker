<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/settings.php';

$countries = get_countries_list();

$activeNav = 'countries';
$pageTitle = 'Countries We Ship To';
include __DIR__ . '/includes/header.php';
?>

<?= page_banner(
    site_copy_tpl('countries_title', [
        'classic' => 'Countries We Ship To', 'modern' => 'Where we deliver', 'minimal' => 'Destinations',
        'bold' => 'Anywhere. Everywhere.', 'corporate' => 'Our Global Network', 'dark-header' => 'Network coverage',
    ]),
    site_copy('countries_intro', (string) tpl([
        'classic' => '{site} ships to every country in the world. Wherever your shipment is headed, we can get it there.',
        'modern' => 'Search for a country to check we deliver there. Spoiler: we almost certainly do.',
        'minimal' => 'A short list would be easier to read. Ours is every country in the world.',
        'bold' => 'If it has an address, we can get there. Search below.',
        'corporate' => 'Door-to-door service to every country and territory, through our partner network.',
        'dark-header' => 'Every country is on the network. Search to confirm yours.',
    ])),
    ['key' => 'countries', 'photo' => 'network', 'crumb' => (string) tpl(['corporate' => 'Global Network', 'minimal' => 'Destinations', 'modern' => 'Coverage', 'dark-header' => 'Network', 'classic' => 'Countries']), 'number' => '04',
     'kicker' => (string) tpl(['modern' => 'Coverage', 'bold' => 'Coverage', 'corporate' => 'Worldwide', 'classic' => 'Coverage'])]
) ?>

<section class="section">
  <div class="container">
    <div class="form-group" style="max-width:420px;margin:0 auto 32px;">
      <input type="text" id="country-search" placeholder="Search a country..." autocomplete="off">
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
