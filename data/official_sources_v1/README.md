# Dữ liệu nguồn chính thức — gói v1

Gói bổ sung độc lập cho Jobsimu. Chỉ là dữ liệu staging; chưa nhập database và chưa thay đổi website.

## Phạm vi đã thu thập

| Tệp | Phạm vi |
| --- | --- |
| `admissions/hust_2026.json` | 68 chương trình ĐHBK Hà Nội, năm 2026; mã chương trình, nhóm chương trình, tổ hợp THPT và tổng chỉ tiêu dự kiến từng chương trình |
| `onet/occupation_data.csv` | 1.016 mã nghề O*NET-SOC, tên và mô tả tiếng Anh |
| `onet/tasks/*.csv` | 18.838 nhiệm vụ, chia theo 22 nhóm mã SOC; giữ nguyên Task ID, loại nhiệm vụ, ngày và Domain Source |
| `onet/career_interest_types.csv` | 8.307 dòng sở thích nghề; giữ Element ID, Scale ID, giá trị và phương pháp nguồn |
| `onet/content_model_reference.csv` | 3.006 mục từ điển mô hình O*NET |
| `onet/scales_reference.csv` | 33 thang đo và giới hạn giá trị |
| `manifest.json` | URL, SHA-256 của nguồn tải xuống, phiên bản, số dòng và hash từng tệp đầu ra |
| `source_registry.json` | Nguồn đã nhập và các nguồn còn chờ; không coi danh sách nguồn là dữ liệu đã thu thập |

O*NET phiên bản 31.0 là dữ liệu Hoa Kỳ, không phải danh mục ngành đào tạo Việt Nam. Giữ tiếng Anh gốc để tránh tạo bản dịch chưa được rà soát. Chưa tạo liên kết nghề–ngành Việt Nam tự động.

## Các giới hạn phải giữ khi tích hợp

- Chưa bao phủ toàn bộ trường/ngành Việt Nam. Không trình bày 68 chương trình như 68 ngành quốc gia độc lập.
- Mã BF1, IT1, IT-E10... là mã chương trình của trường, không phải mã ngành quốc gia. `official_major_code=null` là chủ ý.
- Tổng chỉ tiêu 9.880 là tổng mọi phương thức của các chương trình; không coi là chỉ tiêu riêng THPT.
- Tổ hợp trong JSON chỉ thuộc cột THPT. Nguồn còn có XTTN và ĐGTD nhưng chưa chuẩn hóa hai phương thức này.
- PDF dùng chữ đậm để quy định hệ số Toán. Gói này chưa chuẩn hóa chữ đậm: `subject_weights=null`, `score_calculation_ready=false`. Chỉ dùng để tra cứu tổ hợp sau rà soát; không tính điểm hay dự đoán trúng tuyển.
- K01 và DD2 phải được giữ nguyên theo nguồn. Không loại mã chỉ vì không khớp regex đơn giản kiểu A00.
- `cutoff_score=null`, `tuition_vnd=null`: không có dữ liệu mới về điểm chuẩn/học phí, không được ghi đè giá trị cũ bằng null/0.
- Đây là trích xuất nguồn chính thức, chưa phải kiểm duyệt nội bộ đầy đủ. `official_source_extracted` không đồng nghĩa với `approved`.
- Giá trị sở thích O*NET phải đọc kèm Scale ID, Domain Source, ngày và phiên bản. Không đồng nhất các thang đo, không biến thành xác suất phù hợp nghề hay thay bài test học sinh.
- Không dùng nhiệm vụ nghề làm thang điểm game tự động hoặc thay đổi game Unity trong lần tích hợp này.

## Xác minh và tái tạo

Chạy từ gốc repository:

```bash
python3 data/official_sources_v1/tools/validate.py
# Tùy chọn tái tải nguồn/tái tạo: cần openpyxl và PyMuPDF trong môi trường Python riêng.
python3 data/official_sources_v1/tools/build.py --cache /tmp/jobsimu-official-source-cache
```

Trình build chỉ tải các URL cố định, không ghi database. Manifest ghim hash tệp nguồn: nguồn thay đổi thì dừng, tạo phiên bản mới sau rà soát. Tệp nguồn gốc chỉ nằm trong cache; không đưa toàn bộ PDF/HTML tuyển sinh lên repo. CSV O*NET giữ tên cột và giá trị nguồn, đổi định dạng từ XLSX sang UTF-8 CSV và chia Task Statements theo nhóm SOC. Ngày truy xuất nằm trong manifest; ngày của từng quan sát O*NET vẫn nằm trong CSV.

Đọc `PROMPT_01_INTEGRATE.md` để viết importer tương thích schema thật; đọc `PROMPT_02_PROCESS.md` để chuẩn hóa, rà soát, bổ sung dữ liệu và kết nối UI/chatbot. Hai prompt nối tiếp nhau và không tự cho phép ghi vào database thật.

## Ghi công

This dataset includes information from the O*NET 31.0 Database by the U.S. Department of Labor, Employment and Training Administration (USDOL/ETA). Used under the CC BY 4.0 license. O*NET® is a trademark of USDOL/ETA.

License: https://creativecommons.org/licenses/by/4.0/
Source terms: https://www.onetcenter.org/license_db.html

Jobsimu converted selected source workbooks to CSV and partitioned task rows; original English values are retained. USDOL/ETA has not approved, endorsed, or tested these transformations. Future translations must be identified as adaptations.

Dữ liệu tuyển sinh: Đại học Bách khoa Hà Nội, bảng năm 2026 tại URL trong manifest. Chỉ trích xuất thông tin bảng; không tuyên bố tài liệu của trường mang giấy phép CC BY của O*NET.
