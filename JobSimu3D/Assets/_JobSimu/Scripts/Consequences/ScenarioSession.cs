using System;
using System.Collections.Generic;
using System.Linq;
using System.Text;
using JobSimu.Quests;

namespace JobSimu.Consequences
{
    [Serializable]
    public sealed class SessionRecord
    {
        public string actionId;
        public int minutes;
        public string payload;
    }

    public sealed class EvidenceEntry
    {
        public string actionId, text, npcId, categoryTag;
        public int minute;
        public EvidenceDimension dimension;
    }

    public sealed class TimelineEntry
    {
        public int minute;
        public LessonPhase phase;
        public string actionId;
        public string summary;
        public bool isEvent;
    }

    /// <summary>Deterministic state reducer for the 45-minute Class 8A science lesson.</summary>
    public sealed class ScenarioSession
    {
        public ScenarioDefinition Definition { get; }
        public string StepId { get; private set; }
        public LessonPhase Phase => Definition.steps.Single(s => s.id == StepId).phase;
        public int Minute { get; private set; }
        public int StepStartedAt { get; private set; }
        public int ActionMinutesUsed { get; private set; }
        public int PassiveMinutesUsed { get; private set; }
        public int FacilitateActionsCount { get; private set; }
        public int Seed { get; }
        public bool HasTimedOut { get; private set; }
        public string LastFeedback { get; private set; } = "";
        public bool Complete => Definition.steps.Single(s => s.id == StepId).terminal;
        public string Objective => BuildDynamicObjective();

        public ResourceState Resources { get; } = new ResourceState();
        public ReflectionInput Reflection { get; private set; } = new ReflectionInput();
        public IReadOnlyDictionary<string, GroupState> Groups => groups;
        public IReadOnlyDictionary<string, FocusStudentState> Students => students;
        public IReadOnlyList<OngoingIssue> Issues => issues;
        public IReadOnlyList<SessionRecord> Records => records;
        public IReadOnlyList<EvidenceEntry> Evidence => evidence;
        public IReadOnlyList<TimelineEntry> Timeline => timeline;
        public IReadOnlyCollection<string> Flags => flags;

        private readonly Dictionary<string, GroupState> groups;
        private readonly Dictionary<string, FocusStudentState> students;
        private readonly List<OngoingIssue> issues = new List<OngoingIssue>();
        private readonly List<SessionRecord> records = new List<SessionRecord>();
        private readonly List<EvidenceEntry> evidence = new List<EvidenceEntry>();
        private readonly List<TimelineEntry> timeline = new List<TimelineEntry>();
        private readonly HashSet<string> flags = new HashSet<string>();
        private readonly HashSet<string> performedOneTimeActions = new HashSet<string>();
        private readonly HashSet<string> recordedEvidenceKeys = new HashSet<string>();
        private readonly HashSet<string> firedEvents = new HashSet<string>();
        private readonly Dictionary<string, int> flagSetMinute = new Dictionary<string, int>();
        private readonly Dictionary<ConsequenceKind, int> consequences = new Dictionary<ConsequenceKind, int>();

        public event Action Changed;

        public ScenarioSession(ScenarioDefinition definition, int seed = 801)
        {
            if (definition == null) throw new ArgumentNullException(nameof(definition));
            List<string> errors = definition.ValidateDefinition();
            if (errors.Count != 0) throw new ArgumentException(string.Join("; ", errors));

            Definition = definition;
            Seed = seed;
            StepId = definition.initialStepId;
            Minute = definition.initialMinute > 0 ? definition.initialMinute : 430;
            StepStartedAt = Minute;

            groups = ClassroomCatalog.CreateInitialGroups();
            students = ClassroomCatalog.CreateInitialStudents();
        }

        public int Consequence(ConsequenceKind kind) => consequences.TryGetValue(kind, out int value) ? value : 0;
        public bool HasFlag(string flag) => !string.IsNullOrEmpty(flag) && flags.Contains(flag);

        public GroupState GetGroup(string groupId) => groups.TryGetValue(groupId, out GroupState g) ? g : null;
        public FocusStudentState GetStudent(string studentId) => students.TryGetValue(studentId, out FocusStudentState s) ? s : null;

        public IEnumerable<ScenarioAction> Available(string stationId) =>
            Definition.actions.Where(a => a.stationId == stationId && CanPerform(a));

        public string ExplainTransitionGate(string actionId)
        {
            ScenarioAction action = Definition.actions.FirstOrDefault(a => a.id == actionId);
            if (action == null || action.stepId != StepId) return "";
            if (action.minFacilitateActions > 0 && FacilitateActionsCount < action.minFacilitateActions && Minute < Definition.lessonEndMinute - 10)
                return $"Cần thêm tương tác thực tế với lớp/nhóm trước khi kiểm tra ({FacilitateActionsCount}/{action.minFacilitateActions} hoạt động, hoặc chờ đến 08:05).";
            if (!string.IsNullOrEmpty(action.requiresFlag) && !flags.Contains(action.requiresFlag))
                return "Chưa đủ điều kiện để chuyển bước lúc này.";
            return "";
        }

        public bool CanPerform(ScenarioAction action)
        {
            if (action == null || Complete || action.stepId != StepId) return false;
            if (action.oneTime && performedOneTimeActions.Contains(action.id)) return false;
            if (!string.IsNullOrEmpty(action.requiresFlag) && !flags.Contains(action.requiresFlag)) return false;
            if (!string.IsNullOrEmpty(action.forbidsFlag) && flags.Contains(action.forbidsFlag)) return false;
            if (action.requiresAllFlags != null)
                foreach (string req in action.requiresAllFlags)
                    if (!string.IsNullOrEmpty(req) && !flags.Contains(req)) return false;
            if (action.requiresAnyFlags != null && action.requiresAnyFlags.Length > 0)
            {
                bool anyMatched = false;
                foreach (string req in action.requiresAnyFlags)
                    if (!string.IsNullOrEmpty(req) && flags.Contains(req)) { anyMatched = true; break; }
                if (!anyMatched) return false;
            }
            if (action.forbidsAnyFlags != null)
                foreach (string forb in action.forbidsAnyFlags)
                    if (!string.IsNullOrEmpty(forb) && flags.Contains(forb)) return false;
            if (action.minFacilitateActions > 0 &&
                FacilitateActionsCount < action.minFacilitateActions &&
                Minute < Definition.lessonEndMinute - 10)
                return false;
            return true;
        }

        public bool TryPerform(string actionId)
        {
            ScenarioAction action = Definition.actions.FirstOrDefault(a => a.id == actionId);
            if (action == null || !CanPerform(action) || records.Count >= 1024) return false;

            records.Add(new SessionRecord { actionId = action.id });
            if (action.oneTime) performedOneTimeActions.Add(action.id);

            int previousMinute = Minute;
            int targetMinute = Math.Max(Minute, action.earliestMinute) + action.minutes;
            if (Phase != LessonPhase.Reflection)
                Minute = Math.Min(Definition.lessonEndMinute, targetMinute);
            else
                Minute = Math.Min(Definition.lessonEndMinute + 15, targetMinute);

            ActionMinutesUsed += Math.Max(0, Minute - previousMinute);

            Apply(action.effects);
            if (!string.IsNullOrEmpty(action.setsFlag)) SetFlag(action.setsFlag);
            if (!string.IsNullOrEmpty(action.clearsFlag)) flags.Remove(action.clearsFlag);

            string composedFeedback = BuildActionFeedback(action);
            foreach (ConditionalEffect conditional in action.conditionalEffects)
            {
                if (!string.IsNullOrEmpty(conditional.requiresFlag) && flags.Contains(conditional.requiresFlag))
                {
                    Apply(conditional.effects);
                    if (!string.IsNullOrEmpty(conditional.feedback))
                        composedFeedback += "\n" + conditional.feedback;
                }
            }

            bool shouldAddEvidence = ShouldRecordEvidence(action);
            ApplyClassroomEffects(action, ref composedFeedback);

            if (Phase == LessonPhase.Facilitating && action.nextStepId == StepId && !action.id.EndsWith(".inspect_sheet"))
                FacilitateActionsCount++;

            if (shouldAddEvidence)
            {
                string key = !string.IsNullOrEmpty(action.evidenceKey) ? action.evidenceKey : action.id;
                recordedEvidenceKeys.Add(key);
                evidence.Add(new EvidenceEntry
                {
                    actionId = action.id,
                    text = action.evidence,
                    dimension = action.dimension,
                    minute = Minute,
                    npcId = action.npcId,
                    categoryTag = "Hành động"
                });
            }

            timeline.Add(new TimelineEntry
            {
                minute = Minute,
                phase = Phase,
                actionId = action.id,
                summary = $"{action.label}",
                isEvent = false
            });

            if (action.nextStepId != StepId)
            {
                StepId = action.nextStepId;
                StepStartedAt = Minute;
                OnEnteredStep(StepId, ref composedFeedback);
            }

            LastFeedback = composedFeedback;
            ProcessEvents();
            CheckPeriodTimeout();
            Changed?.Invoke();
            return true;
        }

        public bool AdvancePassiveMinutes(int minutes)
        {
            if (minutes < 1 || minutes > 60 || Complete || Phase == LessonPhase.Reflection ||
                Minute >= Definition.lessonEndMinute || records.Count >= 1024)
                return false;

            int delta = Math.Min(minutes, Definition.lessonEndMinute - Minute);
            if (delta <= 0) return false;

            records.Add(new SessionRecord { actionId = "@clock", minutes = delta });
            Minute += delta;
            PassiveMinutesUsed += delta;

            ProcessEvents();
            CheckPeriodTimeout();
            Changed?.Invoke();
            return true;
        }

        /// <summary>Backward-compatible alias used by tests/saves for passive clock advancement.</summary>
        public bool AdvanceMinutes(int minutes) => AdvancePassiveMinutes(minutes);

        public void SetReflection(ReflectionInput input, bool recordForReplay = true)
        {
            Reflection = input != null ? input.Clone() : new ReflectionInput();
            if (recordForReplay && records.Count < 1024)
            {
                string payload = string.Join(",", Reflection.selectedEvidenceIds);
                records.Add(new SessionRecord { actionId = "@reflect", payload = payload });
            }
            Changed?.Invoke();
        }

        internal void ApplyReplayReflection(string payload)
        {
            var ids = string.IsNullOrEmpty(payload)
                ? new List<string>()
                : payload.Split(new[] { ',' }, StringSplitOptions.RemoveEmptyEntries).ToList();
            Reflection.selectedEvidenceIds = ids;
        }

        private bool ShouldRecordEvidence(ScenarioAction action)
        {
            if (!action.grantsEvidence || string.IsNullOrWhiteSpace(action.evidence)) return false;

            if (!string.IsNullOrEmpty(action.targetGroupId) && action.id.EndsWith(".inspect_sheet"))
            {
                GroupState group = GetGroup(action.targetGroupId);
                if (group == null) return false;
                // Only record sheet inspection evidence when the sheet hasn't been inspected at its current revision.
                if (group.observedByTeacher && group.lastObservedRevision == group.stateRevision)
                    return false;
                return true;
            }

            string key = !string.IsNullOrEmpty(action.evidenceKey) ? action.evidenceKey : action.id;
            return !recordedEvidenceKeys.Contains(key);
        }

        private void SetFlag(string flag)
        {
            if (string.IsNullOrEmpty(flag)) return;
            if (flags.Add(flag)) flagSetMinute[flag] = Minute;
        }

        private void OnEnteredStep(string newStepId, ref string feedback)
        {
            if (newStepId == "begin")
            {
                if (Minute > Definition.lessonNominalStartMinute)
                {
                    Resources.lateStartMinutes = Minute - Definition.lessonNominalStartMinute;
                    AddOrUpdateIssue("late_start", "Vào tiết muộn giờ",
                        $"Chuẩn bị kéo dài thêm {Resources.lateStartMinutes} phút sau 07:30.",
                        Minute, IssueStatus.Active,
                        $"Lớp 8A vào tiết lúc {FormatClock(Minute)}, quỹ thời gian còn {Math.Max(0, Definition.lessonEndMinute - Minute)} phút.");
                    feedback += $"\n\n[Đồng hồ] Bạn vào lớp muộn {Resources.lateStartMinutes} phút so với trống 07:30; quỹ thời gian 45 phút còn đến 08:15.";
                }
                else
                {
                    Minute = Definition.lessonNominalStartMinute;
                    StepStartedAt = Minute;
                    feedback += "\n\n[07:30] Trống vào tiết 1 vang lên. Cả lớp 8A (4 nhóm, 12 học sinh) đang chờ ở bàn.";
                }

                InitializeGroupWorksheetsForMaterials();
            }
            else if (newStepId == "teach")
            {
                BeginGroupWorkState();
            }
            else if (newStepId == "complete")
            {
                FinalizeUnfinishedIssues();
            }
        }

        private void InitializeGroupWorksheetsForMaterials()
        {
            string materialSuffix = Resources.printedSheets && Resources.groupCardsPrepared
                ? "Có cả bảng in so sánh 2 bề mặt và bộ khay mẫu vật đất/gạch."
                : Resources.printedSheets
                    ? "Trên mỗi bàn đã có phiếu in bảng so sánh 2 bề mặt."
                    : Resources.groupCardsPrepared
                        ? "Trên mỗi bàn có bộ thẻ phân loại và khay mẫu đất/gạch."
                        : "Mỗi nhóm tự kẻ bảng vào vở nháp.";
            foreach (GroupState group in groups.Values)
            {
                group.worksheetContent = "Sẵn sàng nghe đề bài. " + materialSuffix;
                group.stateRevision++;
            }
        }

        private void BeginGroupWorkState()
        {
            bool clear = HasFlag("open.clear");
            bool pairChecked = HasFlag("open.checked_pair");

            // Nhóm 1 (Linh): hiểu đúng, làm cẩn thận trên giấy dù ít nói
            groups["group1"].workStatus = GroupWorkStatus.WorkingOnTrack;
            groups["group1"].promptGrasp = PromptUnderstanding.Clear;
            groups["group1"].evidenceQuality = EvidenceQuality.ControlledComparison;
            groups["group1"].worksheetContent = "Bảng 2 cột (Đất trồng / Gạch lát): Đất có khe rỗng giữa hạt nên nước thấm xuống; mặt gạch kín nước đọng lại khi thử lượng nước bằng nhau.";
            groups["group1"].stateRevision++;
            students["linh"].posture = StudentPosture.SeatedWorking;

            // Nhóm 2 (Nam): kết luận nhanh, thiếu kiểm chứng lượng nước & đất nén
            groups["group2"].workStatus = GroupWorkStatus.WorkingOnTrack;
            groups["group2"].promptGrasp = PromptUnderstanding.Clear;
            groups["group2"].evidenceQuality = EvidenceQuality.SurfaceClaim;
            groups["group2"].worksheetContent = "Nháp Nhóm 2: 'Sân bê tông không thấm nên đọng nước, chỗ đất thì thấm hết.' (Chưa ghi lượng nước thử và chưa xét đất nén.)";
            groups["group2"].stateRevision++;
            students["nam"].posture = StudentPosture.RaisingHand;
            AddOrUpdateIssue("g2_unverified_claim", "Nhóm 2 kết luận vội thiếu đối chứng",
                "Nam đưa ra giả thuyết nhanh nhưng chưa thử cùng lượng nước hoặc so sánh đất nén.",
                Minute, IssueStatus.Active, "Cần hỏi cách suy luận hoặc yêu cầu thiết kế phép kiểm tra.");

            // Nhóm 3 (Hà): vẽ tốt, hoàn thành cơ bản sớm sau ít phút
            groups["group3"].workStatus = GroupWorkStatus.WorkingOnTrack;
            groups["group3"].promptGrasp = PromptUnderstanding.Clear;
            groups["group3"].evidenceQuality = EvidenceQuality.VisualOnly;
            groups["group3"].worksheetContent = "Hình vẽ của Hà: Mô tả rõ các hạt đất có khe hở cho nước len xuống và mặt gạch phẳng chặn nước lại; phần giải thích bằng chữ mới viết 1 dòng ngắn.";
            groups["group3"].stateRevision++;
            students["ha"].posture = StudentPosture.SeatedWorking;

            // Nhóm 4 (Minh): hiểu sai yêu cầu hoặc thiếu kiến thức nền
            groups["group4"].workStatus = GroupWorkStatus.WorkingMisaligned;
            groups["group4"].promptGrasp = (clear && pairChecked) ? PromptUnderstanding.Uncertain : PromptUnderstanding.Misunderstood;
            groups["group4"].evidenceQuality = EvidenceQuality.None;
            groups["group4"].worksheetContent = (clear && pairChecked)
                ? "Nhóm 4 (Minh, Hùng, Thảo): Đang phân vân giữa 'chỗ nhiều lá rụng làm nghẹt nước' với việc so sánh mẫu đất và gạch."
                : "Nhóm 4 (Minh, Hùng, Thảo): Ghi danh sách 'Chỗ gần thùng rác nhiều lá bẩn nên đọng nước; sân xi măng sạch nên khô'.";
            groups["group4"].stateRevision++;
            students["minh"].posture = StudentPosture.SeatedWorking;
            AddOrUpdateIssue("g4_misunderstood", "Nhóm 4 hiểu sai yêu cầu nhiệm vụ",
                "Nhóm đang liệt kê chỗ sạch/bẩn thay vì so sánh tính thấm của bề mặt dưới cùng lượng nước.",
                Minute, IssueStatus.Active, "Cần quan sát/hỏi nguyên nhân trước khi chia nhỏ bước hoặc minh họa, sau đó kiểm tra lại bằng ví dụ mới.");
        }

        private string BuildActionFeedback(ScenarioAction action)
        {
            if (action.id == "assess.oral_linh")
            {
                FocusStudentState linh = students["linh"];
                if (linh.safety == StudentSafetyState.Supported)
                    return "Linh trình bày ngắn gọn nhưng rõ ràng: 'Đất tơi có khe hở nên thấm xuống nhanh hơn mặt gạch lát kín khi thử cùng lượng nước.'\nLưu ý giới hạn bằng chứng: mới nghe 1 học sinh nói miệng, chưa thể kết luận cả lớp đã hiểu.";
                return "Linh hơi ngập ngừng, chỉ đọc nhỏ một câu từ vở rồi im lặng. Bài viết của em đúng nhưng em chưa thấy an toàn khi nói trước cả lớp.\nLưu ý giới hạn bằng chứng: chưa thể suy ra mức hiểu của cả lớp từ một lượt gọi miệng.";
            }

            if (action.id == "assess.oral_nam")
            {
                GroupState g2 = groups["group2"];
                if (g2.evidenceQuality == EvidenceQuality.ControlledComparison)
                    return "Nam đứng lên trình bày kèm số liệu đối chiếu 3 giọt nước trên gạch, đất tơi và đất nén chặt.\nLưu ý giới hạn bằng chứng: đây mới là phần trình bày của Nhóm 2, chưa kiểm chứng được các nhóm còn lại.";
                return "Nam trả lời trôi chảy: 'Bê tông không thấm nên đọng nước'. Tuy nhiên khi hỏi thêm về chỗ đất bị giẫm nén thì em chưa trả lời được.\nLưu ý giới hạn bằng chứng: lời nói nhanh của một học sinh không chứng minh cả lớp đã hiểu.";
            }

            return action.feedback ?? "";
        }

        private void ApplyClassroomEffects(ScenarioAction action, ref string feedback)
        {
            switch (action.id)
            {
                case "prep.read_prev_work":
                    Resources.readPastPapers = true;
                    break;
                case "prep.inspect_gear":
                    Resources.inspectedGear = true;
                    break;
                case "prep.print":
                    Resources.printedSheets = true;
                    break;
                case "prep.projector":
                    Resources.projectorPrepared = true;
                    break;
                case "prep.group_cards":
                    Resources.groupCardsPrepared = true;
                    break;

                case "open.clear_task":
                    feedback += "\nMột vài bàn bắt đầu ghi chú; Nam giơ tay sớm, còn Linh giữ yên lặng nhìn vở.";
                    break;
                case "open.quick_task":
                    feedback += "\nBạn nói nhanh yêu cầu để giữ nhịp giờ; ở bàn cuối Minh nhìn Hùng có vẻ chưa chắc mình cần làm gì.";
                    break;
                case "open.check_whole_class":
                    feedback += "\nNam giơ tay nói ngay kết luận; Linh không giơ tay; phía cuối lớp Nhóm 4 chưa đặt câu hỏi nào.";
                    break;
                case "open.check_pair_prompt":
                    feedback += "\nKhi cho trao đổi cặp 1 phút, Minh rụt rè hỏi: 'Có cần giữ lượng nước đổ lên hai chỗ bằng nhau không ạ?' — dấu hiệu cho thấy cần theo sát Nhóm 4.";
                    break;

                case "teach.board_diagram":
                    Resources.boardDiagramDrawn = true;
                    // Using board BEFORE observing a group must remain completely non-contradictory.
                    if (!groups["group4"].observedByTeacher && !groups["group2"].observedByTeacher)
                        feedback = "Bạn vẽ sơ đồ đối chiếu 2 bề mặt (đất có khe rỗng vs gạch lát kín) lên bảng để cả lớp có khung tham chiếu khi tự điều tra.";
                    break;
                case "teach.board_criteria":
                    Resources.boardCriteriaWritten = true;
                    break;

                // Nhóm 1 (Linh)
                case "g1.inspect_sheet":
                    MarkObserved("group1", ref feedback);
                    groups["group1"].teacherNote = "Bài viết của Linh và nhóm khá chính xác dù Linh ít phát biểu.";
                    break;
                case "g1.ask_private":
                    students["linh"].safety = StudentSafetyState.Supported;
                    students["linh"].readiness = StudentParticipationState.Active;
                    students["linh"].observableBehavior = "Mỉm cười nhẹ, giải thích rành mạch bảng so sánh ở cự ly gần.";
                    students["linh"].currentQuote = "Nhóm em nhỏ thử 3 giọt nước lên đất trồng và gạch lát thì thấy đất thấm hết sau vài giây.";
                    students["linh"].shortMemory.Add("Giáo viên hỏi riêng nhẹ nhàng tại bàn.");
                    groups["group1"].observedByTeacher = true;
                    groups["group1"].lastObservedRevision = groups["group1"].stateRevision;
                    groups["group1"].supportedByTeacher = true;
                    groups["group1"].teacherNote = "Hỏi riêng cho thấy Linh nắm rất chắc cách kiểm chứng.";
                    break;
                case "g1.pair_share":
                    students["linh"].safety = StudentSafetyState.Supported;
                    students["linh"].readiness = StudentParticipationState.Active;
                    students["linh"].posture = StudentPosture.Discussing;
                    students["linh"].observableBehavior = "Đang giải thích bảng so sánh cho An và Bình nghe trong nhóm.";
                    students["linh"].shortMemory.Add("Được chia sẻ trong cặp/nhóm nhỏ trước.");
                    groups["group1"].supportedByTeacher = true;
                    groups["group1"].teacherNote = "Linh tự tin diễn đạt khi trao đổi nhóm 3 người.";
                    break;
                case "g1.callout_public":
                    students["linh"].safety = StudentSafetyState.Guarded;
                    students["linh"].readiness = StudentParticipationState.HesitantAfterPressure;
                    students["linh"].posture = StudentPosture.SeatedWorking;
                    students["linh"].observableBehavior = "Cúi nhìn vở, nói nhỏ vài từ rồi im lặng.";
                    students["linh"].shortMemory.Add("Bị gọi đứng lên trước lớp khi chưa sẵn sàng.");
                    // Crucial rule: silent/hesitant student with accurate sheet is NOT marked "doesn't understand"!
                    groups["group1"].teacherNote = "Linh ngại nói trước cả lớp sau khi bị gọi đột ngột, nhưng bài viết của nhóm vẫn chính xác.";
                    break;

                // Nhóm 2 (Nam)
                case "g2.inspect_sheet":
                    MarkObserved("group2", ref feedback);
                    groups["group2"].teacherNote = groups["group2"].evidenceQuality == EvidenceQuality.ControlledComparison
                        ? "Nhóm 2 đã bổ sung bảng thử 3 giọt nước cho cả đất nén."
                        : "Nhóm 2 mới viết kết luận chung, chưa có số liệu đối chiếu lượng nước.";
                    break;
                case "g2.accept_claim":
                    groups["group2"].supportedByTeacher = true;
                    groups["group2"].worksheetContent = "Nhóm 2 giữ nguyên câu: 'Bê tông không thấm nên đọng nước, đất thấm hết' (chưa thử kiểm chứng).";
                    groups["group2"].stateRevision++;
                    students["nam"].posture = StudentPosture.SeatedWorking;
                    students["nam"].shortMemory.Add("Giáo viên đồng ý nhanh câu trả lời đầu tiên.");
                    groups["group2"].teacherNote = "Đã chấp nhận câu trả lời nhanh của Nam; chưa kiểm chứng đất nén.";
                    break;
                case "g2.ask_reasoning":
                    groups["group2"].supportedByTeacher = true;
                    groups["group2"].needsRecheck = true;
                    groups["group2"].workStatus = GroupWorkStatus.PendingRecheck;
                    groups["group2"].worksheetContent = "Nhóm 2 đang ghi bổ sung câu hỏi: 'Nếu đất bị giẫm nén chặt thì sao? Cần thử lại lượng nước bằng nhau.'";
                    groups["group2"].stateRevision++;
                    students["nam"].posture = StudentPosture.Discussing;
                    students["nam"].shortMemory.Add("Được hỏi lại cơ sở chứng minh.");
                    groups["group2"].teacherNote = "Nam nhận ra nhóm chưa kiểm soát lượng nước thử.";
                    break;
                case "g2.design_test":
                case "g2.recheck":
                    bool hasTested = action.id == "g2.design_test" || HasFlag("g2.probed_reasoning");
                    if (hasTested)
                    {
                        groups["group2"].evidenceQuality = EvidenceQuality.ControlledComparison;
                        groups["group2"].workStatus = GroupWorkStatus.RecheckedVerified;
                        groups["group2"].needsRecheck = false;
                        groups["group2"].rechecked = true;
                        groups["group2"].supportedByTeacher = true;
                        groups["group2"].worksheetContent = "Phiếu Nhóm 2 (đã kiểm chứng): Nhỏ 3 giọt nước bằng nhau -> Đất tơi thấm nhanh; Đất nén chặt & Gạch lát giữ nước trên mặt.";
                        groups["group2"].stateRevision++;
                        students["nam"].hasTaskKnowledge = true;
                        students["nam"].posture = StudentPosture.SeatedWorking;
                        students["nam"].currentQuote = "Hóa ra đất bị giẫm nén chặt cũng đọng nước lâu hơn đất tơi!";
                        students["nam"].shortMemory.Add("Đã kiểm tra lại bằng mẫu thử đối chứng.");
                        groups["group2"].teacherNote = "Nhóm 2 đã bổ sung được bằng chứng đối chứng.";
                        ResolveIssue("g2_unverified_claim", "Nhóm 2 đã thực hiện phép thử đối chứng 3 mẫu.");
                    }
                    else
                    {
                        groups["group2"].workStatus = GroupWorkStatus.RecheckedStillGaps;
                        groups["group2"].rechecked = true;
                        groups["group2"].stateRevision++;
                        groups["group2"].teacherNote = "Kiểm tra lại thấy nhóm vẫn chưa thử lượng nước bằng nhau.";
                    }
                    break;

                // Nhóm 3 (Hà)
                case "g3.inspect_sheet":
                    MarkObserved("group3", ref feedback);
                    groups["group3"].teacherNote = groups["group3"].workStatus == GroupWorkStatus.FinishedEarly
                        ? "Nhóm 3 hoàn thành hình vẽ sớm; phần giải thích bằng lời còn ngắn."
                        : "Hình vẽ cắt lớp của Hà rất rõ các khe hạt đất.";
                    break;
                case "g3.bridge_verbal":
                    groups["group3"].evidenceQuality = EvidenceQuality.ControlledComparison;
                    groups["group3"].supportedByTeacher = true;
                    groups["group3"].worksheetContent = "Phiếu Nhóm 3: Kèm hình vẽ cắt lớp của Hà + 2 câu giải thích rõ 'Nước len qua các khe rỗng giữa hạt đất, còn mặt gạch kín không có khe thông'.";
                    groups["group3"].stateRevision++;
                    students["ha"].readiness = StudentParticipationState.Active;
                    students["ha"].safety = StudentSafetyState.Supported;
                    students["ha"].posture = StudentPosture.Discussing;
                    students["ha"].currentQuote = "Em diễn đạt được rồi: đất tơi có khe hở nên nước len xuống được!";
                    students["ha"].shortMemory.Add("Được hướng dẫn diễn đạt hình vẽ thành lời.");
                    groups["group3"].teacherNote = "Hà đã nối được hình vẽ với câu giải thích nguyên nhân.";
                    break;
                case "g3.assign_extension":
                    groups["group3"].assignedExtension = true;
                    groups["group3"].workStatus = GroupWorkStatus.Extended;
                    groups["group3"].worksheetContent += "\n[Mở rộng] Đã thử thêm mẫu đất lối đi bị nén chặt: nước đọng gần giống mặt gạch do khe rỗng bị ép nhỏ.";
                    groups["group3"].stateRevision++;
                    students["ha"].posture = StudentPosture.SeatedWorking;
                    students["ha"].shortMemory.Add("Nhận nhiệm vụ mở rộng về đất nén.");
                    groups["group3"].teacherNote = "Nhóm 3 đang làm nhiệm vụ mở rộng rất tập trung.";
                    ResolveIssue("g3_idle", "Đã giao nhiệm vụ mở rộng giữ tập trung cho Nhóm 3.");
                    break;
                case "g3.cross_check_g4":
                    groups["group3"].assignedExtension = true;
                    groups["group3"].workStatus = GroupWorkStatus.CrossChecking;
                    groups["group3"].stateRevision++;
                    students["ha"].posture = StudentPosture.Discussing;
                    students["ha"].shortMemory.Add("Hướng dẫn Nhóm 3 kiểm tra chéo Nhóm 4 theo câu hỏi gợi mở.");
                    groups["group3"].teacherNote = "Nhóm 3 đối chiếu chéo với Nhóm 4 dưới sự hướng dẫn.";
                    ResolveIssue("g3_idle", "Nhóm 3 được phân công kiểm tra chéo theo tiêu chí.");
                    // Cross-checking nudges Group 4 but still requires teacher follow-up!
                    if (groups["group4"].promptGrasp == PromptUnderstanding.Misunderstood)
                    {
                        groups["group4"].promptGrasp = PromptUnderstanding.Uncertain;
                        groups["group4"].workStatus = GroupWorkStatus.PendingRecheck;
                        groups["group4"].needsRecheck = true;
                        groups["group4"].worksheetContent = "Nhóm 4 (sau khi trao đổi với Nhóm 3): Đã gạch phần lá rụng, bắt đầu xem lại hình vẽ khe đất nhưng vẫn cần thầy/cô kiểm tra lại.";
                        groups["group4"].stateRevision++;
                    }
                    break;

                // Nhóm 4 (Minh)
                case "g4.inspect_sheet":
                    MarkObserved("group4", ref feedback);
                    groups["group4"].teacherNote = groups["group4"].rechecked
                        ? groups["group4"].teacherNote
                        : "Phiếu Nhóm 4 đang lệch hướng: cần tìm hiểu xem do nhầm đề hay thiếu khái niệm thấm.";
                    break;
                case "g4.clarify_cause":
                    students["minh"].shortMemory.Add("Giáo viên hỏi nhỏ tìm nguyên nhân hiểu nhầm.");
                    students["minh"].currentQuote = "Em tưởng xem chỗ nào nhiều rác làm nghẹt nước, với lại em nghĩ nước trên sân xi măng tự bốc hơi biến mất.";
                    groups["group4"].observedByTeacher = true;
                    groups["group4"].lastObservedRevision = groups["group4"].stateRevision;
                    groups["group4"].teacherNote = "Đã biết nguyên nhân: Minh vừa hiểu nhầm đề sang 'chỗ bẩn', vừa chưa phân biệt thấm với bốc hơi.";
                    break;
                case "g4.repeat_prompt":
                    // Re-reading exact prompt does NOT magically fix misunderstanding!
                    groups["group4"].worksheetContent = "Phiếu Nhóm 4: Mới chép lại tiêu đề đề bài, nhưng bên dưới vẫn ghi 'chỗ nhiều rác đọng nước nhiều hơn'.";
                    groups["group4"].stateRevision++;
                    students["minh"].shortMemory.Add("Giáo viên chỉ đọc lại nguyên đề bài.");
                    groups["group4"].teacherNote = "Đọc lại nguyên đề bài chưa giúp Nhóm 4 đổi cách làm; vẫn có thể chia nhỏ hoặc minh họa.";
                    break;
                case "g4.scaffold_steps":
                case "g4.demo_samples":
                    groups["group4"].promptGrasp = PromptUnderstanding.Clear;
                    groups["group4"].workStatus = GroupWorkStatus.PendingRecheck;
                    groups["group4"].supportedByTeacher = true;
                    groups["group4"].needsRecheck = true;
                    groups["group4"].worksheetContent = action.id == "g4.demo_samples"
                        ? "Nhóm 4 (sau khi xem thầy/cô nhỏ nước mẫu thử): Đang đo 3 giọt nước lên mẫu đất tơi và gạch lát. Cần quay lại kiểm tra bằng ví dụ mới!"
                        : "Nhóm 4 (theo 2 bước nhỏ): Đang ghi bảng so sánh cùng lượng nước trên đất trồng và sân lát. Cần quay lại kiểm tra bằng ví dụ mới!";
                    groups["group4"].stateRevision++;
                    students["minh"].promptUnderstanding = PromptUnderstanding.Clear;
                    students["minh"].posture = StudentPosture.SeatedWorking;
                    students["minh"].currentQuote = "À! Vậy không phải so chỗ sạch bẩn mà so độ thấm với cùng lượng nước!";
                    students["minh"].shortMemory.Add(action.id == "g4.demo_samples" ? "Được minh họa mẫu thử trực tiếp." : "Được chia nhỏ nhiệm vụ 2 bước.");
                    groups["group4"].teacherNote = "Đã hỗ trợ Nhóm 4 chuyển hướng; đang chờ bước kiểm tra lại bằng ví dụ mới.";
                    MarkIssuePendingRecheck("g4_misunderstood", "Nhóm 4 đã đổi cách làm, chờ giáo viên quay lại kiểm tra bằng ví dụ mới.");
                    break;
                case "g4.recheck_new_example":
                    bool hadRealSupport = HasFlag("g4.scaffolded") || HasFlag("g4.demoed");
                    groups["group4"].rechecked = true;
                    groups["group4"].needsRecheck = false;
                    if (hadRealSupport)
                    {
                        groups["group4"].evidenceQuality = EvidenceQuality.ControlledComparison;
                        groups["group4"].workStatus = GroupWorkStatus.RecheckedVerified;
                        groups["group4"].worksheetContent = "Phiếu Nhóm 4 (kiểm tra lại đạt): Trả lời đúng ví dụ mới — đường đất nén ít khe rỗng nên đọng nước lâu hơn bồn đất tơi khi cùng lượng mưa.";
                        groups["group4"].stateRevision++;
                        students["minh"].hasTaskKnowledge = true;
                        students["minh"].currentQuote = "Chỗ đất bị giẫm nén chặt cũng ít khe hở nên nước khó thấm giống mặt gạch ạ!";
                        students["minh"].shortMemory.Add("Kiểm tra lại ví dụ mới đạt.");
                        groups["group4"].teacherNote = "Kiểm tra lại bằng ví dụ đất nén xác nhận Nhóm 4 đã nắm được nguyên lý.";
                        ResolveIssue("g4_misunderstood", "Nhóm 4 vượt qua kiểm tra lại với ví dụ đất nén vs đất tơi.");
                    }
                    else
                    {
                        groups["group4"].evidenceQuality = EvidenceQuality.SurfaceClaim;
                        groups["group4"].workStatus = GroupWorkStatus.RecheckedStillGaps;
                        groups["group4"].worksheetContent = "Phiếu Nhóm 4 (kiểm tra lại còn lúng túng): Chưa giải thích được vì sao đường đất nén cũng đọng nước.";
                        groups["group4"].stateRevision++;
                        groups["group4"].teacherNote = "Nhóm 4 vẫn còn hổng khi đổi sang ví dụ đất nén vì chưa được hướng dẫn từng bước/minh họa.";
                    }
                    break;

                // Phase E: Checking Learning
                case "assess.collect_sheets":
                    feedback += "\n\n" + BuildCollectedSheetsSummary();
                    break;
                case "assess.quick_probe":
                    feedback += "\n\n" + BuildQuickProbeSummary();
                    break;
                case "assess.adjust_reteach":
                    Resources.boardAdjusted = true;
                    feedback = BuildAdaptiveAdjustFeedback();
                    break;
            }
        }

        private void MarkObserved(string groupId, ref string feedback)
        {
            GroupState group = GetGroup(groupId);
            if (group == null) return;
            bool unchanged = group.observedByTeacher && group.lastObservedRevision == group.stateRevision;
            group.observedByTeacher = true;
            group.lastObservedRevision = group.stateRevision;
            feedback = $"{group.worksheetTitle}\n• Nội dung hiện có trên bàn: {group.worksheetContent}\n• Nhận xét ghi chú: {group.teacherNote}" +
                (unchanged ? "\n(Bài làm của nhóm chưa thay đổi thêm kể từ lần xem trước — không ghi nhận thêm bằng chứng mới.)" : "");
        }

        private string BuildCollectedSheetsSummary()
        {
            var sb = new StringBuilder();
            sb.AppendLine("[Tổng hợp từ 4 phiếu nhóm]");
            foreach (GroupState group in groups.Values)
                sb.AppendLine($"• {group.groupName}: {group.worksheetContent}");
            return sb.ToString().TrimEnd();
        }

        private string BuildQuickProbeSummary()
        {
            int verifiedCount = groups.Values.Count(g => g.evidenceQuality == EvidenceQuality.ControlledComparison);
            if (verifiedCount == 4)
                return "[Kết quả câu hỏi nhanh ví dụ mới] Cả 4 nhóm đều chỉ ra được đất nén ít khe rỗng nên giữ nước trên bề mặt lâu hơn đất tơi.";
            string[] gapGroups = groups.Values
                .Where(g => g.evidenceQuality != EvidenceQuality.ControlledComparison)
                .Select(g => g.groupName).ToArray();
            return $"[Kết quả câu hỏi nhanh ví dụ mới] {verifiedCount}/4 nhóm vận dụng đúng. {string.Join(", ", gapGroups)} vẫn giải thích mơ hồ hoặc chưa đối chiếu khe rỗng.";
        }

        private string BuildAdaptiveAdjustFeedback()
        {
            string[] gapGroups = groups.Values
                .Where(g => g.evidenceQuality != EvidenceQuality.ControlledComparison)
                .Select(g => g.groupName).ToArray();
            if (gapGroups.Length == 0)
                return "Bạn chốt lại trên bảng cả 3 yếu tố: khe rỗng bề mặt, sự nén chặt đất và giữ lượng nước thử bằng nhau để củng cố cho cả 4 nhóm.";

            foreach (GroupState g in groups.Values.Where(x => x.evidenceQuality != EvidenceQuality.ControlledComparison))
            {
                if (g.promptGrasp == PromptUnderstanding.Misunderstood) g.promptGrasp = PromptUnderstanding.Uncertain;
                g.worksheetContent += "\n[Ghi chú sau điều chỉnh cuối tiết] Đã ghi thêm quy tắc so sánh cùng lượng nước trên bảng.";
                g.stateRevision++;
            }
            return $"Dựa trên lỗ hổng còn lại ở {string.Join(", ", gapGroups)}, bạn vẽ nhanh bảng đối chiếu 3 mẫu (đất tơi / đất nén / gạch lát) với cùng 3 giọt nước trước khi kết thúc tiết.";
        }

        private void ProcessEvents()
        {
            foreach (TimedEvent entry in Definition.timedEvents)
            {
                if (firedEvents.Contains(entry.id)) continue;
                if (!string.IsNullOrEmpty(entry.stepId) && entry.stepId != StepId) continue;
                if (!string.IsNullOrEmpty(entry.requiresFlag) && !flags.Contains(entry.requiresFlag)) continue;
                if (!string.IsNullOrEmpty(entry.forbidsFlag) && flags.Contains(entry.forbidsFlag)) continue;
                if (entry.forbidsAnyFlags != null && entry.forbidsAnyFlags.Any(f => !string.IsNullOrEmpty(f) && flags.Contains(f))) continue;

                int anchorMinute = StepStartedAt;
                if (!string.IsNullOrEmpty(entry.anchorFlag))
                {
                    if (!flagSetMinute.TryGetValue(entry.anchorFlag, out anchorMinute)) continue;
                }
                if (Minute - anchorMinute < entry.delayMinutes) continue;

                firedEvents.Add(entry.id);
                SetFlag(entry.id);
                if (!string.IsNullOrEmpty(entry.setsFlag)) SetFlag(entry.setsFlag);
                Apply(entry.effects);

                OnTimedEventFired(entry);

                LastFeedback = string.IsNullOrEmpty(LastFeedback) ? entry.feedback : LastFeedback + "\n\n[Phát sinh] " + entry.feedback;
                evidence.Add(new EvidenceEntry
                {
                    actionId = entry.id,
                    text = entry.feedback,
                    minute = Minute,
                    dimension = EvidenceDimension.DecisionMaking,
                    categoryTag = "Phát sinh"
                });
                timeline.Add(new TimelineEntry
                {
                    minute = Minute,
                    phase = Phase,
                    actionId = entry.id,
                    summary = entry.feedback,
                    isEvent = true
                });
            }
        }

        private void OnTimedEventFired(TimedEvent entry)
        {
            if (entry.id == "equipment.failed")
            {
                Resources.projectorBroken = true;
                AddOrUpdateIssue("projector_fault", "Máy chiếu ngừng hoạt động giữa tiết",
                    "Bóng đèn máy chiếu chập chờn từ đầu giờ đã quá nhiệt và tắt hẳn ở lớp 8A.",
                    Minute,
                    (Resources.printedSheets || Resources.boardDiagramDrawn) ? IssueStatus.Resolved : IssueStatus.Active,
                    Resources.printedSheets ? "Có bản in dự phòng nên các nhóm vẫn tiếp tục đối chiếu được." : "Cần dùng bảng phấn hoặc khay mẫu thay thế.");
            }
            else if (entry.id == "group3.finished_early")
            {
                GroupState g3 = groups["group3"];
                if (g3.workStatus == GroupWorkStatus.WorkingOnTrack)
                {
                    g3.workStatus = GroupWorkStatus.FinishedEarly;
                    g3.worksheetContent = "Nhóm 3 (Hà, Trang, Đức) đã vẽ xong sơ đồ khe rỗng và mặt gạch. Hà giơ tay nhẹ báo đã xong phần cơ bản.";
                    g3.stateRevision++;
                    students["ha"].posture = StudentPosture.RaisingHand;
                    students["ha"].observableBehavior = "Hoàn thành phần cơ bản sớm và giơ tay nhẹ chờ nhiệm vụ tiếp.";
                }
            }
            else if (entry.id == "group3.idle_drift")
            {
                GroupState g3 = groups["group3"];
                if (!g3.assignedExtension)
                {
                    g3.workStatus = GroupWorkStatus.IdleDrifting;
                    g3.stateRevision++;
                    students["ha"].posture = StudentPosture.IdleLookingAround;
                    students["ha"].readiness = StudentParticipationState.Idle;
                    students["ha"].observableBehavior = "Trang và Đức bắt đầu nói chuyện riêng; Hà quay nhìn sang các bàn khác vì xong việc khá lâu.";
                    AddOrUpdateIssue("g3_idle", "Nhóm 3 giảm tập trung do chờ lâu sau khi xong sớm",
                        "Nhóm hoàn thành phần cơ bản 8 phút trước nhưng chưa có nhiệm vụ mở rộng hay kiểm tra chéo.",
                        Minute, IssueStatus.Active, "Có thể giao mở rộng mẫu đất nén hoặc phân công kiểm tra chéo theo hướng dẫn.");
                }
            }
        }

        private void CheckPeriodTimeout()
        {
            if (HasTimedOut || Complete || Phase == LessonPhase.Reflection) return;
            if (Minute < Definition.lessonEndMinute) return;

            HasTimedOut = true;
            SetFlag("period.timed_out");
            FinalizeUnfinishedIssues();
            StepId = "reflect";
            StepStartedAt = Minute;

            string timeoutNotice = "[08:15 · Hết giờ tiết học] Tiếng trống báo hết tiết 45 phút vang lên. " +
                "Hệ thống kết thúc phần dạy trên lớp với đúng những gì bạn đã kịp làm và chuyển sang bước Phản tư cùng cô Mai.";
            LastFeedback = string.IsNullOrEmpty(LastFeedback) ? timeoutNotice : LastFeedback + "\n\n" + timeoutNotice;
            timeline.Add(new TimelineEntry
            {
                minute = Minute,
                phase = LessonPhase.Reflection,
                actionId = "period.timed_out",
                summary = "Hết 45 phút tiết học (08:15), chuyển sang phản tư các phần đã làm và còn dang dở.",
                isEvent = true
            });
        }

        private void FinalizeUnfinishedIssues()
        {
            foreach (OngoingIssue issue in issues)
                if (issue.status == IssueStatus.Active || issue.status == IssueStatus.NeedsRecheck)
                    issue.status = IssueStatus.UnresolvedAtEnd;
        }

        private void AddOrUpdateIssue(string id, string title, string cause, int minute, IssueStatus status, string summary)
        {
            OngoingIssue existing = issues.FirstOrDefault(i => i.id == id);
            if (existing != null)
            {
                existing.status = status;
                existing.summary = summary;
                return;
            }
            issues.Add(new OngoingIssue
            {
                id = id,
                title = title,
                cause = cause,
                triggeredAtMinute = minute,
                status = status,
                summary = summary
            });
        }

        private void MarkIssuePendingRecheck(string id, string summary)
        {
            OngoingIssue existing = issues.FirstOrDefault(i => i.id == id);
            if (existing != null) { existing.status = IssueStatus.NeedsRecheck; existing.summary = summary; }
        }

        private void ResolveIssue(string id, string summary)
        {
            OngoingIssue existing = issues.FirstOrDefault(i => i.id == id);
            if (existing != null) { existing.status = IssueStatus.Resolved; existing.summary = summary; }
        }

        private void Apply(Consequence[] effects)
        {
            if (effects == null) return;
            foreach (Consequence effect in effects)
                consequences[effect.kind] = Math.Max(-10, Math.Min(10, Consequence(effect.kind) + effect.amount));
        }

        public IEnumerable<EvidenceEntry> MemoryFor(string npcId) =>
            evidence.Where(e => e.npcId == npcId).Reverse().Take(8);

        public IReadOnlyList<string> ComputeUnfinishedTasks()
        {
            var list = new List<string>();
            if (Phase != LessonPhase.Reflection && Phase != LessonPhase.Complete && HasTimedOut)
                list.Add("Hết 45 phút trước khi chủ động khép lại toàn bộ hoạt động lớp.");
            foreach (GroupState g in groups.Values)
            {
                if (!g.observedByTeacher) list.Add($"Chưa quan sát trực tiếp bài làm của {g.groupName}.");
                if (g.needsRecheck && !g.rechecked) list.Add($"Chưa quay lại kiểm tra {g.groupName} sau khi hỗ trợ.");
                if (g.evidenceQuality != EvidenceQuality.ControlledComparison)
                    list.Add($"{g.groupName} chưa đạt mức đối chiếu có kiểm chứng trên bài làm.");
            }
            if (!HasFlag("assess.sheets_collected") && !HasFlag("assess.probe_done") && !HasFlag("assess.oral_done"))
                list.Add("Chưa thực hiện bước kiểm tra việc học ở cuối tiết.");
            else if (!HasFlag("assess.sheets_collected") && !HasFlag("assess.probe_done") && HasFlag("assess.oral_done"))
                list.Add("Mới thu giải thích miệng cá nhân, chưa kiểm tra sản phẩm viết của toàn bộ 4 nhóm.");
            return list;
        }

        /// <summary>Event-grounded mentor debrief text referencing actual occurrences in this session.</summary>
        public string BuildMentorDebrief()
        {
            var points = new List<string>();
            points.Add(Resources.readPastPapers
                ? "• Cô Mai ghi nhận bạn đã đọc bài tiết trước ở phòng giáo viên trước khi vào lớp 8A."
                : "• Cô Mai gợi ý: lần sau có thể xem nhanh bài tiết trước ở phòng giáo viên để đoán trước chỗ học sinh hay nhầm.");

            if (Resources.projectorBroken)
            {
                points.Add(Resources.printedSheets || Resources.boardDiagramDrawn
                    ? "• Khi máy chiếu tắt giữa tiết, bạn đã có phương án thay thế bằng bản in/bảng phấn mà không để lớp đứng chờ."
                    : "• Máy chiếu tắt giữa tiết khiến phần minh họa bị gián đoạn; chuẩn bị thêm bản in hoặc sơ đồ bảng sẽ giảm rủi ro.");
            }

            GroupState g4 = groups["group4"];
            if (g4.workStatus == GroupWorkStatus.RecheckedVerified)
                points.Add("• Ở Nhóm 4 (Minh), bạn đã tìm hiểu nguyên nhân, hỗ trợ rồi quay lại kiểm tra bằng ví dụ đất nén — bài làm của nhóm đã sửa rõ.");
            else if (g4.needsRecheck)
                points.Add("• Bạn đã hỗ trợ Nhóm 4 (Minh), nhưng chưa quay lại kiểm tra bằng ví dụ mới nên chưa chắc nhóm đã sửa hẳn hiểu nhầm.");
            else
                points.Add("• Nhóm 4 (Minh) vẫn còn lệch hướng về 'chỗ sạch/bẩn' ở cuối tiết; tiết sau cần ưu tiên hỏi rõ nguyên nhân từ đầu.");

            GroupState g1 = groups["group1"];
            if (HasFlag("g1.public_pressured"))
                points.Add("• Với Linh (Nhóm 1), em viết đúng trên giấy nhưng bị ngập ngừng khi gọi đột ngột trước lớp — không nên đánh giá em 'không hiểu bài' chỉ vì em ít nói.");
            else if (g1.observedByTeacher)
                points.Add("• Bạn nhận ra Linh làm đúng trên giấy dù ít giơ tay — đây là quan sát quan trọng để không bỏ sót học sinh trầm lặng.");

            if (HasTimedOut)
                points.Add("• Chuông 08:15 vang lên khi một số việc còn dang dở. Nhìn thẳng vào phần chưa kịp làm giúp bạn điều chỉnh nhịp tiết sau thực tế hơn.");

            points.Add("• Đây là dữ liệu từ một tiết thử nghiệm, không dùng để kết luận bạn phù hợp hay không phù hợp nghề giáo viên.");
            return string.Join("\n", points);
        }

        public string BuildDynamicObjective()
        {
            return Phase switch
            {
                LessonPhase.Briefing => "Sân trường · Gặp cô Mai nhận mục tiêu tiết KHTN Lớp 8A (có thể hỏi thêm & xem vũng nước mẫu).",
                LessonPhase.Preparation => $"Phòng giáo viên ({FormatClock(Minute)}) · Đọc bài cũ, kiểm tra thiết bị và phối hợp học liệu trước khi sang Lớp 8A.",
                LessonPhase.Opening => !(HasFlag("open.clear") || HasFlag("open.rushed"))
                    ? "Lớp 8A (Bảng) · Nêu nhiệm vụ điều tra sân trường đọng nước sau mưa."
                    : !(HasFlag("open.checked") || HasFlag("open.checked_pair"))
                        ? "Lớp 8A (Bảng) · Kiểm tra xem học sinh hiểu yêu cầu hay chưa trước khi làm nhóm."
                        : "Lớp 8A (Bảng) · Khởi động hoạt động 4 nhóm.",
                LessonPhase.Facilitating => HasFlag("equipment.failed") && !Resources.boardDiagramDrawn && !Resources.printedSheets
                    ? $"Lớp 8A ({FormatClock(Minute)}) · Máy chiếu tắt! Chuyển sang bảng/mẫu vật, quan sát 4 nhóm và kiểm tra lại sau hỗ trợ."
                    : $"Lớp 8A ({FormatClock(Minute)}) · Điều hành 4 nhóm ({FacilitateActionsCount} thao tác): xem bài, hỗ trợ, kiểm tra lại, rồi chuyển Kiểm tra việc học.",
                LessonPhase.CheckingLearning => !(HasFlag("assess.sheets_collected") || HasFlag("assess.probe_done") || HasFlag("assess.oral_done"))
                    ? $"Lớp 8A ({FormatClock(Minute)}) · Kiểm tra việc học (thu phiếu 4 nhóm, hỏi miệng, hoặc câu hỏi ngắn)."
                    : "Lớp 8A · Điều chỉnh dựa trên kết quả kiểm tra hoặc kết thúc tiết để gặp cô Mai phản tư.",
                LessonPhase.Reflection => "Phòng giáo viên / Cô Mai · Phản tư tiết dạy: chọn sự kiện làm bằng chứng và ghi nhận điều muốn điều chỉnh.",
                _ => "Đã hoàn thành tiết học Lớp 8A · Nhấn [P] để xem Hồ sơ trải nghiệm nghề giáo viên."
            };
        }

        public string Report()
        {
            var sb = new StringBuilder();
            sb.AppendLine("HỒ SƠ TRẢI NGHIỆM NGHỀ GIÁO VIÊN — PHIÊN THỬ NGHIỆM");
            sb.AppendLine("Bối cảnh: Trường THCS Bình Minh (hư cấu) · Tiết Khoa học tự nhiên Lớp 8A (45 phút)");
            sb.AppendLine($"Thời gian: {FormatClock(Minute)} · Hành động: {ActionMinutesUsed} phút · Di chuyển/quan sát thực: {PassiveMinutesUsed} phút" +
                (HasTimedOut ? " · [ĐÃ HẾT GIỜ 08:15]" : ""));
            sb.AppendLine();

            // A. Bạn đã làm gì?
            sb.AppendLine("A. BẠN ĐÃ LÀM GÌ? [Dữ liệu quan sát]");
            if (timeline.Count == 0)
                sb.AppendLine("• Chưa ghi nhận quyết định nào.");
            else
                foreach (TimelineEntry t in timeline)
                    sb.AppendLine($"• {FormatClock(t.minute)} [{PhaseLabel(t.phase)}] {t.summary}");
            sb.AppendLine();

            // B. Có bằng chứng gì?
            sb.AppendLine("B. CÓ BẰNG CHỨNG GÌ? [Dữ liệu quan sát]");
            var observedNotes = new List<string>();
            if (HasFlag("yard.inspected")) observedNotes.Add("Đã quan sát vũng nước mẫu ngoài sân trường.");
            if (Resources.readPastPapers) observedNotes.Add("Đã đọc bài làm tiết trước ở phòng giáo viên.");
            if (Resources.inspectedGear) observedNotes.Add("Đã kiểm tra tình trạng máy chiếu (đèn chập chờn) và khay mẫu.");
            foreach (GroupState g in groups.Values.Where(x => x.observedByTeacher))
                observedNotes.Add($"Đã đọc bài {g.groupName} ({g.members[0]}): {g.worksheetContent}");
            sb.AppendLine("1) Thông tin đã quan sát:");
            sb.AppendLine(observedNotes.Count == 0 ? "• Chưa thu thập quan sát thực địa hay bài làm nhóm nào." : string.Join("\n", observedNotes.Select(n => "• " + n)));

            sb.AppendLine("2) Hành động đã thực hiện & việc còn dang dở:");
            sb.AppendLine($"• Tổng số sự kiện/hành động ghi nhận: {evidence.Count}.");
            IReadOnlyList<string> unfinished = ComputeUnfinishedTasks();
            sb.AppendLine(unfinished.Count == 0
                ? "• Không ghi nhận hạng mục lớn bị bỏ dở."
                : "• Còn dang dở: " + string.Join("; ", unfinished));

            sb.AppendLine("3) Phản ứng thực tế của 4 nhóm lớp 8A:");
            foreach (GroupState g in groups.Values)
                sb.AppendLine($"• {g.groupName} ({string.Join(", ", g.members)}): Trạng thái={StatusLabel(g.workStatus)} | Mức chứng cứ={QualityLabel(g.evidenceQuality)} | Ghi nhận: {g.worksheetContent}");

            sb.AppendLine("4) Kết quả kiểm tra lại & kiểm tra cuối tiết:");
            sb.AppendLine($"• Nhóm 4 (Minh): {(groups["group4"].rechecked ? StatusLabel(groups["group4"].workStatus) : "Chưa quay lại kiểm tra bằng ví dụ mới.")}");
            sb.AppendLine($"• Nhóm 2 (Nam): {(groups["group2"].rechecked ? StatusLabel(groups["group2"].workStatus) : "Chưa kiểm tra lại lập luận có đối chứng.")}");
            if (HasFlag("assess.sheets_collected")) sb.AppendLine("• Đã thu phiếu viết của cả 4 nhóm cuối tiết.");
            if (HasFlag("assess.probe_done")) sb.AppendLine("• Đã kiểm tra câu hỏi ví dụ mới cuối tiết.");
            if (HasFlag("assess.oral_done") && !HasFlag("assess.sheets_collected") && !HasFlag("assess.probe_done"))
                sb.AppendLine("• Chỉ gọi giải thích miệng cá nhân — KHÔNG đủ cơ sở kết luận cả lớp đều hiểu.");
            if (Resources.boardAdjusted) sb.AppendLine("• Đã thực hiện điều chỉnh giảng dạy dựa trên lỗ hổng phát hiện khi kiểm tra.");
            sb.AppendLine();

            // C. Những xu hướng được ghi nhận
            sb.AppendLine("C. NHỮNG XU HƯỚNG ĐƯỢC GHI NHẬN [Suy luận hệ thống — giới hạn trong phiên thử nghiệm]");
            sb.AppendLine($"• Tìm hiểu trước khi can thiệp: {(Resources.readPastPapers || HasFlag("g4.cause_checked") || groups.Values.Count(g => g.observedByTeacher) >= 2 ? "Có xu hướng đọc bài/hỏi làm rõ trước khi chốt cách xử lý." : "Thiên về hành động ngay trước khi đọc đủ dấu hiệu ban đầu.")}");
            sb.AppendLine($"• Điều chỉnh hướng dẫn: {(Resources.boardAdjusted || HasFlag("g4.scaffolded") || HasFlag("g4.demoed") || (Resources.projectorBroken && (Resources.printedSheets || Resources.boardDiagramDrawn)) ? "Có điều chỉnh phương án khi gặp trục trặc thiết bị hoặc hiểu nhầm của học sinh." : "Chưa thấy nhiều bước điều chỉnh theo phản hồi thực tế của lớp.")}");
            sb.AppendLine($"• Phân bổ sự chú ý: Đã trực tiếp quan sát {groups.Values.Count(g => g.observedByTeacher)}/4 nhóm và hỗ trợ {groups.Values.Count(g => g.supportedByTeacher)}/4 nhóm.");
            sb.AppendLine($"• Kiểm tra việc học: {(groups["group4"].rechecked && HasFlag("assess.sheets_collected") ? "Có bước kiểm tra lại sau hỗ trợ và đối chiếu sản phẩm toàn lớp." : "Bước kiểm tra lại sau hỗ trợ hoặc đối chiếu đủ 4 nhóm còn khuyết.")}");
            sb.AppendLine($"• Nhận ra giới hạn thông tin: {(HasFlag("assess.oral_done") && !HasFlag("assess.sheets_collected") ? "Đã nghe trình bày miệng nhưng cần dè dặt vì chưa có dữ liệu 3 nhóm còn lại." : HasFlag("assess.sheets_collected") ? "Đã thu phiếu nhiều nhóm để tránh kết luận từ một cá nhân." : "Chưa đối chiếu đủ dữ liệu đa nguồn cuối tiết.")}");
            sb.AppendLine();

            // D. Cách giải thích khác
            sb.AppendLine("D. CÁCH GIẢI THÍCH KHÁC [Suy luận hệ thống — không quy chụp tính cách]");
            var altExplanations = new List<string>();
            if (groups.Values.Any(g => !g.observedByTeacher || !g.supportedByTeacher))
                altExplanations.Add("Việc chưa kịp đến một nhóm có thể do quỹ 45 phút hạn hẹp, do bạn đang ưu tiên xử lý hiểu nhầm ở nhóm khác, hoặc do tín hiệu ở bàn đó chưa rõ — không tự suy ra bạn 'thiếu thấu cảm'.");
            if (HasFlag("g1.public_pressured"))
                altExplanations.Add("Linh ngập ngừng khi trả lời trước lớp phản ánh cảm giác an toàn tại thời điểm đó, không chứng minh Linh kém hay không hiểu bài.");
            if (altExplanations.Count == 0)
                altExplanations.Add("Nhịp điều hành tương đối đều; tuy vậy một nhóm vẫn có thể thay đổi biểu hiện nếu đề bài hoặc sĩ số lớp thực tế lớn hơn.");
            foreach (string alt in altExplanations) sb.AppendLine("• " + alt);
            sb.AppendLine();

            // E. Tự phản tư
            sb.AppendLine("E. TỰ PHẢN TƯ [Tự báo cáo của người chơi]");
            sb.AppendLine($"• Phần thích / chưa thích: {(string.IsNullOrWhiteSpace(Reflection.liked) ? "(Chưa ghi)" : Reflection.liked)} | Không thích: {(string.IsNullOrWhiteSpace(Reflection.disliked) ? "(Chưa ghi)" : Reflection.disliked)}");
            sb.AppendLine($"• Điều thấy khó: {(string.IsNullOrWhiteSpace(Reflection.difficulty) ? "(Chưa ghi)" : Reflection.difficulty)}");
            sb.AppendLine($"• Muốn giữ lại / thay đổi / thử ở tiết sau: Giữ [{Reflection.keep}] · Đổi [{Reflection.change}] · Thử tiếp [{Reflection.testNext}]");
            if (!string.IsNullOrWhiteSpace(Reflection.freeNote)) sb.AppendLine("• Ghi chú thêm: " + Reflection.freeNote);
            if (Reflection.selectedEvidenceIds != null && Reflection.selectedEvidenceIds.Count > 0)
            {
                string[] labels = Reflection.selectedEvidenceIds
                    .Select(id => evidence.FirstOrDefault(e => e.actionId == id)?.text ?? id).ToArray();
                sb.AppendLine("• Bằng chứng đã chọn để phản tư: " + string.Join(" | ", labels));
            }
            else
                sb.AppendLine("• Chưa chọn sự kiện cụ thể làm bằng chứng phản tư.");
            sb.AppendLine();

            // F. Cần trải nghiệm thêm
            sb.AppendLine("F. CẦN TRẢI NGHIỆM THÊM [Định hướng lượt thử tiếp theo]");
            if (unfinished.Count > 0)
                foreach (string item in unfinished) sb.AppendLine("• " + item);
            if (!Resources.readPastPapers) sb.AppendLine("• Thử đọc bài tiết trước ở phòng giáo viên trước khi chọn học liệu.");
            if (!HasFlag("prep.group_cards")) sb.AppendLine("• Thử phối hợp bộ thẻ & khay mẫu để quan sát phản ứng của học sinh ít nói và học sinh hiểu nhầm.");
            sb.AppendLine("• Ghi chú giới hạn: Nhận xét trên chỉ áp dụng cho phiên thử nghiệm đơn lẻ này; kịch bản là tình huống thiết kế và chưa qua thẩm định sư phạm/tâm lý chính thức.");

            return sb.ToString();
        }

        public static string FormatClock(int minute) => $"{minute / 60:00}:{minute % 60:00}";

        private static string PhaseLabel(LessonPhase phase) => phase switch
        {
            LessonPhase.Briefing => "Nhận nhiệm vụ",
            LessonPhase.Preparation => "Chuẩn bị",
            LessonPhase.Opening => "Mở đầu",
            LessonPhase.Facilitating => "Điều hành",
            LessonPhase.CheckingLearning => "Kiểm tra",
            LessonPhase.Reflection => "Phản tư",
            _ => "Hoàn tất"
        };

        private static string StatusLabel(GroupWorkStatus status) => status switch
        {
            GroupWorkStatus.WaitingPrompt => "Chờ đề bài",
            GroupWorkStatus.WorkingMisaligned => "Đang hiểu sai hướng",
            GroupWorkStatus.WorkingOnTrack => "Đang làm bài",
            GroupWorkStatus.FinishedEarly => "Xong phần cơ bản sớm",
            GroupWorkStatus.IdleDrifting => "Chờ lâu · giảm tập trung",
            GroupWorkStatus.CrossChecking => "Đang kiểm tra chéo",
            GroupWorkStatus.Extended => "Đang làm nhiệm vụ mở rộng",
            GroupWorkStatus.PendingRecheck => "Chờ kiểm tra lại",
            GroupWorkStatus.RecheckedVerified => "Kiểm tra lại đạt",
            GroupWorkStatus.RecheckedStillGaps => "Kiểm tra lại còn hổng",
            _ => status.ToString()
        };

        private static string QualityLabel(EvidenceQuality q) => q switch
        {
            EvidenceQuality.None => "Chưa đúng hướng",
            EvidenceQuality.SurfaceClaim => "Nhận định nhanh · thiếu đối chứng",
            EvidenceQuality.VisualOnly => "Hình vẽ tốt · thiếu diễn đạt lời",
            EvidenceQuality.ControlledComparison => "So sánh có kiểm chứng mẫu",
            _ => q.ToString()
        };
    }
}
