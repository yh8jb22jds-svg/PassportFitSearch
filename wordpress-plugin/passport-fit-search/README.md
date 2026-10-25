# PHolsters Passport Fit Search plugin

This plugin provides the `[passport_fit_search]` shortcode and indexes the firearm data stored in this public repository. It loads importer-format master CSVs, uses fit-review CSVs and alias CSVs to find additional model identities, and caches the index locally in WordPress. The full database is not embedded in the homepage.

## Install

1. Download this repository and extract `wordpress-plugin/passport-fit-search/`.
2. Zip the `passport-fit-search` folder so the ZIP contains `passport-fit-search/passport-fit-search.php`.
3. In WordPress, open **Plugins → Add Plugin → Upload Plugin**, upload that ZIP, install, then activate.
4. In the saved homepage draft (page 410), replace the existing fit finder module with the shortcode `[passport_fit_search]`.
5. Keep the page as a draft and preview it. Do not publish until the search is reviewed.

## Data and refresh

The plugin discovers dated firearm master, fit-review, and search-alias CSV files under `data/` on the repository's `main` branch. Fit-review files contribute searchable model names and aliases only; their proposed fit values are never treated as approved. Alias-only records also remain searchable. WordPress refreshes its local index daily through WordPress Cron.

## Fit rules

A Passport fit is shown only when a master CSV row includes `fit_verified=1` (or `yes` / `true` / `chart`) and a non-empty `passport_fit`. Unverified records remain searchable and show a link to the size chart instead of a guessed fit.

Broad aliases such as `Glock 9` return model choices. Glock 19 has separate records for A3 Compact and A4 Full Barrel so each result has one fit code.

## Requirements and limitations

- WordPress 6.0 or newer with outbound HTTPS requests enabled.
- The repository must remain public for WordPress to read it without a GitHub token.
- WordPress Cron runs when the site receives visits. After updating data, the previous cache can remain for up to 24 hours.
- This plugin is separate from the homepage content. Replacing the current module with the shortcode is a WordPress edit and does not publish the draft.
