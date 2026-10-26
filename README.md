# Passport Fit Search

Search data and a WordPress prototype for the PHolsters size guide. The source of truth for a confirmed holster size is the [PHolsters size chart](https://pholsters.com/size-chart/), which lists models in multiple size categories in some cases.

## Current repository coverage

This repository contains two different kinds of firearm data:

- **Importer master records:** 77 rows in three CSV files: 40 Beretta, 25 Springfield supplement, and 12 popular 9 mm Glock entries. A master row is not automatically a confirmed fit; the WordPress importer shows a size only when `fit_verified` explicitly confirms it.
- **Fit review reports:** exploratory and supporting rows for additional manufacturers. These include proposed sizes and overlapping revisions. They are searchable in the plugin, but are not proof of a chart-confirmed fit.
- **Search alias files:** customer search phrases linked to firearm IDs. Some manufacturers have aliases without corresponding importer master records.

These counts describe checked-in files as of 2026-10-26. Earlier project notes described a planned 1,811-record database; those records are **not all present as importer-ready, chart-confirmed entries here**. Do not use proposed fits or a barrel-length rule as a product compatibility guarantee.

## Glock 9 mm search

The [Glock master](data/glock/2026-10-25_firearm_master_glock_9mm_popular_r1.csv) and [aliases](data/glock/2026-10-25_search_alias_master_glock_9mm_popular_r1.csv) cover the common searches `Glock 9`, `Glock 9mm`, `9mm Glock`, model numbers, and G-number abbreviations. “Glock 9” describes a family search, not one exact model. Search results should present model choices and never assign one holster size to that phrase.

The currently chart-listed 9 mm entries are:

| Model choice | Passport size |
| --- | --- |
| Glock 17 | A4 |
| Glock 19 Compact | A3 |
| Glock 19 Full Barrel | A4 |
| Glock 26 | A2.5 |

The chart lists Glock 19 in both A3 and A4. Confirm which size and holster style the customer means before treating one result as their selection. Other common Glock models in the master remain searchable with a blank `passport_fit` until their fit is confirmed on the chart. See [Glock data notes](data/glock/README.md).

## File roles

- `data/*/*_firearm_master_*_r*.csv` (excluding `fit_review` and `sources`): importer records. A confirmed fit requires `fit_verified` on that row.
- `data/*/*_firearm_master_*_fit_review_*.csv`: supporting review material; proposed assignments are not imported as confirmed fits.
- `data/*/*_search_alias_master_*.csv`: search terms associated with firearm IDs.
- `data/*/*_sources_*.csv`: reference and specification notes.
- `wordpress-plugin/passport-fit-search/`: WordPress plugin prototype and its instructions.

The plugin reads the public GitHub files into a WordPress cache on activation and on a daily schedule. Repository changes do not instantly update the website. Its `[passport_fit_search]` shortcode has to be present on a WordPress page for visitors to use this search. Check the [plugin README](wordpress-plugin/passport-fit-search/README.md) before activation.

## Data review before publication

1. Match each proposed fit to the PHolsters chart or another explicit PHolsters approval.
2. Keep unconfirmed `passport_fit` blank and `fit_verified` false.
3. Check ambiguous family searches and multiple-fit models on the actual customer interface.
4. Refresh the WordPress import and verify that the page uses the intended shortcode before relying on new repository entries.
