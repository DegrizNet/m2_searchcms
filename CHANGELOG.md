# Changelog

## 2.0.0 - 2026-10-13

"Iskanje po CMS straneh" (Search CMS Pages) rebuilt: own search list, ranking, excerpts, Hyvä.

### Added
- Search list `magelan_searchcms_index` (plain folded text per page and store view), built
  automatically on installation/upgrade, kept fresh on page save/delete; `bin/magento
  magelan:searchcms:reindex` and a Rebuild button with status in the settings.
- Search: all terms first, then at least half of them; diacritics and case ignored; empty terms,
  short terms and stop words ignored; `%` and `_` literal.
- Ranking (title, main heading, meta data, text) and an excerpt of about 160 characters with the
  term highlighted (light background and bold).
- Block position above or below the products; without products always above the "no results"
  message. Desktop shows "Maximum results shown" (default 5), phones 3, the rest under "Show all N"
  (`<details>`, no JavaScript).
- Hyvä template (Tailwind 3/4, no Alpine) with Tailwind registration; Luma stylesheet.
- Settings: position, maximum results, excerpt on/off, excluded pages (URL keys, `*` wildcard),
  minimum text length, Magelan Blog posts (labelled "Blog").
- Full page cache tags of the shown pages; ACL resource `Magelan_SearchCms::config_system` declared.
- Public service `Magelan\SearchCms\Api\ContentSearchInterface`.
- Translations: English, Slovenian, Croatian, German.

### Fixed
- A query with extra spaces no longer lists every page.
- HTML markup ("div", "class") is no longer searched; titles are escaped.
- Pages with the "custom" layout are no longer skipped silently; inactive pages and pages of other
  store views are never found.
- Correct page URLs (the 1.x fallback used the URL key as a page ID).

### Changed (please read when upgrading from 1.x)
- Settings section renamed "Search CMS Pages" (id `searchcms` and the path
  `searchcms/general/enabled` unchanged).
- Enabled by default on new installations; a 1.x shop that never saved the field keeps the block off.
- The 1.x plugin on the search result block is removed (the layout is the only way the block renders).
- Licence: proprietary (see `LICENSE.txt`); PHP 8.1 - 8.4, Magento 2.4.6 - 2.4.9.
- Upgrading needs no manual step. In production mode run `bin/magento setup:di:compile`; on Hyvä
  run `bin/magento hyva:config:generate` and rebuild the CSS.

## 1.0.3 - 2026-10-12

- The Enabled switch reads the right path (`searchcms/general/enabled`); only active pages of the
  current store are searched.
