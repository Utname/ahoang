# Quy tắc thiết kế mẫu in Fikko

Danh sách khổ tem, file template và loại tem (AKCE_30 / SROVNÁNÍ / ZÁKLAD): xem `CLAUDE.md`.
Nguồn chuẩn về thiết kế và luật: `design_handoff_fikko/CACH-LAM-CODE.md` cùng các file `.dc.html`.

## 1. Thiết kế là nguồn chuẩn

- File `.dc.html` đã duyệt là chuẩn về khổ giấy, lề, vị trí, font, màu và khoảng cách.
- Không sửa file thiết kế gốc. Template Latte nằm trong `app/Model/PrintTemplates/templates/`.
- Tem được render từ `print_templates.html_source` trong DB: sửa `.latte` xong phải chạy
  `php bin/sync-print-templates.php <tên>`.
- Khai báo khổ mới theo `product_label_type_id`, không theo tên hiển thị.

## 2. Biến dữ liệu trong template

| Nội dung | Biến |
| --- | --- |
| Tên sản phẩm | `{$labelName}` |
| Giá thường / giá 30 ngày | `{$basePriceText}`, `{$lowestPrice30Text}` |
| Giá bán | `{$finalPriceMajor}`, `{$finalPriceMinor}`, `{$currencySymbol}` |
| Loại tem | `{$isAkce30}`, `{$isDiscounted}`, `{$hasSaleRibbon}`, `{$salePercent}` |
| Giá theo đơn vị | `{$unit->unitWeight}`, `{$unit->unitShort}`, `{$unit->price}` |
| Xuất xứ | `{$country->name}`, `{$flagIconDataUri}` |
| Nhiều chủng loại | `{$label->is_more_species}` |
| Platnost | `{$validityText}` (ưu tiên `sale_text`, sau đó ngày khuyến mãi) |
| Poznámka 1/2/3 | `{$note1}`, `{$note2}`, `{$note3}` |
| EAN | `{$eanText}`, vạch `{$bars}` (mọi khổ đều in mã vạch) |
| Tiết kiệm | `{$savingText}` khi `{$saving > 0}` |
| Thành phần | `{$product->ingredients}`, ẩn khi `{$hideIngredients}` |

Không tự điền nội dung giả: trường không có dữ liệu thì ẩn bằng `n:if`.

## 3. Dompdf: các lỗi đã gặp và cách xử lý

- Đơn vị luôn là `mm`. Không có flexbox/grid: dùng `position:absolute` và `table`.
- Bỏ qua `box-sizing:border-box`: border/padding cộng thêm vào `width`/`height`, phải tự trừ.
- `line-height:0` đặt baseline đúng ở mép trên khối, dùng để đặt chữ chính xác theo baseline.
- Font subsetting làm Nunito hiện thành ô vuông: `isFontSubsettingEnabled` phải để `FALSE`
  (trong `PrintTemplatesService::createDompdf()`).
- `%`/`inset:0` trong box tự co theo nội dung tính sai: cho box `width` tường minh (tính theo
  độ dài chữ, xem `$saveW` trong các template).
- CSS `background-image` với PNG có alpha 8-bit không hiện: dùng `<img>`, hoặc bản ảnh đã
  flatten lên nền trắng (`ribbonBadgeBrushFlatDataUri`).
- `brush-20.png` ở box lớn bị thấp một nửa hoặc thành trắng: dùng `ribbonBadgeBrushDataUri`.
- Box chỉ có `width` trong inline style và toàn con `absolute` có thể bị co về 0: để một
  `width` dự phòng trong stylesheet.
- Latte: data URI trong `src` cần `|nocheck`; số thập phân trong `style` cần
  `{=round($x, 3)|noescape}`; không viết `<` `>` trong comment CSS.

## 4. Dữ liệu dài không được làm vỡ mẫu

- Tên dài: xuống dòng (A4/A5) hoặc cắt `…` (Vel.x), không chồng chữ.
- Giá 3–4 chữ số: thu nhỏ cỡ số để không vượt mép.
- Ô `Ušetříte`: nền cọ co giãn theo nội dung.

## 5. Quy trình trước khi áp dụng

1. Đọc file thiết kế, xác định kích thước mm, asset, vùng dữ liệu.
2. Sửa template, sync vào DB.
3. Render PDF qua web thật (không chỉ xem HTML) và mở bằng trình xem PDF của Chrome (PDFium):
   pdf.js có lúc hiện đúng trong khi PDF thật bị lỗi.
4. Kiểm tra ít nhất: tên dài, giá 3 chữ số, tem có khuyến mãi và Poznámka/Platnost.
