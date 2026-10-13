# Magelan_SearchCms - Search CMS Pages ("Iskanje po CMS straneh")

CMS pages (and, optionally, Magelan Blog posts) in the search results, for Magento 2.4.6 - 2.4.9
(PHP 8.1 - 8.4), Luma and Hyvä.

A shopper searches for "delivery" or "returns" and, next to the products, gets your pages that answer
the question: title, a short excerpt with the search term highlighted, and a link. Fewer questions by
e-mail, more shoppers who find the answer themselves.

## What it does

* **Block "Pages matching your search"** on the search results page, above the products (default) or
  below them. When the search finds no products the block always stands above the "no results"
  message, because the pages are then the only useful result.
* **Rows:** page title (one line, ellipsis for long titles, full title in the tooltip), excerpt of about
  160 characters around the first match (2 lines) with the term on a light background and in bold, a
  "Blog" label on blog posts. No page path under the title.
* **How many:** the setting "Maximum results shown" (default 5) on desktop, 3 on phones; the other
  results are under "Show all N" (a `<details>` element, no JavaScript). At most 50 results in total.
* **Search:** every term must occur (title, main heading, meta title/keywords/description or text);
  only when no page has all of them, pages with at least half of the terms (min. 2) are shown.
  Diacritics and case are ignored ("vracilo" finds "Vračilo"), extra spaces and punctuation never
  produce an empty term, terms under 2 characters and common stop words (sl, hr, en, de) do not count,
  `%` and `_` are searched literally.
* **Ranking:** exact title 100, term in the title 50, in the main heading 30, in meta data 20, in the
  text 5 per occurrence (max. 20); then by title.
* **Search list:** its own table `magelan_searchcms_index` with the plain text of each page per store
  view (HTML, scripts, styles and `{{...}}` directives removed, Page Builder markup reduced to text). It
  is **built automatically on installation/upgrade** and kept fresh when a page (or blog post) is saved
  or deleted, in the admin, through the REST API or through the page repository.
* **Only what the shopper may see:** active pages assigned to the current store view or to "All Store
  Views"; a store view page replaces an "All Store Views" page with the same URL key. Pages can be
  excluded by URL key (default: `no-route`, `home`, `enable-cookies`,
  `privacy-policy-cookie-restriction-mode`; a trailing `*` matches any ending).
* **Blog (optional):** with Magelan Blog installed, "Search blog posts too" adds published posts
  (per store view translation, only from their publication date on).
* **Full page cache:** the block carries the cache tags of the pages it shows and of the search list,
  so editing a page refreshes cached results pages.
* **Public service** `Magelan\SearchCms\Api\ContentSearchInterface::search($query, $limit, $storeId,
  $withSnippet)` for other modules (e.g. a search suggestions drawer).
* **Translations:** English, Slovenian, Croatian, German.

## Installing / upgrading

```
composer require degriznet/m2_searchcms
bin/magento setup:upgrade
bin/magento setup:di:compile        # production mode
bin/magento cache:flush
```

Hyvä: the module registers itself for the Tailwind build; run `bin/magento hyva:config:generate` and
rebuild the theme CSS (`npm run build` in the theme's `web/tailwind`).

**Upgrading from 1.x needs no manual step:** the table comes from the declarative schema and the search
list is built by a data patch. A 1.x shop that never saved the "Enabled" field had the block switched
off; it stays off after the upgrade (switch it on in the settings). A saved value is kept.

## Settings

*Stores > Configuration > Magelan > Search CMS Pages* (section `searchcms`, unchanged from 1.x):

| Group | Field | Default |
| --- | --- | --- |
| General | Enabled | Yes (new installations) |
| | Block position: above / below the products | above |
| | Maximum results shown (desktop; phones show at most 3) | 5 |
| | Show text excerpt | Yes |
| Content | Excluded pages (URL keys, one per line, `*` at the end) | 4 system pages |
| | Search blog posts too (only with Magelan Blog) | No |
| | Minimum text length (characters, 0 = all) | 0 |
| Page search list | Status ("Built: 24 pages, last ...") and the **Rebuild** button | |

Command line: `bin/magento magelan:searchcms:reindex`. Log: `var/log/magelan_searchcms.log`.

## Themes

* **Luma and Luma-based themes:** `templates/search_results.phtml` and `web/css/searchcms.css` (CSS
  variables `--mgl-cms-ink`, `--mgl-cms-soft`, `--mgl-cms-mark`, `--mgl-cms-radius`). The block keeps the
  1.x class `cms-search-results`.
* **Hyvä 1.3+ (Tailwind 3 and 4):** `hyva_catalogsearch_result_index.xml` switches to
  `templates/hyva/search_results.phtml` (Tailwind, no Alpine, no JavaScript). Colours follow the theme
  colour `--color-primary`; override them in the theme CSS, for example
  `.mgl-cms-hv { --mgl-cms-ink: #c2410c; --mgl-cms-mark: #ffedd5; }`.
* Both blocks are named `custom.search.results` (above, kept from 1.x) and `magelan.searchcms.below`
  in the container `content`, before/after `search.result`. A theme that moves or removes
  `search.result` should place them itself.

## Known limits

* Content that a page pulls in through `{{block}}` / `{{widget}}` directives or CMS blocks is not
  searched (directives are not executed while indexing).
* Matching is by substring, without stemming ("jogo" does not find "joga"; "mat" also finds "format").
* Other blogs (FishPig WordPress, Magefan Blog) and CMS blocks are not searched.
* For shops with many hundreds of long pages the `LIKE` search on the folded text gets slower; use
  "Minimum text length" and exclusions to keep the list short.

## Tests

* Unit tests: `cd dev/ci && composer install && php vendor/bin/phpunit` (the harness is not part of
  the package).
* `dev/integration-check.php`: creates temporary CMS pages on an installation and checks indexing,
  folding, ranking, AND/OR, exclusions, store views, deactivation, deletion, excerpts and a full
  rebuild (`php app/code/Magelan/SearchCms/dev/integration-check.php <store_id> [<other_store_id>]`).

## Licence

See `LICENSE.txt`.
