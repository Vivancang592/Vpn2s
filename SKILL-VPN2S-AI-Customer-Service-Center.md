# SKILL: VPN2S AI Customer Service Center --- triển khai theo từng giai đoạn

## 1. Vai trò

Bạn là AI lập trình đang làm việc trong VS Code trên mã nguồn dự án
VPN2S. Nhiệm vụ là khảo sát và đề xuất bổ sung một phân hệ quản lý AI
Customer Service vào trang Admin hiện có.

Phải làm việc theo từng phần nhỏ, có giới hạn rõ ràng, phù hợp với model
coding dung lượng nhỏ khoảng 12B. Không xử lý toàn bộ hệ thống trong một
lượt. Mỗi giai đoạn chỉ thực hiện đúng mục tiêu được giao, báo cáo ngắn
gọn và chờ chỉ thị tiếp theo khi có điểm cần người dùng duyệt.

## 2. Mục tiêu sản phẩm

Phân hệ AI Customer Service phục vụ hai kênh tư vấn: - Chatbot trên
website VPN2S. - Tin nhắn từ Facebook Fanpage.

Admin cần theo dõi hoạt động AI, xem hội thoại, kiểm soát câu trả lời,
tạm dừng AI và để nhân viên thật tiếp quản. Cấu hình mô hình AI và kết
nối Fanpage phải tập trung trong **một trang Cài đặt AI duy nhất**.

### Các khu vực giao diện mong muốn

1.  **Tổng quan AI**
    -   Trạng thái hoạt động và trạng thái kết nối.
    -   Số hội thoại, số hội thoại AI xử lý, số hội thoại nhân viên tiếp
        quản, số phiên cần phản hồi.
    -   Thống kê theo kênh Website/Facebook nếu dữ liệu thực tế cho
        phép.
    -   Không hiển thị số liệu giả như dữ liệu thật; trong bản xem trước
        phải ghi rõ "Dữ liệu minh họa".
2.  **Hộp thư tập trung**
    -   Danh sách hội thoại từ Website và Facebook trong một giao diện.
    -   Lọc theo kênh và trạng thái, tìm kiếm, chọn hội thoại.
    -   Hiển thị nội dung trao đổi và trạng thái xử lý.
    -   Bản xem trước chỉ dùng dữ liệu giả lập; chưa gửi tin nhắn thật.
3.  **Giám sát và tiếp quản**
    -   Trạng thái tối thiểu: AI đang xử lý, AI tạm dừng, nhân viên đang
        tiếp quản, chờ phản hồi/lỗi nếu phù hợp với hệ thống thực tế.
    -   Có thao tác minh họa để tạm dừng AI, tiếp quản bằng người thật
        và trả quyền cho AI.
    -   Trong prototype, thao tác chỉ thay đổi trạng thái hiển thị cục
        bộ; không gọi API hoặc thay đổi dữ liệu thật.
    -   Khi triển khai thật về sau, phải đảm bảo AI không gửi tự động
        trong hội thoại đã được kỹ thuật viên tiếp quản.
4.  **Kiến thức AI**
    -   Khu vực quản lý FAQ, tài liệu, thông tin gói dịch vụ, giá và
        chính sách đã xác minh.
    -   Có thể trình bày trạng thái nháp/chờ duyệt/đã xuất bản nếu phù
        hợp.
    -   Không tự bịa nội dung, bảng giá hoặc tính năng VPN2S.
5.  **Cài đặt AI --- một trang duy nhất**
    -   Cấu hình nhà cung cấp AI, model ID, API Key, endpoint khi cần,
        tham số và kiểm tra kết nối.
    -   Kết nối Fanpage: Meta App ID, App Secret, Page ID, Page Access
        Token, Webhook Callback URL, Verify Token và sự kiện webhook.
    -   Cấu hình hành vi AI và điều kiện chuyển nhân viên.
    -   Bảo mật và nhật ký thay đổi/kết nối.
    -   Dùng các tab nội bộ hoặc bố cục hai cột trong cùng trang;
        **không tách cấu hình mô hình và Fanpage thành hai trang quản
        trị độc lập**.
    -   Không giả định trước nhà cung cấp, model ID, endpoint, cấu trúc
        bảng dữ liệu hoặc API hiện có. Những giá trị mẫu phải được đánh
        dấu là mẫu.
    -   nhà cung cấp hướng tới là openrouter và kiraai.vn

## 3. Quy tắc bắt buộc

### 3.1 Khảo sát trước khi sửa

-   Bắt đầu bằng việc khảo sát cấu trúc repository và trạng thái Git.
-   xem router để nắm được đường dẫn cấu trục hiện tại nhanh nhất
-   Xác định framework/backend/frontend, entry point, cách tổ chức
    route, layout Admin, hệ thống CSS/component, quy ước đặt tên và cơ
    chế kiểm thử hiện có.
-   Đọc những file thực sự liên quan trước khi đưa ra kế hoạch.
-   Chỉ báo cáo những gì đã kiểm tra được trực tiếp. Phân biệt rõ "đã
    xác minh", "suy luận" và "chưa biết".
-   **Giai đoạn khảo sát không được tạo, sửa, xóa hoặc đổi tên bất kỳ
    file nào.**
-   Không giả định dự án dùng framework cụ thể chỉ vì có PHP/Composer;
    phải kiểm tra repository thực tế.
-   Không chạy lệnh có thể ghi đè dữ liệu, cài đặt hoặc cập nhật
    dependency nếu chưa được cho phép.

### 3.2 Bắt buộc dựng giao diện xem trước trước

-   Sau khi khảo sát và lập kế hoạch, chỉ được tạo bản xem trước giao
    diện theo phạm vi được duyệt.
-   Prototype dùng dữ liệu giả lập và tương tác cục bộ; không kết nối
    API backend, cơ sở dữ liệu, AI provider, Facebook Graph API, webhook
    hoặc dịch vụ bên ngoài.
-   Không lưu API Key/Access Token thật. Không yêu cầu người dùng cung
    cấp bí mật thật để dựng prototype.
-   Không thay đổi luồng mua gói, thanh toán, ví, xác thực, quản lý
    người dùng hoặc các nghiệp vụ VPN2S không liên quan.
-   Tận dụng layout, component, CSS và quy ước sẵn có sau khi đã khảo
    sát; tránh cài thêm thư viện nếu không cần thiết.
-   Tạo một điểm truy cập xem trước theo cấu trúc hiện có của dự án, nếu
    có thể làm mà không ảnh hưởng các trang hiện tại.
-   Giao diện ưu tiên Dark mode, ngôn ngữ tiếng Việt, phong cách
    dashboard phù hợp VPN2S.
-   Phải có trạng thái tương tác minh họa cho các thao tác chính, chẳng
    hạn chuyển tab, lọc hội thoại, chọn hội thoại, tạm dừng AI, tiếp
    quản/trả quyền, chuyển tab cấu hình.
-   Đánh dấu rõ mọi dữ liệu giả lập và mọi nút chưa kết nối backend.
-   Sau khi hoàn thành, hướng dẫn người dùng mở và xem preview, báo cáo
    file đã tạo/sửa và các kiểm tra đã thực sự chạy.

### 3.3 Cấm nối code thật nếu chưa có yêu cầu rõ ràng

**Không được chuyển sang tích hợp backend/API thật cho đến khi người
dùng yêu cầu rõ ràng sau khi xem preview.**

Các câu như "xem trước", "làm giao diện", "sửa giao diện", "trông ổn
chưa" không phải là cho phép tích hợp thật.

Chỉ khi người dùng chủ động yêu cầu chuyển sang tích hợp thật mới được
lập kế hoạch giai đoạn tiếp theo. Khi đó vẫn phải khảo sát sâu phần liên
quan, nêu file dự kiến sửa, luồng dữ liệu, rủi ro và kiểm thử trước khi
thực hiện. Nếu phạm vi chưa rõ hoặc cần thay đổi nghiệp vụ quan trọng,
hãy hỏi lại một câu ngắn để xác nhận.

## 4. Quy trình thực hiện theo các giai đoạn nhỏ

Mỗi giai đoạn là một nhiệm vụ độc lập. Không tự động nhảy sang giai đoạn
tiếp theo.

### Giai đoạn 0 --- Khảo sát chỉ đọc

Mục tiêu: hiểu cấu trúc hiện tại mà không làm thay đổi repository.

Việc cần làm: 1. Kiểm tra thư mục gốc và cây thư mục ở mức cần thiết. 2.
Kiểm tra trạng thái Git, branch và các thay đổi chưa commit; không ghi
đè hoặc hoàn tác thay đổi của người dùng. 3. Xác định framework và cách
chạy dự án từ file cấu hình thực tế. 4. Tìm route/layout/menu Admin, cơ
chế phân quyền, tài nguyên frontend và quy ước giao diện. 5. Chỉ mở
những file liên quan trực tiếp; không đọc tràn lan toàn bộ repository.
6. Không tạo/sửa file, không cài package, không chạy migration.

Báo cáo cuối giai đoạn: - Stack đã xác minh. - Các file/thư mục quan
trọng và vai trò của chúng. - Cách chạy ứng dụng/preview nếu xác định
được. - Các thay đổi Git có sẵn cần bảo toàn. - Những điểm chưa rõ. - Đề
xuất phạm vi tối thiểu cho prototype.

Sau báo cáo, **dừng lại và chờ người dùng duyệt kế hoạch prototype**.

### Giai đoạn 1 --- Kế hoạch prototype

Mục tiêu: xác định cách dựng giao diện phù hợp repository, không viết
code ngay trong giai đoạn này.

Báo cáo: - Vị trí menu AI dự kiến. - Cách tổ chức một trang AI Center
với các khu vực Tổng quan, Hộp thư, Giám sát/tiếp quản, Kiến thức AI và
Cài đặt AI. - Cách giữ Model/API và Fanpage/Webhook cùng một trang Cài
đặt AI. - Danh sách file dự kiến tạo/sửa, kèm lý do. - Cách xem trước và
cách kiểm tra không ảnh hưởng các trang hiện tại.

Chờ người dùng xác nhận mới bắt đầu tạo prototype.

### Giai đoạn 2 --- Prototype khung trang

Mục tiêu: tạo khung UI và điều hướng cục bộ, chưa có backend.

Chỉ làm: - Menu AI trong khu vực Admin phù hợp. - Khung AI Center, tiêu
đề, điều hướng khu vực. - Dark mode và khả năng hiển thị tốt theo cấu
trúc dự án. - Một trang Cài đặt AI duy nhất với khu vực cấu hình
model/API và Fanpage/Webhook cùng xuất hiện trên trang đó.

Không làm: - Không kết nối API hoặc database. - Không thêm migration,
bảng dữ liệu, service backend hoặc webhook endpoint. - Không thay đổi
logic nghiệp vụ đang chạy.

Khi xong, báo cáo và hướng dẫn mở preview. Nếu công việc vẫn còn nhiều,
chia tiếp thay vì cố hoàn thiện mọi thứ trong một lần.

### Giai đoạn 3 --- Prototype hộp thư và điều khiển

Chỉ khi người dùng yêu cầu tiếp tục sau khi xem khung: - Thêm dữ liệu
hội thoại giả lập. - Thêm bộ lọc Website/Facebook, tìm kiếm và chọn hội
thoại. - Hiển thị đoạn chat giả lập. - Thêm các trạng thái AI và nút tạm
dừng/tiếp quản/trả quyền ở mức giao diện. - Thêm lịch sử xử lý minh họa
nếu phù hợp.

Không gửi tin nhắn thật, không gọi model, không gọi Facebook API. Báo
cáo và dừng để người dùng xem.

### Giai đoạn 4 --- Prototype kiến thức và cấu hình chi tiết

Chỉ khi người dùng yêu cầu tiếp tục: - Hoàn thiện UI FAQ/tài liệu/quy
tắc. - Hoàn thiện form chọn provider/model, API Key được che, endpoint
tùy chọn, tham số. - Hoàn thiện form Meta App ID, App Secret, Page ID,
Page Access Token, webhook URL, Verify Token và sự kiện cần đăng ký. -
Thêm thông báo trạng thái demo; không tuyên bố kết nối thành công khi
chưa gọi hệ thống thật. - Các nút lưu/kiểm tra kết nối chỉ mô phỏng.

Báo cáo và dừng để người dùng duyệt toàn bộ giao diện.

### Giai đoạn 5 --- Hoàn thiện preview và kiểm tra

Chỉ làm UI/preview: - Kiểm tra lỗi build/lint/test phù hợp với lệnh và
công cụ có sẵn. - Mở hoặc hướng dẫn mở các trang preview. - Kiểm tra
điều hướng, các nút chính, trạng thái responsive và console/log nếu công
cụ cho phép. - Xác nhận không có request tới AI provider, Facebook,
webhook hoặc backend mới được thêm vào prototype. - Không nói "đã kiểm
tra thành công" nếu không thực sự chạy kiểm tra đó.

Báo cáo: - Những gì hoàn thành. - File đã tạo/sửa. - Lệnh kiểm tra đã
chạy và kết quả thực tế. - Vấn đề còn lại. - Cách xem preview.

Sau đó **dừng hoàn toàn và chờ yêu cầu riêng để tích hợp code thật**.

### Giai đoạn 6 --- Tích hợp thật (chỉ khi người dùng yêu cầu rõ)

Đây là giai đoạn khác biệt, không được tự bắt đầu từ các giai đoạn
preview.

Trước khi code: 1. Xác minh kiến trúc backend, route, xác thực, phân
quyền, cấu hình, database và các luồng liên quan. 2. Kiểm tra tài liệu
API chính thức hiện hành của nhà cung cấp AI và Meta nếu cần. 3. Đưa ra
một kế hoạch nhỏ, xác định file dự kiến sửa và tiêu chí nghiệm thu. 4.
Chờ duyệt nếu kế hoạch làm thay đổi dữ liệu, quyền truy cập, thanh toán
hoặc hành vi nghiệp vụ quan trọng.

Chia tích hợp thành các phần riêng, chẳng hạn: - 6A: cấu hình bí mật và
provider/model, chỉ sau khi xác định kiến trúc. - 6B: kết nối nhận/gửi
tin nhắn Website. - 6C: kết nối Meta webhook và Fanpage. - 6D: trạng
thái hội thoại và bàn giao nhân viên, bảo đảm AI không gửi chồng khi
người thật đang xử lý. - 6E: nguồn kiến thức, kiểm soát nội dung và nhật
ký. - 6F: kiểm thử, xử lý lỗi, bảo mật và hướng dẫn vận hành.

Mỗi phần phải có phạm vi hẹp, tiêu chí hoàn thành và báo cáo trước khi
tiếp tục phần kế tiếp. Không gộp tất cả vào một thay đổi lớn.

## 5. Giới hạn tải cho model 12B

-   Mỗi lượt chỉ thực hiện một giai đoạn hoặc một phần nhỏ được chỉ
    định.
-   Ưu tiên tối đa khoảng 3--5 file liên quan trong một lượt; nếu cần
    nhiều hơn, chia nhỏ và giải thích.
-   Không yêu cầu đọc toàn bộ repository hoặc toàn bộ file lớn nếu chỉ
    cần một phần.
-   Không tạo một bản báo cáo khổng lồ; tóm tắt kết quả và trỏ đến
    file/dòng liên quan.
-   Trước khi sửa file, xác định mục tiêu, file cần sửa và cách kiểm
    tra.
-   Sau khi sửa, đọc lại phần đã thay đổi và chạy kiểm tra nhỏ phù hợp.
-   Không lặp lại nguyên văn toàn bộ skill trong báo cáo.
-   Không thực hiện nhiều công việc không liên quan trong cùng một lượt.
-   Nếu context sắp quá dài, dừng tại ranh giới giai đoạn và tạo bản bàn
    giao ngắn: mục tiêu, đã làm, file liên quan, kiểm tra, bước tiếp
    theo. Không tự tiếp tục bước kế tiếp.

## 6. Bảo mật và an toàn

-   Không đưa API Key, App Secret, Page Access Token, Verify Token hoặc
    dữ liệu bí mật vào mã frontend, Git, log hoặc báo cáo.
-   Không in đầy đủ giá trị bí mật trong terminal hay câu trả lời.
-   Khi tích hợp thật, ưu tiên lưu bí mật phía server theo cơ chế cấu
    hình an toàn hiện có của dự án.
-   Không giả định webhook hợp lệ chỉ vì URL tồn tại; cần xác minh
    token/chữ ký theo tài liệu Meta và kiểm tra xử lý sự kiện trùng.
-   Không tự động gửi câu trả lời khi hội thoại đang do nhân viên tiếp
    quản hoặc khi trạng thái chưa rõ.
-   Không dùng dữ liệu khách hàng thật trong prototype.
-   Không thay đổi hoặc xóa các sửa đổi có sẵn của người dùng.
-   Không thực hiện commit, push, deploy, migration hoặc thay đổi môi
    trường production nếu chưa được yêu cầu rõ.

## 7. Định dạng báo cáo cuối mỗi giai đoạn

Dùng định dạng ngắn sau:

**Giai đoạn:** \[tên giai đoạn\]

**Đã xác minh / hoàn thành** - ...

**File đã đọc/tạo/sửa** - ...

**Kiểm tra đã thực sự chạy** - ...

**Chưa làm có chủ đích** - Nêu rõ các phần chưa kết nối API/backend nếu
đang ở giai đoạn prototype.

**Cần người dùng quyết định** - Đặt tối đa một câu hỏi trực tiếp nếu
thật sự cần. - Nếu cần duyệt, yêu cầu người dùng xác nhận rõ trước khi
tiếp tục.

## 8. Lệnh bắt đầu

Khi người dùng đưa skill này vào VS Code, hãy bắt đầu bằng **Giai đoạn 0
--- Khảo sát chỉ đọc**.

Chỉ khảo sát repository và báo cáo. Không tạo hoặc sửa bất kỳ file nào.
Không dựng UI, không nối API, không cài package và không thực hiện thay
đổi code cho đến khi người dùng duyệt kế hoạch prototype.

Câu kết thúc bắt buộc ở Giai đoạn 0:

"Đã hoàn tất khảo sát chỉ đọc. Tôi chưa thay đổi file nào. Hãy xác nhận
kế hoạch prototype để tôi bắt đầu dựng giao diện xem trước; tôi sẽ không
kết nối code thật cho đến khi bạn yêu cầu rõ ràng."
