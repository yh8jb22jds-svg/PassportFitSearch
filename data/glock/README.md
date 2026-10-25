# Glock search data (2026-10-25)

The importer-ready Glock files in this folder add common 9mm Glock models and searchable aliases.

## Import files

- `2026-10-25_firearm_master_glock_9mm_popular_r1.csv` uses the repository's firearm master schema.
- `2026-10-25_search_alias_master_glock_9mm_popular_r1.csv` maps model names and family searches to firearm IDs.
- `2026-10-25_firearm_master_glock_fit_review_r2.csv` is a supporting review report; it is not the importer master.

## Search behavior

A broad query such as `Glock 9`, `Glock 9mm`, or `9mm Glock` should return matching model choices. The selected exact model then determines the fit. Never show one arbitrary size for the entire 9mm family.

Fit assignments in the importer master follow the PHolsters size chart:
- Glock 17: A4
- Glock 19: A3 and A4
- Glock 26: A2.5
- Glock 34, 43, 43X, 19X, 45, 47, 48, and 49 are searchable but have blank `passport_fit` because they are not listed on that chart.

The fit review report includes prior Glock inventory rows. A model absent from the PHolsters chart remains unconfirmed; barrel length by itself does not prove tested fit.

## Source links

- PHolsters chart: https://pholsters.com/size-chart/
- Model specifications: official GLOCK product and owner resources linked from the master data where available.
