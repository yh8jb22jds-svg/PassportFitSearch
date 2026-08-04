# Passport Fit Search Database

## Project Overview

**PassportFitSearch** is a comprehensive firearm database mapping models to standardized "passport fit" categories for holster compatibility. The database is organized by manufacturer with consistent schema across all entries.

**Repository:** https://github.com/yh8jb22jds-svg/PassportFitSearch  
**Status:** Active Development (Phase 1: Complete - Beretta, Colt, HK)  
**Last Updated:** 2026-07-25  

---

## Database Structure

The database consists of 4 CSV file types per manufacturer:

### File Types & Naming Convention

```
YYYY-MM-DD_[FILE_TYPE]_[MANUFACTURER]_r[VERSION].csv
```

Example:
- `2026-07-24_firearm_master_beretta_r1.csv`
- `2026-07-24_search_alias_master_beretta_r1.csv`
- `2026-07-24_firearm_master_beretta_sources_r1.csv`
- `2026-07-24_firearm_master_beretta_fit_review_r1.csv`

---

## CSV File Schemas

### 1. **FIREARM MASTER** (`firearm_master_[MFGR]_r#.csv`)

**Purpose:** Primary firearm inventory with specifications

**Columns:**
| Column | Data Type | Description | Example |
|--------|-----------|-------------|---------|
| `firearm_id` | String | Unique identifier (MFG-######) | `BERETTA-000001` |
| `manufacturer_id` | String | Manufacturer code | `BERETTA-MFG` |
| `display_name` | String | Full product name | `Beretta 92FS Full Size` |
| `model` | String | Base model designation | `92FS` |
| `variant` | String | Variant/sub-model | `Full Size`, `Compact`, `Tactical` |
| `caliber` | String | Ammunition caliber | `9mm`, `.45 ACP`, `.357 Magnum` |
| `barrel_length` | Decimal | Barrel length in inches | `4.90`, `3.85` |
| `action_type` | String | Firing mechanism | `Semi-Auto`, `Revolver` |
| `passport_fit` | String | Fit category code | `A1`, `A2.5`, `A4`, `R2.5`, `R3` |
| `active` | Integer | Currently in production (1=yes, 0=no) | `1` or `0` |
| `discontinued` | Integer | Model discontinued (1=yes, 0=no) | `1` or `0` |

**Key Rules:**
- `firearm_id` must be globally unique
- `passport_fit` determines holster compatibility category
- Both `active` and `discontinued` can be 0 (older models no longer sold)
- `barrel_length` must be numeric (decimals for .25", .5" increments)

---

### 2. **SEARCH ALIAS MASTER** (`search_alias_master_[MFGR]_r#.csv`)

**Purpose:** Customer search terms and aliases for each firearm

**Columns:**
| Column | Data Type | Description | Example |
|--------|-----------|-------------|---------|
| `alias_id` | String | Unique alias ID (MFG-ALS-######) | `BERETTA-ALS-000001` |
| `firearm_id` | String | Reference to firearm_master | `BERETTA-000001` |
| `alias_text` | String | Search term variant | `Beretta 92FS`, `92FS`, `M9A3` |
| `normalized_alias` | String | Lowercase, spaces normalized | `beretta 92fs`, `92fs` |
| `alias_type` | String | Category of alias | `Model Variation`, `Abbreviation`, `Caliber Variation`, `Finish Variation` |
| `priority` | Integer | Search ranking (1=primary, 2=secondary, 3=tertiary) | `1`, `2`, `3` |
| `active` | Integer | Alias currently active (1=yes, 0=no) | `1` or `0` |

**Key Rules:**
- Multiple aliases per firearm are expected
- `normalized_alias` enables case-insensitive searching
- Priority 1 = most likely customer search term
- Include: full name, abbreviation, caliber variant, finish variant

**Alias Types:**
- `Model Variation` - Alternative model names
- `Abbreviation` - Short codes (92FS, VP9, etc.)
- `Caliber Variation` - Caliber-specific references
- `Finish Variation` - Color/finish variants
- `Full Name` - Complete descriptive name
- `Military Designation` - Military/law enforcement names
- `Spacing Variant` - Different spacing (H&K vs HK)

---

### 3. **SOURCE REFERENCE REPORT** (`firearm_master_[MFGR]_sources_r#.csv`)

**Purpose:** Specification sources and documentation links

**Columns:**
| Column | Data Type | Description | Example |
|--------|-----------|-------------|---------|
| `firearm_id` | String | Reference to firearm_master | `BERETTA-000001` |
| `display_name` | String | Firearm display name (duplicate) | `Beretta 92FS` |
| `[mfgr]_source_url` | URL | Official manufacturer spec link | `https://www.berettausa.com/...` |
| `passport_chart_url` | URL | Holster fit reference | `https://pholsters.com/size-chart/` |
| `source_type` | String | Source authority | `Official Beretta`, `Public Reference` |
| `date_accessed` | Date | Data collection date (YYYY-MM-DD) | `2026-07-24` |
| `specification_notes` | Text | Summary of key specs | `4.90 inch barrel standard 9mm` |

**Key Rules:**
- Column naming: `[MANUFACTURER_CODE]_source_url` (e.g., `beretta_source_url`, `colt_source_url`)
- Use official manufacturer websites when available
- Provide Wikipedia/public reference for discontinued models
- Keep `specification_notes` concise (one sentence)

---

### 4. **PASSPORT FIT QA REPORT** (`firearm_master_[MFGR]_fit_review_r#.csv`)

**Purpose:** Quality assurance and passport fit category justification

**Columns:**
| Column | Data Type | Description | Example |
|--------|-----------|-------------|---------|
| `firearm_id` | String | Reference to firearm_master | `BERETTA-000001` |
| `display_name` | String | Firearm display name | `Beretta 92FS` |
| `proposed_passport_fit` | String | Assigned fit category | `A4` |
| `confidence_level` | String | Confidence (Proposed/High/Medium/Low) | `Proposed - Category Rule` |
| `passport_chart_exact_match` | String | Chart match status | `Yes`, `No`, `Proposed - Category Rule` |
| `comparable_models` | String | Similar firearms for reference | `Colt 1911 Government - 4.90 inch barrel full-size` |
| `decision_basis` | String | Rationale for fit assignment | `Full-size 9mm 4.90 inch barrel standard Government model` |
| `review_required` | String | Requires manual review (Yes/No) | `No` |
| `reviewer_decision` | String | Final approval status | `Approved`, `Pending Review`, `Rejected` |
| `supporting_source_urls` | URL | Links supporting decision | `https://pholsters.com/size-chart/` |

**Key Rules:**
- `proposed_passport_fit` must match value in firearm_master
- `confidence_level` = "Proposed - Category Rule" for data-driven assignments
- `comparable_models` helps validate consistency across manufacturers
- `decision_basis` explains size category choice
- Default `review_required` = `No` unless ambiguous

---

## Passport Fit Categories

Firearms are classified using standardized codes:

### Pistols (A-Series)

| Code | Category | Barrel Length | Use Case |
|------|----------|---------------|----------|
| **A1** | Micro | < 2.5" | Ultra-compact carry |
| **A1.5** | Subcompact | 2.5" - 3.0" | Deep concealment |
| **A2** | Compact | 3.0" - 3.5" | Concealed carry |
| **A2.5** | Mid-Compact | 3.5" - 4.0" | Versatile carry |
| **A3** | Full-Size | 4.0" - 4.5" | Service/tactical |
| **A4** | Extended | > 4.5" | Competition/full-size |

### Revolvers (R-Series)

| Code | Category | Barrel Length | Use Case |
|------|----------|---------------|----------|
| **R1.5** | Micro | < 2.0" | Ultra-compact |
| **R2** | Compact | 2.0" - 3.0" | Concealed carry |
| **R2.5** | Mid-Compact | 3.0" - 4.0" | Versatile carry |
| **R3** | Service | > 4.0" | Full-size/tactical |

---

## Manufacturer ID Conventions

| Manufacturer | ID Prefix | Example | Status |
|--------------|-----------|---------|--------|
| Beretta | `BERETTA-` | `BERETTA-000001` | ✅ Complete |
| Colt | `COLT-` | `COLT-000001` | ✅ Complete |
| Heckler & Koch | `HK-` | `HK-000001` | ✅ Complete |
| Springfield Armory | `SA-` | `SA-000001` | ⏳ Pending |
| Sig Sauer | `SIG-` | `SIG-000001` | ⏳ Pending |
| Glock | `GLOCK-` | `GLOCK-000001` | ⏳ Pending |
| Ruger | `RUGER-` | `RUGER-000001` | ⏳ Pending |
| Smith & Wesson | `SW-` | `SW-000001` | ⏳ Pending |
| Taurus | `TAURUS-` | `TAURUS-000001` | ⏳ Pending |
| Kimber | `KIMBER-` | `KIMBER-000001` | ⏳ Pending |

---

## Directory Structure

```
PassportFitSearch/
├── README.md
├── CONTRIBUTION_GUIDE.md
├── DATABASE_SCHEMA.md
└── data/
    ├── beretta/
    │   ├── 2026-07-24_firearm_master_beretta_r1.csv
    │   ├── 2026-07-24_search_alias_master_beretta_r1.csv
    │   ├── 2026-07-24_firearm_master_beretta_sources_r1.csv
    │   └── 2026-07-24_firearm_master_beretta_fit_review_r1.csv
    ├── colt/
    │   ├── 2026-07-24_firearm_master_colt_r1.csv
    │   ├── 2026-07-24_search_alias_master_colt_r1.csv
    │   ├── 2026-07-24_firearm_master_colt_sources_r1.csv
    │   └── 2026-07-24_firearm_master_colt_fit_review_r1.csv
    ├── hk/
    │   ├── 2026-07-24_firearm_master_hk_r1.csv
    │   ├── 2026-07-24_search_alias_master_hk_r1.csv
    │   ├── 2026-07-24_firearm_master_hk_sources_r1.csv
    │   └── 2026-07-24_firearm_master_hk_fit_review_r1.csv
    └── [other manufacturers]/
```

---

## How to Create New Manufacturer Files

### Step 1: Research Phase

1. **Collect Current Models** from official manufacturer websites
   - List all production models with specifications
   - Document barrel lengths, calibers, variants
   
2. **Identify Discontinued Models** from public references
   - Wikipedia firearm databases
   - Gun review sites (GunDigest, AmericanRifleman)
   - Manufacturer archives

3. **Verify Specifications**
   - Barrel lengths (convert to decimal inches)
   - Caliber offerings per model
   - Active production status

### Step 2: Create Firearm Master (`firearm_master_[MFGR]_r1.csv`)

**Process:**
1. Generate unique `firearm_id` (MFG-000001, MFG-000002, etc.)
2. List all model variants separately
3. Assign preliminary `passport_fit` based on barrel length:
   - Pistols: Use A1-A4 scale per barrel length
   - Revolvers: Use R1.5-R3 scale per barrel length
4. Set `active=1` for current production, `active=0` for discontinued
5. Keep `discontinued` status accurate

**Excel Template:**
```csv
firearm_id,manufacturer_id,display_name,model,variant,caliber,barrel_length,action_type,passport_fit,active,discontinued
SA-000001,SA-MFG,Springfield Armory 1911 Defender,1911,Defender,9mm,3.0,Semi-Auto,A2,1,0
SA-000002,SA-MFG,Springfield Armory 1911 Standard,1911,Standard,9mm,5.0,Semi-Auto,A3,1,0
```

### Step 3: Create Search Alias Master (`search_alias_master_[MFGR]_r1.csv`)

**For each firearm, create 3-5 aliases:**
- Priority 1: Full product name (most likely search)
- Priority 2: Common abbreviation
- Priority 2: Caliber variant name (if applicable)
- Priority 2-3: Alternative names/slang

**Example for Springfield Armory 1911 Defender:**
```csv
alias_id,firearm_id,alias_text,normalized_alias,alias_type,priority,active
SA-ALS-000001,SA-000001,Springfield Armory 1911 Defender,springfield armory 1911 defender,Model Variation,1,1
SA-ALS-000002,SA-000001,1911 Defender,1911 defender,Model Variation,1,1
SA-ALS-000003,SA-000001,Springfield 1911 Defender,springfield 1911 defender,Abbreviation,2,1
SA-ALS-000004,SA-000001,Defender Compact,defender compact,Model Variation,2,1
```

### Step 4: Create Source Reference (`firearm_master_[MFGR]_sources_r1.csv`)

**For each firearm:**
1. Find official manufacturer spec page
2. Document URL and access date
3. Write brief specification summary

**Example:**
```csv
firearm_id,display_name,sa_source_url,passport_chart_url,source_type,date_accessed,specification_notes
SA-000001,Springfield Armory 1911 Defender,https://www.springfieldarmory.com/1911-defender/,https://pholsters.com/size-chart/,Official Springfield,2026-07-25,3.0 inch barrel compact 9mm
```

### Step 5: Create QA Report (`firearm_master_[MFGR]_fit_review_r1.csv`)

**For each firearm:**
1. Verify `proposed_passport_fit` matches firearm_master
2. Provide comparable model reference
3. Explain barrel-length-based decision
4. Set `review_required=No` (unless questionable)

**Example:**
```csv
firearm_id,display_name,proposed_passport_fit,confidence_level,passport_chart_exact_match,comparable_models,decision_basis,review_required,reviewer_decision,supporting_source_urls
SA-000001,Springfield Armory 1911 Defender,A2,Proposed - Category Rule,No,HK P30 - similar 3.0 inch barrel,Compact 9mm 3.0 inch barrel concealed carry variant,No,Approved,https://pholsters.com/size-chart/
```

---

## Quality Assurance Checklist

Before committing new manufacturer files:

- [ ] **Firearm Master**
  - [ ] All `firearm_id` unique (no duplicates)
  - [ ] `barrel_length` is numeric decimal
  - [ ] `caliber` uses standard notation (9mm, .45 ACP, .357 Magnum)
  - [ ] `passport_fit` aligns with barrel_length (A1-A4 or R1.5-R3)
  - [ ] `active` + `discontinued` logic is correct (not both 1)

- [ ] **Search Alias Master**
  - [ ] All `alias_id` unique
  - [ ] `firearm_id` references valid record
  - [ ] Each firearm has 3+ aliases
  - [ ] Priority 1 is most common customer search term
  - [ ] `normalized_alias` matches `alias_text` (lowercase, spaces)

- [ ] **Source Reference**
  - [ ] URLs are valid and accessible
  - [ ] `date_accessed` is current (YYYY-MM-DD format)
  - [ ] `specification_notes` are concise (< 100 chars)
  - [ ] `source_type` is either "Official [Manufacturer]" or "Public Reference"

- [ ] **QA Report**
  - [ ] `proposed_passport_fit` matches firearm_master value
  - [ ] `comparable_models` cite real similar firearms
  - [ ] `decision_basis` explains the fit category choice
  - [ ] `reviewer_decision` is "Approved"

---

## Manufacturers Remaining (Priority Order)

| # | Manufacturer | Status | Est. Records | Priority |
|---|--------------|--------|--------------|----------|
| 1 | Springfield Armory | ⏳ Pending | 45-55 | ⭐⭐⭐ High |
| 2 | Sig Sauer | ⏳ Pending | 60-70 | ⭐⭐⭐ High |
| 3 | Glock | ⏳ Pending | 40-50 | ⭐⭐⭐ High |
| 4 | Ruger | ⏳ Pending | 55-65 | ⭐⭐⭐ High |
| 5 | Smith & Wesson | ⏳ Pending | 70-80 | ⭐⭐⭐ High |
| 6 | Taurus | ⏳ Pending | 50-60 | ⭐⭐ Medium |
| 7 | Kimber | ⏳ Pending | 40-50 | ⭐⭐ Medium |
| 8 | Walther | ⏳ Pending | 35-45 | ⭐⭐ Medium |
| 9 | CZ-USA | ⏳ Pending | 30-40 | ⭐⭐ Medium |
| 10 | Steyr | ⏳ Pending | 25-35 | ⭐ Low |
| 11 | FN Herstal | ⏳ Pending | 35-45 | ⭐⭐ Medium |
| 12 | Remington | ⏳ Pending | 20-30 | ⭐ Low |
| 13 | Mossberg | ⏳ Pending | 15-25 | ⭐ Low |
| 14 | Savage | ⏳ Pending | 15-25 | ⭐ Low |
| 15 | Browning | ⏳ Pending | 30-40 | ⭐⭐ Medium |
| 16 | Winchester | ⏳ Pending | 20-30 | ⭐ Low |
| 17 | Marlin | ⏳ Pending | 15-25 | ⭐ Low |

**Phase 1 Complete:** Beretta (40 records), Colt (58 records), HK (58 records) = **156 total records**

---

## Contribution Guidelines

See [CONTRIBUTION_GUIDE.md](./CONTRIBUTION_GUIDE.md) for detailed steps to add new manufacturers.

### Quick Start:
1. Fork repository
2. Create feature branch: `git checkout -b add/[manufacturer]`
3. Add 4 CSV files in `data/[mfgr_lowercase]/` directory
4. Validate files against schema
5. Submit pull request with brief summary

---

## File Format Requirements

**All CSV files must:**
- Use UTF-8 encoding
- Include header row
- Use comma delimiter
- Escape commas in values: `"Smith, John"`
- Use YYYY-MM-DD date format
- Decimal notation for numbers (no commas in decimals: 4.25, not 4,25)

---

## Contact & Support

- **Repository:** https://github.com/yh8jb22jds-svg/PassportFitSearch
- **Owner:** yh8jb22jds-svg
- **Questions/Issues:** GitHub Issues

---

**Last Updated:** 2026-07-25  
**Database Version:** 1.0  
**Status:** Active Development
