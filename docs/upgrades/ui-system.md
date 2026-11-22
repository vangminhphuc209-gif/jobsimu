# Hệ Thống Giao Diện & Thiết Kế Dùng Chung (UI System & Design Tokens)
*Cập nhật: Tháng 11/2026 — Antigravity Engineering Lead*

---

## 1. DESIGN TOKENS CHUẨN HÓA

Hệ thống giao diện JobSimu sử dụng bảng token chuẩn hóa trên toàn bộ 4 Hub:

### 1.1. Bảng Màu (Color Palette)
- **Thương hiệu (Brand Primary)**:
  - `--primary`: `#4f46e5` (Indigo 600)
  - `--primary-hover`: `#4338ca` (Indigo 700)
  - `--primary-light`: `#eef2ff` (Indigo 50)
- **Màu văn bản (Typography)**:
  - `--text`: `#0f172a` (Slate 900 — Tiêu đề & nội dung chính)
  - `--text-muted`: `#64748b` (Slate 500 — Chú thích & nhãn phụ)
- **Màu nền & Bề mặt (Surface)**:
  - `--surface`: `#ffffff` (Card & Panel chính)
  - `--surface-alt`: `#f8fafc` (Nền trang & vùng đệm)
  - `--border`: `#e2e8f0` (Đường viền chuẩn)
- **Màu trạng thái (Feedback States)**:
  - `--success`: `#10b981` (Xanh lá — Hoàn thành, Hợp lệ)
  - `--warning`: `#f59e0b` (Vàng hổ phách — Cần lưu ý, Chưa đủ dữ liệu)
  - `--danger`: `#ef4444` (Đỏ — Thất bại, Hủy)

### 1.2. Khoảng Cách (Spacing) & Bo Góc (Border Radius)
- `--space-1` (4px), `--space-2` (8px), `--space-3` (12px), `--space-4` (16px), `--space-6` (24px), `--space-8` (32px).
- `--radius-sm` (8px — Nút bấm, thẻ nhỏ), `--radius-md` (12px — Input, Dropdown), `--radius-lg` (16px — Card), `--radius-full` (9999px — Badges, Pills).

---

## 2. QUY CHUẨN THÀNH PHẦN (COMPONENT STANDARDS)

1. **Nút bấm (Buttons)**:
   - `.btn-primary`: Dành riêng cho 01 hành động chính nổi bật nhất trong ngữ cảnh (Primary CTA).
   - `.btn-outline`: Dành cho các hành động phụ, điều hướng hoặc xem thêm.
   - `.btn-ghost` / `.btn-danger`: Dành cho các thao tác hủy, đăng xuất hoặc xóa tác vụ.
2. **Thẻ thông tin (Cards)**:
   - Tránh việc lồng quá 2 cấp thẻ (`card` trong `card` trong `card`).
   - Mọi card đều có padding tối thiểu 16px trên mobile và 24px trên desktop.
3. **Badges & Nhãn nguồn gốc**:
   - Mọi thông tin tuyển sinh, điểm chuẩn đều đi kèm nhãn nguồn gốc `.badge-subtle` hoặc biểu tượng `🔗 Nguồn thẩm định`.
4. **Trạng thái tải & Rỗng (Loading & Empty States)**:
   - Khi chưa có dữ liệu: Luôn hiển thị thông báo trung tính có hướng dẫn (ví dụ: *"Bạn chưa lưu nghề nào. Hãy bấm ♡ Lưu nghề khi đọc chi tiết"* thay vì để trống trơn).
   - Chống gửi lặp: Mọi form submit quan trọng đều có cờ `data-submit-loading` làm mờ nút và hiện spinner.

---

## 3. THIẾT KẾ ĐÁP ỨNG (RESPONSIVE VIEWPORT BREAKPOINTS)

| Kích thước | Thiết bị đại diện | Quy tắc hiển thị |
| :--- | :--- | :--- |
| **< 640px** | Điện thoại thông minh (360px, 390px, 414px) | • Grid 1 cột duy nhất.<br>• Bottom padding cho body: 72px (tránh bị nút chatbot che lấp).<br>• Bảng dữ liệu có thanh cuộn ngang `table-wrap`.<br>• Menu chuyển thành drawer di động. |
| **640px – 1023px** | Máy tính bảng (768px, 820px) | • Grid 2 cột.<br>• Thanh điều hướng hiển thị rút gọn. |
| **$\ge$ 1024px** | Máy tính xách tay & Màn hình lớn (1280px+) | • Layout 12 cột chuẩn hóa (`lg:col-span-8` và `lg:col-span-4`).<br>• Sidebar cố định (sticky). |

---

## 4. QUY TẮC TRÁNH VA CHẠM CỦA CHATBOT NỔI (CHATBOT SAFE ZONE)

1. Nút nổi Trợ lý La Bàn (`.may-chat-trigger`) có `z-index: 9990`, đặt tại `bottom: 20px; right: 20px;`.
2. Trên màn hình di động (< 640px):
   - `body` luôn có `padding-bottom: 72px` để mọi form submit, nút lưu hay thanh điều hướng cuối trang không bao giờ bị nút chat che khuất.
   - Khi mở cửa sổ chat (`.may-chat-window`), giao diện mở tràn toàn màn hình (`100vw x 100vh`) có nút đóng rõ ràng ở góc trên bên phải và hỗ trợ phím `Escape` để thoát.
