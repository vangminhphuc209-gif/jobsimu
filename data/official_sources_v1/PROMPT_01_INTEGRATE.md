# Prompt 1 — Tích hợp gói nguồn mới, bảo toàn dữ liệu Jobsimu

Bạn là kỹ sư dữ liệu và backend phụ trách repository Jobsimu. Hãy triển khai thực tế, không chỉ đưa ra kế hoạch. Đọc AGENTS.md và hướng dẫn trong repo trước khi sửa. Nguồn mới: `data/official_sources_v1/`. Đọc README, manifest, source_registry và chạy tools/validate.py trước.

## Mục tiêu

Xây dựng pipeline nhập có thể chạy lại, có dry-run, kiểm duyệt, provenance và rollback để kết hợp dữ liệu mới với dữ liệu hiện có. Bảo toàn toàn bộ người dùng, câu trả lời và kết quả test, dữ liệu nghề/ngành/trường cũ, lịch sử tư vấn, lộ trình, mô phỏng, các ID và quan hệ khóa ngoại. Không tự nhập vào database thật.

## 1. Khảo sát bản local và bảo vệ công việc đang có

- Kiểm tra branch, git status, commit hiện tại và hướng dẫn theo thư mục. Máy người dùng có thể đang trên `feat/career-roadmap-redesign`, có commit chưa push và file đang sửa. Không giả định bản main giống local.
- Không reset --hard, clean -fd, force-push, tự stash, checkout đè file hoặc lấy cả main ghi đè ứng dụng. Nếu thiếu thư mục data, fetch origin rồi đưa CHỈ thư mục này từ origin/main vào local sau khi chắc chắn không ghi đè dữ liệu local cùng đường dẫn; trường hợp xung đột thì dùng worktree riêng hoặc hỏi quyết định cụ thể.
- Không commit các thay đổi có sẵn của người dùng. Tạo nhánh/worktree phù hợp cho phần việc mới.
- Đọc `app/training-majors.php`, `app/training-majors-importer.php`, `scripts/import_hnv_majors.php`, `jobsimu_hnv_data/`, các module admissions, migration và tests liên quan. Lập bảng ánh xạ schema thật; không sáng tác tên cột rồi coi như đang tồn tại.
- Importer P2 hiện dành cho cutoff/tuition. Không nhét bản ghi tổ hợp hoặc nghề vào mẫu này bằng điểm/học phí giả.

## 2. Thiết kế nhập tách biệt theo loại dữ liệu

- Reuse cơ chế hiện có khi phù hợp. Nếu cần bảng mới, viết migration bổ sung có kiểm tra tồn tại và kiểm thử trên DB tạm. Không đổi/xóa bảng cũ, không chạy migration thật qua web request.
- Có import_batches, trạng thái staged/validated/needs_review/approved/rejected, bản ghi nguồn, checksum, phiên bản, thời điểm, row locator, quyết định kiểm duyệt và lịch sử thay đổi. Điều chỉnh tên theo schema thật.
- Khóa ổn định tuyển sinh gồm trường + mã chương trình + năm + phương thức + phạm vi cơ sở/nhóm thí sinh nếu có. Tổ hợp là quan hệ con; phân biệt chương trình chuẩn/ELITECH/PFIEV/liên kết. Không gộp chỉ theo tên gần giống.
- Khóa O*NET nghề là phiên bản + O*NET-SOC Code; task là phiên bản + SOC + Task ID; interest là phiên bản + SOC + Element ID + Scale ID. Giữ dữ liệu thô và metadata nguồn.
- Mã chương trình HUST không được gán vào official_major_code. Chỉ ghép với ngành quốc gia khi có nguồn xác minh trực tiếp. Ghép tên tương tự chỉ tạo đề xuất chờ duyệt.
- Không nhập O*NET thành 1.016 ngành học Việt Nam. Nếu cần bảng nghề nguồn riêng, giữ namespace onet; tạo mapping đã duyệt sang careers hiện có, không thay ID cũ.
- Với null hoặc thiếu trường: không ghi đè dữ liệu hiện có, không chuyển thành 0 hoặc chuỗi rỗng. Mâu thuẫn có nguồn phải được ghi lại và chờ duyệt, không âm thầm thay thế.
- Không trộn năm. Dữ liệu năm 2026 không tự thành năm 2027. Không tự hợp nhất tổ hợp mọi trường rồi tuyên bố áp dụng chung.
- Manifest xác minh tính toàn vẹn, không chứng minh dữ liệu đã được duyệt. Không nâng status thành approved chỉ vì URL thuộc trường chính thức.

## 3. CLI và luồng nhập

Triển khai các chế độ tương đương validate, dry-run, stage, apply-approved, rollback-batch theo quy ước repo. Mặc định chỉ validate/dry-run; apply cần cờ rõ ràng và batch đã duyệt. Không thực thi migration/nhập khi require module.

Dry-run xuất số tạo mới, trùng hoàn toàn, bổ sung, mâu thuẫn, thiếu mapping, bị từ chối; kèm lý do và diff theo trường. Stage không thay dữ liệu public. Apply trong transaction, có cơ chế chống hai tiến trình nhập cùng batch, kiểm tra checksum và chống lặp. Nhập lại cùng lô không tạo bản sao hoặc đổi dữ liệu không liên quan.

Rollback chỉ tác động thay đổi thuộc batch; so sánh phiên bản hiện tại để không ghi đè chỉnh sửa về sau. Không xóa bản ghi đã được thực thể khác tham chiếu; ghi xung đột rollback thay vì gây mất dữ liệu.

Không gọi mạng trong transaction database. Chỉ đọc đường dẫn file nằm trong thư mục dataset, giới hạn kích thước; xử lý CSV UTF-8 an toàn, prepared statements, quyền admin và CSRF nếu có giao diện duyệt. Khi xuất CSV dành cho spreadsheet, xử lý formula injection ở lớp xuất, không sửa dữ liệu nguồn.

## 4. Quy tắc nghiệp vụ bắt buộc

- 68 bản ghi HUST là chương trình; tổng 9.880 chỉ tiêu dự kiến thuộc mọi phương thức, không riêng THPT.
- Các mã tổ hợp K01, DD2 giữ đúng nguồn. UI chỉ hiện mã, ví dụ A00 · A01 · D01, không cần diễn giải môn mặc định.
- Chưa có subject_weights và điều kiện đầy đủ: không dùng gói này để tính điểm hoặc dự đoán trúng tuyển. Không tự coi trọng số bằng 1.
- Không thêm điểm chuẩn/học phí không có nguồn. Không thay logic tính điểm Holland, EQ, MBTI, Big Five, CAAS, SCCT hoặc kết quả cũ trong nhiệm vụ nhập dữ liệu.
- O*NET là dữ liệu Hoa Kỳ, không suy ra mức lương Việt Nam, điều kiện hành nghề Việt Nam hay % phù hợp cá nhân. Nhiệm vụ nghề chỉ làm tư liệu, không tự đổi cơ chế chấm game.
- Không sửa routing game, mở API game đang khóa hoặc thay đổi dự án Unity.

## 5. Kiểm thử và nghiệm thu

Trên DB tạm có fixture dữ liệu cũ, kiểm tra: nhập hai lần không trùng; null không ghi đè; xung đột nguồn/năm được giữ; chương trình cùng tên khác mã không bị gộp; mã chương trình không thành mã ngành; mối quan hệ source/occupation/task/interest đúng; thiếu module runtime không phụ thuộc migration lock; lỗi giữa apply rollback toàn transaction; rollback batch không đụng dữ liệu sửa sau đó; stage chưa làm public; người không có quyền không apply được.

So sánh ID, số lượng và checksum dữ liệu người dùng/kết quả test trước–sau trên DB thử nghiệm; chạy tests liên quan của repo và validator dataset. Chỉ báo pass cho lệnh đã chạy. Nếu thiếu MySQL, nêu rõ giới hạn kiểm thử SQLite.

Kết quả bàn giao: mã importer và migration cần thiết, tests, báo cáo dry-run, mapping chưa giải quyết, lệnh vận hành thật đã chuẩn bị, hướng dẫn rollback, diff giới hạn phạm vi. Tạo commit/PR riêng nếu được phép; việc nhập database thật chỉ thực hiện sau khi người dùng xem báo cáo cụ thể và cho phép. Không dừng ở mô tả nếu vẫn có thể hoàn thành code và kiểm thử độc lập.
