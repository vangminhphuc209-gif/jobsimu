# ASSET MANIFEST — JOBSIMU 3D: NGƯỜI THẦY SAU TIẾNG TRỐNG TRƯỜNG

> **Dự án:** JOBSIMU 3D (Vertical Slice — Ngày 1 Nghề Giáo Viên THCS)  
> **Source of Truth:** [`docs/game3d.md`](file:///home/minhphuc/jobsimu/docs/game3d.md)  
> **Phiên bản:** 1.0 — 07/11/2026

---

## 1. NGÂN SÁCH HIỆU NĂNG (PERFORMANCE BUDGET)

| Chỉ số kỹ thuật | Ngưỡng Tối đa (Budget Cap) | Chiến lược Tối ưu |
|---|---|---|
| **Tổng số Polygons / Scene** | $\le 60.000$ tris | Dùng Low-Poly Stylized kết hợp Flat/Gouraud shading |
| **Draw Calls / Frame** | $\le 50$ draw calls | Bắt buộc dùng `instancedMesh` hoặc `thinInstance` cho bàn ghế, đèn quạt |
| **Dung lượng Tải Ban đầu** | $\le 25\text{ MB}$ (Gzip $\le 10\text{ MB}$) | Nén hình học Draco/Meshopt, nén texture KTX2/WebP |
| **Tốc độ khung hình (FPS)** | $30 - 60\text{ FPS}$ | Cung cấp 3 presets đồ họa (`Low`, `Medium`, `High`) |

---

## 2. DANH MỤC ASSETS 3D (GEOMETRY & PROPS)

### 2.1. Phân vùng Kiến trúc P0 (Environments)
- `env_class9a.glb`: Phòng học Lớp 9A (Tường, cửa ra vào, cửa sổ kính, bục giảng, bảng xanh 2 cánh).
- `env_staffroom.glb`: Phòng Giáo viên (Bàn tổ chuyên môn, tủ hồ sơ, bàn Cô Mai, máy in học liệu).
- `env_corridor.glb`: Hành lang kết nối tầng 1 & tầng 2, cầu thang bộ, lan can, bảng tin Đoàn trường.
- `env_yard.glb`: Sân trường, cột cờ, cổng trường, bồn hoa và cây bàng, cây phượng.

### 2.2. Đồ đạc & Đạo cụ Tương tác (Props & Interactables)
- `prop_desk_student_double.glb`: Bàn học sinh đôi tiêu chuẩn trường THCS Việt Nam (Instanced $\times 16$).
- `prop_chair_student.glb`: Ghế tựa học sinh (Instanced $\times 32$).
- `prop_desk_teacher.glb`: Bàn giáo viên trên bục giảng.
- `prop_projector_ceiling.glb`: Máy chiếu treo trần có animation đèn báo chớp vàng/đỏ (`PREP-01`, `CRISIS-02`).
- `prop_projection_screen.glb`: Màn chiếu kéo thả.
- `prop_printer_copier.glb`: Máy in/photo tài liệu phòng giáo viên (Tương tác in bản thảo dự phòng).
- `prop_exit_ticket_stack.glb`: Xấp phiếu kiểm tra hiểu bài cuối giờ (Exit tickets).
- `prop_ceiling_fan.glb`: Quạt trần 3 cánh có animation quay nhẹ.

### 2.3. Nhân vật 3D (Characters)
- `char_teacher_an.glb`: Nhân vật Giáo viên An (Avatar người chơi, trang phục sơ mi công sở gọn gàng).
- `char_mentor_mai.glb`: Cô Mai (Áo dài truyền thống hoặc đồ công sở trang nhã).
- `char_student_minh.glb`: Học sinh Minh (Đồng phục học sinh THCS, balo, dáng ngồi cúi đầu vẽ).
- `char_student_linh.glb`: Lớp trưởng Linh (Đeo khăn quàng đỏ, dáng đứng thẳng, tự tin).
- `char_student_nam.glb`: Nam (Đeo kính cận, sách vở đầy đủ).
- `char_npc_student_crowd.glb`: Bộ 4 mô hình học sinh nền tối ưu hóa polygon để lấp đầy lớp 9A.

---

## 3. DANH MỤC HOẠT ẢNH (ANIMATIONS)

- **Avatar Người chơi:** `idle`, `walk`, `run`, `interact_press_button`, `talk_gesture`, `inspect_paper`.
- **Học sinh:** `seated_idle`, `seated_listening`, `seated_drawing_secretly`, `raise_hand`, `stand_answer`, `whisper_talk`.
- **Giáo viên & Mentor:** `seated_working_laptop`, `stand_talk_warm`, `hand_over_papers`, `look_concerned`.

---

## 4. DANH MỤC ÂM THANH (AUDIO MANIFEST)

| Tên Audio Asset | Định dạng | Loại âm thanh | Mục đích & Vị trí |
|---|---|---|---|
| `sfx_school_bell.mp3` | MP3 / OGG | Hiệu ứng (SFX) | Tiếng trống trường / chuông điện báo chuyển tiết học |
| `sfx_footsteps_tile.mp3` | MP3 / OGG | SFX | Tiếng bước chân trên sàn gạch hoa hành lang / lớp học |
| `sfx_footsteps_concrete.mp3` | MP3 / OGG | SFX | Tiếng bước chân trên sân bê tông sân trường |
| `sfx_paper_flip.mp3` | MP3 / OGG | SFX | Tiếng lật giáo án, phát phiếu exit ticket |
| `sfx_projector_spark.mp3` | MP3 / OGG | SFX | Tiếng quạt tản nhiệt máy chiếu rè và ngắt điện |
| `amb_school_yard.mp3` | MP3 / OGG | Môi trường (Ambience) | Tiếng chim hót, ve kêu, học sinh nô đùa râm ran giờ ra chơi |
| `amb_classroom_murmur.mp3` | MP3 / OGG | Ambience | Tiếng thì thầm trao đổi nhóm trong lớp học |

---

## 5. NGUỒN GỐC & GIẤY PHÉP (LICENSING & ATTRIBUTION)

Toàn bộ tài nguyên 3D và Audio trong dự án tuân thủ:
- Tự thiết kế và xuất từ Blender / Procedural Babylon.js primitives cho giai đoạn blockout.
- Tài nguyên cộng đồng (nếu tích hợp bổ sung) phải thuộc giấy phép **CC0 (Public Domain)** hoặc **MIT / Apache 2.0 / CC-BY** với ghi nhận tác giả đầy đủ.
- Không sử dụng các model vi phạm bản quyền thương mại của bên thứ ba.

## Assets dựng bằng mã trong bản một tiết học

- Tác giả: mã nguồn dự án JOBSIMU, bổ sung trong nhánh `feat/playable-teacher-3d`.
- Nguồn: `game/src/world/SchoolWorld.ts`, `game/src/player/CharacterController.ts`, `game/src/npc/NPCController.ts`.
- Nội dung: mesh nhân vật nhiều bộ phận, bàn ghế instancing, cây, biển chữ, phiếu, biểu đồ bảng.
- Không nhập model/texture/âm thanh của bên thứ ba; giấy phép phân phối theo dự án (repository chưa khai báo license tổng thể). Không tự gán CC0 cho mã hoặc assets.
- Chưa phải model rig/GLB cuối cùng; cần kiểm tra hình ảnh và hiệu năng GPU thực.
