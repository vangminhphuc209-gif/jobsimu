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
        public static ScenarioDefinition GetOrCreate()
        {
            ScenarioDefinition existing = AssetDatabase.LoadAssetAtPath<ScenarioDefinition>(ScenarioPath);
            if (existing != null) return existing;
            Directory.CreateDirectory(Folder); AssetDatabase.Refresh();
            var data = ScriptableObject.CreateInstance<ScenarioDefinition>();
            data.id = "teacher.first-lesson"; data.contentVersion = "1"; data.profession = Profession.Teacher;
            data.title = "Một tiết học · Vì sao sân trường ngập sau mưa?";
            data.context = "Trường THPT An Hòa hư cấu. Học sinh so sánh bề mặt thấm nước và bề mặt lát kín; giáo viên chuẩn bị, hỗ trợ suy luận và thu bằng chứng hiểu bài.";
            data.initialStepId = "brief"; data.participants = new[] { "mai", "linh", "nam" };
            data.steps = new[] {
                Step("brief", "Gặp cô Mai bên trái sân để nhận tiết dạy."),
                Step("prepare", "Phòng giáo viên: chọn bản in, máy chiếu hoặc bộ thẻ nhóm."),
                Step("begin", "Đến bảng lớp 10A1 và bắt đầu tiết học."),
                Step("teach", "Quan sát lớp: hỗ trợ Linh, trao đổi với Nam hoặc minh họa trên bảng."),
                Step("assess", "Kiểm tra việc học: thu phiếu ở góc bảng hoặc nghe Linh trình bày."),
                Step("reflect", "Kết thúc tiết. Quay lại cô Mai ở sân để nhìn lại bằng chứng."),
                new ScenarioStep { id = "complete", objective = "Đã hoàn thành tiết học. Nhấn P xem báo cáo và ghi phản tư.", terminal = true }
            };
            data.actions = new[] {
                Act("brief.accept", "brief", "mai", "prepare", "Nhận tiết dạy và hỏi mục tiêu", "Bắt đầu chuẩn bị lúc 07:00.",
                    "Hãy giúp học sinh giải thích nước mưa đi đâu trên sân lát kín và trên đất. Ba bộ học liệu ở phòng giáo viên; lựa chọn của bạn sẽ ảnh hưởng giờ học.",
                    "Đã xác nhận mục tiêu học tập trước khi chuẩn bị.", EvidenceDimension.Accuracy, InteractionKind.Dialogue, 0, 420, "mai"),
                Act("prep.print", "prepare", "printer", "begin", "In ảnh và phiếu quan sát", "12 phút · có học liệu dự phòng, ít thời gian chuẩn bị thêm.",
                    "Đã nhận ảnh in. Khi thiết bị không hoạt động, các nhóm vẫn có vật liệu để so sánh.",
                    "Chuẩn bị bản in trước giờ học.", EvidenceDimension.DecisionMaking, InteractionKind.Physical, 12, 0, "", "printed",
                    Fx(ConsequenceKind.Preparation, 2), Fx(ConsequenceKind.TimePressure, 1), Fx(ConsequenceKind.Trust, 1)),
                Act("prep.projector", "prepare", "projector", "begin", "Chuẩn bị trình chiếu", "4 phút · nhanh, nhưng chưa có học liệu dự phòng.",
                    "Bài trình chiếu đã sẵn sàng. Đèn báo máy chiếu chập chờn; bạn có thể chuyển sang bảng nếu cần.",
                    "Chọn trình chiếu để tiết kiệm thời gian chuẩn bị.", EvidenceDimension.DecisionMaking, InteractionKind.Physical, 4, 0, "", "projector",
                    Fx(ConsequenceKind.Preparation, 1)),
                Act("prep.group", "prepare", "group-kit", "begin", "Chuẩn bị bộ thẻ thảo luận", "8 phút · không cần máy chiếu; cần phân chia lượt nói.",
                    "Đã chuẩn bị thẻ: đất trồng, sân bê tông, nước đọng. Nhóm sẽ phân loại rồi giải thích.",
                    "Thiết kế học liệu phân loại theo nhóm.", EvidenceDimension.Creativity, InteractionKind.Physical, 8, 0, "", "group-kit",
                    Fx(ConsequenceKind.Participation, 2), Fx(ConsequenceKind.Preparation, 1)),
                Act("lesson.begin", "begin", "board", "teach", "Bắt đầu: quan sát hai bề mặt", "07:30 · đặt câu hỏi trước khi đưa lời giải.",
                    "Linh muốn thêm một ví dụ. Nam hỏi: ‘Nếu lượng mưa như nhau, vì sao sân bê tông đọng nước lâu hơn?’. Hãy đến gần để trao đổi hoặc dùng bảng.",
                    "Mở đầu tiết học bằng câu hỏi so sánh.", EvidenceDimension.Analysis, InteractionKind.Physical, 2, 450, ""),
                Act("teach.scaffold", "teach", "linh", "assess", "Cùng Linh so sánh từng bước", "10 phút · tăng hỗ trợ, giảm thời gian mở rộng.",
                    "Linh: ‘Em thấy lượng nước vào đất khác với lượng nước đọng trên sân’. Nhóm có thêm một cách giải thích; cần kiểm tra xem các bạn khác có hiểu tương tự không.",
                    "Lắng nghe câu hỏi và chia nhiệm vụ thành các bước.", EvidenceDimension.Empathy, InteractionKind.Dialogue, 10, 0, "linh", "scaffolded",
                    Fx(ConsequenceKind.Trust, 2), Fx(ConsequenceKind.Understanding, 2), Fx(ConsequenceKind.TimePressure, 1)),
                Act("teach.compare", "teach", "nam", "assess", "Cùng Nam đối chiếu hai quan sát", "8 phút · giữ điều kiện lượng mưa như nhau và tìm bằng chứng.",
                    "Nam: ‘Cần so sánh cùng lượng mưa và cùng thời gian quan sát’. Nhóm sửa cách giải thích bằng bằng chứng thay vì đoán.",
                    "Đối chiếu dữ liệu và kiểm tra giả định cùng học sinh.", EvidenceDimension.Analysis, InteractionKind.Evidence, 8, 0, "nam", "compared",
                    Fx(ConsequenceKind.Understanding, 2), Fx(ConsequenceKind.Participation, 1)),
                Act("teach.explain", "teach", "board", "assess", "Vẽ sơ đồ dòng nước trên bảng", "5 phút · tiết kiệm thời gian; chưa nghe lý giải của từng nhóm.",
                    "Sơ đồ đã làm rõ dòng nước thấm và chảy trên bề mặt. Một số học sinh ghi lại; chưa biết các em tự giải thích được đến đâu.",
                    "Dùng sơ đồ thay thế để duy trì tiết học.", EvidenceDimension.Creativity, InteractionKind.Physical, 5, 0, "", "diagram",
                    Fx(ConsequenceKind.Understanding, 1)),
                Act("assess.tickets", "assess", "tickets", "reflect", "Thu và đối chiếu phiếu cuối giờ", "7 phút · có sản phẩm từng nhóm để chuẩn bị tiết sau.",
                    "Phiếu cho thấy một nhóm còn nhầm ‘nước biến mất’ với ‘nước thấm’. Bạn ghi lại ví dụ cần dùng ở tiết sau. Tiết học đã kết thúc.",
                    "Thu sản phẩm học tập và xác định điểm cần kiểm tra lại.", EvidenceDimension.Accuracy, InteractionKind.Evidence, 7, 0, "", "tickets-collected",
                    Fx(ConsequenceKind.Understanding, 1)),
                Act("assess.oral", "assess", "linh", "reflect", "Mời Linh tổng kết bằng lời", "3 phút · nhanh, nhưng chưa có bằng chứng của cả lớp.",
                    "Linh tóm tắt được nội dung. Bạn chưa thể suy ra cả lớp đã hiểu; ghi việc kiểm tra thêm vào tiết sau. Tiết học đã kết thúc.",
                    "Thu phản hồi miệng và thừa nhận giới hạn dữ liệu.", EvidenceDimension.Empathy, InteractionKind.Dialogue, 3, 0, "linh", "oral-only",
                    Fx(ConsequenceKind.Trust, 1)),
                Act("reflect.finish", "reflect", "mai", "complete", "Nhìn lại tiết dạy với cô Mai", "5 phút · xem bằng chứng, chọn điều muốn thử ở tiết sau.",
                    "Cô Mai: ‘Hãy xem điều gì có bằng chứng và điều gì còn cần kiểm tra. Một tiết học không quyết định sự phù hợp nghề nghiệp’. ",
                    "Trao đổi sau tiết học và xác định việc cần cải thiện.", EvidenceDimension.DecisionMaking, InteractionKind.Dialogue, 5, 0, "mai")
            };
            data.actions[4].conditionalEffects = new[] {
                Conditional("printed", "Ảnh in đã được đặt trên bàn; nhóm tiếp tục quan sát.", Fx(ConsequenceKind.Understanding, 1)),
                Conditional("group-kit", "Bộ thẻ đã chia cho nhóm; Linh sẵn sàng trao đổi.", Fx(ConsequenceKind.Participation, 1)),
                Conditional("projector", "Máy chiếu không lên hình. Bạn vẫn có thể dùng bảng, trao đổi với học sinh và hoàn thành tiết học.", Fx(ConsequenceKind.TimePressure, 2))
            };
            data.timedEvents = new[] { new TimedEvent { id = "equipment.waited", stepId = "teach", requiresFlag = "projector", delayMinutes = 2,
                feedback = "Đã chờ thiết bị thêm hai phút. Máy chiếu chưa hoạt động; chuyển sang bảng hoặc trao đổi trực tiếp vẫn khả thi.", effects = new[] { Fx(ConsequenceKind.TimePressure, 1) } } };
            AssetDatabase.CreateAsset(data, ScenarioPath); return data;
        }
        public static NpcProfile Profile(string id, string name, string role, string introduction, bool seated)
        {
            string path = Folder + "/NPC-" + id + ".asset";
            NpcProfile value = AssetDatabase.LoadAssetAtPath<NpcProfile>(path);
            if (value != null) return value;
            value = ScriptableObject.CreateInstance<NpcProfile>();
            value.id = id; value.displayName = name; value.role = role; value.introduction = introduction;
            value.schedule = new[] { new ScheduleEntry { minute = 405, waypoint = 0 }, new ScheduleEntry { minute = 450, waypoint = 1, seated = seated }, new ScheduleEntry { minute = 570, waypoint = 0 } };
            AssetDatabase.CreateAsset(value, path); return value;
        }
        private static ScenarioStep Step(string id, string objective) => new ScenarioStep { id = id, objective = objective };
        private static Consequence Fx(ConsequenceKind kind, int amount) => new Consequence { kind = kind, amount = amount };
        private static ConditionalEffect Conditional(string flag, string feedback, params Consequence[] effects) => new ConditionalEffect { requiresFlag = flag, feedback = feedback, effects = effects };
        private static ScenarioAction Act(string id, string step, string station, string next, string label, string tradeoff, string feedback,
            string evidence, EvidenceDimension dimension, InteractionKind kind, int minutes, int earliestMinute, string npc, string flag = "", params Consequence[] effects) =>
            new ScenarioAction { id = id, stepId = step, stationId = station, nextStepId = next, label = label, tradeoff = tradeoff, feedback = feedback,
                evidence = evidence, dimension = dimension, kind = kind, minutes = minutes, earliestMinute = earliestMinute, npcId = npc, setsFlag = flag, effects = effects };
    }
}
