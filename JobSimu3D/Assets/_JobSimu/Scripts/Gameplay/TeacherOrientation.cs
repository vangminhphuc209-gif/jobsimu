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
            OrientationStage.MeetMentor => "Gặp cô Mai ở sân trường THCS Bình Minh.",
            OrientationStage.CollectPlan => "Vào phòng giáo viên, nhận kế hoạch trên bàn.",
            OrientationStage.VisitClassroom => "Mở cửa lớp 8A; đọc bảng và chuẩn bị hoạt động đầu giờ.",
            OrientationStage.Debrief => "Quay lại gặp cô Mai để phản tư buổi chuẩn bị.",
            _ => "Đã hoàn thành buổi làm quen. Bạn có thể tiếp tục khám phá trường."
        };

        public string Act(OrientationAction action)
        {
            if (action == OrientationAction.Mentor && Stage == OrientationStage.MeetMentor)
            {
                Stage = OrientationStage.CollectPlan;
                evidence.Add("Thấu cảm: chủ động trao đổi với giáo viên hướng dẫn.");
                return "Chào thầy/cô! Hãy nhận kế hoạch ở phòng giáo viên bên phải, rồi đến lớp 8A bên trái. " +
                    "Đọc bảng để chuẩn bị một câu hỏi mở cho học sinh. Sau đó quay lại cùng tôi nhìn lại cách chuẩn bị.";
            }
            if (action == OrientationAction.LessonPlan && Stage == OrientationStage.CollectPlan)
            {
                Stage = OrientationStage.VisitClassroom;
                evidence.Add("Chuẩn xác: đã nhận và đọc kế hoạch trước khi vào lớp.");
                return "Kế hoạch: mở đầu bằng câu hỏi ‘Vì sao một số vị trí trong sân trường đọng nước sau mưa?’. " +
                    "Cho học sinh thời gian quan sát, so sánh mẫu theo nhóm, rồi mời chia sẻ. " +
                    "Đến bảng lớp 8A để chuẩn bị hoạt động.";
            }
            if (action == OrientationAction.ClassroomBoard && Stage == OrientationStage.VisitClassroom)
            {
                Stage = OrientationStage.Debrief;
                evidence.Add("Phân tích: đã kiểm tra không gian và nội dung bảng trước giờ học.");
                return "Đã chuẩn bị câu hỏi mở và hướng dẫn so sánh mẫu trên bảng lớp 8A. " +
                    "Đây là cảnh làm quen cơ bản; để chơi đủ tiết học 45 phút với 12 học sinh, hãy mở scene SharedFrameworkDemo. " +
                    "Quay lại cô Mai để phản tư.";
            }
            if (action == OrientationAction.Mentor && Stage == OrientationStage.Debrief)
            {
                Stage = OrientationStage.Complete;
                evidence.Add("Ra quyết định: hoàn tất trình tự chuẩn bị và quay lại trao đổi.");
                return "Bạn đã hoàn thành buổi làm quen. Hãy tự hỏi: học sinh cần thêm hỗ trợ nào để tham gia?\n\n" +
                    string.Join("\n", evidence) + "\nSáng tạo: chưa có đủ bằng chứng trong cảnh làm quen này.\n\n" +
                    "Đây là ghi nhận hành động, không phải kết luận năng lực nghề nghiệp.";
            }
            if (Stage == OrientationStage.Complete)
                return "Buổi làm quen đã hoàn thành.\n\n" + string.Join("\n", evidence) +
                    "\nSáng tạo: chưa có đủ bằng chứng.\nBạn có thể khám phá hành lang và sân trường.";
            return "Việc tiếp theo: " + Objective;
        }
    }
}
