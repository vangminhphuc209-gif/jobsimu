using System.Linq;
using NUnit.Framework;
using UnityEditor;
using JobSimu.Quests;
using JobSimu.Consequences;
using JobSimu.SaveSystem;
using JobSimu.Editor;

namespace JobSimu.Tests
{
    public sealed class SharedFrameworkTests
    {
        private ScenarioDefinition definition;
        [SetUp] public void Setup() => definition = AssetDatabase.LoadAssetAtPath<ScenarioDefinition>(TeacherScenarioAssets.ScenarioPath);
        private ScenarioSession Run(string preparation)
        {
            var session = new ScenarioSession(definition);
            foreach (string id in new[] { "brief.accept", preparation, "lesson.begin", "teach.compare", "assess.tickets", "reflect.finish" })
                Assert.That(session.TryPerform(id), Is.True, id);
            return session;
        }
        [Test] public void ContentIsValidAndProvidesPhysicalDialogueAndEvidenceApproaches()
        {
            Assert.That(definition.ValidateDefinition(), Is.Empty);
            Assert.That(definition.actions.Select(a => a.kind).Distinct().Count(), Is.EqualTo(3));
        }
        [Test] public void UnknownDuplicateAndOutOfOrderActionsDoNotChangeState()
        {
            var session = new ScenarioSession(definition);
            Assert.That(session.TryPerform("reflect.finish"), Is.False);
            Assert.That(session.TryPerform("invented-action"), Is.False);
            Assert.That(session.Records, Is.Empty);
            Assert.That(session.TryPerform("brief.accept"), Is.True);
            Assert.That(session.TryPerform("brief.accept"), Is.False);
            Assert.That(session.Evidence.Count, Is.EqualTo(1));
        }
        [Test] public void ThreePreparationsProduceDifferentConsequencesAndReports()
        {
            ScenarioSession[] runs = new[] { "prep.print", "prep.projector", "prep.group" }.Select(Run).ToArray();
            Assert.That(runs.All(r => r.Complete), Is.True);
            Assert.That(runs.Select(r => r.Report()).Distinct().Count(), Is.EqualTo(3));
            Assert.That(runs.Select(r => string.Join(",", System.Enum.GetValues(typeof(ConsequenceKind)).Cast<ConsequenceKind>().Select(r.Consequence))).Distinct().Count(), Is.EqualTo(3));
        }
        [Test] public void SaveRoundTripReconstructsConsequencesAndReflection()
        {
            var original = Run("prep.group");
            Assert.That(SaveCodec.TryDecode(SaveCodec.Encode(original, "Lần sau em sẽ kiểm tra từng nhóm."), definition, out var restored, out string reflection, out _), Is.True);
            Assert.That(restored.Report(), Is.EqualTo(original.Report()));
            Assert.That(restored.Flags, Is.EquivalentTo(original.Flags));
            Assert.That(reflection, Does.Contain("từng nhóm"));
        }
        [TestCase("{\"schemaVersion\":99}")]
        [TestCase("{\"schemaVersion\":1,\"scenarioId\":\"teacher.first-lesson\",\"contentVersion\":\"1\",\"records\":[{\"actionId\":\"reflect.finish\"}]}")]
        [TestCase("{\"schemaVersion\":1,\"scenarioId\":\"teacher.first-lesson\",\"contentVersion\":\"1\",\"records\":[{\"actionId\":\"@clock\",\"minutes\":-3}]}")]
        [TestCase("{broken")]
        public void InvalidSavesAreRejected(string json) => Assert.That(SaveCodec.TryDecode(json, definition, out _, out _, out _), Is.False);
        [Test] public void DelayedEventFiresOnceAndReplaysIdentically()
        {
            var session = new ScenarioSession(definition);
            session.TryPerform("brief.accept"); session.TryPerform("prep.projector"); session.TryPerform("lesson.begin");
            session.AdvanceMinutes(2); session.AdvanceMinutes(2);
            Assert.That(session.Evidence.Count(e => e.actionId == "equipment.waited"), Is.EqualTo(1));
            Assert.That(SaveCodec.TryDecode(SaveCodec.Encode(session, ""), definition, out var restored, out _, out _), Is.True);
            Assert.That(restored.Report(), Is.EqualTo(session.Report()));
            Assert.That(restored.Consequence(ConsequenceKind.TimePressure), Is.EqualTo(session.Consequence(ConsequenceKind.TimePressure)));
        }
        [Test] public void EveryTeachingAndAssessmentApproachCanFinish()
        {
            foreach (string teach in new[] { "teach.scaffold", "teach.compare", "teach.explain" })
                foreach (string assess in new[] { "assess.tickets", "assess.oral" })
                {
                    var session = new ScenarioSession(definition);
                    foreach (string id in new[] { "brief.accept", "prep.projector", "lesson.begin", teach, assess, "reflect.finish" })
                        Assert.That(session.TryPerform(id), Is.True, id);
                    Assert.That(session.Complete, Is.True);
                }
        }
    }
}
