# Passport Holsters Genitron Database

This replacement uses Genitron.com as the firearm-data source. Do not merge old CSVs, aliases, or records from the superseded database.

`genitron_database.csv` has exactly four columns: Manufacturer, Model, Barrel Length (inches), and Passport Fit Size. Each row represents one Genitron variant. `genitron_sources.csv` stores the matching Genitron URL separately.

The current catalog contains 7,575 variant rows across all 135 Genitron manufacturers. Genitron pages supplied the model and barrel data. Revolver fit mapping also uses the capacity listed on its Genitron detail page. Passport size rules are applied to the barrel length and firearm type.

Sixty-nine rows are labeled `Manual fit review`: 19 Genitron pages do not list a barrel length, and 50 have a barrel length without an established size mapping. These entries are retained so the manufacturer and model remain searchable, but the plugin must not present them as confirmed fits until reviewed.
