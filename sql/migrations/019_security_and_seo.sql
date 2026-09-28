-- Rate limiting, the security event log, and the per-page search engine
-- settings written in the admin panel.
--
-- Safe to run more than once: every table uses CREATE TABLE IF NOT EXISTS
-- and every setting uses INSERT IGNORE, so running it twice adds nothing
-- and changes nothing you have already set.
--
-- Everything here is also in sql/schema.sql. Import this file if you have
-- a live database with real shipments in it; import schema.sql instead if
-- you are starting from empty.

-- ---------------------------------------------------------------
-- 1. Rate limiting.
--
-- One row per (bucket, actor, window) with a counter, rather than one row
-- per request, so the table stays small and the write stays cheap even
-- while an attack is in progress. See includes/security.php.
--
-- `bucket`  is what is being limited: 'login', 'contact', 'page'.
-- `actor`   is who: normally an address, sometimes an address plus an
--           account, hashed if it would otherwise be too long for the key.
-- `blocked_until` is a unix timestamp; 0 means not blocked.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limits (
  bucket VARCHAR(40) NOT NULL,
  actor VARCHAR(190) NOT NULL,
  window_start INT UNSIGNED NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  blocked_until INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (bucket, actor, window_start),
  INDEX idx_updated (updated_at),
  INDEX idx_blocked (bucket, actor, blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- 2. Security events: requests that were refused outright because they
--    were shaped like an attack rather than like a visit.
--
--    Kept so the site owner can see what is being tried, and so a pattern
--    (the same address probing all week) is visible rather than invisible.
--    Old rows are cleared out by the application, there is no cron here.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS security_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL DEFAULT '',
  reason VARCHAR(60) NOT NULL DEFAULT '',
  request_path VARCHAR(255) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_created (created_at),
  INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- 3. Per-page search engine settings, written at /admin/seo.php.
--
--    One row per public page. A page with no row here still renders
--    perfectly well: the site falls back to its heading plus the site
--    name for the title, and to the site-wide description. Nothing has to
--    be filled in for the site to work.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seo_pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- Matches the keys in seo_pages() in includes/seo.php: home, track,
  -- request, services, countries, about, contact, privacy, terms.
  page_key VARCHAR(40) NOT NULL UNIQUE,

  meta_title VARCHAR(255) NOT NULL DEFAULT '',
  meta_description VARCHAR(320) NOT NULL DEFAULT '',
  -- The one phrase this page is trying to rank for.
  focus_keyword VARCHAR(120) NOT NULL DEFAULT '',
  -- Supporting phrases, comma separated as they were typed.
  meta_keywords VARCHAR(500) NOT NULL DEFAULT '',

  -- What a link to this page looks like when it is shared in a message.
  og_title VARCHAR(255) NOT NULL DEFAULT '',
  og_description VARCHAR(320) NOT NULL DEFAULT '',

  -- Overrides the address search engines are told is the real one for
  -- this page. Blank means "the page's own address", which is right
  -- almost always.
  canonical_path VARCHAR(255) NOT NULL DEFAULT '',
  -- 1 keeps the page online but out of search results and out of the
  -- sitemap.
  noindex TINYINT(1) NOT NULL DEFAULT 0,

  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- 4. The site-wide search engine settings.
--
--    All blank or off by default, so importing this changes nothing about
--    how the site currently appears. seo_noindex_site in particular
--    defaults to '0' (visible): defaulting it to hidden would quietly
--    take a working site out of Google.
-- ---------------------------------------------------------------
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('seo_default_description', ''),
('seo_share_image', ''),
('seo_noindex_site', '0'),
('seo_google_verification', ''),
('seo_google_verification_file', ''),
('seo_bing_verification', '');
