# Glock 9mm search data repair (2026-10-25)

This repair adds common 9mm Glock models and search aliases and replaces the earlier Glock fit review with a chart-first review.

## Files

- `2026-10-25_firearm_master_glock_9mm_popular_r1.csv`: 11 common 9mm Glock model records.
- `2026-10-25_search_alias_master_glock_9mm_popular_r1.csv`: specific aliases plus broad `Glock 9` / `Glock 9mm` aliases.
- `2026-10-25_firearm_master_glock_fit_review_r2.csv`: prior Glock review rows rechecked against the PHolsters size chart, plus the six missing common models.
- The original `2026-07-24_firearm_master_glock_fit_review_r1.csv` is retained as historical input. Use the r2 review for chart-based fit decisions.

## Fit behavior

A broad search for `Glock 9`, `Glock 9mm`, or `9mm Glock` is a family search. The search UI must show the matching model choices; it must not return one arbitrary fit for the whole family.

Only explicit PHolsters chart entries receive a fit:
- Glock 17: A4
- Glock 19: A3 and A4
- Glock 26: A2.5
- Glock 23 and the other exact models listed in the corrected review keep only their chart-listed fit codes.
- Models absent from the chart remain searchable but have a blank `passport_fit` and `NOT_IN_CHART` / pending status. Barrel length alone is not proof that PHolsters has tested a fit.

## Source of truth

Fit assignments link to https://pholsters.com/size-chart/. Model caliber and barrel lengths cite official GLOCK product pages or owner/buyer documents. Do not replace a blank chart fit with a guessed barrel-length category without explicit PHolsters approval.
