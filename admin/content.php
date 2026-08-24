<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/settings.php';
require_admin();

$sections = [
    'home' => [
        'home_hero_title', 'home_hero_lead', 'stat_countries', 'stat_ontime', 'stat_support', 'stat_delivered',
        'home_track_title', 'home_track_lead',
        'home_features_eyebrow', 'home_features_title', 'home_features_lead',
        'home_feature1_title', 'home_feature1_desc',
        'home_feature2_title', 'home_feature2_desc',
        'home_feature3_title', 'home_feature3_desc',
        'home_feature4_title', 'home_feature4_desc',
        'home_feature5_title', 'home_feature5_desc',
        'home_feature6_title', 'home_feature6_desc',
        'home_operate_eyebrow', 'home_operate_title', 'home_operate_lead',
        'home_row1_title', 'home_row1_desc',
        'home_row2_title', 'home_row2_desc',
        'home_row3_title', 'home_row3_desc',
        'home_row4_title', 'home_row4_desc',
        'home_steps_eyebrow', 'home_steps_title',
        'home_step1_title', 'home_step1_desc',
        'home_step2_title', 'home_step2_desc',
        'home_step3_title', 'home_step3_desc',
    ],
    'about' => ['about_title', 'about_lead', 'about_body'],
    'services' => [
        'services_title', 'services_lead',
        'services_card1_title', 'services_card1_desc',
        'services_card2_title', 'services_card2_desc',
        'services_card3_title', 'services_card3_desc',
        'services_include_eyebrow', 'services_include_title',
        'services_include1_title', 'services_include1_desc',
        'services_include2_title', 'services_include2_desc',
        'services_include3_title', 'services_include3_desc',
        'services_include4_title', 'services_include4_desc',
    ],
    'request' => ['request_title', 'request_lead'],
    'contact' => ['contact_intro', 'contact_phone', 'contact_email', 'contact_address'],
    'footer' => ['footer_tagline', 'contact_email', 'contact_phone', 'footer_rights_text', 'footer_bottom_note'],
    'countries' => ['countries_title', 'countries_intro', 'countries_list'],
    'privacy' => ['privacy_title', 'privacy_lead', 'privacy_body'],
    'terms' => ['terms_title', 'terms_lead', 'terms_body'],
];

$tabLabels = [
    'home' => 'Home', 'about' => 'About', 'services' => 'Services', 'request' => 'Ship Now Page',
    'contact' => 'Contact', 'footer' => 'Footer', 'countries' => 'Countries',
    'privacy' => 'Privacy Policy', 'terms' => 'Terms of Service',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    if (isset($sections[$section])) {
        foreach ($sections[$section] as $key) {
            if (isset($_POST[$key])) {
                set_setting($key, trim((string) $_POST[$key]));
            }
        }
        flash_set('success', ucfirst($section) . ' content updated.');
    }
    redirect('/admin/content.php?tab=' . urlencode($section ?: 'home'));
}

$activeTab = $_GET['tab'] ?? 'home';
if (!isset($sections[$activeTab])) {
    $activeTab = 'home';
}

$activeAdminNav = 'content';
$pageTitle = 'Site Content';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-topbar">
  <h1>Site Content</h1>
</div>

<?php if ($msg = flash_get('success')): ?>
  <div class="alert alert-success"><?= h($msg) ?></div>
<?php endif; ?>

<div class="content-tabs">
  <?php foreach (array_keys($sections) as $tab): ?>
    <a href="/admin/content.php?tab=<?= $tab ?>" class="content-tab <?= $activeTab === $tab ? 'active' : '' ?>"><?= h($tabLabels[$tab] ?? ucfirst($tab)) ?></a>
  <?php endforeach; ?>
</div>

<div class="form-card" style="max-width:720px;">

  <?php if ($activeTab === 'home'): ?>
    <form method="post">
    <?= csrf_field() ?>
      <input type="hidden" name="section" value="home">
      <div class="form-group">
        <label>Hero Title</label>
        <input type="text" name="home_hero_title" value="<?= h(get_setting('home_hero_title')) ?>">
      </div>
      <div class="form-group">
        <label>Hero Subtext</label>
        <textarea name="home_hero_lead" rows="3"><?= h(get_setting('home_hero_lead')) ?></textarea>
      </div>
      <h3>Stats Strip</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Countries Served</label>
          <input type="text" name="stat_countries" value="<?= h(get_setting('stat_countries')) ?>">
        </div>
        <div class="form-group">
          <label>On-Time Rate</label>
          <input type="text" name="stat_ontime" value="<?= h(get_setting('stat_ontime')) ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Support Availability</label>
          <input type="text" name="stat_support" value="<?= h(get_setting('stat_support')) ?>">
        </div>
        <div class="form-group">
          <label>Packages Delivered</label>
          <input type="text" name="stat_delivered" value="<?= h(get_setting('stat_delivered')) ?>">
        </div>
      </div>

      <h3>Track Box</h3>
      <div class="form-group">
        <label>Heading</label>
        <input type="text" name="home_track_title" value="<?= h(get_setting('home_track_title', 'Track your shipment')) ?>">
      </div>
      <div class="form-group">
        <label>Subtext</label>
        <input type="text" name="home_track_lead" value="<?= h(get_setting('home_track_lead', 'Enter your tracking number to see live location and delivery status.')) ?>">
      </div>

      <h3>"Why Us" Section</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Eyebrow</label>
          <input type="text" name="home_features_eyebrow" value="<?= h(get_setting('home_features_eyebrow', 'Why ' . get_site_name())) ?>">
        </div>
        <div class="form-group">
          <label>Heading</label>
          <input type="text" name="home_features_title" value="<?= h(get_setting('home_features_title', 'Built for peace of mind')) ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Lead Paragraph</label>
        <input type="text" name="home_features_lead" value="<?= h(get_setting('home_features_lead', 'Every shipment is monitored end-to-end, with automatic alerts so your receiver is never left guessing.')) ?>">
      </div>
      <?php
      $featureDefaults = [
          1 => ['Live Map Tracking', 'Watch your package move across an interactive world map in real time, from pickup to doorstep.'],
          2 => ['Automatic Email Alerts', 'The receiver gets an email the instant a shipment status changes — picked up, in transit, out for delivery, delivered.'],
          3 => ['Real-Time Status Timeline', 'A full, timestamped history of every checkpoint your package has passed through.'],
          4 => ['Regular & Express Options', 'Choose the service level that matches your urgency, and ship by air, sea, or land.'],
          5 => ['Worldwide Coverage', 'From coast to coast across the U.S. and worldwide, we move freight and parcels reliably to every country we serve.'],
          6 => ['Secure Handling', 'Every parcel is logged, verified and handled by trained staff at each checkpoint.'],
      ];
      foreach ($featureDefaults as $i => [$defTitle, $defDesc]):
      ?>
        <div class="form-row">
          <div class="form-group">
            <label>Card <?= $i ?> Title</label>
            <input type="text" name="home_feature<?= $i ?>_title" value="<?= h(get_setting("home_feature{$i}_title", $defTitle)) ?>">
          </div>
          <div class="form-group">
            <label>Card <?= $i ?> Description</label>
            <input type="text" name="home_feature<?= $i ?>_desc" value="<?= h(get_setting("home_feature{$i}_desc", $defDesc)) ?>">
          </div>
        </div>
      <?php endforeach; ?>

      <h3>"How We Operate" Section</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Eyebrow</label>
          <input type="text" name="home_operate_eyebrow" value="<?= h(get_setting('home_operate_eyebrow', 'How We Operate')) ?>">
        </div>
        <div class="form-group">
          <label>Heading</label>
          <input type="text" name="home_operate_title" value="<?= h(get_setting('home_operate_title', 'Real people, real fleet, real care')) ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Lead Paragraph</label>
        <input type="text" name="home_operate_lead" value="<?= h(get_setting('home_operate_lead', 'From the warehouse floor to your front door, every shipment is handled by trained staff and tracked the whole way.')) ?>">
      </div>
      <?php
      $rowDefaults = [
          1 => ['Careful handling at every hub', 'Every parcel and pallet is scanned, verified, and handled by trained staff the moment it arrives at one of our facilities — logged instantly so your tracking page updates in real time.'],
          2 => ['A fleet built for reliability', 'Ground transport by van, trailer, or rail, and air and sea freight for long-haul and international shipments — routed for speed without cutting corners.'],
          3 => ['Fast, careful unloading', 'At every stop, our team unloads and sorts shipments quickly and carefully, keeping your delivery window tight and your package intact.'],
          4 => ['Right to your door', "The last mile matters most. Our couriers deliver directly to your doorstep, and your receiver gets an email the moment it's dropped off."],
      ];
      foreach ($rowDefaults as $i => [$defTitle, $defDesc]):
      ?>
        <div class="form-group">
          <label>Row <?= $i ?> Title</label>
          <input type="text" name="home_row<?= $i ?>_title" value="<?= h(get_setting("home_row{$i}_title", $defTitle)) ?>">
        </div>
        <div class="form-group">
          <label>Row <?= $i ?> Description</label>
          <textarea name="home_row<?= $i ?>_desc" rows="2"><?= h(get_setting("home_row{$i}_desc", $defDesc)) ?></textarea>
        </div>
      <?php endforeach; ?>

      <h3>"How It Works" Section</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Eyebrow</label>
          <input type="text" name="home_steps_eyebrow" value="<?= h(get_setting('home_steps_eyebrow', 'How it works')) ?>">
        </div>
        <div class="form-group">
          <label>Heading</label>
          <input type="text" name="home_steps_title" value="<?= h(get_setting('home_steps_title', 'Three simple steps')) ?>">
        </div>
      </div>
      <?php
      $stepDefaults = [
          1 => ['Book a shipment', 'Our team creates your shipment and issues a unique tracking number.'],
          2 => ['We move it', 'Your package travels through our network of hubs — each checkpoint logged live.'],
          3 => ['You & the receiver stay informed', 'Every update triggers an instant email, and anyone can watch progress on the live map.'],
      ];
      foreach ($stepDefaults as $i => [$defTitle, $defDesc]):
      ?>
        <div class="form-row">
          <div class="form-group">
            <label>Step <?= $i ?> Title</label>
            <input type="text" name="home_step<?= $i ?>_title" value="<?= h(get_setting("home_step{$i}_title", $defTitle)) ?>">
          </div>
          <div class="form-group">
            <label>Step <?= $i ?> Description</label>
            <input type="text" name="home_step<?= $i ?>_desc" value="<?= h(get_setting("home_step{$i}_desc", $defDesc)) ?>">
          </div>
        </div>
      <?php endforeach; ?>

      <button type="submit" class="btn btn-primary">Save Home Content</button>
    </form>

  <?php elseif ($activeTab === 'about'): ?>
    <form method="post">
    <?= csrf_field() ?>
      <input type="hidden" name="section" value="about">
      <div class="form-group">
        <label>Page Title</label>
        <input type="text" name="about_title" value="<?= h(get_setting('about_title')) ?>">
      </div>
      <div class="form-group">
        <label>Lead Paragraph</label>
        <textarea name="about_lead" rows="2"><?= h(get_setting('about_lead')) ?></textarea>
      </div>
      <div class="form-group">
        <label>Body (separate paragraphs with a blank line)</label>
        <textarea name="about_body" rows="10"><?= h(get_setting('about_body')) ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Save About Content</button>
    </form>

  <?php elseif ($activeTab === 'services'): ?>
    <form method="post">
    <?= csrf_field() ?>
      <input type="hidden" name="section" value="services">
      <div class="form-group">
        <label>Page Title</label>
        <input type="text" name="services_title" value="<?= h(get_setting('services_title', 'Our Services')) ?>">
      </div>
      <div class="form-group">
        <label>Lead Paragraph</label>
        <textarea name="services_lead" rows="2"><?= h(get_setting('services_lead', 'Flexible shipping options for every kind of package, budget and deadline.')) ?></textarea>
      </div>

      <h3>Service Tiers</h3>
      <?php
      $tierDefaults = [
          1 => ['Priority', 'Our fastest service for time-critical shipments, with premium handling and priority routing at every hub.'],
          2 => ['Express', 'Reliable, fast international delivery — ideal for business documents and time-sensitive parcels.'],
          3 => ['Standard', 'Cost-effective shipping for everyday parcels, with the same live tracking and email alerts.'],
      ];
      foreach ($tierDefaults as $i => [$defTitle, $defDesc]):
      ?>
        <div class="form-row">
          <div class="form-group">
            <label>Tier <?= $i ?> Title</label>
            <input type="text" name="services_card<?= $i ?>_title" value="<?= h(get_setting("services_card{$i}_title", $defTitle)) ?>">
          </div>
          <div class="form-group">
            <label>Tier <?= $i ?> Description</label>
            <input type="text" name="services_card<?= $i ?>_desc" value="<?= h(get_setting("services_card{$i}_desc", $defDesc)) ?>">
          </div>
        </div>
      <?php endforeach; ?>

      <h3>"Every Plan Includes" Section</h3>
      <div class="form-row">
        <div class="form-group">
          <label>Eyebrow</label>
          <input type="text" name="services_include_eyebrow" value="<?= h(get_setting('services_include_eyebrow', 'Every plan includes')) ?>">
        </div>
        <div class="form-group">
          <label>Heading</label>
          <input type="text" name="services_include_title" value="<?= h(get_setting('services_include_title', 'Full visibility, no extra cost')) ?>">
        </div>
      </div>
      <?php
      $includeDefaults = [
          1 => ['Live Map Tracking', 'Free on every shipment, every service tier.'],
          2 => ['Email Alerts', "Automatic updates sent to your receiver's inbox."],
          3 => ['Delivery Timeline', 'A timestamped history from pickup to drop-off.'],
          4 => ['24/7 Support', 'Our team is available around the clock.'],
      ];
      foreach ($includeDefaults as $i => [$defTitle, $defDesc]):
      ?>
        <div class="form-row">
          <div class="form-group">
            <label>Card <?= $i ?> Title</label>
            <input type="text" name="services_include<?= $i ?>_title" value="<?= h(get_setting("services_include{$i}_title", $defTitle)) ?>">
          </div>
          <div class="form-group">
            <label>Card <?= $i ?> Description</label>
            <input type="text" name="services_include<?= $i ?>_desc" value="<?= h(get_setting("services_include{$i}_desc", $defDesc)) ?>">
          </div>
        </div>
      <?php endforeach; ?>

      <button type="submit" class="btn btn-primary">Save Services Content</button>
    </form>

  <?php elseif ($activeTab === 'request'): ?>
    <form method="post">
    <?= csrf_field() ?>
      <input type="hidden" name="section" value="request">
      <div class="form-group">
        <label>Page Title</label>
        <input type="text" name="request_title" value="<?= h(get_setting('request_title', 'Request a Shipment')) ?>">
      </div>
      <div class="form-group">
        <label>Lead Paragraph</label>
        <textarea name="request_lead" rows="2"><?= h(get_setting('request_lead', "Tell us what you're shipping and when — we'll get back to you with a confirmed quote. Prices below are a live estimate.")) ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Save Ship Now Content</button>
    </form>

  <?php elseif ($activeTab === 'contact'): ?>
    <form method="post">
    <?= csrf_field() ?>
      <input type="hidden" name="section" value="contact">
      <div class="form-group">
        <label>Intro Text</label>
        <textarea name="contact_intro" rows="2"><?= h(get_setting('contact_intro')) ?></textarea>
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="contact_phone" value="<?= h(get_setting('contact_phone')) ?>">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="text" name="contact_email" value="<?= h(get_setting('contact_email')) ?>">
      </div>
      <div class="form-group">
        <label>Address</label>
        <input type="text" name="contact_address" value="<?= h(get_setting('contact_address')) ?>">
      </div>
      <button type="submit" class="btn btn-primary">Save Contact Content</button>
    </form>

  <?php elseif ($activeTab === 'footer'): ?>
    <form method="post">
    <?= csrf_field() ?>
      <input type="hidden" name="section" value="footer">
      <div class="form-group">
        <label>Footer Tagline</label>
        <textarea name="footer_tagline" rows="3"><?= h(get_setting('footer_tagline')) ?></textarea>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Get in Touch — Email</label>
          <input type="text" name="contact_email" value="<?= h(get_setting('contact_email')) ?>">
        </div>
        <div class="form-group">
          <label>Get in Touch — Phone</label>
          <input type="text" name="contact_phone" value="<?= h(get_setting('contact_phone')) ?>">
        </div>
      </div>
      <p style="margin-top:-6px;color:var(--muted);font-size:13px;">
        These are the same email and phone shown on the Contact page — editing them here updates both.
      </p>
      <div class="form-group">
        <label>Rights Text (after "&copy; <?= date('Y') ?> <?= h(get_site_name()) ?>.")</label>
        <input type="text" name="footer_rights_text" value="<?= h(get_setting('footer_rights_text', 'All rights reserved.')) ?>">
      </div>
      <div class="form-group">
        <label>Footer Bottom Note</label>
        <input type="text" name="footer_bottom_note" value="<?= h(get_setting('footer_bottom_note')) ?>">
      </div>
      <button type="submit" class="btn btn-primary">Save Footer Content</button>
    </form>

  <?php elseif ($activeTab === 'countries'): ?>
    <form method="post">
    <?= csrf_field() ?>
      <input type="hidden" name="section" value="countries">
      <div class="form-group">
        <label>Page Title</label>
        <input type="text" name="countries_title" value="<?= h(get_setting('countries_title', 'Countries We Ship To')) ?>">
      </div>
      <div class="form-group">
        <label>Intro Text</label>
        <textarea name="countries_intro" rows="2"><?= h(get_setting('countries_intro')) ?></textarea>
      </div>
      <div class="form-group">
        <label>Countries List (one per line)</label>
        <textarea name="countries_list" rows="16" style="font-family:monospace;font-size:13px;"><?php
          echo h(implode("\n", get_countries_list()));
        ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Save Countries List</button>
    </form>

  <?php elseif ($activeTab === 'privacy'): ?>
    <form method="post">
    <?= csrf_field() ?>
      <input type="hidden" name="section" value="privacy">
      <div class="form-group">
        <label>Page Title</label>
        <input type="text" name="privacy_title" value="<?= h(get_setting('privacy_title', 'Privacy Policy')) ?>">
      </div>
      <div class="form-group">
        <label>Lead Paragraph</label>
        <textarea name="privacy_lead" rows="2"><?= h(get_setting('privacy_lead')) ?></textarea>
      </div>
      <div class="form-group">
        <label>Body (separate paragraphs with a blank line)</label>
        <textarea name="privacy_body" rows="18"><?= h(get_setting('privacy_body')) ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Save Privacy Policy</button>
    </form>

  <?php elseif ($activeTab === 'terms'): ?>
    <form method="post">
    <?= csrf_field() ?>
      <input type="hidden" name="section" value="terms">
      <div class="form-group">
        <label>Page Title</label>
        <input type="text" name="terms_title" value="<?= h(get_setting('terms_title', 'Terms of Service')) ?>">
      </div>
      <div class="form-group">
        <label>Lead Paragraph</label>
        <textarea name="terms_lead" rows="2"><?= h(get_setting('terms_lead')) ?></textarea>
      </div>
      <div class="form-group">
        <label>Body (separate paragraphs with a blank line)</label>
        <textarea name="terms_body" rows="18"><?= h(get_setting('terms_body')) ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Save Terms of Service</button>
    </form>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
