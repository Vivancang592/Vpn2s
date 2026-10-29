# Kế hoạch tích hợp thật AI Customer Service

## Nguyên tắc bắt buộc

- **Bảo toàn giao diện hiện tại**: không tự ý đổi bố cục, cấu trúc HTML, CSS, nội dung, thứ tự tab, nút và cách hiển thị của trang AI Customer Service. Chỉ thay đổi giao diện theo yêu cầu đã được người dùng duyệt; các yêu cầu này phải được cập nhật trong kế hoạch.
- Không gộp nhiều phần tích hợp thành một lượt. Hoàn thành, kiểm tra và báo cáo từng phần; chỉ bắt đầu phần kế tiếp khi được yêu cầu.
- Trước mỗi phần, khảo sát đúng các file liên quan, chốt phạm vi/file dự kiến sửa/tiêu chí nghiệm thu. Giới hạn mỗi phần ở khoảng 3–5 file khi khả thi.
- Không ghi bí mật vào mã nguồn, Git, frontend, log hoặc báo cáo. Không dùng dữ liệu khách hàng thật khi kiểm thử.
- Không chạy migration, deploy, commit hoặc thay đổi môi trường production nếu chưa được cho phép rõ ràng. Mọi thay đổi dữ liệu cần kế hoạch rollback và duyệt riêng.
- Giữ nguyên và tích hợp trên các thay đổi đang có trong worktree; không hoàn tác hoặc ghi đè chúng.

## Trạng thái

- [x] Hoàn thiện và duyệt giao diện preview hiện tại.
- [x] 6A — Khảo sát sâu và nền tảng an toàn.
- [ ] Tích hợp thật — code 6B–6G đã được nối ở mức ứng dụng; provider đã có kiểm thử thật nhưng quyền truy cập có thể khác theo model/tài khoản; Meta handshake/gửi nhận thật, kiểm thử nhiều phiên được duyệt và rà soát responsive vẫn còn chờ. Chính sách retention đã được yêu cầu và triển khai, nhưng mặc định vẫn tắt. Chưa chạy migration hoặc deploy production.

## Các phần tích hợp đề xuất

### Phần 6A — Khảo sát sâu và nền tảng an toàn

- [x] Xác minh cấu hình môi trường, bootstrap/router, xác thực admin, CSRF, quy ước phản hồi lỗi và logging.
- [x] Xác minh cơ chế lưu bí mật hiện có; thiết kế lưu API key, Meta App Secret và Page Access Token phía server, không trả bí mật về trình duyệt.
- [x] Xác minh schema/database Laragon đang chạy so với các file SQL trong repository; không suy luận schema production từ file SQL mẫu.
- [x] Chốt thiết kế dữ liệu, API nội bộ, quyền truy cập, xử lý lỗi và kiểm thử cho từng phần trước khi triển khai.
- **Tiêu chí hoàn thành:** có thiết kế được đối chiếu với mã và môi trường thực tế, không làm thay đổi UI, chưa chạy migration hay gọi dịch vụ bên ngoài.

### Phần 6B — Cài đặt provider và kiểm tra kết nối

- [x] Nối các trường cấu hình hiện có với endpoint admin phía server, bắt đầu với OpenRouter và KiraAI sau khi xác minh tài liệu chính thức.
- [x] Lưu cấu hình thường và bí mật theo cơ chế an toàn đã duyệt; bảo đảm không lộ key qua response, log, lỗi hoặc nhật ký giao diện.
- [x] Lưu API Key trước với model tạm `auto`, sau đó mới tải danh sách model theo quyền của key và chọn/lưu model cụ thể; giữ nguyên tên provider trả về.
- [x] Khởi tạo `appBasePath` trước các luồng tải hội thoại/nhật ký để các API và nút trong trang hoạt động sau khi tải trang.
- [x] Kiểm tra kết nối bằng yêu cầu chat tối thiểu do admin chủ động bấm; đã có kiểm thử provider thật và Website chat với `kira-mini-1.0`. Kết quả theo từng lần thử được ghi ở phần kết quả 6B; quyền truy cập model/tài khoản KiraAI vẫn cần theo dõi nếu provider trả 403.
- **Tiêu chí hoàn thành:** lưu/đọc cấu hình an toàn; kiểm tra kết nối có kết quả thật và lỗi rõ ràng; không hiển thị hoặc ghi lại bí mật.

### Phần 6C — Dữ liệu hội thoại và trạng thái tiếp quản

- [x] Dùng schema hội thoại đã xác minh; nối truy vấn hội thoại, tin nhắn, kênh và trạng thái sở hữu.
- [x] Nối danh sách, tìm kiếm, lọc, nội dung chat và đồng bộ định kỳ với dữ liệu backend.
- [x] Nối thao tác tiếp quản/trả AI và phản hồi nhân viên bằng transaction, khóa dòng và kiểm tra trạng thái.
- [x] Khi trả quyền cho AI, kiểm tra tin nhắn cuối; nếu khách chưa được phản hồi thì tạo và lưu phản hồi AI ngay, không gửi trùng khi tin nhắn cuối đã có phản hồi.
- **Tiêu chí hoàn thành:** code dùng transaction/khóa dòng và từ chối trạng thái sai; kiểm chứng hai phiên admin vẫn chờ bộ dữ liệu thử cô lập được duyệt.

### Phần 6D — Website chat và phản hồi AI

- [x] Xác minh vị trí và cơ chế tích hợp widget website hiện có trước khi thêm endpoint hay script.
- [x] Nối nhận/gửi tin nhắn website với luồng hội thoại; có session riêng, CSRF, giới hạn tốc độ, giới hạn dữ liệu và chống gửi lặp.
- [x] Kết nối phản hồi AI với tài liệu đã xuất bản; kiểm tra runtime/trạng thái trước khi lưu, đưa lỗi provider về trạng thái an toàn.
- [x] Với chat Website, gửi ảnh JPEG/PNG/WebP dưới dạng ảnh đa phương thức khi catalog xác nhận hoặc không nêu rõ khả năng; nếu catalog xác nhận không hỗ trợ, hoặc provider từ chối ảnh, AI gửi câu chuyển tiếp và hội thoại chuyển sang hàng chờ kỹ thuật viên.
- [x] Giới hạn prompt Website vào dịch vụ VPN2S (gói VPN, đăng ký/thanh toán, tài khoản, cài đặt và khắc phục sự cố liên quan); câu hỏi ngoài phạm vi được từ chối lịch sự.
- [x] Phân biệt tài liệu chung của ứng dụng bên thứ ba với bằng chứng VPN2S hỗ trợ ứng dụng đó; không suy luận quy trình/nút/link cấu hình và không lấy câu trả lời AI cũ làm căn cứ.
- [x] Loại bỏ dấu sao khỏi nội dung phản hồi model trước khi lưu/gửi để tin nhắn không hiển thị dấu Markdown `**` hoặc ký tự `*`.
- [x] Khi admin trả quyền xử lý, AI xử lý tin khách cuối chưa được trả lời trên Website và Facebook; phản hồi Facebook được gửi qua Meta trước khi lưu.
- [x] Tăng ngữ cảnh hội thoại gửi cho model lên tối đa 23 tin gần nhất trong ngân sách 12.000 byte; hướng dẫn AI bám lượt trao đổi trước, không hỏi lại dữ kiện khách đã cung cấp.
- **Tiêu chí hoàn thành:** code kiểm soát phiên/trạng thái và chặn AI khi bị tắt/nhân viên giữ; kiểm thử end-to-end vẫn chờ credential và môi trường thử.

### Phần 6E — Fanpage Facebook

- [x] Xác minh tài liệu Meta hiện hành và phiên bản Graph API trước khi cấu hình.
- [x] Triển khai xác minh callback, HMAC chữ ký request, chống gửi lặp theo khóa DB và lưu sự kiện tin nhắn.
- [x] Nối nhận/gửi tin nhắn Fanpage; chỉ xác nhận hoạt động sau handshake Meta và credential hợp lệ.
- **Tiêu chí hoàn thành:** xác minh HMAC đúng/sai thuần cục bộ đã đạt; handshake/sự kiện/gửi thật vẫn chờ Meta credential và webhook được duyệt.

### Phần 6F — Kiến thức, quy tắc và tác vụ vận hành

- [x] Nối tạo/sửa/xem/lưu trữ mềm tài liệu và trạng thái duyệt trong thư viện tri thức hiện tại.
- [x] Nối quy tắc bàn giao, bật/tắt bong bóng chat và trạng thái runtime với backend; quy tắc bàn giao được áp dụng cho chat Website.
- [x] Admin có thể nhập tài liệu HTTPS từ trang web công khai vào biểu mẫu kiến thức; máy chủ chặn địa chỉ riêng/từ chối redirect, giới hạn kích thước/thời gian, trích xuất văn bản và yêu cầu admin kiểm tra/lưu/duyệt trước khi AI dùng.
- [x] Gửi email tới các admin đang hoạt động khi khách yêu cầu kỹ thuật viên hoặc ảnh cần bàn giao vì model không hỗ trợ; ghi trạng thái gửi/thất bại vào nhật ký AI và nhật ký email.
- [x] Nối tab Giám sát với trạng thái runtime, số hội thoại theo trạng thái, cấu hình provider/Meta và quy tắc đã lưu; đồng bộ dữ liệu giám sát định kỳ từ server.
- [x] Triển khai tùy chọn tự xóa hội thoại không có hoạt động quá 7 ngày bằng cron server hiện có; mặc định tắt, yêu cầu xác nhận khi bật, mỗi lượt xử lý tối đa 100 hội thoại và xóa cả tin nhắn, lịch sử trạng thái cùng ảnh chat riêng sau khi transaction xóa DB thành công.
- **Tiêu chí hoàn thành:** tài liệu, runtime, widget và quy tắc bàn giao dùng backend; retention chỉ chạy khi quản trị viên chủ động bật và cron server hoạt động.

### Phần 6G — Tổng quan, nhật ký và nghiệm thu

- [x] Thay số liệu minh họa bằng truy vấn dữ liệu thật, phân tách Website/Facebook; đếm hội thoại có phản hồi AI theo conversation.
- [x] Nối nhật ký cấu hình/vận hành server-side, chỉ ghi sự kiện tổng quát và không ghi bí mật/nội dung khách.
- [x] Giới hạn nhật ký AI Customer Service còn 60 sự kiện mới nhất; API phân trang, mặc định 20 sự kiện/trang, quản trị viên chọn 1–60 sự kiện/trang.
- [x] Cập nhật thao tác phân trang bằng nút mũi tên gọn, thêm khoảng cách giữa các thẻ trong tab Cài đặt theo yêu cầu giao diện.
- [x] Trên điện thoại, Hộp thư mặc định chỉ hiển thị danh sách; chọn hội thoại mở khung chat toàn màn hình, nút quay lại đưa về danh sách. Chiều cao khung chat đồng bộ visual viewport để thích ứng bàn phím.
- [x] Widget không còn hiển thị như thể nhân viên đang gõ khi chỉ biết hội thoại ở trạng thái chờ/đã tiếp quản; chỉ hiện nội dung chờ nhân viên tiếp nhận/phản hồi khi tin nhắn khách cuối thực sự chưa có trả lời. Xóa trạng thái chờ sau phản hồi AI/nhân viên.
- [x] Ghi nhận thiết lập Temperature: mặc định 0,7, phạm vi hợp lệ 0–2; mức 0,3–0,7 là khuyến nghị vận hành cho CSKH, không phải giới hạn bắt buộc.
- [x] Chạy lint PHP/JavaScript, kiểm tra whitespace và các kiểm tra thuần cục bộ/đọc-only khả thi.
- [ ] Chạy kiểm thử tích hợp hai phiên admin; xác nhận Meta handshake/sự kiện/gửi nhận thật sau khi có credential; rà soát các trạng thái UI trên bộ dữ liệu thử cô lập được duyệt. Provider đã được kiểm thử thật nhưng còn phụ thuộc quyền của từng model/tài khoản.
- [ ] Xác nhận không có thay đổi trực quan ngoài ý muốn; kiểm tra các thao tác hiện tại và responsive.
- **Tiêu chí hoàn thành:** số liệu/audit đọc backend và các kiểm tra tĩnh/đọc-only đã chạy được báo cáo; kiểm thử tích hợp còn chờ điều kiện an toàn nêu trên.

## Kết quả phần 6A — đã xác minh

- Môi trường `.env` hiện tại khai báo môi trường local và database host loopback; `.env` được Git ignore. PHP CLI Laragon 8.3.33 và cổng MySQL local hoạt động. Chỉ truy vấn metadata `information_schema`; không đọc nội dung hội thoại, người dùng, giá trị cài đặt hay bí mật; không ghi dữ liệu.
- Schema MySQL local đang chạy có `vc_ai_conversations`, `vc_ai_messages`, `vc_ai_knowledge`. Các trạng thái hiện có lần lượt gồm `ai_active/ai_paused/staff_active/waiting/error`, sender `user/ai/staff`, knowledge `draft/pending/published/archived`; kênh `website/facebook`. `vc_ai_messages` có unique key cho external message; các bảng có khóa ngoại. Không có các bảng này trong `database/vpn2s.sql` đang có trong worktree, nên file SQL không đại diện cho schema local hoặc có thể cài mới.
- Router chặn khu vực Admin theo `user_id` và `role=admin`; đăng nhập tái tạo session ID. BaseController cấp CSRF token ngẫu nhiên và so sánh constant-time, nhưng router không tự áp CSRF cho mọi POST: từng endpoint thay đổi dữ liệu phải xác minh token.
- `vc_settings` là key-value `TEXT` không mã hóa; `Setting::getAllAsKeyValue()` trả tất cả key/value. Do đó không được đặt bí mật dạng rõ vào settings hoặc đưa toàn bộ settings vào view/API. Các tên khóa cấu hình AI cũ có tồn tại trong database local; giá trị của chúng chưa được đọc. Ban đầu `.env` chưa có khóa mã hóa AI; sau đó đã tạo khóa ngẫu nhiên cục bộ và xác minh round-trip Sodium, không đọc/in giá trị khóa.
- `SystemLog` và `error_log` hiện là các cơ chế log sẵn có. Không ghi request/response thô, prompt, API key, token, chữ ký hoặc nội dung khách vào nhật ký tích hợp. `APP_DEBUG` đang bật trên môi trường local; phản hồi API mới vẫn phải trả lỗi đã chuẩn hóa, không trả exception/provider body.
- View preview có thể giữ nguyên HTML/CSS/thứ tự tab; các tương tác hiện tại chủ yếu lưu `localStorage`, chưa gọi backend.

### Thiết kế nền tảng đã chốt cho các phần tiếp theo

- Chỉ dùng schema đã xác minh; không chạy migration. Nếu cần hỗ trợ cài mới, chuẩn bị SQL additive riêng sau khi đối chiếu cấu trúc đầy đủ và không tự áp dụng.
- Mọi endpoint quản trị phải qua router Admin, dùng CSRF cho thao tác ghi, xác thực dữ liệu, giới hạn truy vấn và trả JSON lỗi ổn định. Webhook công khai là ngoại lệ có xác minh chữ ký/handshake và không dựa vào session Admin.
- Bí mật provider/Meta phải được mã hóa xác thực ở server bằng khóa mã hóa riêng từ environment; không lưu dạng rõ vào `vc_settings`, HTML, JavaScript, localStorage, response, log hay audit. Nếu khóa chưa cấu hình, thao tác lưu/kiểm tra bí mật phải báo lỗi rõ ràng và không ghi dữ liệu. Trường bí mật chỉ nhận giá trị thay thế; response chỉ trả trạng thái đã cấu hình.
- Thao tác takeover/trả AI và gửi AI phải kiểm tra/khóa trạng thái hội thoại trong transaction để tránh gửi khi `staff_active` hoặc `ai_paused`; webhook/message external phải chống lặp bằng unique key hiện có.
- Ban đầu retention bị khóa chờ phê duyệt. Sau khi người dùng yêu cầu triển khai rõ ràng, đã thêm chính sách opt-in xóa hội thoại quá 7 ngày; mặc định vẫn tắt và không chạy cleanup trên dữ liệu thật khi xác minh.
- Chưa có API key OpenRouter/KiraAI hay thông tin Meta được cấu hình trong environment. Kết nối thật với dịch vụ bên ngoài chỉ được kiểm chứng khi có cấu hình an toàn; giai đoạn 6A không gọi các dịch vụ này.

## Kết quả phần 6B — triển khai code, chờ xác thực credential thật

- Đã nối lưu cấu hình provider, tải danh sách model và kiểm tra kết nối vào các route Admin có xác thực; thao tác ghi dùng CSRF và phản hồi `no-store`.
- API Key được lưu bằng Sodium `secretbox` trong khóa riêng theo provider; API/HTML chỉ nhận cờ đã cấu hình, không nhận ciphertext hay plaintext. Key thử kết nối chỉ được gửi trong POST về server và trường nhập bị xóa sau đó. Không dùng các khóa legacy chưa xác minh và không đọc giá trị chúng.
- Endpoint chỉ cho phép HTTPS tới đúng `openrouter.ai` hoặc `kiraai.vn`, không theo redirect; timeout 45 giây, xác minh TLS, giới hạn response 2 MiB. Kiểm tra kết nối gửi yêu cầu chat tối đa 2048 token và chỉ khi admin chủ động nhấn nút; chỉ báo thành công nếu nội dung phản hồi không rỗng.
- Đã xác minh tài liệu OpenRouter về API OpenAI-compatible và endpoint model; trang chính thức KiraAI nêu base URL `https://kiraai.vn/api/v1` và tương thích OpenAI SDK. GET model catalog thực tế với key local thành công: KiraAI 106 model, model đã chọn `gpt-oss-120b` có trong catalog.
- Kiểm tra POST `/chat/completions` tối đa 1 token thực tế trả HTTP 403 cho cả `gpt-oss-120b` và `kira-3.5-flash`; không ghi hội thoại/DB. Vì vậy server và key có thể đọc catalog, nhưng tài khoản/API key hiện không được phép gọi chat completion (hoặc thiếu điều kiện quyền/số dư ở provider). Trang KiraAI công khai ghi gói token miễn phí chỉ áp dụng model `kira-`, còn model đối tác như GPT cần nạp ví riêng; tuy nhiên model `kira-` cũng trả 403 nên chỉ nạp ví chưa đủ để kết luận đã xử lý — cần xác minh quyền API key/tài khoản KiraAI.
- Đã sửa thông báo lỗi provider để phân biệt HTTP 401 với 403; khi completion trong Website chat lỗi, audit Admin ghi mã trạng thái và gợi ý kiểm tra quyền/key/số dư, không ghi nội dung chat hoặc bí mật. Phía khách vẫn nhận thông báo dự phòng trung tính.
- Kiểm tra trực tiếp `kira-mini-1.0` với ngân sách 512 token trả HTTP 200 nhưng `finish_reason=length`, không có nội dung; 1024 token đủ cho prompt ngắn nhưng không đủ ổn định với prompt kiến thức dài. Website chat và kiểm tra kết nối hiện cho phép tối đa 2048 token, đồng thời vẫn tôn trọng giới hạn thấp hơn đã lưu trong cấu hình.
- Đã gửi một tin thử end-to-end qua `AiCustomerServiceWebsiteChatService::receiveMessage()` với external ID ngẫu nhiên, nhận 1 tin AI tiếng Việt từ `kira-mini-1.0`, xác minh model không đổi, rồi xóa đúng 2 tin nhắn và hội thoại thử. Không sửa hội thoại khách thật.
- Kiểm thử tra cứu kiến thức thực tế bằng Website chat với đúng model `kira-mini-1.0`: AI trả đúng gói `SAMPLE-STARTER-30D` (49.000 VND, 30 ngày, tối đa 1 thiết bị); với mã coupon giả ngẫu nhiên, AI nêu đúng mức 17% và nói rõ không dùng được cho thanh toán thật. Lần thử coupon đầu tiên vẫn rơi về câu dự phòng; sau khi kiểm tra provider trả nội dung đúng ở ngân sách 2048, lần chạy lại qua luồng Website chat thành công. Không tạo coupon có hiệu lực; các tài liệu test đã lưu trữ và các hội thoại/tin thử đã xóa. Bản ghi gói hiện có chứa ký tự `?` trong tên (`Starter 1 th?ng`), nên phép thử đối chiếu mã gói và các thuộc tính số, không xác nhận tên hiển thị.
- Khóa mã hóa `AI_CUSTOMER_SERVICE_ENCRYPTION_KEY` đã được tạo riêng trong `.env` bị Git ignore; không được xóa/đổi nếu đã mã hóa API key, và cần sao lưu an toàn ngoài Git. Mất khóa sẽ không giải mã được API Key đã lưu.
- Lần lưu key đầu tiên trước khi có khóa mã hóa dừng trước transaction, không ghi dữ liệu. Sau đó key đã được lưu mã hóa; trạng thái được kiểm tra bằng cờ cấu hình và tải catalog, không đọc/in key hoặc ciphertext.

## Kết quả phần 6C — đã nối backend, còn chờ kiểm thử phiên đồng thời

- Đã thêm truy vấn inbox có giới hạn 200 hội thoại, tìm kiếm theo tên/tài khoản/ID/nội dung, lọc kênh/trạng thái và tải tối đa 200 tin nhắn gần nhất.
- Đã nối route Admin có xác thực; mọi thao tác ghi kiểm tra CSRF. Tiếp quản, trả quyền cho AI và phản hồi nhân viên khóa dòng hội thoại trong transaction; trạng thái không hợp lệ trả lỗi xung đột. Tìm kiếm/polling không còn giữ hội thoại đã bị lọc khỏi danh sách; phần tử và nội dung tin nhắn được tạo bằng `textContent`.
- PHP lint cho model/service/controller/routes/view, cú pháp JavaScript và `EXPLAIN` các truy vấn inbox/chi tiết đều đạt. Kiểm tra đầu vào xác nhận bộ lọc sai bị từ chối trước truy vấn.
- Không đọc nội dung hội thoại thực và không ghi dữ liệu. Vì chưa có bộ dữ liệu thử nghiệm cô lập được duyệt, chưa kiểm thử tích hợp hai phiên admin hoặc thực thi các chuyển trạng thái/reply trên DB; tiêu chí đồng bộ đồng thời chưa được nghiệm thu. Không chạy migration/deploy.

## Kết quả bổ sung phần 6D–6G

- 6D: Widget chỉ được render khi thiết lập server bật; chat dùng PHP session, token CSRF, giới hạn 5 tin/phút/session, UUID chống lặp, giới hạn body/ký tự và lưu vào bảng hội thoại hiện có. Prompt chỉ nhận tối đa 8 tài liệu đã xuất bản. Trước khi lưu phản hồi AI, hệ thống khóa lại hội thoại và xác minh runtime, widget cùng trạng thái sở hữu. Các phiên cũ ở `waiting/error/ai_paused` được khôi phục về `ai_active` khi có tin mới nếu runtime hoạt động và chưa được kỹ thuật viên tiếp quản, ngoại trừ hàng chờ kỹ thuật viên được tạo bởi quy tắc `customer_requests_human`; phiên kỹ thuật viên tiếp quản và phiên chờ kỹ thuật viên đều dừng AI cho đến khi người thật xử lý.
- Sau phản hồi về việc menu Admin tải dạng partial và không chạy lại `DOMContentLoaded`, script trang AI Customer Service đã được đổi sang khởi tạo ngay khi fragment được thực thi; timer inbox được dọn khi rời fragment. Runtime mới mặc định `active`; trạng thái đã được Admin lưu trước đó vẫn được tôn trọng.
- Đã sửa nguyên nhân phiên chat cũ `waiting` dù công tắc AI đang bật: trạng thái này trước đây chặn model vĩnh viễn vì AI chỉ chạy với conversation ở `ai_active`, còn công tắc là thiết lập tách biệt. Tin nhắn khách mới tự phục hồi state chờ/lỗi cũ nếu không phải hàng chờ nhân viên có lý do bàn giao đang chờ xử lý.
- Danh sách model hiện dùng OpenRouter `/models/user` và xác thực bằng API key đã mã hóa; KiraAI `/models` cũng dùng Bearer key đã lưu. Không gửi key lên trình duyệt/URL. Tên model hiển thị bằng đúng trường `name` provider trả về, không ghép ID; khi cấu hình đang `auto`/model không còn được cấp quyền, UI chọn model hợp lệ đầu tiên và lưu lựa chọn bằng API admin.
- 6E: Đã thêm webhook handshake, xác minh HMAC-SHA256 trên raw body trước parse, giới hạn kích thước/số sự kiện và kiểm tra page ID. Tin nhắn lặp được phân biệt qua unique key; lỗi integrity khác không bị coi nhầm là duplicate. Phản hồi Facebook dùng Graph API v26.0, token trong Authorization header và giới hạn cửa sổ phản hồi 24 giờ. Chưa có Meta credential/handshake thật nên chưa nghiệm thu với Meta.
- 6F: Tài liệu kiến thức có tạo/sửa/trạng thái/lưu trữ mềm và audit. Thiết lập runtime, widget Website, quy tắc bàn giao được lưu trong `vc_settings`; auto-reply luôn bị khóa khi kỹ thuật viên tiếp quản. Theo yêu cầu triển khai, retention xóa hội thoại không có hoạt động quá 7 ngày qua cron hiện có (mỗi 5 phút, tối đa 100 hội thoại/lượt); tùy chọn mặc định **tắt** và giao diện yêu cầu xác nhận khi bật. Không chạy cleanup trên dữ liệu thật trong kiểm chứng.
- 6G: Dashboard truy vấn số liệu trực tiếp; audit đọc từ `vc_system_logs` và không đưa username, nội dung hội thoại hoặc bí mật ra UI. Nhật ký AI tự giữ tối đa 60 sự kiện mới nhất; endpoint hỗ trợ phân trang, mặc định 20 sự kiện/trang, cho phép nhập 1–60. Nút chuyển trang dùng mũi tên; tab Cài đặt có khoảng cách rõ giữa các thẻ theo yêu cầu. Temperature mặc định 0,7, chấp nhận 0–2; 0,3–0,7 là khoảng khuyến nghị, không được áp đặt như giới hạn sản phẩm.
- Hộp thư trên mobile: ban đầu chỉ hiện danh sách; chọn hội thoại mở khung chi tiết phủ toàn màn hình và nút quay lại đưa về danh sách. Chiều cao overlay theo `visualViewport` để thích ứng bàn phím điện thoại.
- Tab Tổng quan hiển thị rõ ý nghĩa số liệu thật: số hội thoại từng có phản hồi AI, số hội thoại hiện do kỹ thuật viên tiếp quản, số cần xử lý gồm chờ phản hồi và lỗi. Trạng thái provider chỉ báo đã cấu hình/chưa xác minh, không tự nhận Online; nhiệm vụ cấu hình provider/Fanpage lấy theo trạng thái lưu thật và mở đúng tab cài đặt.
- Đã sửa lời gọi service Admin để truyền `adminId`, đưa các phương thức audit ra đúng phạm vi class controller và bổ sung route lưu quy tắc. PHP lint toàn bộ file tích hợp, cú pháp JS nội tuyến/widget, kiểm tra HMAC đúng/sai bằng khóa thử nghiệm và `git diff --check` đều đạt.
- KiraAI đã có API Key lưu mã hóa và model `kira-mini-1.0` đã được kiểm thử thực tế qua Website chat; không ghi/in key hay ciphertext. Meta App Secret/Page Token và webhook thật vẫn chưa cấu hình. Không chạy migration/deploy/commit. Kiểm thử đồng thời giữa nhiều admin vẫn cần phiên thử riêng.

## Cập nhật Hộp thư và bàn giao theo quy tắc

- Nút “Xóa hội thoại” nằm ngay trên thẻ đoạn chat trong danh sách Hộp thư; thao tác yêu cầu xác nhận, sau đó xóa toàn bộ tin nhắn, lịch sử trạng thái, liên kết external ID và hội thoại trong một transaction. Endpoint Admin kiểm tra CSRF và khóa hội thoại; audit chỉ ghi sự kiện, không ghi nội dung tin nhắn.
- Admin gửi phản hồi bằng Enter; Shift+Enter vẫn chèn dòng mới.
- Khi `customer_requests_human` đã bật, các cách diễn đạt yêu cầu gặp kỹ thuật viên/người thật đưa chat Website sang `waiting`, lưu lời xác nhận trong hội thoại và ghi lý do chuyển trạng thái. Các tin tiếp theo không tự kích hoạt AI lại; hội thoại chờ cho tới khi kỹ thuật viên tiếp quản.
- Đã thống nhất cách gọi trên Website chat và Hộp thư: người trả lời mặc định là **Nhân viên** (AI); người thật là **Kỹ thuật viên**. Lời mời và quy tắc bàn giao dùng “Gặp kỹ thuật viên”; khi hội thoại còn chờ, chỉ báo “đang chờ kỹ thuật viên tiếp nhận”; chỉ sau khi kỹ thuật viên thực sự tiếp quản mới xác nhận “Kỹ thuật viên đã tiếp nhận”. Nội dung AI không gọi người trả lời mặc định là kỹ thuật viên và không tự hứa chuyển cho kỹ thuật viên nếu khách chưa yêu cầu.
- Khi kỹ thuật viên tiếp quản, hệ thống lưu thông báo vào hội thoại Website; Facebook gửi thông báo qua Meta trong cửa sổ phản hồi 24 giờ. Nếu Meta từ chối thì thao tác tiếp quản được rollback và báo lỗi.
- Đã nối typing presence thật cho chat Website: trình duyệt khách/kỹ thuật viên gửi heartbeat khi có nội dung đang soạn, dừng khi ngừng gõ/gửi/đổi hội thoại/rời trường nhập; hai bên chỉ thấy chỉ báo khi có heartbeat trong 3 giây gần nhất. Kỹ thuật viên chỉ phát tín hiệu khi đang tiếp quản hội thoại Website. Tín hiệu tạm thời dùng tệp ngoài webroot, khóa bằng `flock`, không cần migration.
- Đã sửa chỉ báo soạn tin ở cả widget và Hộp thư để không làm danh sách hội thoại nhảy lên/xuống: giữ một hàng có chiều cao cố định và chỉ đổi độ hiển thị, không thêm/xóa dòng khỏi danh sách; vẫn giữ debounce trạng thái dừng và đổi hội thoại sẽ ẩn trạng thái gõ của khách trước đó.
- Mỗi hội thoại Website giới hạn tối đa 5 tin khách chưa được Nhân viên hoặc Kỹ thuật viên trả lời. Widget khóa ô nhập/nút gửi khi chạm ngưỡng, cập nhật lại số tin đang chờ khi đồng bộ và server kiểm tra dưới khóa hội thoại để chặn gửi đồng thời từ nhiều tab; tin gửi lặp cùng mã idempotency không bị tính thành tin mới.
- Bộ đếm dự phòng 2 phút và email chỉ áp dụng khi AI đang chịu trách nhiệm trả lời hoặc gặp lỗi. Bản vá mới đảm bảo phản hồi dự phòng tức thì từ backend (ví dụ provider chưa trả lời) không hiển thị thông báo/email sớm; chỉ mở form sau đủ 120 giây kể từ khi gửi tin khách đang chờ phản hồi. Timer kiểm tra lại mốc thời gian khi callback chạy. Khi hội thoại `waiting`/`staff_active`, không chạy timeout và không hiện form email; UI chỉ báo chờ kỹ thuật viên hoặc kỹ thuật viên đã tiếp nhận. Nội dung timeout dùng thông báo tất cả tư vấn viên đang bận và đề nghị nhắn lại hoặc để email.
- Rà soát hội thoại WS-39 cho thấy phản hồi ảnh không phù hợp. Luồng Website nay gửi ảnh JPEG/PNG/WebP thật tới model đa phương thức nếu catalog xác nhận hỗ trợ hoặc không công bố khả năng; catalog xác nhận không hỗ trợ, GIF hoặc provider từ chối ảnh sẽ gửi lời chuyển tiếp và đưa hội thoại vào hàng chờ kỹ thuật viên. Không sửa tin nhắn WS-39 đã lưu; chỉ áp dụng cho tin nhắn mới. Chưa kiểm thử ảnh với model/provider thật trong lượt này.
- Rà soát WS-45: AI khẳng định VPN2S hỗ trợ OpenVPN, WireGuard, Clash/Karing và liệt kê Surfshark/ExpressVPN dù ngữ cảnh/tài liệu không xác nhận; câu trả lời còn mở đầu, liệt kê và hỏi lại theo kiểu khuôn mẫu. Đã siết prompt: không suy diễn tương thích app/giao thức, không đưa dịch vụ đối thủ vào danh sách hỗ trợ, trả lời trực tiếp ngắn gọn theo lượt chat, không lặp lời chào/giới thiệu, không tự nhắc thiếu gói hoặc dẫn /plans khi khách không hỏi. Không sửa nội dung hội thoại WS-45 đã lưu; áp dụng cho phản hồi mới.
- Đã thêm thao tác đọc nội dung từ URL HTTPS công khai vào biểu mẫu tài liệu. Yêu cầu được tải server-side với DNS pinning tới IP công khai (IPv4/IPv6), không theo redirect, xác minh TLS và giới hạn 2 MB/15 giây; admin phải rà soát và lưu tài liệu trước khi AI sử dụng. Đã kiểm thử trích xuất trang tĩnh HTTPS công khai và chặn IP loopback; chưa kiểm thử với trang hướng dẫn bên ngoài thực tế.
- Prompt Website chỉ hỗ trợ dịch vụ VPN2S. Khi khách yêu cầu kỹ thuật viên hoặc ảnh buộc chuyển tuyến, hệ thống gửi email đến các tài khoản admin đang hoạt động, kèm mã hội thoại và đường dẫn Hộp thư; kết quả gửi được ghi vào audit/email log. Chưa kiểm thử SMTP thật.
- Đã kiểm tra cú pháp PHP của các tệp liên quan, cú pháp JavaScript widget và script nội tuyến Admin, cùng `git diff --check`; chưa kiểm thử tương tác hai trình duyệt hoặc Facebook với Meta credential thật.
- Đã kiểm thử trên DB và trình duyệt local bằng hội thoại thử riêng: yêu cầu “Tôi muốn gặp nhân viên hỗ trợ” chuyển sang `waiting`; tin nhắn tiếp theo vẫn chờ; kiểm thử UI gửi phản hồi bằng Enter và xóa toàn bộ hội thoại qua nút trên thẻ danh sách. Đã xác nhận bản ghi hội thoại, mọi tin nhắn, lịch sử trạng thái và external ID liên quan đều được xóa; không sửa hội thoại khách thật.
- Thẻ hội thoại trong Hộp thư và phần chi tiết/từng tin hiển thị rõ `Khách hàng`, `Khách vãng lai` hoặc `Khách Facebook`. Website chat mới được gắn với tài khoản đang đăng nhập khi khách gửi tin; hội thoại vãng lai giữ nhãn riêng. Đã kiểm tra hai nhãn trên thẻ danh sách và người gửi tin, xác minh liên kết guest → customer bằng dữ liệu thử rồi dọn toàn bộ fixture.

## Các điều kiện còn lại để nghiệm thu vận hành

1. Hoàn tất bước provider KiraAI và xác minh chat Website trên model hiện tại; tiếp tục bảo vệ, sao lưu an toàn `AI_CUSTOMER_SERVICE_ENCRYPTION_KEY`.
2. Cấu hình Meta trong môi trường được phép, xác nhận webhook handshake/sự kiện và gửi tin thử theo quy trình được duyệt.
3. Cấp phiên thử cô lập để kiểm tra tranh chấp trạng thái giữa hai admin.
4. Retention đã được triển khai nhưng để mặc định tắt; cần xác nhận cấu hình cron trên production và chỉ bật sau khi quản trị viên cân nhắc việc xóa vĩnh viễn. Không chạy cleanup production trong lượt triển khai hiện tại.
5. Kiểm tra trực tiếp typing presence giữa hai trình duyệt khi kỹ thuật viên đang tiếp quản, ngừng gõ, gửi tin, đổi hội thoại và trả quyền cho AI; xác nhận chỉ báo tự tắt khi heartbeat hết hạn. Kiểm tra Meta takeover chỉ sau khi credential/webhook được cấu hình và được phép gửi tin thử.
6. Kiểm thử ảnh thật với model đang chọn và xác nhận model/provider xử lý đúng định dạng; kiểm thử SMTP với tài khoản admin được duyệt trước khi bật vận hành. Không gửi email thử hoặc ảnh khách thật trong lượt triển khai này.
