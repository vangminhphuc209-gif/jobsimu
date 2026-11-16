# JobSimu — Góc người trong nghề và trợ lý hướng nghiệp

Ngày: 16/11/2026. Đây là kế hoạch sản phẩm và kỹ thuật, không phải tuyên bố hệ thống đã bao phủ mọi kiến thức hướng nghiệp.

## 1. Bản thử được triển khai trong nhánh này

Trang chủ có mục Góc người trong nghề dẫn vào AI & Bản đồ hành trình. Có 9 tình huống ở 3 nghề: giáo viên, lập trình viên, thiết kế đồ họa. Học sinh chọn tình huống, chọn/sửa câu hỏi gợi ý rồi gửi; chat tiếp tục theo tình huống. Mỗi tình huống có bối cảnh, hai hướng để cân nhắc, câu hỏi phản tư, nguồn tham khảo nhiệm vụ và nhãn giả định/chưa thẩm định.

Tình huống là nội dung biên soạn thử nghiệm. O*NET chỉ tham khảo nhóm nhiệm vụ nghề tại Hoa Kỳ; không chứng minh câu chuyện đã xảy ra, không đại diện mọi môi trường tại Việt Nam. Chưa có phỏng vấn hay lời chứng thực từ người đi làm. Không sử dụng nội dung nhạy cảm để làm bài thử.

Lịch sử được tách theo tài khoản và tình huống; chat thông thường là một ngữ cảnh riêng. Giữ hạn mức hiện có 10 lượt/ngày dùng chung. Bản thử không lưu riêng phản tư thành chỉ số hồ sơ và không đưa chat vào xếp hạng nghề. Học sinh có thể viết phản tư trong hội thoại. Chat tình huống mặc định không gửi tóm tắt hồ sơ đánh giá; chỉ gửi khi học sinh chọn. Tin nhắn và câu trả lời gần đây trong cùng ngữ cảnh vẫn được gửi để duy trì hội thoại, vì vậy không nhập thông tin nhận dạng.

Khi chưa cấu hình AI hoặc API lỗi, trang hiển thị bản đọc thử biên soạn sẵn, ghi rõ chưa phân tích câu trả lời riêng. Không giả vờ đây là phản hồi AI. Không phát sinh nhà cung cấp hay dịch vụ trả phí mới: tiếp tục adapter AI hiện có.

Có 4 hướng dẫn thao tác với liên kết nội bộ do server quản lý: làm đánh giá, xem báo cáo, tìm ngành đào tạo, tra cứu trường. Chat thông thường có thể trả lời các câu hỏi từ khóa bằng hướng dẫn này khi không có AI. Đây chưa phải công cụ tra cứu toàn bộ dữ liệu nghề/tuyển sinh.

## 2. Định hướng: tra cứu tri thức trước, fine-tuning sau

Dùng mô hình sẵn có kết hợp kho dữ liệu do dự án quản lý (RAG). Mỗi câu hỏi sẽ lấy dữ liệu liên quan, đúng nguồn và thời điểm để cung cấp cho mô hình; không cần nhét toàn bộ website vào một prompt. NIST mô tả RAG là kết hợp mô hình với hệ thống truy xuất tri thức và cung cấp nội dung tìm được trong ngữ cảnh [1].

Fine-tuning chỉ nên thử sau khi có tập đánh giá ổn định và quyền sử dụng dữ liệu, nhằm cải thiện phong cách tiếng Việt, cách hỏi lại hoặc định dạng trả lời. Không dùng fine-tuning làm nơi lưu điểm chuẩn, học phí hay URL website thay đổi theo năm. Không tự dùng chat học sinh làm dữ liệu training.

Mục tiêu: hỗ trợ toàn bộ nhóm nhu cầu hướng nghiệp trong phạm vi dữ liệu đã duyệt; khi chưa có dữ liệu thì nói rõ phần thiếu và chỉ đường tra cứu, thay vì hứa biết tất cả.

## 3. Năm loại câu hỏi và nguồn xử lý

| Nhu cầu | Ví dụ | Cách trả lời |
|---|---|---|
| Hiểu nghề/ngành | Ngành CNTT và nghề lập trình khác nhau thế nào? | Truy xuất hồ sơ nghề và ngành đã duyệt; giải thích quan hệ nhiều-nhiều, dẫn nguồn |
| Tuyển sinh theo năm | Ngành X trường Y năm Z xét tuyển thế nào? | Truy vấn bản ghi có cấu trúc đúng trường, chương trình, năm, phương thức; hỏi lại nếu thiếu |
| Hiểu bản thân | Kết quả bài test của em có ý nghĩa gì? | Chỉ lấy hồ sơ của tài khoản hiện tại khi được chọn; nêu giới hạn tự báo cáo |
| Hiểu đời sống nghề | Nếu khách hàng đổi yêu cầu thì sao? | Tình huống giả định hoặc phỏng vấn đã duyệt, phân biệt rõ hai loại |
| Sử dụng website | Em xem lại kết quả ở đâu? | Sổ hướng dẫn chức năng có phiên bản và action ID; server tạo đường dẫn hợp lệ |

Một câu hỏi có thể gồm nhiều nhu cầu. Ví dụ “Em thích Toán, nên học ngành gì và xem trường ở đâu?” cần kết hợp tự nhận xét, hồ sơ ngành và nút mở danh mục. Không biến thích một môn thành kết luận đủ điều kiện xét tuyển.

## 4. Chuẩn hóa kho dữ liệu

Tận dụng bảng hiện có: careers, training_majors, major_fields, major_subgroups, training_major_mappings, knowledge_articles, institutions, programs, admission_records, tuition_records và admission_source_documents. Kiểm kê trạng thái công bố/xác minh thực tế trước khi nhập vào chỉ mục; trường `published` không tự chứng minh độ đúng của nội dung.

Giữ tách danh mục ngành đào tạo training_majors với careers và bảng majors cũ dùng trong tuyển sinh. Tạo ánh xạ có người duyệt theo mã ngành, hệ đào tạo, tên chuẩn và thời điểm; không nối bằng tên gần giống rồi coi là chắc chắn. Dữ liệu lịch sử HNV có thể giúp tìm ngành/trường, không được mặc định là thông báo năm hiện hành.

Mỗi tài liệu cần: ID, loại, tiêu đề, nội dung, đơn vị phát hành, URL chuẩn, quốc gia, ngôn ngữ, nghề/ngành/trường liên quan, năm tuyển sinh nếu có, ngày ban hành, hiệu lực, ngày kiểm tra, trạng thái duyệt, người duyệt, quyền sử dụng, phiên bản và checksum. Tài liệu không có quyền lưu toàn văn chỉ giữ metadata, tóm tắt được phép và liên kết.

Quy trình: nguồn được chọn → nhập vùng chờ → tách nội dung/khử trùng → kiểm tra mã, năm, nguồn và quyền sử dụng → duyệt → tạo chỉ mục → công bố. Khi nội dung đổi, thu hồi trạng thái xác minh và làm mới chỉ mục/cache. Tài liệu bị rút phải ngừng xuất hiện ở truy xuất. Không tự truy cập URL do người dùng đưa vào từ server; nếu bổ sung nhập URL thì phải chặn mạng nội bộ, redirect nguy hiểm, giới hạn loại file và dung lượng.

Dữ liệu trải nghiệm thật cần sự đồng ý về mục đích sử dụng, ẩn thông tin cá nhân, bối cảnh làm việc và quyền rút nội dung. Mời người đi làm góp ý 9 tình huống trước, rồi mở rộng từng nghề lên 10–15 tình huống khi đủ chất lượng. Không suy rộng một lời kể thành chuẩn chung của nghề.

## 5. Kiến trúc đề xuất trên nền PHP hiện có

1. Bộ phân loại ý định xác định câu hỏi về nghề, tuyển sinh, hồ sơ, tình huống hay thao tác. Câu thiếu trường/năm cần hỏi lại trước khi trả số liệu.
2. Service truy xuất chỉ đọc nhận tham số có kiểu rõ ràng; mô hình không được gửi SQL tùy ý. Tuyển sinh dùng truy vấn có cấu trúc. Văn bản dùng tìm từ khóa và tìm ngữ nghĩa, có bộ lọc nguồn/năm/trạng thái/quyền truy cập.
3. Gộp kết quả, loại trùng, chọn vài đoạn liên quan kèm ngữ cảnh tài liệu. Tìm kiếm lai là phương án để đánh giá, không giả định đã tốt hơn trên tiếng Việt của dự án [2].
4. Adapter mô hình nhận câu hỏi, lịch sử giới hạn, trích đoạn có ID, và hồ sơ cá nhân tối thiểu nếu được chọn. Tài liệu và tin nhắn là dữ liệu không đáng tin cậy, không có quyền đổi chỉ dẫn hệ thống.
5. Trả kết quả có cấu trúc: answer, evidence_ids, missing_information, next_questions, action_ids. Server kiểm tra evidence_ids nằm trong tập đã lấy và action_ids thuộc danh mục cho phép. Kiểm tra ID không thay thế kiểm tra rằng nguồn thực sự hỗ trợ câu trả lời.
6. Giao diện hiện câu trả lời, nguồn/năm/giới hạn, câu hỏi tiếp và nút hành động. Không có bằng chứng thì trả lời thiếu dữ liệu, không điền số liệu từ trí nhớ mô hình.

Dữ liệu SQL hiện có là nguồn gốc cho số liệu; chỉ mục tìm kiếm là bản có thể dựng lại. Giai đoạn đầu dùng tìm từ khóa và truy vấn có cấu trúc để đo chất lượng; chỉ thêm vector service khi bộ câu hỏi cho thấy cần tìm ngữ nghĩa. Không ép đổi toàn bộ PHP/MySQL sang kiến trúc mới.

Tách các service dự kiến: KnowledgeRepository, AdmissionsReader, WebsiteGuideRegistry, ConversationService, AnswerValidator và AIProvider. Bản thử hiện tại mới có catalog tình huống, bộ hướng dẫn nhỏ và mở rộng ConversationService cũ; chưa có pipeline RAG đầy đủ.

## 6. Trợ lý sử dụng website

Mỗi mục hướng dẫn gồm action_id, đường dẫn, điều kiện đăng nhập, vai trò được phép, bước thao tác, kết quả mong đợi, lỗi thường gặp và phiên bản website. Đường dẫn do server tạo từ action_id; AI không tự phát minh.

Ví dụ: “Xem báo cáo của tôi” → giải thích ngắn → nút Mở báo cáo. Chưa có bài làm → nút Mở bộ đánh giá. “So sánh hai trường” → hỏi hai trường nếu thiếu → mở trang so sánh với ID đã kiểm tra. Không tự sửa hồ sơ hay gửi thông tin cho giáo viên. Thao tác ghi dữ liệu trong tương lai cần xem trước nội dung và xác nhận cụ thể.

Có thể thêm nút trợ giúp theo trang sau khi registry ổn định. Gửi page_id và trạng thái cần thiết, không gửi toàn bộ HTML, cookie hoặc dữ liệu người khác. Tài liệu admin chỉ cho admin; nội dung nháp không dùng để trả lời học sinh.

## 7. Lưu trữ, chi phí và cập nhật

Lưu metadata, phiên bản, ánh xạ và số liệu trong SQL; file nguồn được phép lưu đặt ở kho object với checksum; chỉ mục tìm kiếm lưu riêng và có thể dựng lại. Tách log vận hành khỏi nội dung chat, không ghi API key. Có sao lưu và diễn tập khôi phục.

Ví dụ lập kế hoạch, chưa phải số đo: 10.000 đoạn × 2 KB ≈ 20 MB văn bản; 10.000 vector × 1.536 chiều × 4 byte ≈ 61 MB vector thô. Còn overhead chỉ mục, metadata, file gốc và bản sao lưu. Dung lượng thường không phải lý do cần train mô hình riêng; phải đo chi phí API, nhập dữ liệu và duyệt nội dung.

Ngân sách tháng = số lượt × (token đầu vào × đơn giá + token đầu ra × đơn giá) + embedding tài liệu thay đổi + lưu trữ/vận hành. Đo token trên bộ thử rồi mới chọn mô hình và giới hạn. Giữ hạn mức tài khoản, timeout, giới hạn độ dài; cache chỉ câu hỏi tri thức công khai theo phiên bản dữ liệu, không chia sẻ cache hồ sơ cá nhân giữa tài khoản.

Đề xuất kiểm tra nguồn tuyển sinh thường xuyên trong mùa công bố, theo lịch thưa hơn ngoài mùa; đây là đề xuất, chưa tạo tác vụ tự động. Thông tin không xác minh đủ mới phải hiển thị năm/ngày kiểm tra. Website guide cập nhật cùng PR đổi giao diện.

## 8. Triển khai theo cổng nghiệm thu

| Giai đoạn | Việc làm | Điều kiện hoàn thành |
|---|---|---|
| P0 — bản thử hiện tại | 9 tình huống, hỏi nhanh, nguồn/giới hạn, lịch sử riêng, hướng dẫn website, fallback | Test đăng nhập, CSRF, hạn mức, chống gửi lặp, ngữ cảnh và opt-in hồ sơ; người dùng thử nội dung |
| P1 — kho tri thức | Kiểm kê dữ liệu, ánh xạ ngành/nghề, trạng thái duyệt, 20 hướng dẫn website, nhập tài liệu có phiên bản | Không lẫn nháp/đã duyệt, lịch sử/hiện hành; thu hồi tài liệu cập nhật được chỉ mục |
| P2 — chat tra cứu | Truy xuất nghề/ngành và SQL tuyển sinh, evidence ID, hỏi lại, action ID | Đạt bộ đánh giá tri thức/nguồn/thao tác; không trả số liệu thiếu trường/năm/nguồn |
| P3 — trải nghiệm sâu | Phỏng vấn được phép, duyệt tình huống Việt Nam, nhiều góc nhìn, phản tư tự xác nhận | Có provenance/consent, chuyên môn duyệt; không tự chuyển chat thành điểm phù hợp |
| P4 — cá nhân hóa và vận hành | Trợ giúp theo trang, quyền xem hồ sơ, phản hồi chất lượng, theo dõi lỗi/chi phí | Kiểm tra quyền, xóa dữ liệu, giám sát và rollback; pilot với giáo viên/học sinh |

Không ấn định lịch hoàn thành trước khi biết số người duyệt và lượng tài liệu. Ưu tiên P1 rồi P2, song song thu góp ý nội dung P0; khảo sát môn học yêu thích là phần việc riêng, không mất khỏi backlog.

## 9. Bộ đánh giá và điều kiện mở rộng

Chuẩn bị 120 câu hỏi do người duyệt chốt đáp án: 30 nghề/ngành, 25 tuyển sinh có năm, 20 hướng dẫn website, 15 thiếu dữ liệu/mâu thuẫn nguồn, 15 tình huống, 15 quyền riêng tư/prompt injection. Giữ một phần riêng không dùng để sửa prompt. Thêm câu tiếng Việt không dấu, tên viết tắt, ngành cùng tên khác trường và câu nối tiếp đổi chủ đề.

Mục tiêu đề xuất trước khi mở rộng: ít nhất 90% truy xuất đúng nguồn trong top 5 và 90% hướng dẫn thao tác đúng trên bộ chốt; 100% số liệu tuyển sinh được trả phải có nguồn đúng bản ghi/năm, 0 rò dữ liệu chéo tài khoản, 0 action ID ngoài danh mục. Đo độ đúng dẫn nguồn bằng người kiểm tra, không chỉ đếm URL. Đây là ngưỡng mục tiêu, chưa phải kết quả đạt được.

Đo thời gian trả lời p50/p95, chi phí/lượt, tỷ lệ thiếu nguồn, tỷ lệ người dùng tìm được chức năng và phản hồi hữu ích. Khảo sát với nhóm nhỏ có giáo viên điều phối; không tối ưu chỉ theo số lượt chat hoặc thời gian giữ học sinh ở website. Khi phát hiện nguồn sai, có thể thu hồi nguồn, tắt truy xuất liên quan và trở lại câu trả lời thiếu dữ liệu.

## 10. Kiểm tra bản thử và giới hạn hiện tại

- `php tests/career-conversations-test.php`: danh mục, nguồn/nhãn, gợi ý, fallback, guide lookup và tách lịch sử.
- `python tests/career-conversations-http.py` (requests; PHP_BIN tùy chọn): SQLite/server tạm, route thật, opt-in, lịch sử, gửi lặp, XSS, CSRF, đăng nhập và quota.
- CI chạy PHP 8.1/8.3. Không thêm migration; không áp dụng thay đổi lên DB thật; không đổi game 3D hoặc thuật toán xếp hạng.
- Chưa đánh giá chất lượng trả lời của mô hình qua API thật, chưa có phỏng vấn xác minh, chưa có UI xóa lịch sử riêng, chưa có RAG toàn bộ catalog. Cần hoàn tất chính sách lưu/xóa và đánh giá live trước khi mở rộng pilot. Lịch sử hiển thị tối đa 20 tin cùng ngữ cảnh trong 200 tin gần nhất; không phải truy xuất lịch sử vô hạn.

## Nguồn thiết kế

[1] NIST, định nghĩa RAG: https://csrc.nist.gov/glossary/term/retrieval_augmented_generation (đọc 16/11/2026).

[2] Anthropic, Contextual Retrieval: https://www.anthropic.com/research/contextual-retrieval (đọc 16/11/2026). Tham khảo kỹ thuật truy xuất; không dùng benchmark của bài để cam kết chất lượng JobSimu.

Nguồn tham khảo nhóm nhiệm vụ cho bản thử: https://www.onetonline.org/link/summary/25-2022.00 ; https://www.onetonline.org/link/summary/15-1252.00 ; https://www.onetonline.org/link/summary/27-1024.00 . Tình huống và đối thoại không phải trích dẫn từ các nguồn này.
