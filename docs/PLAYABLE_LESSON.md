# Bản thử nghiệm 3D: Một tiết học lớp 9A

## Đánh giá và phạm vi

Bản trước chưa đạt vertical slice: build TypeScript thất bại, mặt sàn giữa các khu không liên tục, lan can chặn lối vào lớp, hành động chưa áp dụng vào trạng thái và báo cáo offline tự tạo bằng chứng không có thật.

Bản sửa triển khai một tiết học cục bộ: nhân vật giáo viên, Cô Mai, Nam, 12 học sinh nền, bốn khu trường học, camera thứ ba, tương tác gần đồ vật, ba phương án dạy học, quan sát bốn nhóm, thu phiếu, báo cáo và lưu/tiếp tục trên trình duyệt. Học liệu giấy, biểu đồ bảng và màu thẻ nhóm thay đổi theo hành động. Assets nhân vật và đồ vật được dựng bằng mã Babylon.js trong repository, chưa có bộ model/rig/âm thanh sản xuất hoàn chỉnh.

Chưa hoàn thành toàn bộ Ngày 1, 7 bước/14 tình huống, 3 arc xuyên ngày, lịch đi lại đầy đủ của NPC, animation rig, âm thanh, đồng bộ tài khoản và nghiệm thu hiệu năng. Các JSON cũ chưa được nối đầy đủ vào vòng chơi mới. Không coi bộ test nội dung cũ là chứng nhận QA-01..12.

## Chạy trên máy tính

Yêu cầu Node.js 22+ và trình duyệt có WebGL.

```bash
cd game
npm ci
npm run dev
```

Mở URL Vite in ra. Không mở trực tiếp `index.html` bằng `file://` và không đưa TypeScript nguồn lên PHP host rồi kỳ vọng trình duyệt chạy được.

- WASD/mũi tên: di chuyển; Shift: chạy; kéo chuột: xoay camera.
- E: tương tác đồ vật/NPC gần nhất; J: nhật ký hoặc báo cáo đã hoàn thành.
- Esc/P: tạm dừng, chất lượng đồ họa, bắt đầu lại.
- Gặp Cô Mai; chọn máy in hoặc bàn soạn hoạt động nhóm; cũng có thể vào lớp ngay.
- Đi qua cửa phòng giáo viên ra hành lang, vào cửa phía tây lớp 9A, đến bảng bắt đầu tiết.
- Quan sát thẻ bài tập của bốn nhóm, trao đổi với Nam, dùng bảng minh họa; thu phiếu trên bàn giáo viên.
- Trở về bàn soạn bài ở phòng giáo viên để kết thúc. Có thể kết thúc khi chưa thu phiếu, nhưng báo cáo sẽ ghi thiếu bằng chứng.

Tiến độ lưu tự động cùng origin trong localStorage; không chia sẻ giữa trình duyệt, thiết bị hoặc tên miền. Lỗi lưu được hiển thị. Không tuyên bố đã đồng bộ máy chủ.

## Triển khai thử trên hosting

```bash
npm --prefix game ci
npm --prefix game run build
```

Đưa **nội dung** `game/dist/` vào một thư mục web tĩnh riêng, ví dụ `public_html/teacher-3d/`, rồi truy cập đường dẫn thư mục đó. `base: './'` hỗ trợ assets dưới thư mục con. Không tải `node_modules` lên hosting. Không cần PHP/MySQL để chơi bản thử cục bộ. Chưa tự sửa nút truy cập trong website legacy hoặc chạy migration.

Các endpoint `api/game/*` hiện cố ý trả `503 GAME_SYNC_UNAVAILABLE`: implementation cũ thiếu xác thực quyền sở hữu và tin dữ liệu hành động/bằng chứng từ client. Xem `api/game/README.md` trước khi phát triển lại đồng bộ. Đây là thay đổi có chủ đích, cần được xem xét trước khi triển khai cùng backend.

## Kết quả kiểm tra

- TypeScript: đạt.
- Vitest: 34/34 bài đạt, gồm phân kỳ ba cách chuẩn bị, thứ tự hành động, chống lặp, báo cáo thiếu bằng chứng, replay bản lưu và geometry Babylon NullEngine.
- Production build: đạt; còn cảnh báo bundle chính khoảng 1.27 MB chưa nén (~313 KB gzip).
- NullEngine xác nhận dựng mesh/props và phạm vi nền; **không** chứng minh render GPU, camera/collision khi chơi hay FPS.
- Trình duyệt kiểm thử báo WebGL không hỗ trợ; xác nhận được màn hình báo lỗi khởi động thay vì trang trắng. Chưa xác nhận chơi trọn tiết qua browser.
- PHP đã được phân tích cú pháp bằng parser; môi trường không có PHP/MySQL nên chưa chạy kiểm thử backend thực.

Trước khi merge/phát hành cần chơi ba lượt trên máy có WebGL, đi qua cả hai cửa, kiểm tra va chạm/camera, tương tác tất cả mục tiêu, F5 giữa tiết và đo FPS với preset Low. Bộ tài liệu thiết kế gốc vẫn là mục tiêu dài hạn, không phải danh sách tính năng đã hoàn thành.
