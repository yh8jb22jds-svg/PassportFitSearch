# Passport Holsters Genitron Database

This replacement uses Genitron.com as the firearm-data source. Do not merge old CSVs, aliases, or records from the superseded database.

`genitron_database.csv` has exactly four columns: Manufacturer, Model, Barrel Length (inches), and Passport Fit Size. Each row represents a Genitron-listed model variant. `genitron_sources.csv` stores the matching Genitron page URL separately.

The clean rebuild currently contains one individually checked record: Glock 42, 3.2-inch barrel, A3. The full catalog is not yet populated. Genitron's sitemap lists 135 manufacturers and 7,575 variant pages, which must be reviewed and assigned against the Passport Holsters sizing rules before the database can be treated as complete.