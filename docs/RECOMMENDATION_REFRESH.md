# Cập nhật gợi ý từ hồ sơ hiện tại

Khi học sinh hoàn thành hoặc làm lại Big Five, CAAS, MBTI hay EQ, hệ thống lưu bài và cập nhật gợi ý của lần Holland mới nhất trong cùng giao dịch. Nếu chưa hoàn thành Holland và bước điều kiện, bài bổ sung vẫn được lưu để sử dụng sau. Báo cáo tổng hợp và trang kết quả cũng kiểm tra cập nhật khi mở.

Holland, điều kiện, dữ liệu tự đánh giá năng lực đã lưu và Big Five hợp lệ tiếp tục dùng thuật toán xếp hạng hiện có. Big Five bị cảnh báo chất lượng không tham gia xếp hạng. CAAS, MBTI và EQ được ghi nguồn và phục vụ phản tư/ngữ cảnh tư vấn; không có trọng số xếp hạng mới. Đây không phải xác suất phù hợp nghề đã được kiểm định.

Khi hồ sơ thay đổi, lời tư vấn AI cũ được xóa và cần tạo lại. Không tự gọi AI sau mỗi bài bổ sung. Phản hồi AI đang chạy bị từ chối nếu hồ sơ đã thay đổi trước lúc lưu. Làm mới cùng hồ sơ không xóa lời tư vấn đã có. Tự động cập nhật không sửa các lần Holland lịch sử; thao tác sửa điều kiện được yêu cầu rõ cho một lần đánh giá vẫn áp dụng cho lần đó.

Không thêm migration; metadata nguồn được lưu trong validity_metrics.recommendation_snapshot. Không thay đổi game 3D, admin hoặc danh mục ngành. Khảo sát môn học yêu thích là bước tiếp theo, chưa có trong thay đổi này.

## Kiểm tra

- `php tests/recommendations-test.php`: cập nhật, làm lại, nguồn, chất lượng, cách ly tài khoản, lịch sử, tính ổn định, AI cũ và rollback.
- `php tests/psychometrics-test.php` và `php tests/four-hubs-data-test.php`: hồi quy chấm điểm và dữ liệu.
- `python tests/recommendations-http.py` (cần requests): chạy PHP server và SQLite tạm, đăng nhập, Holland, điều kiện, bốn bài, làm lại, báo cáo, CSRF và xác thực. Có thể đặt PHP_BIN để chọn PHP.
- Workflow recommendations.yml chạy PHP 8.1/8.3, SQLite, MySQL 8 và luồng HTTP. Không dùng cơ sở dữ liệu thật.
