using System.Collections.Generic;
using UnityEngine;

namespace JobSimu.Gameplay
{
    public enum OrientationStage { MeetMentor, CollectPlan, VisitClassroom, Debrief, Complete }
    public enum OrientationAction { Mentor, LessonPlan, ClassroomBoard }

    public sealed class TeacherOrientation : MonoBehaviour
    {
        public OrientationStage Stage { get; private set; }
        private readonly List<string> evidence = new List<string>();
        public IReadOnlyList<string> Evidence => evidence;
        public string Objective => Stage switch
        {
            OrientationStage.MeetMentor => "Gặp cô Mai ở sân trường.",
            OrientationStage.CollectPlan => "Vào phòng giáo viên, nhận kế hoạch trên bàn.",
            OrientationStage.VisitClassroom => "Mở cửa lớp 10A1; đọc bảng và chuẩn bị hoạt động đầu giờ.",
            OrientationStage.Debrief => "Quay lại gặp cô Mai để phản tư buổi chuẩn bị.",
            _ => "Đã hoàn thành buổi làm quen. Bạn có thể tiếp tục khám phá trường."
        };

        public string Act(OrientationAction action)
        {
            if (action == OrientationAction.Mentor && Stage == OrientationStage.MeetMentor)
            {
                Stage = OrientationStage.CollectPlan;
                evidence.Add("Thấu cảm: chủ động trao đổi với giáo viên hướng dẫn.");
                return "Chào thầy/cô! Hãy nhận kế hoạch ở phòng giáo viên bên phải, rồi đến lớp 10A1 bên trái. " +
                    "Đọc bảng để chuẩn bị một câu hỏi mở cho học sinh. Sau đó quay lại cùng tôi nhìn lại cách chuẩn bị.";
            }
            if (action == OrientationAction.LessonPlan && Stage == OrientationStage.CollectPlan)
            {
                Stage = OrientationStage.VisitClassroom;
                evidence.Add("Chuẩn xác: đã nhận và đọc kế hoạch trước khi vào lớp.");
                return "Kế hoạch: mở đầu bằng câu hỏi ‘Điều gì giúp một lớp học biết lắng nghe?’. " +
                    "Cho học sinh thời gian suy nghĩ, trao đổi theo cặp, rồi mời chia sẻ tự nguyện. " +
                    "Đến bảng lớp 10A1 để chuẩn bị hoạt động.";
            }
            if (action == OrientationAction.ClassroomBoard && Stage == OrientationStage.VisitClassroom)
            {
                Stage = OrientationStage.Debrief;
                evidence.Add("Phân tích: đã kiểm tra không gian và nội dung bảng trước giờ học.");
                return "Đã chuẩn bị câu hỏi mở và hướng dẫn trao đổi theo cặp trên bảng. " +
                    "Đây là buổi làm quen: chưa có tiết dạy hay phản ứng học sinh được mô phỏng. " +
                    "Quay lại cô Mai để phản tư.";
            }
            if (action == OrientationAction.Mentor && Stage == OrientationStage.Debrief)
            {
                Stage = OrientationStage.Complete;
                evidence.Add("Ra quyết định: hoàn tất trình tự chuẩn bị và quay lại trao đổi.");
                return "Bạn đã hoàn thành buổi làm quen. Hãy tự hỏi: học sinh cần thêm hỗ trợ nào để tham gia?\n\n" +
                    string.Join("\n", evidence) + "\nSáng tạo: chưa có đủ bằng chứng trong prototype này.\n\n" +
                    "Đây là ghi nhận hành động, không phải kết luận năng lực nghề nghiệp.";
            }
            if (Stage == OrientationStage.Complete)
                return "Buổi làm quen đã hoàn thành.\n\n" + string.Join("\n", evidence) +
                    "\nSáng tạo: chưa có đủ bằng chứng.\nBạn có thể khám phá lớp 10A2 và sân trường.";
            return "Việc tiếp theo: " + Objective;
        }
    }
}
