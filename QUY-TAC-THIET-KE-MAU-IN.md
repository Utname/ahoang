# Quy tắc thiết kế mẫu in Fikko

Áp dụng cho mọi mẫu mới sau A4 và Velikost 2B.

## 1. Thiết kế là nguồn chuẩn

- File `.dc.html` đã duyệt là chuẩn tuyệt đối về khổ giấy, lề, vị trí, font, màu và khoảng cách.
- Không sửa file thiết kế gốc. Tạo template Latte động riêng trong `app/Model/PrintTemplates/templates/`.
- Mỗi mẫu mới phải có bản sao template trước khi chỉnh sửa lớn.

## 2. Mapping khổ in

| Khổ | Mapping | Cách in |
| --- | --- | --- |
| A4 | `width_mm=210`, `height_mm=297`, loại nhãn A4 | 1 nhãn trên 1 trang A4 |
| Velikost 2B | `width_mm=133`, `height_mm=52`, loại nhãn Size 2 | 2 nhãn giống nhau trên 1 trang A4, có một đường phân cách ở giữa |
| A5 | `width_mm=200`, `height_mm=138.5`, loại nhãn A5 | 2 nhãn giống nhau trên 1 trang A4 (mỗi nhãn 148.5mm cao, khớp 2×148.5=297), có một đường phân cách ở giữa |
| Vel.1 | `width_mm=150`, `height_mm=44`, loại nhãn Size 1 | 3 nhãn giống nhau trên 1 trang A4, xếp dọc, lề trang 6mm, không có khoảng cách giữa các nhãn (viền chấm tự chồng lên nhau đúng như file gốc) |
| Vel.3 | `width_mm=182`, `height_mm=56`, loại nhãn Size 3 | 2 nhãn giống nhau trên 1 trang A4, xếp dọc, lề trang 8mm, cách nhau 2mm |
| Khổ mới | Đúng kích thước mm từ file thiết kế | Khai báo mapping theo `product_label_type_id`, không map theo tên hiển thị |

## 3. Token dữ liệu chuẩn

| Nội dung trên mẫu | Token / nguồn |
| --- | --- |
| Tên sản phẩm | `{$labelName}` |
| Giá thường | `{$basePriceText} {$currencySymbol}` |
| Giá khuyến mãi | `{$finalPriceMajor}`, `{$finalPriceMinor}`, `{$currencySymbol}` |
| Giá theo đơn vị | `{$unitText}`, `{$unit->unitWeight}`, `{$unit->unitShort}` |
| Xuất xứ | `{$country->name}` và `{$flagIconDataUri}` |
| Nhiều chủng loại | `{$label->is_more_species}` |
| Platnost | `{$validityText}`; lấy ưu tiên từ `sale_text`, sau đó từ thời hạn khuyến mãi |
| Poznámka 1/2/3 | `{$label->note_1}`, `{$label->note_2}`, `{$label->note_3}` |
| EAN dạng chữ | `{$eanText}` |
| Mức tiết kiệm | `{$savingText} {$currencySymbol}` khi `{$saving > 0}` |
| Ruy băng phần trăm giảm giá (kiểu "Nejnižší cena za 30 dní") | `{$salePercent}` (số nguyên, vd 20), hiện khi `{$hasSaleRibbon}`; lấy từ `label->sale_percent` do admin nhập khi tạo phiếu in, hoặc tự tính từ tỉ lệ giảm giá nếu xem trực tiếp không qua phiếu in |
| Thành phần / Složení | `{$product->ingredients}` (cột `products.ingredients`, có sẵn trong DB) |

Không tự điền nội dung giả. Trường chưa có dữ liệu thì ẩn bằng `n:if`.

**Ngoại lệ tạm thời — badge "Rozmrazeno" ở Vel.1**: thiết kế gốc có một badge tĩnh "đã rã đông", nhưng hệ thống chưa có cột dữ liệu boolean tương ứng (đã kiểm tra `ProductsEntity`/`ProductLabelsEntity`, không có field nào kiểu `is_thawed`/`frozen`). Đang tạm dùng **sự tồn tại của `label->note_3`** làm cờ bật/tắt badge này (nhập bất kỳ giá trị vào Poznámka 3 của phiếu in Vel.1 sẽ hiện badge, nội dung note_3 không hiển thị ra tem). Nếu cần tách bạch rõ ràng, nên thêm cột dữ liệu riêng (ví dụ `product_labels.is_thawed`) rồi thay thế cách dùng tạm này.

## 4. Quy tắc bố cục in

- Dùng `mm` cho mọi kích thước và tọa độ, không dùng pixel.
- Ưu tiên `position:absolute` và `display:table` cho PDF. Dompdf không render ổn định `flex`.
- A4, A5, Vel.1 và Vel.3 giữ EAN dạng chữ, không in barcode — file `.dc.html` của các khổ này đều có sẵn code sinh barcode (`sc-for`/`bars`, hạ tầng chung của design tool) nhưng đó không phải là artwork đã duyệt để in ra (mở file gốc trực tiếp, không qua runtime của tool thiết kế, thì vùng barcode chỉ là khoảng trống, không có vạch). Hiện chỉ Velikost 2B thực sự in barcode. Khổ mới chỉ in barcode khi có xác nhận rõ ràng đó là artwork đã duyệt, không suy đoán từ việc code có sẵn hàm sinh barcode.
- Asset được nhúng qua data URI. Với Latte, ảnh data URI cần xuất HTML an toàn bằng `|noescape`; nếu không logo/icon có thể bị rỗng.
- Chỉ dùng font đã đăng ký trong Dompdf, đặc biệt Nunito cho giá và chữ thương hiệu.
- **Dompdf's font SUBSETTING làm hỏng font Nunito hoàn toàn (P0 nghiêm trọng nhất từng phát hiện)**: mọi chữ dùng `font-family:Nunito` (giá, "FIKKO CENA", badge Ušetříte...) render thành ô trống (tofu/`.notdef`) trên PDF thật xuất ra, ở MỌI kích thước font (đã test 72mm→5mm), MỌI khổ (A4/A5/Vel.1/Vel.2B/Vel.3 — tái hiện y hệt trên cả 5), kể cả trường hợp tối giản nhất (`<div style="font-family:Nunito;font-weight:bold;font-style:italic">39,90</div>` không qua bất kỳ code nào của app). Đã cô lập bằng cách bật tắt `Options::setIsFontSubsettingEnabled()`: `true` (mặc định) → tofu; `false` → chữ hiện đúng hoàn toàn, không đổi gì khác. Đã loại trừ: không phải do DOM surgery riêng của A4 (so sánh HTML trước/sau `stripInlineA4Barcode()` giống hệt nhau), không phải do cỡ chữ quá lớn, không phải do đăng ký font sai key (`registerFont()` với `weight=>900` tạo key `"900_italic"` hợp lệ theo đúng logic `FontMetrics::getType()`, không phải bug). Cả 2 file `Nunito-BlackItalic.ttf` và `Nunito-RegularItalic.ttf` đều bị (là bản static cắt từ variable font qua công cụ build "FontKit", theo font name-table). **Fix đã áp dụng**: `$options->set('isFontSubsettingEnabled', FALSE)` trong `PrintTemplatesService::createDompdf()` — làm PDF nặng hơn (label 1-3 trang từ ~200KB lên ~1-1.5MB) nhưng chấp nhận được, và chữ hiện đúng 100% trên cả 5 khổ đã test lại. Lưu ý: lỗi này từng KHÔNG xuất hiện trong một số lần render trước đó của cùng dự án (ví dụ A5 với dữ liệu khác) — có vẻ phụ thuộc dữ liệu/ký tự cụ thể chứ không phải lúc nào cũng trăm-phần-trăm tái hiện, nên **không được coi "test 1 lần ra chữ đúng" là bằng chứng đủ** — luôn kiểm tra với `isFontSubsettingEnabled` đã tắt tường minh, đừng dựa vào cache/may rủi.
- **Phần trăm (`%`) hoặc `inset:0` trên con của một box đang tự co giãn theo nội dung (`position:absolute` không khai `width`, tự "shrink-to-fit") không đáng tin trong Dompdf**: gặp ở badge "Ušetříte" của Vel.3 khi cho phép box tự co giãn rộng theo độ dài `savingText` (không hard-code `width`) — box cha tự đo đúng kích thước khi KHÔNG có ảnh cọ bên trong, nhưng hễ thêm `<img class="save-brush" style="position:absolute;inset:0;width:100%">` làm con thì box cha bị đo RỘNG HƠN THỰC TẾ 20-40mm (bất kể `<img>` là con trực tiếp của box tự co giãn hay con của một `display:table` bên trong nó) — % của `<img>` không tính đúng theo kích thước cha vừa tự đo, mà theo một mốc khác. **Cách sửa đáng tin cậy**: cho box đó `width` TƯỜNG MINH (tính bằng công thức dựa theo độ dài chữ, không hard-code 1 giá trị — xem `$saveBadgeWidthMm` trong `vel3.latte`, hiệu chỉnh qua đo PDF thật với chuỗi rỗng và các độ dài khác nhau), rồi các phần tử con dùng `inset:0`/`width:100%` (cho ảnh nền) hoặc `left`+`right` (không khai `width`, cho khối chữ) bên trong — cả hai cách này đều tính theo "kích thước cha trừ đi phần đã biết", không phải phép đo hai lượt mơ hồ, nên chạy đúng.
- **`background-image` với PNG có kênh alpha 8-bit (color type 6, RGBA) không được Dompdf vẽ ra khi khai qua CSS — không lỗi, không cảnh báo, chỉ im lặng không hiện** (chỉ `background-color` bên dưới hiện ra, nếu có). Đã cô lập tới tận gốc bằng cách chèn debug trực tiếp vào `vendor/dompdf/dompdf`: mọi bước ở phía Dompdf đều "thành công" (data URI resolve đúng, GD buffer compositing đúng — đã dump ra PNG kiểm tra bằng mắt, thấy đúng hình — file tạm tồn tại, tọa độ đúng), nhưng `Cpdf::addImagePngAlpha()` (hàm pure-GD dùng để nhúng ảnh nền có alpha) tự nó có bug đã được chính tác giả ghi chú trong code (`// FIXME The pixel transformation doesn't work well with 8bit PNGs`): biểu thức `$eight_bit = ($byte & 4) !== 4` cho `colorType=6` (RGBA 8-bit, loại phổ biến nhất) ra `false`, rơi vào nhánh xử lý sai khiến ảnh nền trở nên vô hình dù không văng lỗi gì. Một thẻ `<img>` thường (không phải CSS `background-image`) dùng CÙNG file PNG alpha đó lại hiện đúng — vì đi qua code path khác (`Renderer/Image.php`), không phải vì bản thân data URI hay dấu `;` trong nó có vấn đề (giả thuyết trước đây trong bản ghi này là sai, đã tự kiểm chứng lại bằng debug thật, không suy đoán). **Cách sửa đáng tin cậy, đã áp dụng cho badge Ušetříte của Vel.3**: không cố sửa lỗi Dompdf, mà né hẳn nhánh code bị lỗi — làm phẳng ảnh cọ (flatten) lên nền trắng đặc (khớp màu nền thật của tem) thành PNG KHÔNG có kênh alpha (`color type 2`, dùng `imagesavealpha($img, false)` khi xuất bằng GD), rồi dùng file phẳng đó làm `background-image`. Chỉ áp dụng được khi box luôn nằm trên nền phẳng đã biết trước (ở đây là nền trắng của tem) — nếu nền thay đổi theo dữ liệu thì cách này không dùng được, phải cân nhắc `<img>` (kèm rủi ro tràn ở mục 5b) hoặc giải pháp khác.
- **Dompdf bỏ qua `box-sizing: border-box`** trên các box có cả `border` và `padding` cùng `height`/`width` cố định: border+padding bị CỘNG THÊM vào kích thước đã khai, không được trừ vào như trình duyệt thường làm (khác hẳn hành vi Chrome dùng để xem trước HTML). Ví dụ thực tế: `.logo-box` ở Vel.3 khai `height:11mm;border:.4mm;padding:.8mm;box-sizing:border-box` nhưng khi render PDF thật lại cao ~13.3mm (11 + 2×.4 + 2×.8), làm logo cao hơn thanh đen ngay cạnh — dù xem HTML/preview thì đúng 11mm. Không tin `box-sizing:border-box` khi có border+padding+height/width cùng lúc trên Dompdf: phải tự trừ ngược, khai `height`/`width` bằng đúng phần nội dung (content-box), để border+padding cộng vào ra đúng tổng kích thước mong muốn. Luôn kiểm chứng bằng đo pixel trên PDF thật (không chỉ so ảnh chụp bằng mắt) khi nghi ngờ sai lệch vài mm.

## 5. Dữ liệu dài phải không làm vỡ mẫu

- Tên sản phẩm sang 2 dòng: đẩy đường kẻ và các khối phía dưới xuống; không được chồng chữ.
- Giá từ 3 chữ số: giảm cỡ số và canh lại phần thập phân/đơn vị để không vượt mép.
- Badge `Ušetříte`: nội dung phải co giãn theo giá trị, nền cọ phải rộng đủ và cách phần giá lớn.
- Poznámka 2/3 không xuống dòng trừ khi thiết kế gốc quy định nhiều dòng.

## 5b. BẮT BUỘC: kiểm chứng bằng trình xem PDF thật (PDFium), không chỉ pdf.js

**Phát hiện quan trọng nhất từ đợt sửa Vel.3 (2026-09-24)**: mọi lần render/đo/chụp PDF trong dự án này từ trước tới nay đều dùng `pdf_to_png.js` (thư viện **pdf.js** của Mozilla, vẽ ra `<canvas>`). Với 1 lỗi cụ thể — `<img>` `position:absolute;inset:0;width:100%` bên trong 1 box `position:absolute` khác — pdf.js hiển thị ĐÚNG (không tràn), nhưng **chính file PDF đó**, mở bằng trình xem PDF gốc của Chrome (PDFium — đúng cái người dùng thật nhìn thấy khi bấm "in"/"tải xuống"), lại hiển thị SAI (ảnh tràn ra hàng chục mm, đè lên nội dung bên cạnh). Đã tốn rất nhiều thời gian vì liên tục "xác nhận đã sửa xong" dựa trên pdf.js trong khi hệ thống thật vẫn lỗi.

- **Từ nay, MỌI kết luận "đã sửa đúng" đều phải kiểm chứng lại bằng `pdf_screenshot_headed.js`** (mở PDF thật trong Chrome, dùng trình xem PDF gốc/PDFium) — không được chỉ tin `pdf_to_png.js` một mình cho bất kỳ phần tử nào có `<img>` lồng trong box `position:absolute`.
- Lỗi tràn này **không liên quan đến ảnh cọ brush cụ thể nào** (đã thử: đổi màu ảnh, bỏ z-index, thêm `overflow:hidden`, bỏ `transform:rotate()` — không cái nào hết lỗi) — chỉ hết khi **bỏ hẳn thẻ `<img>`** khỏi markup. Ban đầu đã tạm thay bằng `background-color` phẳng (mất hẳn hoa văn cọ), nhưng đó KHÔNG phải giải pháp cuối cùng: nguyên nhân ảnh nền không hiện qua CSS là một bug alpha-PNG riêng của Dompdf (xem mục 4, dòng về `background-image`/`addImagePngAlpha`), và đã sửa đúng gốc bằng cách dùng ảnh nền đã flatten hết kênh alpha — kết quả vừa giữ được hoa văn cọ, vừa né được cả lỗi tràn của `<img>` lẫn lỗi vô hình của `background-image` có alpha. Nếu badge/box nào khác gặp lại tình huống tương tự (ảnh nền lồng trong box `position:absolute`), thử flatten ảnh trước khi cân nhắc đổi sang màu phẳng (mất hoa văn) hoặc `<img>` (rủi ro tràn) — nhưng luôn kiểm chứng lại bằng PDFium, không suy đoán từ trường hợp này sẽ luôn đúng cho ảnh khác.
- Bài học phụ: khi 1 box chỉ có `width` qua **inline style** (không có `width` nào trong stylesheet làm nền), nếu phần tử con bên trong cũng `position:absolute` (không có nội dung "in-flow" nào), Dompdf có thể co box đó về **0 width** dù inline style đúng giá trị — luôn để 1 giá trị `width` hợp lý trong stylesheet làm fallback, để inline style ghi đè theo từng trường hợp.

## 6. Quy trình bắt buộc trước khi áp dụng

1. Đọc file thiết kế gốc và xác định kích thước mm, asset, vùng dữ liệu động.
2. Tạo template Latte mới và lưu bản sao an toàn của template đang dùng.
3. Đồng bộ template vào `print_templates.html_source` của đúng khổ/loại nhãn.
4. Render PDF qua runtime web thật, không chỉ xem HTML trên trình duyệt.
5. Kiểm tra ít nhất ba dữ liệu: tên dài 2 dòng, giá 3 chữ số và nhãn giảm giá có Poznámka/Platnost.
6. Chỉ sau khi PNG render không có chữ chồng, nội dung bị cắt, icon mất hoặc viền sai mới cho dùng trên trang sản phẩm.

