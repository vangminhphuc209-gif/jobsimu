using UnityEngine;
using JobSimu.Core;
using JobSimu.Consequences;
using JobSimu.Quests;

namespace JobSimu.Gameplay
{
    /// <summary>Updates visible 3D classroom artifacts (board, projector screen, sheets, trays, desk cues) from ScenarioSession state.</summary>
    public sealed class TeacherLessonVisuals : MonoBehaviour
    {
        public SimulationRuntime runtime;
        public GameObject printedMaterials, groupMaterials, projectorSignal, projectorFaultSignal;
        public GameObject projectorScreen;
        public TextMesh boardText, projectorScreenText;
        public TextMesh[] groupBoardLabels = new TextMesh[4];
        public Renderer[] groupStatusMarkers = new Renderer[4];
        public GameObject[] groupPrintedSheets = new GameObject[4];
        public GameObject[] groupSampleTrays = new GameObject[4];
        public Material neutralMat, activeMat, warningMat, verifiedMat;

        private string lastSnapshotKey = "";

        private void Update()
        {
            if (runtime == null || runtime.Session == null) return;
            ScenarioSession session = runtime.Session;
            ResourceState res = session.Resources;

            if (printedMaterials != null) printedMaterials.SetActive(res.printedSheets && session.Phase >= LessonPhase.Opening);
            if (groupMaterials != null) groupMaterials.SetActive(res.groupCardsPrepared && session.Phase >= LessonPhase.Opening);

            bool projectorActive = res.projectorPrepared && !res.projectorBroken && session.Phase >= LessonPhase.Opening;
            if (projectorSignal != null) projectorSignal.SetActive(projectorActive);
            if (projectorFaultSignal != null) projectorFaultSignal.SetActive(res.projectorPrepared && res.projectorBroken);
            if (projectorScreen != null) projectorScreen.SetActive(res.projectorPrepared && session.Phase >= LessonPhase.Opening);
            if (projectorScreenText != null)
            {
                projectorScreenText.gameObject.SetActive(res.projectorPrepared && session.Phase >= LessonPhase.Opening);
                projectorScreenText.text = !res.projectorPrepared ? "" :
                    res.projectorBroken
                        ? "MÁY CHIẾU NGẮT NGUỒN\n(Đèn quá nhiệt · dùng bảng/mẫu)"
                        : "SO SÁNH BỀ MẶT SÂN TRƯỜNG\nCùng lượng mưa · Đất trồng vs Gạch lát";
            }

            for (int i = 0; i < 4; i++)
            {
                if (groupPrintedSheets != null && i < groupPrintedSheets.Length && groupPrintedSheets[i] != null)
                    groupPrintedSheets[i].SetActive(res.printedSheets && session.Phase >= LessonPhase.Opening);
                if (groupSampleTrays != null && i < groupSampleTrays.Length && groupSampleTrays[i] != null)
                    groupSampleTrays[i].SetActive((res.groupCardsPrepared || session.Phase >= LessonPhase.Facilitating) && session.Phase >= LessonPhase.Opening);
            }

            string key = $"{session.StepId}|{res.boardDiagramDrawn}|{res.boardCriteriaWritten}|{res.boardAdjusted}|" +
                $"{session.HasFlag("open.framed")}|{res.projectorBroken}|" +
                $"{GroupRevisionKey(session)}";
            if (key == lastSnapshotKey) return;
            lastSnapshotKey = key;

            UpdateBoardText(session);
            UpdateGroupDeskVisuals(session);
        }

        private static string GroupRevisionKey(ScenarioSession s) =>
            $"{s.GetGroup("group1")?.stateRevision}-{s.GetGroup("group2")?.stateRevision}-{s.GetGroup("group3")?.stateRevision}-{s.GetGroup("group4")?.stateRevision}";

        private void UpdateBoardText(ScenarioSession s)
        {
            if (boardText == null) return;
            ResourceState r = s.Resources;
            if (s.Phase <= LessonPhase.Preparation || !(s.HasFlag("open.clear") || s.HasFlag("open.rushed")))
            {
                boardText.text = "KHOA HỌC TỰ NHIÊN · LỚP 8A\nChủ đề: Nước mưa đi đâu trên sân trường?\nQuan sát · So sánh mẫu · Giải thích";
                return;
            }
            if (r.boardAdjusted)
            {
                boardText.text = "ĐIỀU CHỈNH CUỐI TIẾT · SO SÁNH 3 MẪU\nCùng 3 giọt nước: Đất tơi (thấm) | Đất nén (đọng) | Gạch (đọng)\nGhi rõ bằng chứng quan sát vào phiếu";
                return;
            }
            if (r.boardDiagramDrawn && r.boardCriteriaWritten)
            {
                boardText.text = "SƠ ĐỒ DÒNG THẤM & TIÊU CHÍ\n[Đất tơi: có khe hạt]  vs  [Gạch/Đất nén: khe kín]\n1. Cùng lượng nước  2. Hai bề mặt  3. Thời gian rút";
                return;
            }
            if (r.boardDiagramDrawn)
            {
                boardText.text = "MINH HỌA TRÊN BẢNG\nHạt đất có khe rỗng -> nước len xuống\nMặt gạch lát kín -> nước giữ lại trên mặt";
                return;
            }
            if (r.boardCriteriaWritten)
            {
                boardText.text = "TIÊU CHÍ QUAN SÁT (LỚP 8A)\n1. Giữ lượng nước thử bằng nhau\n2. Đặt cạnh nhau hai mẫu khác biệt\n3. Ghi hiện tượng thật trước khi kết luận";
                return;
            }
            boardText.text = s.Phase == LessonPhase.CheckingLearning || s.Phase == LessonPhase.Reflection
                ? "TỔNG KẾT TIẾT 8A\nNêu kết luận kèm bằng chứng từ mẫu đã thử\nĐiều gì nhóm còn cần kiểm tra thêm?"
                : "NHIỆM VỤ NHÓM LỚP 8A\nVì sao có chỗ sân đọng nước sau mưa?\nSo sánh mẫu và ghi bằng chứng vào phiếu";
        }

        private void UpdateGroupDeskVisuals(ScenarioSession session)
        {
            for (int i = 0; i < 4; i++)
            {
                GroupState g = session.GetGroup("group" + (i + 1));
                if (g == null) continue;
                if (groupBoardLabels != null && i < groupBoardLabels.Length && groupBoardLabels[i] != null)
                {
                    string shortCue = g.workStatus switch
                    {
                        GroupWorkStatus.WaitingPrompt => "Chờ giao việc",
                        GroupWorkStatus.WorkingMisaligned => "Ghi danh sách sân sạch/bẩn",
                        GroupWorkStatus.WorkingOnTrack => "Đang so sánh mẫu",
                        GroupWorkStatus.FinishedEarly => "Đã xong phần cơ bản",
                        GroupWorkStatus.IdleDrifting => "Chờ lâu · mất tập trung",
                        GroupWorkStatus.CrossChecking => "Đang đối chiếu chéo",
                        GroupWorkStatus.Extended => "Đang làm phần mở rộng",
                        GroupWorkStatus.PendingRecheck => "Đã sửa · chờ kiểm tra lại",
                        GroupWorkStatus.RecheckedVerified => "Đã kiểm chứng ví dụ mới",
                        GroupWorkStatus.RecheckedStillGaps => "Cần kiểm chứng thêm",
                        _ => ""
                    };
                    groupBoardLabels[i].text = $"{g.groupName} ({g.members[0]})\n{shortCue}";
                }
                if (groupStatusMarkers != null && i < groupStatusMarkers.Length && groupStatusMarkers[i] != null)
                {
                    Material target = g.workStatus switch
                    {
                        GroupWorkStatus.WorkingMisaligned or GroupWorkStatus.IdleDrifting or GroupWorkStatus.RecheckedStillGaps => warningMat,
                        GroupWorkStatus.RecheckedVerified or GroupWorkStatus.Extended => verifiedMat,
                        GroupWorkStatus.WorkingOnTrack or GroupWorkStatus.FinishedEarly or GroupWorkStatus.PendingRecheck or GroupWorkStatus.CrossChecking => activeMat,
                        _ => neutralMat
                    };
                    if (target != null) groupStatusMarkers[i].sharedMaterial = target;
                }
            }
        }
    }
}
