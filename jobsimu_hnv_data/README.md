# Bộ dữ liệu ngành đào tạo cho JobSimu

Nguồn: https://huongnghiepviet.com/nganh-nghe
Ngày thu thập: 2026-11-11T13:18:23.407691+00:00 (UTC).
Phạm vi: toàn bộ mục trong khối danh mục ngành đào tạo tại URL trên; không phải toàn bộ website hoặc danh mục pháp lý đã xác minh.

## Độ bao phủ
- 358 ngành, 23 lĩnh vực, 76 nhóm ngành theo cách phân nhóm của nguồn.
- Trạng thái trang chi tiết: {"fetched": 308, "not_linked_by_source": 49, "unexpected_page": 1}.
- 288 ngành có phần bài giới thiệu đọc được.
- 2712 liên kết ngành–nguồn trường, thuộc 248 URL thông tin trường/tuyển sinh khác nhau.
- Số URL nguồn trường không đồng nghĩa số trường độc lập: một trường có thể có nhiều URL/hệ đào tạo.
- Đã kiểm tra ID duy nhất, tham chiếu nhóm/ngành, quan hệ trùng và tính toàn vẹn SQLite.

## Dùng tệp nào
- jobsimu_hnv.json: gói dữ liệu chính, Unicode UTF-8; chứa nhóm, ngành, nguồn trường, quan hệ và báo cáo bao phủ.
- jobsimu_hnv.sqlite: cơ sở dữ liệu để truy vấn/import; các trường bổ sung nằm trong payload_json.
- majors.csv, groups.csv, subgroups.csv: danh mục phẳng, UTF-8 BOM.
- major_institution_links.csv: quan hệ ngành với nguồn tuyển sinh, năm tin và các mã quan sát được.
- institution_source_records.csv: danh mục URL nguồn trường, chưa hợp nhất thành pháp nhân trường.
- quality_report.json: thống kê và các URL chưa xử lý thành công.
- review_queue.csv: hàng chờ rà soát các mục thiếu trang chi tiết, thiếu bài, chưa có mã đơn nhất hoặc không có liên kết trường.
- collect.py: công cụ thu thập lại dữ liệu thực tế, giới hạn 4 yêu cầu đồng thời, có cache/retry.
- build.py: tạo CSV/SQLite, kiểm tra tham chiếu và đóng gói.

## Quy tắc sử dụng dữ liệu
1. Đây là ngành đào tạo (training_major), chưa phải danh mục nghề hay ánh xạ ngành–nghề. Không tự dùng tên ngành làm chức danh nghề.
2. Đây là dữ liệu tham khảo từ nguồn thứ cấp, chưa đối chiếu toàn bộ với Bộ GD&ĐT và từng trường.
3. official_major_code để null. source_major_code_candidate là mã ứng viên lấy từ ID của nguồn hoặc mã duy nhất trong bảng trường; vẫn cần kiểm chứng. observed_major_codes có thể chứa mã ngành/chương trình khác xuất hiện cùng bảng.
4. source_news_year là năm tin được nguồn ghi, không tự coi là năm tuyển sinh hiện hành hoặc năm có hiệu lực. Không gán số liệu cũ sang năm mới.
5. listed_school_count là số gần đúng do trang danh mục hiển thị, không phải số trường đã xác minh. Nó có thể khác số quan hệ trích xuất.
6. observed_subject_combination_codes là mã quan sát trong bảng nguồn; chưa xác nhận phương thức hoặc điều kiện áp dụng ở năm hiện tại.
7. not_linked_by_source: nguồn chỉ liệt kê tên, không có liên kết chi tiết. Không tự bịa URL hoặc nội dung.
8. fetched chỉ nghĩa là đọc được trang có tiêu đề, không đồng nghĩa nội dung chính xác/đầy đủ/hiện hành. content_topics_present chỉ ghi nhận từ khóa chủ đề trong bài, không chứng nhận chất lượng bài.
9. Không phân phối lại toàn văn bài viết, hình ảnh, video. Gói này chứa dữ kiện danh mục, quan hệ và đường dẫn để đọc nguồn. Không có mô tả dài, mức lương, học phí, điểm chuẩn hay chỉ tiêu do AI tự tạo.
10. Không gán điểm Holland, MBTI, EQ hoặc độ phù hợp nghề khi chưa có mô hình và căn cứ riêng. assessment_mapping_status=not_scored.
11. Trường thiếu dữ liệu là null hoặc mảng rỗng; không biến thành số 0 hoặc kết luận không tồn tại.
12. Cấu trúc trang có thể thay đổi. Khi chạy lại cần kiểm tra số lượng, lỗi và đối chiếu mẫu trước khi dùng dữ liệu mới.

## Cách tái tạo
Yêu cầu Python 3, requests, beautifulsoup4.
Chạy collect.py với --out là thư mục này và --cache là thư mục tạm bên ngoài, sau đó chạy build.py.
Không công bố cache HTML. Cache là đầu vào xử lý tạm, không nằm trong gói bàn giao.
Kiểm tra điều kiện truy cập/robots của nguồn trước các đợt thu thập tiếp theo.

## Ví dụ truy vấn
```sql
SELECT g.name AS linh_vuc, COUNT(*) AS so_nganh
FROM majors m JOIN groups g ON g.id=m.group_id GROUP BY g.id;

SELECT m.name, s.source_label, l.source_news_year, l.evidence_url
FROM major_institution_links l
JOIN majors m ON m.id=l.major_id
JOIN institution_source_records s ON s.id=l.institution_source_id
WHERE m.id='hnv-giao-duc-hoc';
```

## Phân bố theo lĩnh vực của nguồn
| Lĩnh vực | Số mục ngành |
|---|---:|
| Khoa học giáo dục và đào tạo giáo viên | 41 |
| Nghệ thuật | 29 |
| Nhân văn | 27 |
| Khoa học xã hội và hành vi | 25 |
| Báo chí và thông tin | 12 |
| Kinh doanh và quản lý | 18 |
| Pháp luật | 3 |
| Khoa học sự sống | 4 |
| Khoa học tự nhiên | 13 |
| Toán và thống kê | 6 |
| Máy tính và công nghệ thông tin | 8 |
| Công nghệ kỹ thuật | 24 |
| Kỹ thuật | 33 |
| Sản xuất và chế biến | 10 |
| Kiến trúc và xây dựng | 19 |
| Nông, lâm nghiệp và thuỷ sản | 20 |
| Thú y | 1 |
| Sức khoẻ | 17 |
| Dịch vụ xã hội | 3 |
| Du lịch, khách sạn, thể thao và dịch vụ cá nhân | 6 |
| Dịch vụ vận tải | 4 |
| Môi trường và bảo vệ môi trường | 4 |
| An ninh, Quốc phòng | 31 |

## Việc cần làm trước khi đưa vào gợi ý cá nhân hóa
Đối chiếu mã ngành chính thức; hợp nhất danh tính trường; xác minh thông tin tuyển sinh theo năm;
biên soạn mô tả nghề riêng có nguồn; xây dựng quan hệ ngành–nghề và đánh giá dựa trên cả năm bộ câu hỏi.
Gói này chưa được nhập vào repository hoặc website JobSimu.
