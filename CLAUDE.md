# Fikko market – notes for Claude

Nette 3 + Latte 3 + PHP 8.2 admin/e-shop for a Czech grocery chain. Most work is the
**price label printing** in the admin. README.md is unedited GitLab boilerplate.

## Where things are
- `app/AdminModule/Products/` – product list (`templates/default.latte`, big), label print
  handlers in `Traits/ProductLabelsTrait.php`, inline price save in `Traits/ProductPricesTrait.php`
- `app/Model/ProductLabels/ProductLabelsService.php` – `generatePdf()`: queue → PDF; new HTML
  templates via Dompdf, otherwise the legacy TCPDF layouts in the same file
- `app/Model/PrintTemplates/PrintTemplatesService.php` – `renderHtml()` (price/discount logic,
  template variables), `renderProductLabelsPdf()` (stacks labels on A4)
- `app/Model/PrintTemplates/templates/*.latte` – one template per label size
- `app/AdminModule/ProductLabels/templates/barcodeFruit.latte` – fruit label (tab4), browser print
- `app/locale/admin.{cs_CZ,vi_VN,en_US}.neon` – translations, keys `admin.<section>.<key>`
- `www/js/admin/custom.js`, `www/css/admin/custom.css` – admin JS/CSS
- `config/*.neon` – `production.neon` is used when `APP_ENV=production` (Railway)

## Label templates
Labels render from `print_templates.html_source` in the DB, **not** from the .latte file.
After editing a template run `php bin/sync-print-templates.php [name ...]`.

| file | type id | size mm | design file |
|---|---|---|---|
| a4-discount | 1 A4 | 210×297 | A4 / A4 Akce |
| a5-discount | 2 A5 | 210×148.5 | A5 / A5 Akce |
| vel1 | 3 | 164×41 | vel. 1 |
| vel2 | 4 | 133×41 | vel. 2 |
| vel3 | 5 | 182×56 | vel. 3 / vel. 3 Akce |
| vel2b-akce | 6 | 133×52 | vel. 2B Akce |
| vel2-akce | 7 (hidden in admin) | 133×38 | vel. 2 Akce |
| pecivo | 8 | 150×41 | Pecivo |
| fruit | 9 | 50×35 | Etiketa ovoce vaha (32 per A4 sheet) |

A4 uses the new template only for products ticked "Nová šablona", otherwise legacy TCPDF.
Designs: `C:\Users\adsf\Downloads\design_handoff_fikko\` – `CACH-LAM-CODE.md` is authoritative.
The mapping table in `QUY-TAC-THIET-KE-MAU-IN.md` is outdated.

## Label type rule (CACH-LAM-CODE.md §3)
- **AKCE_30**: lowest price of last 30 days > sale price → "Nejnižší cena za 30 dní" struck
  through, %, "Akce platí do"
- **SROVNÁNÍ**: "Běžná cena" (no strike), "Ušetříte … oproti běžné ceně"; no %, no "Akce"
- **ZÁKLAD**: only "Naše cena"
- Known gaps (not fixed yet): type is driven by the label's stored `sale_percent`; sales outside
  their dates still print the sale price; A4 prints "Platnost" on non-AKCE labels.
- Texts printed on labels stay Czech in every admin language.

Print links send unsaved row values as `priceOverrides` JSON {productId: {price, price_action,
action_from, action_to}} (custom.js → `ProductLabelsTrait::parsePriceOverrides()`); nothing is saved.

## Dompdf (v3) quirks that bit before
- Ignores `box-sizing`: borders/padding are added outside width/height.
- No flexbox/grid → templates use absolute positioning; tables ignore `height` (set it on cells).
- `line-height: 0` puts the baseline at the block top → used to place text exactly.
- Latte strips `data:` URIs in `src` → use `|nocheck`; decimals in `style` get escaped →
  `{=round($x, 3)|noescape}`; no `<`/`>` inside CSS comments (breaks the style block).
- `brush-20.png` renders white in large boxes → `ribbonBadgeBrushDataUri`.

## Run / test
- Local: http://localhost, MariaDB db `website` user root/toor
  (`C:\Program Files\MariaDB 12.3\bin\mysql.exe`). Clear cache: `rm -rf /c/FreeBlancer/ahoang/temp/cache/*`.
- For browser tests create a temporary admin (`admin_users`, role 1, all branches) and delete it after.
- Label PDF URL: `/admin/products/?productsId=ID&typeId=N&isDownload=0&do=downloadSingleProductLabelPdf`.
- Never commit unless asked.

## Deploy
Remote `ahoang` = github.com/Utname/ahoang; pushing `master` auto-deploys Railway project
`radiant-education`, service `web` (Dockerfile). DB shell on Railway:
`railway ssh -s web -- mysql --skip-ssl -h "$MYSQLHOST" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" "$MYSQLDATABASE"`.

## Search scope
`.ignore` files skip `www/plugins/` (Font Awesome etc.), SQL dumps in `app/database/`, binary
assets and `*.min.*`. Don't open those unless a task needs them.
