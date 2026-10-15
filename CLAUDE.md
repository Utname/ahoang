# Fikko market – notes for Claude

Nette 3 + Latte 3 + PHP 8.2 admin/e-shop for a Czech grocery chain. Most work is the
**price label printing** in the admin. README.md is unedited GitLab boilerplate.

## Where things are
- `app/AdminModule/Products/` – product list (`templates/default.latte`, big), label print
  handlers in `Traits/ProductLabelsTrait.php`, inline price save in `Traits/ProductPricesTrait.php`
- Product list = design "Admin - Produkty v2": each row is `__components/ProductLabels/ProductLabelsControl`
  with three snippets (`:price` Cena/Akce cells, `:chips` Štítky k tisku, `:panel` Možnosti tisku);
  its `render()` outputs all three for ajax redraws (`redrawAll()`). Styles `.pv-*` in custom.css,
  behaviour at the end of custom.js, texts `admin.pv.*`. Phones (≤767px, no design for it): CSS only
  (`@media` after `.pv-missing`) turns each row into a card via grid areas – the cells are found by
  position (`[id$="-price"] > .pv-cellbtn:first-child/last-child`), so keep that markup order
- Phones in the rest of the admin (end of custom.css): every `mosaic-table-compact` list becomes cards –
  custom.js `tableLabels()` copies the header text (or icon tooltip) to each cell's `data-label` and marks
  the first named cell `mt-title`; `td.actions` and the bulk checkbox sit in the card's top right.
  Other `table-compact` tables scroll sideways. Tisk z mobilu: list cards are a swipe strip on phones.
  The last block of custom.css compacts the whole frame on phones (52px header, smaller type/gaps);
  old theme rules use `!important` + `table.table` selectors, so overrides need the same weight.
  Inputs are 32px/13px: @layout.latte adds `maximum-scale=1` on iOS only (else iOS zooms into inputs
  under 16px). Modals and edit forms keep col-*-3..6 at 50% (two short fields per line) unless the
  column holds a nested row, textarea, editor, input-group/button or the big name input.
  Modal footers (`.modal-footer`, its `.btns`, `.pv-editor-foot`, `.pp-foot`) wrap on phones – a single
  flex line pushed "Uložit" off the screen. CKEditor is 4.4.7, which disables itself on Android
  (`isCompatible`); `initWSW()` forces it on, otherwise Android shows raw HTML in the editors.
  Sidebar ≤991px (off-canvas, modern.js MOBILE_BREAKPOINT): sub-menus open in place (no hover flyouts,
  modern.js skips placing them), backdrop `.page-sidebar.visible ~ .page-inner::after`, a tap outside closes.
  The loaded Font Awesome is "6 Pro" (plugins/fontawesome) – "6 Free"/"5 Pro" light in CSS draw boxes.
  Phone audit (Playwright, Galaxy S8 360px + iPhone 13): every menu page, first edit page + tabs,
  modals, front pages, /admin/mobil – check overflow, controls cut by a non-scrolling parent, tiny taps
- Search while typing: the filters form goes by ajax, `ProductsPresenter` onFilterChange redraws only
  `productCount` + `productList` (`recountPaginator`: the paginator was created before the list).
  Name filter looks EAN/codes up first (indexes from `bin/migrate.php`); 8+ digits = EAN prefix only.
  The `:panel` part is rendered only for an open row; others load it on first open
  (`.pv-lazy`, `handleLoadPanel`) – it was ~90 % of the list HTML.
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
After editing a template run `php bin/sync-print-templates.php [name ...]` locally; on Railway
`docker/entrypoint.sh` runs it on every container start, so a deploy syncs the templates
(edits made in the admin template editor are overwritten by the next deploy).

| file | type ids (`ProductLabelsEntity::TYPES_ARR`) | size mm | design file |
|---|---|---|---|
| a4-discount | 1 A4, 10 A4 Akce | 210×297 | A4 / A4 Akce |
| a5-discount | 2 A5, 11 A5 Akce | 210×148.5 | A5 / A5 Akce |
| vel1 | 3 | 164×41 | vel. 1 |
| vel2 | 4 | 133×41 | vel. 2 |
| vel3 | 5, 12 Vel. 3 Akce | 182×56 | vel. 3 / vel. 3 Akce |
| vel2b-akce | 6 Vel. 2B Akce, 14 Vel. 2 B, 16 Vel. 2B Složení (167×52) | 133×52 | vel. 2B Akce / velikost 2B / vel. 2B Slozeni |
| vel2-akce | 7 Vel. 2 Akce, 15 Vel. 2 Složení (167×38) | 133×38 | vel. 2 Akce / vel. 2 Slozeni |
| pecivo | 8 | 150×41 | Pecivo |
| fruit | 9 | 50×35 | Etiketa ovoce vaha (32 per A4 sheet) |

Type 13 Vel. 1 B has no design yet: listed but locked (`isLockedType()`). A 167 mm row renders
the same file with a Složení column (template picks it by `width_mm`). Sheet stacking for every
size comes from `PrintTemplatesService::sheetLayout()`. `bin/migrate.php` adds the new columns
(products.rozmrazeno, products.price_unit, product_labels.ingredients/price_unit); entrypoint runs it.
Designs: `C:\Users\adsf\Downloads\design_handoff_fikko\` – `CACH-LAM-CODE.md` is authoritative.
The mapping table in `QUY-TAC-THIET-KE-MAU-IN.md` is outdated.

## Label type rule (CACH-LAM-CODE.md §3)
- **AKCE_30**: lowest price of last 30 days > sale price → "Nejnižší cena za 30 dní" struck
  through, %, "Akce platí do"
- **SROVNÁNÍ**: "Běžná cena" (no strike), "Ušetříte … oproti běžné ceně"; no %, no "Akce"
- **ZÁKLAD**: only "Naše cena"
- The chosen template decides (`renderHtml()`, group in TYPES_ARR): **akce** templates print AKCE_30
  whenever there is an action price (30-day low, or the regular price when there is none – the
  user chooses this), **std** templates never AKCE (SROVNÁNÍ only within the action dates), **spec**
  apply the rule automatically within the dates. Label `sale_percent` 0 = computed; >0 = typed in ✎.
- "Běžná cena" (SROVNÁNÍ) = the regular price saved/typed in the row (`product_prices.price`), never
  `getPriceWithLog()`'s log reference (the shop's own earlier price) – that one is only the 30-day low.
- Header (`product_label_headers`) is shared by the product's labels: A4/A5/Vel.3 title bar, narrow templates print it
  word by word in the brand column. Akce templates start with "Akce !!!", others "Fikko Cena".
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
- Country flags (countries.image) are square icons with transparent bands: `countryFlagImages()` trims
  them — `flagIconDataUri` square for round flags, `flagRectDataUri` for rectangles.
- Multi-label PDFs (Náhled všech, queue print) are ONE document: templates share class names, so
  `renderProductLabelsPdf()` scopes each template's CSS (`scopeTemplateHtml()`). Keep it when changing
  that path; a single-label preview can look right while the combined one is broken.
- Floated barcode bars get misplaced (a thin bar lands on the gap before it → phones read another
  EAN): templates draw only the dark bars of `EanBarcodeHelper::getBars()`, absolutely positioned
  at `left`, in a positioned container. Check a label's barcode by decoding the PDF's rectangles.
  Bar module (`renderHtml()` 'bars'): keep ≥ 0.26 mm (EAN minimum) with ~3 mm free beside the bars.
  On a screen at 100 % a module is ~1 px and phones misread it: preview links open `#zoom=115`
  (`pdfViewZoom` in custom.js; the user chose 115 % over fit-to-width).
- `max-height`/`overflow:hidden` do not clip text → long Složení is shrunk and `|truncate`d in the template.

## Výkup lahví (bottle/crate returns)
Design: `design_handoff_fikko/Vykup lahvi.dc.html` + `vykup-lahvi/README-TICH-HOP.md`.
`app/AdminModule/Vykup/` (presenter, templates `_nav`, `_voucher` = 80 mm voucher, `print` = own page
that prints itself), rules in `app/Model/Vykup/VykupService.php`, EAN-8 SVG in `VykupEan8.php`.
- Tables `vykup_*` + `branches.vykup_code` (voucher prefix) come from `bin/migrate.php`; items lahev
  00000819 3 Kč, prepravka 00000802 100 Kč (GoKasa must have the same EANs). Company/IČO are
  settings `vykup_company`/`vykup_ico`, edited on the Pracovníci tab.
- Works in one branch from the session, limited to the admin's branches. Times use Europe/Prague
  (`VykupService::now()/today()`), not the DB/PHP clock.
- Voucher no. `<code>-V<ymd>-<seq>` per branch per day; reprint max 2× same day (DUPLIKÁT);
  storno never deletes, restore same day only. Permissions `Vykup_list` / `Vykup_edit` (staff, header).
- Texts `admin.vykup.*`; the voucher itself is always Czech. The presenter has no repository, so it
  overrides `createComponentEditForm()` for `BaseModulePresenter::beforeRender()`.

## Tisk z mobilu (print lists from the phone)
Handoff `C:\Users\adsf\Downloads\predani-tisk-stitku\` (HUONG-DAN.md, html/, kod/). Its SQL/PHP
propose new price tables – NOT used: prices stay in `product_prices` / `product_prices_log`.
- `print_lists` / `print_list_items` (bin/migrate.php): a list per branch (M-001…, waiting/printed/
  deleted = soft); an item = product + label type (TYPES_ARR) + qty + header + `overrides` JSON that
  change only that print (never the product card, never the admin's label queue).
- `app/Model/PrintLists/PrintListsService.php` (lists, items, PDF via `renderProductLabelsPdf()`);
  `PrintListLabelEntity` = a transient ProductLabelsEntity (ActiveRow built from an array, product /
  header / country given up front – no ref()). Typed sale price in ✎ = running action for that print.
- `AdminModule/PrintLists` page: rows reuse the Produkty markup (.pv-row/.pv-pfields/.pv-hdr) so
  "Cena a akce" runs on the same JS and saves the card through `ProductPriceSaver` (shared with
  ProductPricesTrait). `print` action = PDF in an iframe + print dialog, marks the list printed.
- Náhled tisku v3 = `__components/PrintPreview/printPreview.latte`, data `$pp` built by
  `ProductLabelsTrait::buildPrintPreview()` (queue) and `PrintListsPresenter::printPreview()` (list);
  card selects must have `select-default` (no select2). Menu badge: `MenuGenerator::setBadges()`.
- Lists come from three places: the phone app (source mobile, M-…), "+ Nový seznam" on the PC page
  (P-…, rows added by EAN/name autocomplete) and "Poslat do Tisk z mobilu" in Náhled tisku on Produkty
  (`handleQueueToPrintList()` → `createFromQueue()`: label texts become overrides, queue counts → 0).
- Phone app `/admin/mobil` (`AdminModule/Mobil`): standalone page, no admin layout, uses the
  PrintLists permissions (`setActualModuleId('PrintLists')`). The presenter builds a JSON config
  (types, headers, countries, units, urls, texts `admin.mob.*`); `www/js/admin/mobil.js` +
  `www/css/mobil.css` (vanilla JS) do the rest. The queue lives in localStorage until "Odeslat"
  (`handleSend`, raw JSON body → `PrintListsService::create()`). Other JSON signals: search, byCode,
  products, savePrice (`ProductPriceSaver`), lists, stats, preview (`pdfFromItems()`, unsent queue).
  An unknown scanned code opens "Nový produkt" (with the Products_create right): `handleCreateProduct` →
  `ProductQuickCreator` (multipart POST: code/EAN, name in all languages, category, country, photo –
  shrunk to 1600 px on the phone, upload limit 2 MB –, package/unit price basis, label notes, ingredients,
  thawed, consumption, perex; price/action for the branch or all the admin's branches via
  ProductPriceSaver); then the templates sheet (quick mode: 1 label at once). Railway has no volume for
  www/files: uploaded photos are lost on the next deploy.
  Camera: `BarcodeDetector`, else ZXing from unpkg; it needs https (works on Railway, not on
  http://localhost from a phone). Fake-camera test: Chromium `--use-file-for-fake-video-capture`.
  Real phone locally: the dev server listens on `[::1]` only, so run a second one on
  `127.0.0.1:8080`, then `adb reverse tcp:8080 tcp:8080` and open http://localhost:8080/admin/mobil
  (localhost = secure, camera works); `adb forward tcp:9222 localabstract:chrome_devtools_remote`
  lets Playwright `connectOverCDP` inspect the phone's Chrome. Scanning decodes only the part of
  the frame shown in the box and needs the same code twice within 1.5 s.
- Akce templates still print after the action ended (user's choice, same as Produkty).

## Front (public web)
Look of the template `C:\FreeBlancer\landwind` (Tailwind/Flowbite "Landwind") in Fikko orange, written as
plain CSS `www/css/front/landwind.css` (classes `lw-*`, no build step) on top of the old Bootstrap/style.css.
Header (`__inc/header/top.latte`: fixed navbar, home slider as hero), footer, and the content blocks
CATEGORIES / TEXT_IMAGE / FORM_WITH_BRANCHES / MAP / TEXT use it, home blocks follow the template's sections
(feature rows with check lists, stats, pricing cards = branches, CTA = contact form). Sub-pages: page title
from BreadcrumbsControl (`lw-page-head`), texts in `lw-article` (inside `lw-container`), Branches list/detail
and Error rebuilt; product/Akce lists and blog (pages switched off) only restyled in CSS (`body.lw …`).
TEXT blocks go through `app/Model/Front/ArticleLayout.php`: the editor HTML becomes intro, title+text
sections, feature cards ("Label: text" lines), check-list tiles, job cards (bold title + "Požadujeme:" /
"Benefity:" groups, phone/e-mail → buttons) and a contact block; unstructured text stays `lw-article`.
Content pages show their meta image next to the title (Breadcrumbs), not as the header band.
Motion: `www/js/front/landwind.js` + the motion part of landwind.css (scroll reveal: text from the left,
images from the right, cards pop in turn, list rows slide; hero entrance; `data-count` count-up; card tilt
+ light sweep). It runs also with prefers-reduced-motion (the user's Windows has "Show animations" off):
`html.lw-calm` keeps the entrances, drops loops/tilt. Side entrances widen the page → sections clip overflow-x. Header language picker = languages active + in menu (admin Jazyky; flags in
`www/images/front/flags/<id>.svg`). Staff login on the website: `BaseStaffLoginTrait` reads the admin session
("adminModule"), "Přihlásit" → `/admin/sign/in?back=<path>` (Sign:in keeps a same-site path), `staffSignOut!`.
The navbar uses `/images/front/logo-color.png` (the admin logo is white text for dark backgrounds).
JS hooks: `.lw-toggle`/`.lw-menu` (script.js), section scrolling subtracts `#lw-header` height (custom.js).
`getMenuBlocksItems()` grows with every call – dedupe when listing it twice.
Phones (≤639px): the last block of landwind.css – denser sections, smaller type, the home branch cards
(`.lw-branch-strip`) are a strip to swipe. `ArticleLayout::fixLinks()` also drops blank `<p>&nbsp;</p>`.
The VI|CS Google Translate script in the front/login layouts has a Latte comment inside
`n:syntax="off"` → "Unexpected token '*'" in the console; fixing it would switch on the
auto-translation to Vietnamese for every first visit (also live) – ask before touching it.

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

## Search scope and big files
`.ignore` files skip `www/plugins/` (Font Awesome etc.), SQL dumps in `app/database/`, binary
assets and `*.min.*`. Don't open those unless a task needs them.

These are large; Grep for the spot first, then Read only that range (offset/limit):
`www/css/admin/custom.css` (~36k tokens), `www/css/admin/modern.css` (~22k),
`app/AdminModule/Products/templates/default.latte` (~18k), `www/js/admin/custom.js` (~16k),
`app/AdminModule/ProductLabels/templates/default.latte` (~15k), `app/locale/admin.*.neon` (~13k each),
`PrintTemplatesService.php` and `ProductLabelsService.php` (~10k each).
Locale files are nested NEON (tabs and, in places, spaces): add keys under the right section.
New queue labels: `ProductLabelsRepository::insertForProduct()`.
Label texts are shared per product (✎ edits all templates at once): `ProductLabelsRepository::saveShared()`
writes `SHARED_FIELDS` to every label of the product and to its `type_id` 0 row, which new labels copy.
The header is shared too: `setHeaderForProduct()` (chip/card select, or a header changed in ✎) sets it on
every template, creating count-0 labels where missing. Only `count` is per template. The row control
reads labels with `ProductLabelsControl::getLabels()` (fresh query), not `$product->getProductLabels()`.
