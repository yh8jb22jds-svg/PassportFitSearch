# PHolsters Passport Fit Search plugin

This plugin provides the `[passport_fit_search]` shortcode. It reads firearm master and alias CSV files from the public PassportFitSearch repository, caches them locally in WordPress, and refreshes the cache daily. Search requests are served by WordPress; the full database is not embedded in the homepage.

## Install

1. Download this repository and extract `wordpress-plugin/passport-fit-search/`.
2. Zip the `passport-fit-search` folder so the ZIP contains `passport-fit-search/passport-fit-search.php`.
3. In WordPress, open **Plugins → Add Plugin → Upload Plugin**, upload that ZIP, install, then activate.
4. In the saved homepage draft (page 410), replace the existing fit finder module with the shortcode `[passport_fit_search]`.
5. Keep the page as a draft and preview it. Do not publish until the search is reviewed.

## Data and refresh

The plugin discovers importer-format files named `YYYY-MM-DD_firearm_master_*.csv` and `YYYY-MM-DD_search_alias_master_*.csv` under `data/` on the repository's `main` branch. It builds its local cache when activated and refreshes daily through WordPress Cron. A failure to refresh keeps any existing cached data available.

## Fit rules

The plugin displays only the `passport_fit` value stored in the firearm master. It does not infer a Passport fit from barrel length. A blank fit displays a clear unconfirmed message and a link to the official size chart.

Broad aliases such as `Glock 9` return model choices. Glock 19 has separate records for A3 Compact and A4 Full Barrel so each result has one fit code.

## Requirements and limitations

- WordPress 6.0 or newer with outbound HTTPS requests enabled.
- The repository must remain public for WordPress to read it without a GitHub token.
- WordPress Cron runs when the site receives visits. After updating data, the previous cache can remain for up to 24 hours.
- This plugin is separate from the homepage content. Replacing the current module with the shortcode is a manual WordPress edit and does not publish the draft.
