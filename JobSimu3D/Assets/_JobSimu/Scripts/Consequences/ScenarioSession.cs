using System;
using System.Collections.Generic;
using System.Linq;
using JobSimu.Quests;

namespace JobSimu.Consequences
{
    [Serializable] public sealed class SessionRecord { public string actionId; public int minutes; }
    public sealed class EvidenceEntry
    {
        public string actionId, text, npcId;
        public int minute;
        public EvidenceDimension dimension;
    }

    /// <summary>Deterministic reducer. Every saved consequence is reconstructed from validated actions.</summary>
    public sealed class ScenarioSession
    {
        public ScenarioDefinition Definition { get; }
        public string StepId { get; private set; }
        public int Minute { get; private set; } = 405;
        public int StepStartedAt { get; private set; } = 405;
        public string LastFeedback { get; private set; } = "";
        public bool Complete => Definition.steps.Single(s => s.id == StepId).terminal;
        public string Objective => Definition.steps.Single(s => s.id == StepId).objective;
        public IReadOnlyList<SessionRecord> Records => records;
        public IReadOnlyList<EvidenceEntry> Evidence => evidence;
        public IReadOnlyCollection<string> Flags => flags;
        private readonly List<SessionRecord> records = new List<SessionRecord>();
        private readonly List<EvidenceEntry> evidence = new List<EvidenceEntry>();
        private readonly HashSet<string> flags = new HashSet<string>();
        private readonly HashSet<string> firedEvents = new HashSet<string>();
        private readonly Dictionary<ConsequenceKind, int> consequences = new Dictionary<ConsequenceKind, int>();
        public event Action Changed;

        public ScenarioSession(ScenarioDefinition definition)
        {
            if (definition == null) throw new ArgumentNullException(nameof(definition));
            List<string> errors = definition.ValidateDefinition();
            if (errors.Count != 0) throw new ArgumentException(string.Join("; ", errors));
            Definition = definition; StepId = definition.initialStepId;
        }
        public int Consequence(ConsequenceKind kind) => consequences.TryGetValue(kind, out int value) ? value : 0;
        public bool HasFlag(string flag) => flags.Contains(flag);
        public IEnumerable<ScenarioAction> Available(string stationId) => Definition.actions.Where(a => a.stationId == stationId && CanPerform(a));
        private bool CanPerform(ScenarioAction action) => !Complete && action.stepId == StepId &&
            (string.IsNullOrEmpty(action.requiresFlag) || flags.Contains(action.requiresFlag)) &&
            (string.IsNullOrEmpty(action.forbidsFlag) || !flags.Contains(action.forbidsFlag));

        public bool TryPerform(string actionId)
        {
            ScenarioAction action = Definition.actions.FirstOrDefault(a => a.id == actionId);
            if (action == null || !CanPerform(action) || records.Count >= 1024) return false;
            records.Add(new SessionRecord { actionId = action.id });
            Minute = Math.Min(1005, Math.Max(Minute, action.earliestMinute) + action.minutes);
            Apply(action.effects);
            if (!string.IsNullOrEmpty(action.setsFlag)) flags.Add(action.setsFlag);
            LastFeedback = action.feedback;
            foreach (ConditionalEffect conditional in action.conditionalEffects)
                if (flags.Contains(conditional.requiresFlag)) { Apply(conditional.effects); LastFeedback += "\n" + conditional.feedback; }
            evidence.Add(new EvidenceEntry { actionId = action.id, text = action.evidence, dimension = action.dimension, minute = Minute, npcId = action.npcId });
            StepId = action.nextStepId; StepStartedAt = Minute;
            ProcessEvents(); Changed?.Invoke(); return true;
        }
        public bool AdvanceMinutes(int minutes)
        {
            if (minutes < 1 || minutes > 60 || Complete || Minute >= 1005 || records.Count >= 1024) return false;
            records.Add(new SessionRecord { actionId = "@clock", minutes = minutes });
            Minute = Math.Min(1005, Minute + minutes);
            ProcessEvents(); Changed?.Invoke(); return true;
        }
        private void ProcessEvents()
        {
            foreach (TimedEvent entry in Definition.timedEvents)
                if (entry.stepId == StepId && Minute - StepStartedAt >= entry.delayMinutes && !firedEvents.Contains(entry.id) &&
                    (string.IsNullOrEmpty(entry.requiresFlag) || flags.Contains(entry.requiresFlag)))
                {
                    firedEvents.Add(entry.id); flags.Add(entry.id); Apply(entry.effects);
                    LastFeedback = entry.feedback;
                    evidence.Add(new EvidenceEntry { actionId = entry.id, text = entry.feedback, minute = Minute, dimension = EvidenceDimension.DecisionMaking });
                }
        }
        private void Apply(Consequence[] effects)
        {
            foreach (Consequence effect in effects)
                consequences[effect.kind] = Math.Max(-10, Math.Min(10, Consequence(effect.kind) + effect.amount));
        }
        public IEnumerable<EvidenceEntry> MemoryFor(string npcId) => evidence.Where(e => e.npcId == npcId).Reverse().Take(8);
        public string Report()
        {
            var lines = new List<string>();
            foreach (EvidenceDimension dimension in Enum.GetValues(typeof(EvidenceDimension)))
            {
                string name = new[] { "Phân tích", "Sáng tạo", "Thấu cảm", "Ra quyết định", "Chuẩn xác" }[(int)dimension];
                EvidenceEntry[] entries = evidence.Where(e => e.dimension == dimension).ToArray();
                lines.Add(name + ": " + (entries.Length == 0 ? "Chưa đủ bằng chứng." : string.Join(" ", entries.Select(e => e.text))));
            }
            lines.Add(Consequence(ConsequenceKind.Understanding) >= 3 ?
                "Đã thu được thêm bằng chứng về mức hiểu nhiệm vụ. Cần kiểm tra lại trong tình huống tiếp theo." :
                "Bằng chứng hiểu nhiệm vụ còn hạn chế; cần tìm hiểu thêm trước khi kết luận.");
            lines.Add(Consequence(ConsequenceKind.TimePressure) >= 2 ?
                "Đã phát sinh áp lực thời gian; cân nhắc chuẩn bị phương án dự phòng." :
                "Chưa ghi nhận áp lực thời gian lớn trong lượt này.");
            lines.Add("Đây là bằng chứng trong mô phỏng, chưa đủ để kết luận bạn phù hợp hay không phù hợp nghề.");
            return string.Join("\n\n", lines);
        }
    }
}
