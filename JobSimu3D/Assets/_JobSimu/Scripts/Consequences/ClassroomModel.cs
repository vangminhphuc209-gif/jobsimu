using System;
using System.Collections.Generic;
using System.Linq;

namespace JobSimu.Consequences
{
    public enum PromptUnderstanding { Misunderstood, Uncertain, Clear }
    public enum EvidenceQuality { None, SurfaceClaim, VisualOnly, ControlledComparison }
    public enum GroupWorkStatus
    {
        WaitingPrompt,
        WorkingMisaligned,
        WorkingOnTrack,
        FinishedEarly,
        IdleDrifting,
        CrossChecking,
        Extended,
        PendingRecheck,
        RecheckedVerified,
        RecheckedStillGaps
    }

    public enum StudentParticipationState { Quiet, Cautious, Active, HesitantAfterPressure, Idle }
    public enum StudentSafetyState { Guarded, Cautious, Supported }
    public enum StudentPosture { SeatedWorking, RaisingHand, Discussing, IdleLookingAround, Listening }

    /// <summary>Observable + internal state for one of the four Class 8A groups (3 students each).</summary>
    [Serializable]
    public sealed class GroupState
    {
        public string groupId;
        public string groupName;
        public string focusStudentId;
        public string[] members;
        public PromptUnderstanding promptGrasp;
        public EvidenceQuality evidenceQuality;
        public GroupWorkStatus workStatus;
        public string worksheetTitle;
        public string worksheetContent;
        public string teacherNote;
        public bool observedByTeacher;
        public bool supportedByTeacher;
        public bool needsRecheck;
        public bool rechecked;
        public bool assignedExtension;
        public int lastObservedRevision;
        public int stateRevision;
    }

    /// <summary>Situational state for a focus student (never shown as floating overhead numeric score).</summary>
    [Serializable]
    public sealed class FocusStudentState
    {
        public string id;
        public string displayName;
        public string groupId;
        public string situationalStartingNote;
        public bool hasTaskKnowledge;
        public PromptUnderstanding promptUnderstanding;
        public StudentParticipationState readiness;
        public StudentSafetyState safety;
        public string observableBehavior;
        public string currentQuote;
        public StudentPosture posture;
        public readonly List<string> shortMemory = new List<string>();
    }

    public enum IssueStatus { Dormant, Active, NeedsRecheck, Resolved, UnresolvedAtEnd }

    [Serializable]
    public sealed class OngoingIssue
    {
        public string id;
        public string title;
        public string cause;
        public int triggeredAtMinute;
        public IssueStatus status;
        public string summary;
    }

    [Serializable]
    public sealed class ResourceState
    {
        public int prepBudgetMinutes = 20;
        public int lateStartMinutes;
        public bool readPastPapers;
        public bool inspectedGear;
        public bool printedSheets;
        public bool projectorPrepared;
        public bool projectorBroken;
        public bool groupCardsPrepared;
        public bool boardDiagramDrawn;
        public bool boardCriteriaWritten;
        public bool boardAdjusted;
    }

    [Serializable]
    public sealed class ReflectionInput
    {
        public string liked = "";
        public string disliked = "";
        public string difficulty = "";
        public string keep = "";
        public string change = "";
        public string testNext = "";
        public string freeNote = "";
        public List<string> selectedEvidenceIds = new List<string>();

        public ReflectionInput Clone() => new ReflectionInput
        {
            liked = liked ?? "",
            disliked = disliked ?? "",
            difficulty = difficulty ?? "",
            keep = keep ?? "",
            change = change ?? "",
            testNext = testNext ?? "",
            freeNote = freeNote ?? "",
            selectedEvidenceIds = selectedEvidenceIds != null ? new List<string>(selectedEvidenceIds) : new List<string>()
        };
    }

    public static class ClassroomCatalog
    {
        public static Dictionary<string, GroupState> CreateInitialGroups()
        {
            return new Dictionary<string, GroupState>
            {
                ["group1"] = new GroupState
                {
                    groupId = "group1",
                    groupName = "Nhóm 1",
                    focusStudentId = "linh",
                    members = new[] { "Linh", "An", "Bình" },
                    promptGrasp = PromptUnderstanding.Clear,
                    evidenceQuality = EvidenceQuality.ControlledComparison,
                    workStatus = GroupWorkStatus.WaitingPrompt,
                    worksheetTitle = "Phiếu Nhóm 1 (Linh, An, Bình)",
                    worksheetContent = "Chờ giáo viên nêu nhiệm vụ điều tra sân trường đọng nước.",
                    teacherNote = "Chưa quan sát bàn này.",
                    stateRevision = 1
                },
                ["group2"] = new GroupState
                {
                    groupId = "group2",
                    groupName = "Nhóm 2",
                    focusStudentId = "nam",
                    members = new[] { "Nam", "Vy", "Khánh" },
                    promptGrasp = PromptUnderstanding.Clear,
                    evidenceQuality = EvidenceQuality.SurfaceClaim,
                    workStatus = GroupWorkStatus.WaitingPrompt,
                    worksheetTitle = "Phiếu Nhóm 2 (Nam, Vy, Khánh)",
                    worksheetContent = "Chờ giáo viên nêu nhiệm vụ điều tra sân trường đọng nước.",
                    teacherNote = "Chưa quan sát bàn này.",
                    stateRevision = 1
                },
                ["group3"] = new GroupState
                {
                    groupId = "group3",
                    groupName = "Nhóm 3",
                    focusStudentId = "ha",
                    members = new[] { "Hà", "Trang", "Đức" },
                    promptGrasp = PromptUnderstanding.Clear,
                    evidenceQuality = EvidenceQuality.VisualOnly,
                    workStatus = GroupWorkStatus.WaitingPrompt,
                    worksheetTitle = "Phiếu Nhóm 3 (Hà, Trang, Đức)",
                    worksheetContent = "Chờ giáo viên nêu nhiệm vụ điều tra sân trường đọng nước.",
                    teacherNote = "Chưa quan sát bàn này.",
                    stateRevision = 1
                },
                ["group4"] = new GroupState
                {
                    groupId = "group4",
                    groupName = "Nhóm 4",
                    focusStudentId = "minh",
                    members = new[] { "Minh", "Hùng", "Thảo" },
                    promptGrasp = PromptUnderstanding.Misunderstood,
                    evidenceQuality = EvidenceQuality.None,
                    workStatus = GroupWorkStatus.WaitingPrompt,
                    worksheetTitle = "Phiếu Nhóm 4 (Minh, Hùng, Thảo)",
                    worksheetContent = "Chờ giáo viên nêu nhiệm vụ điều tra sân trường đọng nước.",
                    teacherNote = "Chưa quan sát bàn này.",
                    stateRevision = 1
                }
            };
        }

        public static Dictionary<string, FocusStudentState> CreateInitialStudents()
        {
            return new Dictionary<string, FocusStudentState>
            {
                ["linh"] = new FocusStudentState
                {
                    id = "linh",
                    displayName = "Linh",
                    groupId = "group1",
                    situationalStartingNote = "Hiểu quan sát nhưng ít phát biểu trước cả lớp.",
                    hasTaskKnowledge = true,
                    promptUnderstanding = PromptUnderstanding.Clear,
                    readiness = StudentParticipationState.Quiet,
                    safety = StudentSafetyState.Cautious,
                    observableBehavior = "Ngồi ngay ngắn, mắt hướng vở ghi, ít giơ tay khi hỏi cả lớp.",
                    currentQuote = "Em ghi lại bảng so sánh hai bề mặt ở nháp rồi ạ.",
                    posture = StudentPosture.Listening
                },
                ["nam"] = new FocusStudentState
                {
                    id = "nam",
                    displayName = "Nam",
                    groupId = "group2",
                    situationalStartingNote = "Đưa giả thuyết nhanh nhưng dễ thiếu bước kiểm chứng lượng nước.",
                    hasTaskKnowledge = false,
                    promptUnderstanding = PromptUnderstanding.Clear,
                    readiness = StudentParticipationState.Active,
                    safety = StudentSafetyState.Supported,
                    observableBehavior = "Nhanh chóng muốn giơ tay trả lời ngay khi nghe câu hỏi.",
                    currentQuote = "Chỗ bê tông không thấm nên đọng nước, đất thấm hết ạ!",
                    posture = StudentPosture.Listening
                },
                ["ha"] = new FocusStudentState
                {
                    id = "ha",
                    displayName = "Hà",
                    groupId = "group3",
                    situationalStartingNote = "Thể hiện tốt qua hình vẽ cắt lớp, cần gợi ý để diễn đạt thành lời.",
                    hasTaskKnowledge = true,
                    promptUnderstanding = PromptUnderstanding.Clear,
                    readiness = StudentParticipationState.Cautious,
                    safety = StudentSafetyState.Cautious,
                    observableBehavior = "Cầm bút phác thảo cấu tạo khe hạt đất và mặt gạch trong vở.",
                    currentQuote = "Em vẽ được đường nước len qua hạt đất rồi nhưng chưa biết nói sao cho gọn.",
                    posture = StudentPosture.Listening
                },
                ["minh"] = new FocusStudentState
                {
                    id = "minh",
                    displayName = "Minh",
                    groupId = "group4",
                    situationalStartingNote = "Có thể hiểu nhầm yêu cầu hoặc thiếu kiến thức nền về độ thấm; cần tìm hiểu trước khi hỗ trợ.",
                    hasTaskKnowledge = false,
                    promptUnderstanding = PromptUnderstanding.Misunderstood,
                    readiness = StudentParticipationState.Cautious,
                    safety = StudentSafetyState.Cautious,
                    observableBehavior = "Đọc đi đọc lại bảng yêu cầu rồi trao đổi nhỏ với Hùng về chỗ có lá rụng.",
                    currentQuote = "Ủa, mình tìm chỗ sân sạch hay sân bẩn đọng nước hả Hùng?",
                    posture = StudentPosture.Listening
                }
            };
        }
    }
}
