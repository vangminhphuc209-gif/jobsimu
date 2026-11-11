using System;
using System.IO;
using UnityEditor;
using UnityEngine;
using JobSimu.Quests;
using JobSimu.NPC;

namespace JobSimu.Editor
{
    public static class TeacherScenarioAssets
    {
        public const string Folder = "Assets/_JobSimu/Simulators/Teacher";
        public const string ScenarioPath = Folder + "/FirstLesson.asset";

        public static ScenarioDefinition GetOrCreate(bool forceRebuild = false)
        {
            ScenarioDefinition existing = AssetDatabase.LoadAssetAtPath<ScenarioDefinition>(ScenarioPath);
            if (existing != null && !forceRebuild) return existing;

            Directory.CreateDirectory(Folder);
            AssetDatabase.Refresh();

            ScenarioDefinition data = existing != null ? existing : ScriptableObject.CreateInstance<ScenarioDefinition>();
            PopulateScenario(data);

            if (existing == null) AssetDatabase.CreateAsset(data, ScenarioPath);
            else EditorUtility.SetDirty(data);

            AssetDatabase.SaveAssets();
            return data;
        }

        public static void PopulateScenario(ScenarioDefinition data)
        {
            data.id = "teacher.first-lesson";
            data.contentVersion = "2";
            data.profession = Profession.Teacher;
            data.title = "Tiết KHTN Lớp 8A · Vì sao sân trường đọng nước sau mưa?";
            data.context = "Trường THCS Bình Minh (hư cấu). Giáo viên mới phụ trách tiết Khoa học tự nhiên Lớp 8A (45 phút) dưới sự hướng dẫn của cô Mai: điều tra vì sao một số vị trí trong sân trường đọng nước sau mưa qua quan sát và so sánh mẫu. Tình huống thiết kế trải nghiệm nghề, chưa tuyên bố thẩm định chương trình chính thức.";
            data.initialStepId = "brief";
            data.initialMinute = 430; // 07:10
            data.lessonNominalStartMinute = 450; // 07:30
            data.lessonEndMinute = 495; // 08:15
            data.participants = new[]
            {
                "mai",
                "linh", "an", "binh",
                "nam", "vy", "khanh",
                "ha", "trang", "duc",
                "minh", "hung", "thao"
            };

            data.steps = new[]
            {
                Step("brief", LessonPhase.Briefing, "Gặp cô Mai ở sân trường để nhận mục tiêu tiết KHTN Lớp 8A (có thể hỏi thêm & xem vũng nước mẫu)."),
                Step("prepare", LessonPhase.Preparation, "Phòng giáo viên: đọc bài cũ, kiểm tra thiết bị và phối hợp học liệu trước giờ vào Lớp 8A."),
                Step("begin", LessonPhase.Opening, "Lớp 8A (Bảng): nêu nhiệm vụ điều tra và kiểm tra xem học sinh hiểu yêu cầu hay chưa."),
                Step("teach", LessonPhase.Facilitating, "Lớp 8A: quan sát bài 4 nhóm, hỏi làm rõ, gợi ý/minh họa, mở rộng, kiểm tra lại sau hỗ trợ."),
                Step("assess", LessonPhase.CheckingLearning, "Lớp 8A: thu bằng chứng hiểu bài (phiếu 4 nhóm, hỏi miệng, phiếu ngắn) và điều chỉnh nếu cần."),
                Step("reflect", LessonPhase.Reflection, "Phòng giáo viên / Cô Mai: nhìn lại diễn biến, chọn bằng chứng và phản tư tiết học."),
                new ScenarioStep { id = "complete", phase = LessonPhase.Complete, objective = "Đã hoàn thành tiết học Lớp 8A. Nhấn [P] để xem Hồ sơ trải nghiệm nghề giáo viên.", terminal = true }
            };

            // Actions list
            var actions = new System.Collections.Generic.List<ScenarioAction>
            {
                // --- PHASE A: BRIEFING (Sân trường) ---
                Act("brief.ask_class", "brief", "mai", "brief",
                    "Hỏi cô Mai về thói quen học tập của Lớp 8A",
                    "1 phút · biết thêm tín hiệu ban đầu cô Mai từng quan sát tuần trước.",
                    "Cô Mai: 'Lớp 8A có 4 nhóm (12 bạn). Tuần trước vài bạn hay đoán kết luận trước khi đo thử. Riêng Minh từng nhầm giữa yêu cầu quan sát với việc kể tên vật bẩn; còn Linh làm bài cẩn thận nhưng ít giơ tay trước lớp.'",
                    "Hỏi thông tin nền về thói quen học tập của lớp trước khi chuẩn bị.",
                    EvidenceDimension.Analysis, InteractionKind.Dialogue, 1, 0, "mai", "brief.asked_class"),

                Act("brief.ask_gear", "brief", "mai", "brief",
                    "Hỏi cô Mai về thiết bị và mẫu vật lớp 8A",
                    "1 phút · kiểm tra thông tin thực tế về phòng học và máy chiếu.",
                    "Cô Mai: 'Phòng 8A xếp 4 cụm bàn. Bóng đèn máy chiếu chiều qua hơi chập chờn nên đừng phụ thuộc hoàn toàn vào slide; trong phòng giáo viên có khay mẫu đất - gạch và máy in.'",
                    "Chủ động hỏi tình trạng thiết bị lớp học trước khi chọn học liệu.",
                    EvidenceDimension.Accuracy, InteractionKind.Dialogue, 1, 0, "mai", "brief.asked_gear"),

                Act("yard.inspect_puddle", "brief", "yard_puddle", "brief",
                    "Quan sát vũng đọng nước mẫu ngoài sân trường",
                    "2 phút · đối chiếu hiện trường thật trước khi soạn bài.",
                    "Quan sát tại sân: phần gạch lát trũng cạnh lối đi giữ vũng nước nông, trong khi bồn đất tơi cách đó 1m chỉ ẩm mặt vì nước thấm qua các khe đất.",
                    "Quan sát trực tiếp mẫu sân trường đọng nước trước giờ lên lớp.",
                    EvidenceDimension.Analysis, InteractionKind.Physical, 2, 0, "", "yard.inspected"),

                Act("brief.accept", "brief", "mai", "prepare",
                    "Nhận tiết dạy Lớp 8A và vào Phòng giáo viên chuẩn bị",
                    "2 phút · sang Phòng giáo viên trước trống 07:30.",
                    "Cô Mai: 'Mục tiêu tiết 8A là giúp các nhóm giải thích vì sao có chỗ sân đọng nước sau mưa bằng cách quan sát và so sánh mẫu. Hãy xem bài cũ và chuẩn bị học liệu trong phòng giáo viên nhé.'",
                    "Xác nhận mục tiêu tiết học KHTN Lớp 8A cùng giáo viên hướng dẫn.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Dialogue, 2, 0, "mai", "brief.accepted"),

                // --- PHASE B: PREPARATION (Phòng giáo viên — được phép phối hợp nhiều phương án) ---
                Act("prep.read_prev_work", "prepare", "prev_work", "prepare",
                    "Đọc một số bài làm tiết trước của Lớp 8A",
                    "4 phút · tốn thời gian chuẩn bị nhưng phát hiện sớm kiểu sai hay gặp.",
                    "Đọc 3 bài cũ: (1) Minh từng viết 'nước biến mất vào không khí' và hay lạc sang tiêu chí sạch/bẩn; (2) Nam viết kết luận đúng nhưng bỏ trống cột lượng nước thử; (3) Linh kẻ bảng rõ ràng dù ít phát biểu.",
                    "Đọc trước bài làm cũ để nhận diện chỗ học sinh dễ hiểu nhầm.",
                    EvidenceDimension.Analysis, InteractionKind.Evidence, 4, 0, "", "prep.read_prev"),

                Act("prep.inspect_gear", "prepare", "gear_check", "prepare",
                    "Kiểm tra học liệu, khay mẫu vật và nhật ký máy chiếu",
                    "2 phút · xác nhận mẫu đất/gạch đầy đủ và đèn máy chiếu đang báo vàng.",
                    "Khay mẫu (đất tơi, đất nén, gạch lát, ống nhỏ giọt) dùng tốt. Sổ thiết bị ghi: 'Đèn máy chiếu lớp 8A có dấu hiệu quá nhiệt nếu bật liên tục'.",
                    "Kiểm tra thiết bị và học liệu thực tế trước giờ dạy.",
                    EvidenceDimension.Accuracy, InteractionKind.Physical, 2, 0, "", "prep.checked_gear"),

                Act("prep.print", "prepare", "printer", "prepare",
                    "In phiếu bảng so sánh & ảnh mẫu cho 4 nhóm",
                    "5 phút · tốn thời gian hơn máy chiếu nhưng có tài liệu dự phòng khi thiết bị hỏng.",
                    "Đã in đủ phiếu bảng 2 cột và ảnh sân trường sau mưa cho 4 nhóm. Có thể dùng độc lập hoặc kết hợp cùng bộ thẻ.",
                    "In phiếu học tập dự phòng và bảng ghi quan sát cho 4 nhóm.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Physical, 5, 0, "", "printed"),

                Act("prep.projector", "prepare", "projector", "prepare",
                    "Chuẩn bị slide trình chiếu so sánh hai bề mặt",
                    "4 phút · nhanh gọn, nhưng dễ gặp trục trặc giữa tiết nếu không có phương án dự phòng.",
                    "Đã chép slide hình ảnh đối chiếu lượng mưa lên máy tính lớp 8A. Đèn nguồn báo chập chờn nhẹ.",
                    "Soạn trình chiếu minh họa để tối ưu thời gian chuẩn bị.",
                    EvidenceDimension.Creativity, InteractionKind.Physical, 4, 0, "", "projector"),

                Act("prep.group_cards", "prepare", "group-kit", "prepare",
                    "Soạn bộ thẻ thảo luận & khay mẫu thử theo nhóm",
                    "5 phút · chuẩn bị mẫu thật cho từng bàn và thẻ chia lượt nói cho học sinh ít phát biểu.",
                    "Đã xếp 4 khay mẫu (đất trồng, gạch lát, ống nhỏ giọt) kèm thẻ phân vai đọc - ghi - thử nước cho 4 nhóm.",
                    "Chuẩn bị khay mẫu trực quan và thẻ hỗ trợ trao đổi nhóm.",
                    EvidenceDimension.Empathy, InteractionKind.Physical, 5, 0, "", "group-kit"),

                Act("prep.enter_class", "prepare", "staff_exit", "begin",
                    "Mang học liệu sang Lớp 8A để bắt đầu tiết học",
                    "2 phút · chuyển sang Lớp 8A (nếu quá 07:30 sẽ mất bớt thời gian trong tiết 45 phút).",
                    "Bạn mang học liệu đã chuẩn bị bước qua hành lang vào Lớp 8A.",
                    "Hoàn tất khâu chuẩn bị và di chuyển sang Lớp 8A.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Physical, 2, 0, "", "prep.entered",
                    requiresFlag: "prep.any_selected_or_ready"),

                // Note: keep unconditional enter action so player is NEVER softlocked even if they skipped printing/cards/projector!
                Act("prep.enter_class_direct", "prepare", "staff_exit", "begin",
                    "Vào Lớp 8A ngay (dùng bảng phấn & trao đổi trực tiếp)",
                    "2 phút · không tốn thêm thời gian soạn học liệu, nhưng nhóm phải tự kẻ bảng nháp.",
                    "Bạn quyết định vào Lớp 8A ngay với giáo án cơ bản và dùng bảng phấn.",
                    "Vào lớp giảng dạy bằng bảng phấn khi chưa chuẩn bị học liệu bổ sung.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Physical, 2, 0, "", "prep.board_only",
                    forbidsFlag: "prep.any_selected_or_ready"),

                // --- PHASE C: OPENING (Bảng Lớp 8A) ---
                Act("open.clear_task", "begin", "board", "begin",
                    "Nêu nhiệm vụ so sánh hai mẫu dưới cùng lượng nước",
                    "3 phút · giải thích rõ mục tiêu và tiêu chí so sánh trước khi giao việc.",
                    "Bạn viết rõ câu hỏi lên bảng: 'Cùng một lượng nước nhỏ xuống, vì sao chỗ đất tơi rút nhanh còn mặt lát kín lại đọng nước?'.",
                    "Nêu rõ ràng câu hỏi điều tra và yêu cầu so sánh mẫu trên bảng.",
                    EvidenceDimension.Accuracy, InteractionKind.Dialogue, 3, 0, "", "open.clear"),

                Act("open.quick_task", "begin", "board", "begin",
                    "Nêu nhanh yêu cầu để sớm vào hoạt động nhóm",
                    "1 phút · tiết kiệm 2 phút đầu giờ nhưng dễ khiến nhóm đọc lướt hiểu nhầm.",
                    "Bạn nói ngắn gọn: 'Các nhóm xem vì sao sân đọng nước rồi ghi lại lý do nhé'. Một vài bàn quay sang hỏi nhỏ nhau.",
                    "Nêu nhanh nhiệm vụ đầu giờ để tăng thời gian hoạt động nhóm.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Dialogue, 1, 0, "", "open.rushed",
                    forbidsFlag: "open.clear"),

                Act("open.check_whole_class", "begin", "board", "begin",
                    "Hỏi cả lớp kiểm tra xem đã hiểu yêu cầu chưa",
                    "2 phút · nhanh, nhưng thường chỉ học sinh nhanh miệng giơ tay trả lời.",
                    "Bạn hỏi: 'Cả lớp rõ việc cần làm chưa?'.",
                    "Kiểm tra nhanh mức hiểu yêu cầu qua câu hỏi cả lớp.",
                    EvidenceDimension.Analysis, InteractionKind.Dialogue, 2, 0, "", "open.checked",
                    requiresFlag: "open.framed", forbidsFlag: "open.checked"),

                Act("open.check_pair_prompt", "begin", "board", "begin",
                    "Cho trao đổi cặp 1 phút và hỏi lại 1 câu kiểm tra hiểu đề",
                    "3 phút · tốn thêm 1 phút nhưng bộc lộ câu hỏi thật từ học sinh rụt rè.",
                    "Bạn yêu cầu mỗi cặp nhắc lại 2 việc cần làm rồi mời đặt câu hỏi.",
                    "Kiểm tra mức hiểu yêu cầu bằng trao đổi cặp trước khi chia việc nhóm.",
                    EvidenceDimension.Empathy, InteractionKind.Dialogue, 3, 0, "", "open.checked_pair",
                    requiresFlag: "open.framed", forbidsFlag: "open.checked"),

                Act("open.start_group_work", "begin", "board", "teach",
                    "Phát lệnh cho 4 nhóm bắt đầu quan sát & thảo luận",
                    "1 phút · chính thức chuyển sang điều hành 4 nhóm trong Lớp 8A.",
                    "Cả 4 nhóm bắt đầu làm việc tại bàn. Bạn có thể tự do đi quan sát từng bàn, xem phiếu hoặc dùng bảng minh họa.",
                    "Khởi động hoạt động điều tra theo 4 nhóm Lớp 8A.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Physical, 1, 0, "", "open.groups_started",
                    requiresFlag: "open.checked"),

                // --- PHASE D: FACILITATING (Lớp 8A — Tự do nhiều hành động qua 4 nhóm & bảng) ---
                Act("teach.board_diagram", "teach", "board", "teach",
                    "Vẽ sơ đồ khe rỗng giữa hạt đất vs mặt lát kín lên bảng",
                    "4 phút · làm rõ nguyên lý cho cả lớp; không phụ thuộc máy chiếu.",
                    "Bạn vẽ hai lát cắt song song lên bảng để các nhóm tự đối chiếu với quan sát của mình.",
                    "Dùng sơ đồ bảng minh họa nguyên lý thấm nước của bề mặt.",
                    EvidenceDimension.Creativity, InteractionKind.Physical, 4, 0, "", "board.illustrated"),

                Act("teach.board_criteria", "teach", "board", "teach",
                    "Chốt 3 tiêu chí đối chứng lên góc bảng",
                    "3 phút · nhắc lại điều kiện giữ lượng nước bằng nhau cho các nhóm đang làm.",
                    "Bạn ghi 3 gạch đầu dòng lên góc bảng: (1) Cùng lượng nước thử, (2) Đặt cạnh 2 bề mặt, (3) Ghi hiện tượng trước khi kết luận.",
                    "Bổ sung tiêu chí đối chứng rõ ràng lên bảng trong giờ làm nhóm.",
                    EvidenceDimension.Accuracy, InteractionKind.Physical, 3, 0, "", "board.criteria_written"),

                // Nhóm 1 — Linh (Tình huống 3: học sinh im lặng nhưng hiểu bài)
                Act("g1.inspect_sheet", "teach", "group1_sheet", "teach",
                    "Đọc phiếu bài làm của Nhóm 1 (Linh, An, Bình)",
                    "1 phút · xem sản phẩm viết thực tế trên bàn (không cộng bằng chứng trùng nếu bài chưa đổi).",
                    "Đọc phiếu Nhóm 1.",
                    "Quan sát bài làm viết trên bàn của Nhóm 1 để kiểm chứng thực tế.",
                    EvidenceDimension.Analysis, InteractionKind.Evidence, 1, 0, "linh",
                    oneTime: false, targetGroupId: "group1"),

                Act("g1.ask_private", "teach", "linh", "teach",
                    "Hỏi nhỏ riêng với Linh ngay tại bàn",
                    "3 phút · giữ không gian an toàn, không gây áp lực trước cả lớp.",
                    "Linh nói nhỏ nhưng rành mạch: 'Nhóm em thử nhỏ cùng 3 giọt nước thì đất tơi rút xuống hết, còn gạch lát vẫn đọng giọt nước trên mặt ạ.'",
                    "Trao đổi riêng ở cự ly gần giúp Linh chia sẻ cách hiểu mà không bị áp lực công khai.",
                    EvidenceDimension.Empathy, InteractionKind.Dialogue, 3, 0, "linh", "g1.private_asked",
                    targetGroupId: "group1"),

                Act("g1.pair_share", "teach", "linh", "teach",
                    "Khuyến khích Linh giải thích trước cho An và Bình trong nhóm",
                    "3 phút · xây dựng nhịp giao tiếp an toàn trong nhóm 3 người.",
                    "Sau khi nói thử với An và Bình, Linh gật đầu tự tin hơn và ghi thêm phần giải thích vào phiếu.",
                    "Tạo cầu nối trao đổi nhóm nhỏ để học sinh ít nói tham gia chủ động hơn.",
                    EvidenceDimension.Empathy, InteractionKind.Dialogue, 3, 0, "linh", "g1.pair_shared",
                    targetGroupId: "group1"),

                Act("g1.callout_public", "teach", "linh", "teach",
                    "Gọi Linh đứng lên trình bày ngay trước cả lớp",
                    "2 phút · nhanh, nhưng có thể khiến học sinh đang ngại thu mình lại.",
                    "Linh đứng lên nhìn vở, đọc ngập ngừng một câu rồi ngồi xuống im lặng. Tuy nhiên bài viết trên giấy của em vẫn đúng và đủ ý.",
                    "Gọi phát biểu công khai ngay khi chưa kiểm tra mức sẵn sàng của học sinh.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Dialogue, 2, 0, "linh", "g1.public_pressured",
                    targetGroupId: "group1"),

                // Nhóm 2 — Nam (Tình huống 4: trả lời đúng bề mặt nhưng thiếu bằng chứng)
                Act("g2.inspect_sheet", "teach", "group2_sheet", "teach",
                    "Đọc phiếu bài làm của Nhóm 2 (Nam, Vy, Khánh)",
                    "1 phút · kiểm tra xem nhóm có số liệu đối chứng hay mới nêu nhận định.",
                    "Đọc phiếu Nhóm 2.",
                    "Kiểm tra bài làm của Nhóm 2 để xem căn cứ đằng sau câu trả lời.",
                    EvidenceDimension.Analysis, InteractionKind.Evidence, 1, 0, "nam",
                    oneTime: false, targetGroupId: "group2"),

                Act("g2.accept_claim", "teach", "nam", "teach",
                    "Ghi nhận ngay câu trả lời của Nam để chuyển bàn khác",
                    "1 phút · rất nhanh, nhưng nhóm có nguy cơ dừng ở nhận định thiếu kiểm chứng.",
                    "Bạn gật đầu: 'Đúng hướng rồi đó'. Nam hí hửng gấp bút lại và không thử thêm mẫu đất nén nào nữa.",
                    "Chấp nhận câu trả lời đúng bề mặt mà chưa yêu cầu kiểm chứng.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Dialogue, 1, 0, "nam", "g2.accepted_surface",
                    targetGroupId: "group2"),

                Act("g2.ask_reasoning", "teach", "nam", "teach",
                    "Hỏi cách suy luận: 'Nhóm đã giữ lượng nước bằng nhau chưa?'",
                    "3 phút · buộc nhóm rà soát biến kiểm soát và trường hợp đất nén.",
                    "Nam khựng lại: 'À, tụi em mới đoán từ lần thấy ngoài sân chứ chưa nhỏ giọt thử cùng lượng nước lên đất nén!'",
                    "Đặt câu hỏi phản biện làm rõ thiếu sót kiểm chứng trong lập luận của Nam.",
                    EvidenceDimension.Analysis, InteractionKind.Dialogue, 3, 0, "nam", "g2.probed_reasoning",
                    targetGroupId: "group2"),

                Act("g2.design_test", "teach", "nam", "teach",
                    "Cùng Nhóm 2 thiết kế phép thử 3 giọt nước trên 3 mẫu",
                    "4 phút · mất thời gian hơn nhưng biến giả thuyết thành bằng chứng đo được.",
                    "Nam, Vy và Khánh nhỏ đều 3 giọt nước lên gạch, đất tơi và đất nén: nhóm ghi lại ngay đất nén cũng giữ nước trên mặt lâu gần bằng gạch!",
                    "Hướng dẫn học sinh thiết kế phép thử đối chứng để chứng minh giả thuyết.",
                    EvidenceDimension.Accuracy, InteractionKind.Evidence, 4, 0, "nam", "g2.tested_evidence",
                    targetGroupId: "group2"),

                Act("g2.recheck", "teach", "nam", "teach",
                    "Quay lại kiểm tra lập luận có bằng chứng của Nhóm 2",
                    "3 phút · xác nhận xem nhóm đã tự sửa cách giải thích chưa.",
                    "Bạn kiểm tra lại: Nhóm 2 trình bày được đầy đủ lý do cần giữ cùng lượng nước và giải thích thêm được vùng đất giẫm nén.",
                    "Quay lại đối chiếu lập luận mới của Nhóm 2 sau khi nhắc nhở.",
                    EvidenceDimension.Accuracy, InteractionKind.Evidence, 3, 0, "nam", "g2.rechecked",
                    requiresFlag: "g2.probed_reasoning", forbidsFlag: "g2.tested_evidence", targetGroupId: "group2"),

                // Nhóm 3 — Hà (Tình huống 2: xong sớm + diễn đạt hình vẽ thành lời)
                Act("g3.inspect_sheet", "teach", "group3_sheet", "teach",
                    "Đọc phiếu & hình vẽ của Nhóm 3 (Hà, Trang, Đức)",
                    "1 phút · quan sát hình vẽ cắt lớp và tiến độ thực tế của nhóm.",
                    "Đọc phiếu Nhóm 3.",
                    "Quan sát sơ đồ vẽ tay và tiến độ hoàn thành của Nhóm 3.",
                    EvidenceDimension.Analysis, InteractionKind.Evidence, 1, 0, "ha",
                    oneTime: false, targetGroupId: "group3"),

                Act("g3.bridge_verbal", "teach", "ha", "teach",
                    "Hướng dẫn Hà chuyển hình vẽ thành 2 câu giải thích rõ ràng",
                    "3 phút · hỗ trợ kỹ năng diễn đạt bằng lời từ thế mạnh trực quan của Hà.",
                    "Bạn chỉ vào từng lớp hạt đất trên hình của Hà và gợi ý mẫu câu 'Vì... nên...'. Hà đọc lại trôi chảy cho Trang ghi thêm vào phiếu.",
                    "Giúp Hà diễn đạt ý tưởng từ hình vẽ thành câu giải thích khoa học.",
                    EvidenceDimension.Empathy, InteractionKind.Dialogue, 3, 0, "ha", "g3.verbalized",
                    targetGroupId: "group3"),

                Act("g3.assign_extension", "teach", "ha", "teach",
                    "Giao nhiệm vụ mở rộng: so sánh mẫu lối đất nén dưới gốc cây",
                    "3 phút · giữ tập trung cho nhóm xong sớm bằng thử thách nâng cao.",
                    "Trang và Đức hào hứng nhận mẫu đất nén để thử thêm, cả nhóm quay lại ghi chép tập trung.",
                    "Giao nhiệm vụ mở rộng phù hợp cho nhóm hoàn thành sớm.",
                    EvidenceDimension.Creativity, InteractionKind.Physical, 3, 0, "ha", "g3.extended",
                    requiresFlag: "g3.finished_early", targetGroupId: "group3"),

                Act("g3.cross_check_g4", "teach", "ha", "teach",
                    "Phân công Nhóm 3 kiểm tra chéo Nhóm 4 theo câu hỏi gợi mở",
                    "4 phút · kết nối nhóm xong sớm hỗ trợ nhóm gặp khó mà không làm hộ bài.",
                    "Bạn dặn Hà và Trang chỉ hỏi Nhóm 4: 'Hai mẫu của nhóm bạn đã dùng cùng lượng nước chưa?'. Hai nhóm bắt đầu đối chiếu tại bàn.",
                    "Tổ chức kiểm tra chéo có hướng dẫn giữa Nhóm 3 và Nhóm 4.",
                    EvidenceDimension.Creativity, InteractionKind.Dialogue, 4, 0, "ha", "g3.cross_checked",
                    requiresFlag: "g3.finished_early", targetGroupId: "group3"),

                // Nhóm 4 — Minh (Tình huống 1: hiểu nhầm yêu cầu / thiếu nền + kiểm tra lại)
                Act("g4.inspect_sheet", "teach", "group4_sheet", "teach",
                    "Đọc phiếu bài làm của Nhóm 4 (Minh, Hùng, Thảo)",
                    "1 phút · phát hiện dấu hiệu hiểu sai trên giấy trước khi can thiệp.",
                    "Đọc phiếu Nhóm 4.",
                    "Đọc sản phẩm nháp của Nhóm 4 để phát hiện sai lệch hướng điều tra.",
                    EvidenceDimension.Analysis, InteractionKind.Evidence, 1, 0, "minh",
                    oneTime: false, targetGroupId: "group4"),

                Act("g4.clarify_cause", "teach", "minh", "teach",
                    "Hỏi làm rõ riêng với Minh vì sao nhóm chọn tiêu chí sạch/bẩn",
                    "3 phút · xác định Minh nhầm đề bài hay thiếu khái niệm thấm nước trước khi chọn cách giúp.",
                    "Minh nói nhỏ: 'Em tưởng tìm chỗ nhiều lá rác làm nghẹt nước, với lại em nghĩ nước trên sân xi măng tự bốc hơi biến mất chứ không có lỗ nhỏ nào để thấm.'",
                    "Tìm hiểu nguyên nhân hiểu nhầm trước khi đưa ra cách hỗ trợ cho Minh.",
                    EvidenceDimension.Empathy, InteractionKind.Dialogue, 3, 0, "minh", "g4.cause_checked",
                    targetGroupId: "group4"),

                Act("g4.repeat_prompt", "teach", "minh", "teach",
                    "Đọc lại nguyên văn đề bài yêu cầu so sánh sân trường",
                    "2 phút · nhanh, nhưng chưa chắc giải quyết được chỗ vướng của nhóm.",
                    "Bạn nhắc lại nguyên câu đề bài. Minh và Hùng gật đầu cho qua nhưng trên phiếu vẫn chỉ ghi thêm hai chữ 'sau mưa' mà chưa biết thử mẫu thế nào.",
                    "Thử nhắc lại nguyên hướng dẫn; ghi nhận phản ứng chưa chuyển biến thực sự.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Dialogue, 2, 0, "minh", "g4.repeated_only",
                    targetGroupId: "group4"),

                Act("g4.scaffold_steps", "teach", "minh", "teach",
                    "Chia nhỏ nhiệm vụ cho Nhóm 4 thành 2 bước cụ thể",
                    "4 phút · giúp nhóm thoát bế tắc từng bước; cần quay lại kiểm tra bằng ví dụ mới.",
                    "Bạn chia thành 2 bước trên nháp của Minh: (1) Nhỏ 3 giọt vào đất tơi và đếm giây rút; (2) Nhỏ 3 giọt lên gạch lát. Cả nhóm bắt đầu làm lại đúng hướng.",
                    "Chia nhỏ nhiệm vụ thành bước cụ thể giúp Nhóm 4 sửa cách làm.",
                    EvidenceDimension.Empathy, InteractionKind.Dialogue, 4, 0, "minh", "g4.scaffolded",
                    targetGroupId: "group4"),

                Act("g4.demo_samples", "teach", "minh", "teach",
                    "Minh họa trực tiếp bằng mẫu đất và gạch ngay tại bàn Nhóm 4",
                    "4 phút · trực quan cho học sinh thiếu kiến thức nền; vẫn phải kiểm tra lại sau đó.",
                    "Bạn nhỏ 3 giọt nước lên mẫu đất và mảnh gạch trước mặt Minh, Hùng, Thảo: cả ba thấy rõ nước thấm qua khe đất trong khi đọng tròn trên gạch.",
                    "Minh họa trực quan bằng mẫu vật giúp Nhóm 4 phân biệt thấm và đọng nước.",
                    EvidenceDimension.Creativity, InteractionKind.Physical, 4, 0, "minh", "g4.demoed",
                    targetGroupId: "group4"),

                Act("g4.recheck_new_example", "teach", "minh", "teach",
                    "Quay lại kiểm tra Nhóm 4 bằng ví dụ mới (lối đất nén vs đất vườn tơi)",
                    "3 phút · kiểm chứng xem nhóm thực sự hiểu nguyên lý hay chỉ làm theo mẫu vừa chỉ.",
                    "Bạn đưa câu hỏi ví dụ mới về lối đất bị giẫm nén chặt ở gốc cây và bồn đất tơi. Minh trả lời thay cho nhóm.",
                    "Quay lại kiểm tra Nhóm 4 bằng ví dụ mới sau bước hỗ trợ.",
                    EvidenceDimension.Accuracy, InteractionKind.Evidence, 3, 0, "minh", "g4.rechecked_done",
                    requiresAny: new[] { "g4.scaffolded", "g4.demoed", "g3.cross_checked" }, targetGroupId: "group4"),

                // Transition D -> E (Explicit, NOT auto-triggered after 1 action!)
                Act("teach.to_check", "teach", "board", "assess",
                    "Chuyển sang giai đoạn Kiểm tra việc học cuối tiết",
                    "1 phút · chỉ mở sau khi đã có ít nhất 2 hoạt động điều hành lớp (hoặc gần cuối giờ 08:05).",
                    "Bạn ra hiệu cho cả lớp tạm dừng làm bài để chuyển sang phần kiểm tra việc học.",
                    "Chuyển lớp sang bước thu thập bằng chứng hiểu bài cuối tiết.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Physical, 1, 0, "", "teach.moved_to_check",
                    minFacilitateActions: 2),

                // --- PHASE E: CHECKING LEARNING (Lớp 8A) ---
                Act("assess.collect_sheets", "assess", "tickets", "assess",
                    "Thu và đọc nhanh phiếu làm việc của cả 4 nhóm",
                    "4 phút · có sản phẩm viết của đủ 12 học sinh vào cuối giờ.",
                    "Bạn thu bài của đủ 4 nhóm và đối chiếu trực tiếp những lỗi còn sót.",
                    "Thu phiếu viết của cả 4 nhóm để kiểm tra toàn diện mức hiểu.",
                    EvidenceDimension.Accuracy, InteractionKind.Evidence, 4, 0, "", "assess.sheets_collected"),

                Act("assess.quick_probe", "assess", "tickets", "assess",
                    "Phát phiếu ngắn 1 câu hỏi vận dụng trên mẫu đất nén",
                    "3 phút · kiểm tra khả năng chuyển sang ví dụ mới ở cuối giờ.",
                    "Bạn cho các nhóm viết nhanh câu trả lời ngắn về hiện tượng đọng nước trên đường đất nén sau giờ ra chơi.",
                    "Kiểm tra nhanh khả năng vận dụng nguyên lý bằng ví dụ mới.",
                    EvidenceDimension.Analysis, InteractionKind.Evidence, 3, 0, "", "assess.probe_done"),

                Act("assess.oral_linh", "assess", "linh", "assess",
                    "Mời Linh giải thích miệng phần tóm tắt của Nhóm 1",
                    "3 phút · nhanh, nhưng chỉ phản ánh 1 học sinh, chưa kết luận cho cả lớp.",
                    "Linh trình bày ngắn.",
                    "Nghe Linh trình bày miệng và ghi nhận giới hạn của mẫu đơn lẻ.",
                    EvidenceDimension.Empathy, InteractionKind.Dialogue, 3, 0, "linh", "assess.oral_done"),

                Act("assess.oral_nam", "assess", "nam", "assess",
                    "Mời Nam trình bày miệng kết luận của Nhóm 2",
                    "3 phút · nhanh, kiểm tra lại cách Nam dùng bằng chứng trước lớp.",
                    "Nam trình bày trước lớp.",
                    "Nghe Nam trình bày miệng và ghi nhận giới hạn chưa đại diện cho 3 nhóm kia.",
                    EvidenceDimension.Analysis, InteractionKind.Dialogue, 3, 0, "nam", "assess.oral_done"),

                Act("assess.adjust_reteach", "assess", "board", "assess",
                    "Điều chỉnh ngắn trên bảng dựa theo lỗ hổng vừa phát hiện",
                    "3 phút · kịp thời chốt lại điểm các nhóm còn nhầm trước khi hết tiết.",
                    "Bạn chốt lại điểm còn vướng trên bảng dựa đúng vào bằng chứng vừa thu được.",
                    "Điều chỉnh nội dung giảng chốt cuối tiết dựa trên kết quả kiểm tra thực tế.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Physical, 3, 0, "", "assess.adjusted",
                    requiresFlag: "assess.has_evidence"),

                Act("assess.end_lesson", "assess", "board", "reflect",
                    "Kết thúc tiết dạy Lớp 8A & quay lại phản tư với cô Mai",
                    "1 phút · khép lại tiết học 45 phút và chuyển sang bước phản tư.",
                    "Bạn chào lớp 8A, thu dọn học liệu và ra trao đổi tổng kết tiết học cùng cô Mai.",
                    "Chủ động khép lại tiết dạy và bước vào phản tư bằng chứng.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Physical, 1, 0, "", "assess.lesson_ended",
                    requiresFlag: "assess.has_evidence"),

                // --- PHASE F: REFLECTION (Phản tư với cô Mai) ---
                Act("reflect.finish", "reflect", "mai", "complete",
                    "Hoàn tất phản tư và nhận trao đổi từ cô Mai",
                    "5 phút · tổng hợp Hồ sơ trải nghiệm nghề giáo viên — phiên thử nghiệm.",
                    "Cô Mai: 'Hãy đối chiếu từng nhận xét với sự kiện đã xảy ra trong tiết học 8A vừa rồi.'",
                    "Hoàn thành phản tư có dẫn chiếu bằng chứng và nhận phản hồi từ cô Mai.",
                    EvidenceDimension.DecisionMaking, InteractionKind.Dialogue, 5, 0, "mai", "reflect.done")
            };

            // Wire multi-flag / helper flags cleanly
            foreach (var a in actions)
            {
                if (a.id == "prep.print" || a.id == "prep.projector" || a.id == "prep.group_cards")
                {
                    // Set secondary ready marker via ConditionalEffect? Easier: let ScenarioSession check or set prep.any_selected_or_ready!
                    // Actually, let's check both `printed || projector || group-kit` cleanly in ScenarioAssets:
                    // Wait: ScenarioAction only has one `setsFlag`. Let's use `requiresAnyFlags` or keep `prep.enter_class` always available AND distinguish label!
                }
            }

            // Adjust `prep.enter_class` and `prep.enter_class_direct` so there is no unfulfilled flag mismatch:
            // Actually if `prep.enter_class` checks `Resources` or `requiresAnyFlags` (`printed`, `projector`, `group-kit`), let's wire `requiresAllFlags`? Wait: we only had `requiresAllFlags` and `forbidsAnyFlags` in `ScenarioDefinition`! Let's add `requiresAnyFlags` or just make `prep.enter_class` unconditional (always available as "Mang học liệu hiện có sang Lớp 8A") so there's zero redundant button AND player can combine 0, 1, 2, or 3 methods seamlessly!
            actions.RemoveAll(a => a.id == "prep.enter_class_direct");
            var enterClass = actions.Find(a => a.id == "prep.enter_class");
            enterClass.requiresFlag = "";
            enterClass.label = "Sang Lớp 8A để bắt đầu tiết học";
            enterClass.tradeoff = "2 phút · dùng toàn bộ học liệu bạn đã chuẩn bị (hoặc chỉ bảng phấn nếu chưa soạn thêm).";

            // Fix open.framed flag: set `open.framed` when either `open.clear_task` or `open.quick_task` executes
            // Because `open.clear_task` sets `open.clear` and `open.quick_task` sets `open.rushed`, let's allow `open.check_whole_class` & `open.check_pair_prompt` to check `open.framed` OR let's add `requiresAnyFlags` to `ScenarioDefinition` & `ScenarioSession`!
            // Let's also allow `assess.adjust_reteach` and `assess.end_lesson` when ANY of `assess.sheets_collected`, `assess.probe_done`, `assess.oral_done` is set!
            // Wait: adding `public string[] requiresAnyFlags` to `ScenarioDefinition` & `ScenarioSession` takes 5 lines and makes multi-path preconditions super clean and expressive!
            var checkWhole = actions.Find(a => a.id == "open.check_whole_class");
            checkWhole.requiresFlag = "";
            checkWhole.requiresAllFlags = System.Array.Empty<string>();
            checkWhole.requiresAnyFlags = new[] { "open.clear", "open.rushed" };
            checkWhole.forbidsFlag = "";
            checkWhole.forbidsAnyFlags = new[] { "open.checked", "open.checked_pair" };

            var checkPair = actions.Find(a => a.id == "open.check_pair_prompt");
            checkPair.requiresFlag = "";
            checkPair.requiresAnyFlags = new[] { "open.clear", "open.rushed" };
            checkPair.forbidsFlag = "";
            checkPair.forbidsAnyFlags = new[] { "open.checked", "open.checked_pair" };

            var startGroup = actions.Find(a => a.id == "open.start_group_work");
            startGroup.requiresFlag = "";
            startGroup.requiresAnyFlags = new[] { "open.checked", "open.checked_pair" };

            var recheckG4 = actions.Find(a => a.id == "g4.recheck_new_example");
            recheckG4.requiresFlag = "";
            recheckG4.requiresAnyFlags = new[] { "g4.scaffolded", "g4.demoed", "g3.cross_checked" };

            var adjustReteach = actions.Find(a => a.id == "assess.adjust_reteach");
            adjustReteach.requiresFlag = "";
            adjustReteach.requiresAnyFlags = new[] { "assess.sheets_collected", "assess.probe_done", "assess.oral_done" };

            var endLesson = actions.Find(a => a.id == "assess.end_lesson");
            endLesson.requiresFlag = "";
            endLesson.requiresAnyFlags = new[] { "assess.sheets_collected", "assess.probe_done", "assess.oral_done" };

            var openClear = actions.Find(a => a.id == "open.clear_task");
            openClear.forbidsAnyFlags = new[] { "open.clear", "open.rushed" };
            var openQuick = actions.Find(a => a.id == "open.quick_task");
            openQuick.forbidsFlag = "";
            openQuick.forbidsAnyFlags = new[] { "open.clear", "open.rushed" };

            data.actions = actions.ToArray();

            data.timedEvents = new[]
            {
                new TimedEvent
                {
                    id = "equipment.failed",
                    stepId = "teach",
                    requiresFlag = "projector",
                    delayMinutes = 6,
                    feedback = "Đèn máy chiếu lớp 8A tắt ngấm sau 6 phút vì quá nhiệt! Màn chiếu tối đen; bạn có thể chuyển sang bảng phấn, bản in dự phòng hoặc mẫu vật trên bàn.",
                    effects = new[] { Fx(ConsequenceKind.TimePressure, 1) }
                },
                new TimedEvent
                {
                    id = "group3.finished_early",
                    stepId = "teach",
                    delayMinutes = 5,
                    setsFlag = "g3.finished_early",
                    feedback = "Nhóm 3 (Hà, Trang, Đức) đã vẽ xong sơ đồ khe đất và giơ tay báo xong phần cơ bản.",
                    effects = new[] { Fx(ConsequenceKind.Participation, 1) }
                },
                new TimedEvent
                {
                    id = "group3.idle_drift",
                    stepId = "teach",
                    anchorFlag = "g3.finished_early",
                    requiresFlag = "g3.finished_early",
                    forbidsAnyFlags = new[] { "g3.extended", "g3.cross_checked" },
                    delayMinutes = 8,
                    feedback = "Nhóm 3 chờ khá lâu sau khi xong sớm mà chưa có việc tiếp: Trang và Đức bắt đầu nói chuyện riêng làm giảm tập trung.",
                    effects = new[] { Fx(ConsequenceKind.TimePressure, 1) }
                }
            };
        }

        public static NpcProfile Profile(string id, string name, string role, string introduction, bool seated, float speed = 0.7f)
        {
            string path = Folder + "/NPC-" + id + ".asset";
            NpcProfile value = AssetDatabase.LoadAssetAtPath<NpcProfile>(path);
            bool isNew = value == null;
            if (isNew) value = ScriptableObject.CreateInstance<NpcProfile>();

            value.id = id;
            value.displayName = name;
            value.role = role;
            value.introduction = introduction;
            value.speed = speed;
            value.schedule = new[]
            {
                new ScheduleEntry { minute = 430, waypoint = 0, seated = seated },
                new ScheduleEntry { minute = 450, waypoint = seated ? 0 : 1, seated = seated },
                new ScheduleEntry { minute = 495, waypoint = 0, seated = seated }
            };

            if (isNew) AssetDatabase.CreateAsset(value, path);
            else EditorUtility.SetDirty(value);
            return value;
        }

        private static ScenarioStep Step(string id, LessonPhase phase, string objective) =>
            new ScenarioStep { id = id, phase = phase, objective = objective };

        private static Consequence Fx(ConsequenceKind kind, int amount) =>
            new Consequence { kind = kind, amount = amount };

        private static ScenarioAction Act(
            string id, string step, string station, string next,
            string label, string tradeoff, string feedback, string evidence,
            EvidenceDimension dimension, InteractionKind kind,
            int minutes, int earliestMinute, string npc, string flag = "",
            bool oneTime = true, string targetGroupId = "", string requiresFlag = "", string forbidsFlag = "",
            string[] requiresAny = null, int minFacilitateActions = 0,
            params Consequence[] effects) =>
            new ScenarioAction
            {
                id = id,
                stepId = step,
                stationId = station,
                nextStepId = next,
                label = label,
                tradeoff = tradeoff,
                feedback = feedback,
                evidence = evidence,
                dimension = dimension,
                kind = kind,
                minutes = minutes,
                earliestMinute = earliestMinute,
                npcId = npc,
                setsFlag = flag,
                oneTime = oneTime,
                grantsEvidence = true,
                targetGroupId = targetGroupId,
                requiresFlag = requiresFlag,
                forbidsFlag = forbidsFlag,
                requiresAllFlags = System.Array.Empty<string>(),
                requiresAnyFlags = requiresAny ?? System.Array.Empty<string>(),
                forbidsAnyFlags = System.Array.Empty<string>(),
                minFacilitateActions = minFacilitateActions,
                effects = effects ?? System.Array.Empty<Consequence>()
            };
    }
}
