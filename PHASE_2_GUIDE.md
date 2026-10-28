# PHASE 2: CONTINUATION GUIDE

## Quick Reference for Creating Remaining Manufacturers

### COMPLETED (Phase 1 - 156 Records)
- Beretta: 40 firearms
- Colt: 58 firearms  
- Heckler & Koch: 58 firearms

### NEXT PRIORITY (Phase 2)

#### 1. SPRINGFIELD ARMORY (SA)
**Est. 45-55 records**
- 1911 Defender, Standard, TRP Operator
- XD-S Mod.2 (9mm, .45 ACP variants)
- Hellcat, Hellcat Pro
- XD Full Size, Mod.2 variants
- Website: www.springfieldarmory.com

#### 2. SIG SAUER (SIG)  
**Est. 60-70 records**
- P226, P229, P320 series
- P365, P365XL, P365SAS
- P238, P938 (micro)
- Website: www.sigsauer.com

#### 3. GLOCK (GLOCK)
**Est. 40-50 records**
- G17-G48 Gen 5 series
- Subcompact variants
- Website: www.glock.com

### FILE STRUCTURE PER MANUFACTURER

```
data/[manufacturer]/
├── YYYY-MM-DD_firearm_master_[mfgr]_r1.csv
├── YYYY-MM-DD_search_alias_master_[mfgr]_r1.csv
├── YYYY-MM-DD_firearm_master_[mfgr]_sources_r1.csv
└── YYYY-MM-DD_firearm_master_[mfgr]_fit_review_r1.csv
```

### PASSPORT FIT VERIFICATION

The [PHolsters size chart](https://pholsters.com/size-chart/) is the source of truth.
Do not assign a fit from barrel length alone or copy proposed fits from review reports.

- Set `fit_verified=1` only with an explicit chart listing or PHolsters approval.
- Keep `passport_fit` blank and `fit_verified=0` for unconfirmed models and variants.
- Preserve multiple chart-listed fits as separate, clearly labeled choices.
- Review-report decisions such as "Approved" do not verify importer master fits.
- Keep generation and MOS identities separate unless their fit is explicitly verified.

### UPLOAD TO GITHUB

1. Go to: https://github.com/yh8jb22jds-svg/PassportFitSearch
2. Click "Add file" > "Create new file"
3. Enter path: `data/[mfgr]/filename.csv`
4. Paste CSV content
5. Commit with message: "Add [Manufacturer] database - Phase 2"

### CSV VALIDATION CHECKLIST

BEFORE uploading, verify each file:

**Firearm Master:**
- [ ] All IDs unique (MFG-######)
- [ ] Barrel lengths numeric (3.0, not "3 inches")
- [ ] Passport fit has explicit chart or PHolsters approval; otherwise blank
- [ ] Active/discontinued logic correct

**Search Alias:**
- [ ] All IDs unique (MFG-ALS-######)
- [ ] 3-5 aliases minimum per firearm
- [ ] Normalized alias is lowercase version

**Source Reference:**
- [ ] URLs valid and accessible
- [ ] Source type: "Official [Mfgr]" or "Public Reference"
- [ ] Column name: [mfgr_lowercase]_source_url

**QA Report:**
- [ ] Proposed fits remain review material and are not imported as confirmed
- [ ] Comparable models from completed mfgrs
- [ ] Confirmed master rows have explicit fit_verified and supporting approval

### ESTIMATED TIME
- Research: 30-45 min
- Firearm Master: 30-45 min
- Aliases: 45-60 min
- Sources: 30-45 min
- QA Report: 30-45 min
- **Total: 2.5-4 hours per manufacturer**

### KEY CONTACT
Repository: https://github.com/yh8jb22jds-svg/PassportFitSearch
Branch: main
Status: Phase 2 Ready to Begin
