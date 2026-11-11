# Bản chuyển tiếp cũ (Babylon.js) — Tài liệu tham khảo

> **Cập nhật 10/11/2026:** Bản game 3D chính hiện là **Unity 6 (`JobSimu3D/`)** với bối cảnh **Trường THCS Bình Minh — Lớp 8A** theo [`docs/TEACHER_8A_REDESIGN.md`](file:///home/minhphuc/jobsimu/docs/TEACHER_8A_REDESIGN.md).  
> Thư mục `game/` (Babylon.js) và cổng `app/simulation-3d.php` được **giữ nguyên như bản cũ trong giai đoạn chuyển tiếp**, chưa xóa và chưa trỏ sang Unity. Tài liệu dưới đây mô tả bản Babylon.js cũ để tham khảo khi bảo trì chuyển tiếp.

---

# Bản thử nghiệm cũ trong `game/`: Một tiết học (Babylon.js)

## Đánh giá và phạm vi

Bản sửa trong `game/` triển khai một tiết học cục bộ bằng Babylon.js: nhân vật giáo viên, Cô Mai, Nam, 12 học sinh nền, bốn khu trường học, camera thứ ba, tương tác gần đồ vật, ba phương án dạy học, quan sát bốn nhóm, thu phiếu, báo cáo và lưu/tiếp tục trên trình duyệt.

Chưa hoàn thành toàn bộ các mục thiết kế mới và không còn là nhánh phát triển chính của phần 3D; nhánh phát triển 3D chính hiện nằm ở `JobSimu3D/` (xem [`JobSimu3D/Assets/_JobSimu/FRAMEWORK.md`](file:///home/minhphuc/jobsimu/JobSimu3D/Assets/_JobSimu/FRAMEWORK.md)).

## Chạy bản chuyển tiếp `game/` (nếu cần đối chiếu)

```bash
cd game
npm ci
npm run dev
```

Các endpoint `api/game/*` tiếp tục trả `503 GAME_SYNC_UNAVAILABLE` theo [`api/game/README.md`](file:///home/minhphuc/jobsimu/api/game/README.md).
