# JOBSIMU — Career & Admissions Intelligence (Kế hoạch và tiêu chuẩn dữ liệu)

Cập nhật: 2026-11-09. Tài liệu thiết kế, **chưa phải tính năng đã triển khai**.

## Mục tiêu
Liên kết nghề nghiệp -> ngành đào tạo -> chương trình -> trường -> dữ liệu tuyển sinh theo từng năm -> lộ trình chuẩn bị của học sinh. Giữ PHP/MySQL/cPanel, tái sử dụng app/knowledge-hub.php, careers, knowledge_articles, external_portals, RIASEC và mô phỏng nghề.

## Nguồn tham khảo
- Danh mục nhóm nghề (24 nhóm): https://hocvienhuongnghiep.com/tu-dien-huong-nghiep/
- Công cụ tra cứu trường: https://hocvienhuongnghiep.com/thong-tin-tra-cuu-cua-cac-truong-dh-cd/
- Cổng tuyển sinh Bộ GD&ĐT: https://tuyensinh.moet.gov.vn/ts/
- Thông báo chính thức từng trường (ưu tiên cao nhất cho học phí, tín chỉ và ngành).
- Cơ quan chủ quản Công an/Quân đội (sơ tuyển, điều kiện riêng).

Trang tham khảo không được coi là bằng chứng cho điểm chuẩn/học phí khi nội dung không được công bố rõ. Không tự động sao chép toàn văn nội dung bên thứ ba khi chưa có quyền.

## Mô hình dữ liệu chuẩn hóa (đề xuất)
1. career_groups: id, name, slug, display_order, source_url.
2. careers: giữ bảng hiện tại, thêm group_id nullable, source_status, verification metadata (qua migration tương thích).
3. majors: id, code, name, degree_level, description, core_subjects, typical_skills, source_url.
4. career_major_paths: career_id, major_id, relevance, notes (N:N).
5. institutions: id, code, name, aliases, type, province, website, admissions_url, public_status.
6. campuses: institution_id, address, province.
7. programs: id, institution_id, major_id, program_code, name, campus_id, degree_level, duration_semesters, credits_required nullable, status.
8. admissions_cycles: id, program_id, year, track (general / police / military / civilian), official_notice_url, publication_date.
9. admission_methods: cycle_id, method_code, method_name, score_scale, score_formula, conversion_rule_url.
10. subject_combinations: code, subjects_json; admission_method_combinations: admission_method_id, combination_code.
11. cutoffs: admission_method_id, combination_code nullable, candidate_category nullable, region_group nullable, cutoff_score decimal nullable, score_scale, quota nullable, source_document_id, verified_at. UNIQUE phải tính đến cohort/method/combination/category; không ép một điểm cho mọi tổ hợp.
12. tuition_records: program_id, academic_year, charge_basis (credit / semester / academic_year / whole_course / exempt), amount_vnd nullable, credit_load_assumed nullable, mandatory_fees_vnd nullable, policy_url, source_document_id. Không dùng 0 khi chưa biết học phí.
13. admission_requirements: cycle_id, requirement_type (academic / health / politics / eyesight / physical / age / residency / portfolio / language / pre_screening / other), description, applicability_json, official_url, source_document_id.
14. admission_events: cycle_id, event_type, start_at, end_at, timezone, status (announced / historical / tentative), source_document_id.
15. source_documents: id, publisher, title, url, document_date, fetched_at, checked_at, content_hash nullable, license_status, verification_status (unverified / verified / superseded), reviewer_id nullable.
16. learning_roadmaps: career_id, grade_level, month_offset, subject, objective, resource_url, estimated_hours, verification_state.
17. student_shortlists: user_id, program_id, saved_at, notes.

Các bảng nhập dữ liệu phải có lịch sử cập nhật và provenance; không xóa lịch sử khi ban hành đề án mới.

## Quy tắc nghiệp vụ
- Mọi dữ liệu tuyển sinh có khóa (program_id, year, method, combination, candidate group).
- Điểm chuẩn không so trực tiếp giữa phương thức hoặc thang điểm khác nhau.
- Hiển thị năm tài liệu, ngày kiểm chứng và nguồn; khi thiếu dữ liệu: “Chưa có dữ liệu xác thực”.
- Tăng học phí (%) = (học phí năm sau / học phí năm trước - 1)*100 chỉ khi cùng chương trình, cùng cơ sở tính và có thể so sánh. Không suy luận trần tăng nếu không có văn bản.
- Học phí dự toán = tổng số tín chỉ dự kiến * đơn giá tín chỉ nếu trường áp dụng theo tín chỉ; phân biệt học phí, lệ phí và sinh hoạt phí.
- Yêu cầu sức khỏe, sơ tuyển và chính trị là checklist mang tính hướng dẫn, không chẩn đoán hay tự động kết luận bị loại.
- Lịch sử năm 2026 không được tự suy ra năm 2027.
- Các chương trình miễn học phí hoặc đào tạo đặc thù phải lưu riêng cơ chế chi trả, trợ cấp, nghĩa vụ phục vụ.
- AI chỉ đưa ra khuyến nghị từ records có nguồn và nhắc người dùng xác nhận đề án năm dự tuyển.

## UX
Knowledge Hub: danh mục nghề (24 nhóm), lọc theo RIASEC + môn học + sở thích.
Trang nghề: công việc thực tế, tình huống, ưu nhược, năng lực, môn học, đường đào tạo, chứng chỉ, game phù hợp.
University Explorer: tìm trường/ngành/tỉnh, chọn năm, phương thức, tổ hợp, so sánh 2-3 chương trình.
Chi tiết chương trình: bảng điểm chuẩn theo 3-5 năm, học phí từng năm, số tín chỉ, điều kiện, học bổng, mốc tuyển sinh, nguồn và ngày cập nhật.
Planner: nhập lớp, mục tiêu trường/ngành, điểm hiện tại, ngân sách; nhận lộ trình; lưu shortlist; cảnh báo lịch theo kỳ.
Admin: nhập CSV có xem trước và kiểm tra; duyệt nguồn; nhật ký chỉnh sửa; trạng thái xác minh; phát hiện dữ liệu lỗi.

## Dữ liệu thực đã đối chiếu (ví dụ duy nhất, chưa seed production)
Đại học Bách khoa Hà Nội, Khoa học máy tính IT1, điểm xét tuyển theo điểm thi THPT:
2023 = 29.42; 2024 = 28.53; 2025 = 29.19.
Nguồn: https://soict.hust.edu.vn/diem-chuan-tham-khao.html
Tổ hợp công bố trên trang tham khảo: A00, A01, K01. Không diễn giải là điểm chuẩn riêng của từng tổ hợp.
Tuyển sinh quân sự 2026: sơ tuyển 10/02–15/04/2026 theo nguồn Bộ Quốc phòng:
https://mod.gov.vn/wcm/connect/mod/sa-mod-site/sa-ttsk/sa-tt-qpan/them-phuong-thuc-xet-tuyen-moi-cho-ky-tuyen-sinh-quan-su-nam-2026

## Lộ trình triển khai ưu tiên
P0: Migration idempotent MySQL + SQLite cho toàn bộ bảng, tests FK/index; seed 24 nhóm ngành chuẩn hóa.
P1: Trường/chương trình, danh mục ngành liên kết nghề; trang tìm kiếm và trang chi tiết; admin CRUD.
P2: Import dữ liệu nguồn chính thức có người duyệt, dashboard độ phủ, bảng điểm chuẩn và học phí 2023–2026, biểu đồ so sánh.
P3: Điều kiện đặc thù và lịch tuyển sinh; hồ sơ học sinh, checklist và lộ trình cá nhân.
P4: Tích hợp mô phỏng 3D; mỗi nghề có thể có game riêng; nghề chưa có game vẫn có hồ sơ học tập.
P5: Kiểm thử trực quan, accessibility, phân quyền, bảo vệ dữ liệu học sinh, load test, triển khai cPanel và rollback.

## Điều kiện nghiệm thu
- Không ghi giá trị ngẫu nhiên hoặc AI-generated thành dữ kiện tuyển sinh.
- 100% điểm chuẩn/học phí/điều kiện công bố có URL nguồn và năm.
- Phân biệt null / 0, năm tuyển sinh / năm học, hệ quân sự / dân sự.
- Form tìm kiếm có phân trang, lọc tổ hợp/năm; SQL prepared statements và escaped output.
- Migration chạy lại không trùng bản ghi, seed không ghi đè sửa đổi của admin.
- Kiểm thử: cutoff theo phương thức/tổ hợp, tuition theo niên khóa, cảnh báo hồ sơ nguồn cũ, phân quyền, không lộ PII.
