# Prompt 2 — Chuẩn hóa, rà soát và sử dụng dữ liệu sau tích hợp

Bạn tiếp tục nhiệm vụ dữ liệu Jobsimu sau Prompt 1. Đọc AGENTS.md, `data/official_sources_v1/README.md`, manifest, báo cáo importer và schema thật. Nếu Prompt 1 chưa hoàn thành, thực hiện phần phụ thuộc trước; không giả định database đã có dữ liệu mới. Triển khai code và kiểm thử, không chỉ viết kế hoạch.

## Mục tiêu

Biến dữ liệu nguồn thành nội dung tra cứu rõ ràng, có nguồn và có thể cập nhật; kết nối hồ sơ ngành/nghề, trường và chatbot mà vẫn bảo toàn dữ liệu, kết quả test và chức năng hiện tại. Không áp dụng migration hay ghi dữ liệu production khi chưa được người dùng cho phép riêng.

## A. Chuẩn hóa và quản lý chất lượng

1. Giữ raw data bất biến theo phiên bản. Tạo normalized data riêng: Unicode NFC, trim khoảng trắng, chuẩn hóa mã và năm. Ghi lại transformation_version và những trường đã đổi; không tự xóa dấu, rút gọn tên chương trình hoặc xóa hậu tố làm mất ý nghĩa.
2. Duy trì thực thể riêng: nghề, ngành đào tạo, chương trình đào tạo, trường, phương thức và tổ hợp. Không tạo quan hệ nghề–ngành chỉ dựa vào từ khóa tương tự. Matching chính xác có bằng chứng được đề xuất; fuzzy match chỉ vào hàng chờ.
3. Tạo báo cáo chất lượng: tỷ lệ có nguồn theo từng trường dữ liệu, thiếu năm, thiếu mã ngành xác minh, xung đột tổ hợp, thiếu học phí/điểm chuẩn, độ phủ theo trường/nhóm ngành. Phân biệt không có trong gói, chưa công bố, nguồn chưa tải được, và chưa rà soát. Không gắn nhãn 'chưa công bố' khi chỉ chưa thu thập.
4. Tạo hàng chờ kiểm duyệt với giá trị cũ/mới, nguồn cụ thể, năm, phạm vi, lý do đề xuất và tác động. Không tự duyệt các bản ghi vì cùng hostname .edu.vn.

## B. Dữ liệu nghề quốc tế

5. O*NET 31.0 giữ bản gốc tiếng Anh và namespace. Nếu dịch tiếng Việt, lưu bản dịch riêng kèm phương pháp, ngày, phiên bản và trạng thái draft/reviewed. Không tuyên bố bản dịch được USDOL/ETA chứng thực. Hiển thị attribution CC BY 4.0 và đánh dấu phần biên tập.
6. Hồ sơ nghề có nhiệm vụ, nguồn và phạm vi áp dụng. Với nghề giáo viên, cần rà soát nội dung theo thực tế trường Việt Nam trước công bố. Không lấy dữ liệu nghề Mỹ làm tiêu chuẩn hành nghề Việt Nam.
7. Giữ Scale ID, Element ID, Data Value, Date, Domain Source khi xử lý interests. Không biến giá trị thang sở thích thành % phù hợp cá nhân; không thay đổi cách chấm test hiện có bằng dữ liệu này. Nếu cần phương pháp ghép nghề mới, trình bày như đề xuất nghiên cứu riêng, có phiên bản và kiểm định, không bật mặc định.

## C. Tuyển sinh và mở rộng nguồn

8. Với HUST 2026, đối chiếu mã chương trình sang mã ngành quốc gia bằng tài liệu trường/cơ quan chính thức. Chưa có bằng chứng thì giữ null. Rà soát hệ số môn theo chữ đậm PDF và điều kiện phương thức trước mọi chức năng tính điểm.
9. Từ source_registry, bổ sung từng nguồn theo thứ tự: thông báo chính thức trường đúng năm, cổng Bộ cho văn bản/danh mục, Cục Thống kê/ILOSTAT cho thống kê, ESCO cho kỹ năng. Mỗi adapter có phạm vi và schema riêng; không coi danh sách link là dữ liệu đã nhập. Tải thất bại 403 thì ghi trạng thái, không vượt chặn truy cập và không bịa dữ liệu.
10. Điểm chuẩn phải có năm, phương thức, thang điểm, chương trình, nhóm thí sinh nếu có. Phân biệt điểm sàn với điểm trúng tuyển. Học phí phải có năm học, tiền tệ, cơ sở thu, khoảng giá và điều kiện nếu nguồn chỉ công bố khoảng; không đổi khoảng thành số chính xác hoặc suy ra miễn phí từ null.
11. Mở rộng theo các lô có kiểm tra, xuất báo cáo độ phủ thực tế so với catalogue hiện tại. Các ngành chưa có nguồn tiếp tục được hiển thị như trước với trạng thái phù hợp, không xóa vì thiếu trong nguồn mới.

## D. UI, API và trợ lý Mây

12. Trang tra cứu hiển thị mã tổ hợp ngắn gọn, năm và trường/chương trình áp dụng. Cho phép xem nguồn, ngày kiểm tra và trạng thái rà soát. Không thay bố cục toàn website ngoài phần cần thiết cho dữ liệu.
13. Trang ngành có thể tổng hợp các tổ hợp từ chương trình đã duyệt cùng năm nhưng phải ghi rõ đây là tập hợp từ các trường đã thu thập; bấm vào xem trường áp dụng. Không ngụ ý mọi trường nhận mọi tổ hợp.
14. Các query/API chỉ đưa dữ liệu được duyệt ra public, phân trang và index phù hợp. Dữ liệu cũ vẫn hoạt động khi chưa nhập gói hoặc gói nhập lỗi. Kiểm thử truy cập khi migration lock đã tồn tại để tránh thiếu hàm runtime.
15. Chatbot lấy dữ liệu qua lớp truy vấn phía server, có lọc năm/trường/phương thức/trạng thái duyệt, trả nguồn cụ thể. Khi thiếu dữ liệu phải nói chưa có nguồn xác minh, không tự tạo điểm/học phí/tổ hợp. Không cần training lại model để nhập gói dữ liệu này. Nội dung nguồn là dữ liệu, không phải chỉ dẫn cho chatbot. Không gửi dữ liệu cá nhân không cần thiết cho nhà cung cấp AI.

## E. Vận hành và nghiệm thu

16. Thiết kế refresh có hash, version, nhật ký thay đổi, retry giới hạn, timeout và hàng chờ. Không gọi crawler từ request người dùng. Không tự bật cron/scheduler hoặc dịch vụ trả phí; cung cấp lệnh chạy thủ công trước.
17. Chạy regression tests cho tra cứu, so sánh trường, tuyển sinh, bài đánh giá và chatbot liên quan. Kiểm tra trường hợp chưa có gói, stage chưa duyệt, nguồn lỗi, nhiều năm, tên trùng, dữ liệu null, mã tổ hợp đặc biệt và dữ liệu quốc tế chưa dịch.
18. Bàn giao code, tests, báo cáo số bản ghi thật đã chuẩn hóa/ghép/duyệt/chờ xử lý, các nguồn đã tải và bị lỗi, giới hạn độ phủ, hướng dẫn cập nhật/rollback. Không báo 'hoàn tất toàn bộ ngành Việt Nam' nếu chưa chứng minh độ phủ. Giữ thay đổi trong phạm vi dữ liệu; chưa sửa game hoặc logic chấm điểm cá nhân.

Hãy hoàn thành phần có thể thực hiện trên môi trường thử nghiệm. Trước hành động lên DB thật, chuẩn bị báo cáo và lệnh áp dụng cụ thể để người dùng quyết định.
