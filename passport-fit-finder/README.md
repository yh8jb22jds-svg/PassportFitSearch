# Passport Holsters Fit Finder

WordPress shortcode plugin for the Passport Holsters firearm database.

## Install

1. Upload `passport-fit-finder.php` as a plugin folder named `passport-fit-finder`.
2. Activate **Passport Holsters Fit Finder**.
3. Add `[passport_fit_finder]` to the page where the Fit Finder should appear.

The plugin reads `genitron_database.csv` from the repository's `main` branch and caches parsed rows for 15 minutes. New or corrected records become available after the cache expires, without a plugin code update. If GitHub is temporarily unavailable, the plugin uses its last successful database copy.

A row is customer-displayable only when all four columns are populated, the barrel length is numeric, and the fit size is a recognized size. Rows marked `Manual fit review` are omitted. Manufacturer and model choices are derived from these complete rows; a row with a missing barrel length or fit size cannot appear in the model list or results. This does not alter or repair the CSV.

The A2.5/A3 customer wording is kept exactly as approved, with only the database barrel length substituted. Other fit sizes use the previously approved recommendation wording. The plugin does not change WordPress settings or the live site unless installed and activated by the site owner.
