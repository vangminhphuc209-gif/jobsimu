using System.Collections.Generic;
using System.Linq;
using NUnit.Framework;
using UnityEditor;
using UnityEngine;
using JobSimu.Quests;
using JobSimu.Consequences;
using JobSimu.SaveSystem;
using JobSimu.Editor;

namespace JobSimu.Tests
{
    /// <summary>Deterministic reducer and data contract tests (EditMode).</summary>
    public sealed class SharedFrameworkTests
    {
        private ScenarioDefinition definition;

        [SetUp]
        public void Setup()
        {
            definition = TeacherScenarioAssets.GetOrCreate(forceRebuild: true);
        }

        [Test]
        public void ScenarioDefinitionIsValidAndCoversAllSixPhasesAndThreeKinds()
        {
            Assert.That(definition.ValidateDefinition(), Is.Empty);
            Assert.That(definition.contentVersion, Is.EqualTo("2"));
            Assert.That(definition.participants.Length, Is.EqualTo(13));
            Assert.That(definition.actions.Select(a => a.kind).Distinct().Count(), Is.EqualTo(3));
            foreach (LessonPhase p in new[]
            {
                LessonPhase.Briefing, LessonPhase.Preparation, LessonPhase.Opening,
                LessonPhase.Facilitating, LessonPhase.CheckingLearning, LessonPhase.Reflection, LessonPhase.Complete
            })
                Assert.That(definition.steps.Any(s => s.phase == p), Is.True, "Missing phase " + p);
        }

        [Test]
        public void ContextIsStrictlyThcsBinhMinhClass8A()
        {
            Assert.That(definition.title, Does.Contain("8A"));
            Assert.That(definition.context, Does.Contain("THCS Bình Minh"));
            Assert.That(definition.context, Does.Not.Contain("THPT"));
            Assert.That(definition.context, Does.Not.Contain("10A1"));
            Assert.That(definition.context, Does.Not.Contain("9A"));
        }

        [Test]
        public void HelpingLinhDoesNotLockOutNamBoardOrOtherGroups()
        {
            var s = new ScenarioSession(definition);
            Assert.That(s.TryPerform("brief.accept"), Is.True);
            Assert.That(s.TryPerform("prep.print"), Is.True);
            Assert.That(s.TryPerform("prep.group_cards"), Is.True); // Combining prep methods is allowed!
            Assert.That(s.TryPerform("prep.enter_class"), Is.True);
            Assert.That(s.TryPerform("open.clear_task"), Is.True);
            Assert.That(s.TryPerform("open.check_pair_prompt"), Is.True);
            Assert.That(s.TryPerform("open.start_group_work"), Is.True);

            Assert.That(s.Phase, Is.EqualTo(LessonPhase.Facilitating));
            Assert.That(s.TryPerform("g1.ask_private"), Is.True);
            Assert.That(s.Phase, Is.EqualTo(LessonPhase.Facilitating), "Must NOT auto-transition after 1 teach action.");

            // Still free to help Nam, use board, and help Minh!
            Assert.That(s.TryPerform("g2.ask_reasoning"), Is.True);
            Assert.That(s.TryPerform("teach.board_diagram"), Is.True);
            Assert.That(s.TryPerform("g4.scaffold_steps"), Is.True);
        }

        [Test]
        public void UsingBoardBeforeObservingGroupsHasNoContradictoryFeedback()
        {
            var s = new ScenarioSession(definition);
            PerformToTeach(s, "prep.print");
            Assert.That(s.TryPerform("teach.board_diagram"), Is.True);
            Assert.That(s.LastFeedback, Does.Not.Contain("Minh").And.Not.Contain("đã thấy lỗi"));
            Assert.That(s.GetGroup("group4").observedByTeacher, Is.False);
        }

        [Test]
        public void IneffectiveSupportAllowsRetryAndRequiresRecheckWithNewExample()
        {
            var s = new ScenarioSession(definition);
            PerformToTeach(s, "prep.group_cards");

            // Just repeating original prompt does NOT fix Group 4!
            Assert.That(s.TryPerform("g4.repeat_prompt"), Is.True);
            Assert.That(s.GetGroup("group4").promptGrasp, Is.EqualTo(PromptUnderstanding.Misunderstood));
            Assert.That(s.TryPerform("g4.recheck_new_example"), Is.False, "Cannot recheck before real support.");

            // Retry with scaffolding, then verify with new example
            Assert.That(s.TryPerform("g4.scaffold_steps"), Is.True);
            Assert.That(s.GetGroup("group4").needsRecheck, Is.True);
            Assert.That(s.GetGroup("group4").workStatus, Is.EqualTo(GroupWorkStatus.PendingRecheck));

            Assert.That(s.TryPerform("g4.recheck_new_example"), Is.True);
            Assert.That(s.GetGroup("group4").workStatus, Is.EqualTo(GroupWorkStatus.RecheckedVerified));
            Assert.That(s.GetGroup("group4").needsRecheck, Is.False);
        }

        [Test]
        public void SilentStudentWithAccurateSheetIsNotMarkedAsNotUnderstanding()
        {
            var s = new ScenarioSession(definition);
            PerformToTeach(s, "prep.print");
            Assert.That(s.TryPerform("g1.inspect_sheet"), Is.True);
            Assert.That(s.TryPerform("g1.callout_public"), Is.True);

            Assert.That(s.GetStudent("linh").readiness, Is.EqualTo(StudentParticipationState.HesitantAfterPressure));
            Assert.That(s.GetGroup("group1").evidenceQuality, Is.EqualTo(EvidenceQuality.ControlledComparison));

            s.TryPerform("teach.board_diagram");
            s.TryPerform("teach.to_check");
            s.TryPerform("assess.oral_linh");
            Assert.That(s.LastFeedback, Does.Contain("chưa thể suy ra mức hiểu của cả lớp"));
            s.TryPerform("assess.end_lesson");
            s.TryPerform("reflect.finish");

            string report = s.Report();
            Assert.That(report, Does.Contain("cảm giác an toàn"));
            Assert.That(s.BuildMentorDebrief(), Does.Contain("không nên đánh giá em 'không hiểu bài'"));
        }

        [Test]
        public void SingleStudentOralCheckDoesNotClaimWholeClassUnderstood()
        {
            var s = new ScenarioSession(definition);
            PerformToTeach(s, "prep.projector");
            s.TryPerform("teach.board_diagram");
            s.TryPerform("g2.accept_claim");
            s.TryPerform("teach.to_check");
            Assert.That(s.TryPerform("assess.oral_nam"), Is.True);
            Assert.That(s.LastFeedback, Does.Contain("không chứng minh cả lớp đã hiểu"));
            Assert.That(s.ComputeUnfinishedTasks().Any(t => t.Contains("4 nhóm")), Is.True);
        }

        [Test]
        public void InteractionSpamDoesNotInflateCompetencyEvidence()
        {
            var s = new ScenarioSession(definition);
            PerformToTeach(s, "prep.print");
            Assert.That(s.TryPerform("g1.inspect_sheet"), Is.True);
            Assert.That(s.Evidence.Count(e => e.actionId == "g1.inspect_sheet"), Is.EqualTo(1));

            // Repeating sheet inspection when group state hasn't changed adds 0 evidence!
            Assert.That(s.TryPerform("g1.inspect_sheet"), Is.True);
            Assert.That(s.TryPerform("g1.inspect_sheet"), Is.True);
            Assert.That(s.Evidence.Count(e => e.actionId == "g1.inspect_sheet"), Is.EqualTo(1));

            // One-time actions cannot be spammed
            Assert.That(s.TryPerform("teach.board_diagram"), Is.True);
            Assert.That(s.Evidence.Count(e => e.actionId == "teach.board_diagram"), Is.EqualTo(1));
            Assert.That(s.TryPerform("teach.board_diagram"), Is.False);
            Assert.That(s.Evidence.Count(e => e.actionId == "teach.board_diagram"), Is.EqualTo(1));
        }

        [Test]
        public void TimeExpirationEndsHonestlyWithUnfinishedItemsAndNoSoftlock()
        {
            var s = new ScenarioSession(definition);
            PerformToTeach(s, "prep.print");
            // Advance passive time past 08:15 (495)
            s.AdvancePassiveMinutes(45);
            Assert.That(s.HasTimedOut, Is.True);
            Assert.That(s.Phase, Is.EqualTo(LessonPhase.Reflection));
            Assert.That(s.Minute, Is.EqualTo(495));

            // Still finishes cleanly through Reflection into honest Report
            Assert.That(s.TryPerform("reflect.finish"), Is.True);
            Assert.That(s.Complete, Is.True);
            string report = s.Report();
            Assert.That(report, Does.Contain("ĐÃ HẾT GIỜ 08:15"));
            Assert.That(report, Does.Contain("Còn dang dở"));
            Assert.That(report, Does.Not.Contain("Bạn phù hợp nghề giáo viên"));
        }

        [Test]
        public void EarlyFinishGroupDoesNotPenalizeImmediatelyWhileHelpingOthers()
        {
            var s = new ScenarioSession(definition);
            PerformToTeach(s, "prep.group_cards");
            s.TryPerform("g4.clarify_cause"); // 3 min -> 437 -> 457
            s.TryPerform("g4.scaffold_steps"); // +5 min -> 462 (5+ min in teach -> group3.finished_early fires, NOT idle_drift yet!)
            Assert.That(s.HasFlag("g3.finished_early"), Is.True);
            Assert.That(s.HasFlag("group3.idle_drift"), Is.False);

            // Helping Group 3 within window resolves early idle cleanly
            Assert.That(s.TryPerform("g3.assign_extension"), Is.True);
            s.AdvancePassiveMinutes(10);
            Assert.That(s.HasFlag("group3.idle_drift"), Is.False);
        }

        [Test]
        public void DelayedProjectorAndIdleEventsFireOnceAndReplayIdentically()
        {
            var s = new ScenarioSession(definition);
            PerformToTeach(s, "prep.projector");
            s.AdvancePassiveMinutes(6); // fires equipment.failed + group3.finished_early
            Assert.That(s.Resources.projectorBroken, Is.True);
            Assert.That(s.Evidence.Count(e => e.actionId == "equipment.failed"), Is.EqualTo(1));

            s.AdvancePassiveMinutes(8); // fires group3.idle_drift
            Assert.That(s.GetGroup("group3").workStatus, Is.EqualTo(GroupWorkStatus.IdleDrifting));

            s.SetReflection(new ReflectionInput
            {
                liked = "Quan sát bài từng bàn",
                keep = "Kiểm tra lại bằng ví dụ mới",
                selectedEvidenceIds = new List<string> { "equipment.failed" }
            });

            string json = SaveCodec.Encode(s, 9.5f);
            Assert.That(SaveCodec.TryDecode(json, definition, out ScenarioSession restored, out float acc, out string err), Is.True, err);
            Assert.That(acc, Is.EqualTo(9.5f).Within(0.01f));
            Assert.That(restored.Report(), Is.EqualTo(s.Report()));
            Assert.That(restored.Flags, Is.EquivalentTo(s.Flags));
            Assert.That(restored.GetGroup("group3").workStatus, Is.EqualTo(GroupWorkStatus.IdleDrifting));
        }

        [Test]
        public void CorruptSaveIsPreservedAndNotOverwrittenByEmptyAutosave()
        {
            string key = "jobsimu.unity.v2." + definition.id;
            string backupKey = key + ".backup";
            string corruptKey = key + ".corrupt";
            string prevMain = PlayerPrefs.GetString(key, "");
            string prevBackup = PlayerPrefs.GetString(backupKey, "");
            string prevCorrupt = PlayerPrefs.GetString(corruptKey, "");

            try
            {
                PlayerPrefs.DeleteKey(backupKey);
                PlayerPrefs.DeleteKey(corruptKey);
                PlayerPrefs.SetString(key, "{corrupt_save_data_payload");
                PlayerPrefs.Save();

                var store = new LocalSaveStore(definition.id);
                var emptySession = new ScenarioSession(definition);

                // Autosave with empty/unstarted session MUST NOT erase corrupt primary save!
                Assert.That(store.Write(emptySession, 0f, out _, allowOverwriteWithEmpty: false), Is.False);
                Assert.That(PlayerPrefs.GetString(key), Is.EqualTo("{corrupt_save_data_payload"));
                Assert.That(PlayerPrefs.GetString(corruptKey), Is.EqualTo("{corrupt_save_data_payload"));

                // Failed Read also preserves corrupt payload
                Assert.That(store.Read(definition, out _, out _, out string msg), Is.False);
                Assert.That(msg, Does.Contain("giữ lại"));
                Assert.That(PlayerPrefs.GetString(corruptKey), Is.EqualTo("{corrupt_save_data_payload"));
            }
            finally
            {
                RestorePref(key, prevMain);
                RestorePref(backupKey, prevBackup);
                RestorePref(corruptKey, prevCorrupt);
                PlayerPrefs.Save();
            }
        }

        [Test]
        public void ThreeDistinctPlaythroughRunsDivergeAcrossTimeArtifactsUnfinishedAndReport()
        {
            ScenarioSession run1Lecture = PlayRun1LectureAndBoard();
            ScenarioSession run2GroupSupport = PlayRun2QuestionAndGroupSupport();
            ScenarioSession run3CombinedAdaptive = PlayRun3CombinedAndAdaptive();

            ScenarioSession[] runs = { run1Lecture, run2GroupSupport, run3CombinedAdaptive };
            Assert.That(runs.All(r => r.Complete), Is.True);

            // Distinct time usage
            Assert.That(runs.Select(r => r.ActionMinutesUsed).Distinct().Count(), Is.EqualTo(3));

            // Distinct student/group outcomes
            Assert.That(run1Lecture.GetGroup("group4").workStatus, Is.EqualTo(GroupWorkStatus.WorkingMisaligned));
            Assert.That(run2GroupSupport.GetGroup("group4").workStatus, Is.EqualTo(GroupWorkStatus.RecheckedVerified));
            Assert.That(run3CombinedAdaptive.GetGroup("group3").workStatus, Is.EqualTo(GroupWorkStatus.CrossChecking));

            Assert.That(run1Lecture.GetGroup("group2").evidenceQuality, Is.EqualTo(EvidenceQuality.SurfaceClaim));
            Assert.That(run2GroupSupport.GetGroup("group2").evidenceQuality, Is.EqualTo(EvidenceQuality.ControlledComparison));

            // Distinct unfinished items & full reports
            Assert.That(runs.Select(r => string.Join("|", r.ComputeUnfinishedTasks())).Distinct().Count(), Is.EqualTo(3));
            Assert.That(runs.Select(r => r.Report()).Distinct().Count(), Is.EqualTo(3));

            // None concludes single suitability percentage
            foreach (ScenarioSession r in runs)
            {
                string rep = r.Report();
                Assert.That(rep, Does.Contain("HỒ SƠ TRẢI NGHIỆM NGHỀ GIÁO VIÊN — PHIÊN THỬ NGHIỆM"));
                Assert.That(rep, Does.Not.Contain("phù hợp nghề giáo viên"));
            }
        }

        private ScenarioSession PlayRun1LectureAndBoard()
        {
            var s = new ScenarioSession(definition);
            Assert.That(s.TryPerform("brief.accept"), Is.True);
            Assert.That(s.TryPerform("prep.projector"), Is.True);
            Assert.That(s.TryPerform("prep.enter_class"), Is.True);
            Assert.That(s.TryPerform("open.quick_task"), Is.True);
            Assert.That(s.TryPerform("open.check_whole_class"), Is.True);
            Assert.That(s.TryPerform("open.start_group_work"), Is.True);
            Assert.That(s.TryPerform("teach.board_diagram"), Is.True);
            Assert.That(s.TryPerform("teach.board_criteria"), Is.True);
            Assert.That(s.TryPerform("teach.to_check"), Is.True);
            Assert.That(s.TryPerform("assess.oral_nam"), Is.True);
            Assert.That(s.TryPerform("assess.end_lesson"), Is.True);
            s.SetReflection(new ReflectionInput { liked = "Giảng nhanh trên bảng", keep = "Dùng tiêu chí bảng", change = "Cần kiểm tra các nhóm khác" });
            Assert.That(s.TryPerform("reflect.finish"), Is.True);
            return s;
        }

        private ScenarioSession PlayRun2QuestionAndGroupSupport()
        {
            var s = new ScenarioSession(definition);
            Assert.That(s.TryPerform("brief.ask_class"), Is.True);
            Assert.That(s.TryPerform("brief.ask_gear"), Is.True);
            Assert.That(s.TryPerform("yard.inspect_puddle"), Is.True);
            Assert.That(s.TryPerform("brief.accept"), Is.True);
            Assert.That(s.TryPerform("prep.read_prev_work"), Is.True);
            Assert.That(s.TryPerform("prep.group_cards"), Is.True);
            Assert.That(s.TryPerform("prep.enter_class"), Is.True);
            Assert.That(s.TryPerform("open.clear_task"), Is.True);
            Assert.That(s.TryPerform("open.check_pair_prompt"), Is.True);
            Assert.That(s.TryPerform("open.start_group_work"), Is.True);
            Assert.That(s.TryPerform("g4.inspect_sheet"), Is.True);
            Assert.That(s.TryPerform("g4.clarify_cause"), Is.True);
            Assert.That(s.TryPerform("g4.scaffold_steps"), Is.True);
            Assert.That(s.TryPerform("g1.inspect_sheet"), Is.True);
            Assert.That(s.TryPerform("g1.ask_private"), Is.True);
            Assert.That(s.TryPerform("g2.inspect_sheet"), Is.True);
            Assert.That(s.TryPerform("g2.ask_reasoning"), Is.True);
            Assert.That(s.TryPerform("g2.design_test"), Is.True);
            Assert.That(s.TryPerform("g4.recheck_new_example"), Is.True);
            Assert.That(s.TryPerform("teach.to_check"), Is.True);
            Assert.That(s.TryPerform("assess.collect_sheets"), Is.True);
            Assert.That(s.TryPerform("assess.end_lesson"), Is.True);
            s.SetReflection(new ReflectionInput
            {
                liked = "Hỏi làm rõ nguyên nhân của Minh",
                difficulty = "Quản lý thời gian giữa 4 nhóm",
                keep = "Kiểm tra lại bằng ví dụ mới",
                selectedEvidenceIds = new List<string> { "g4.recheck_new_example", "g1.ask_private" }
            });
            Assert.That(s.TryPerform("reflect.finish"), Is.True);
            return s;
        }

        private ScenarioSession PlayRun3CombinedAndAdaptive()
        {
            var s = new ScenarioSession(definition);
            Assert.That(s.TryPerform("brief.ask_class"), Is.True);
            Assert.That(s.TryPerform("brief.accept"), Is.True);
            Assert.That(s.TryPerform("prep.read_prev_work"), Is.True);
            Assert.That(s.TryPerform("prep.inspect_gear"), Is.True);
            Assert.That(s.TryPerform("prep.print"), Is.True);
            Assert.That(s.TryPerform("prep.projector"), Is.True);
            Assert.That(s.TryPerform("prep.enter_class"), Is.True);
            Assert.That(s.TryPerform("open.clear_task"), Is.True);
            Assert.That(s.TryPerform("open.check_whole_class"), Is.True);
            Assert.That(s.TryPerform("open.start_group_work"), Is.True);
            Assert.That(s.TryPerform("g4.repeat_prompt"), Is.True); // ineffective first try
            Assert.That(s.TryPerform("g3.inspect_sheet"), Is.True);
            Assert.That(s.TryPerform("g4.demo_samples"), Is.True); // causes equipment.failed + group3.finished_early
            Assert.That(s.TryPerform("g3.cross_check_g4"), Is.True);
            Assert.That(s.TryPerform("g3.bridge_verbal"), Is.True);
            Assert.That(s.TryPerform("g4.recheck_new_example"), Is.True);
            Assert.That(s.TryPerform("teach.to_check"), Is.True);
            Assert.That(s.TryPerform("assess.quick_probe"), Is.True);
            Assert.That(s.TryPerform("assess.adjust_reteach"), Is.True);
            Assert.That(s.TryPerform("assess.end_lesson"), Is.True);
            s.SetReflection(new ReflectionInput
            {
                liked = "Chuyển hướng kịp thời khi máy chiếu tắt nhờ có bản in",
                change = "Kiểm tra thêm nhóm 2 về lượng nước",
                selectedEvidenceIds = new List<string> { "equipment.failed", "assess.adjust_reteach" }
            });
            Assert.That(s.TryPerform("reflect.finish"), Is.True);
            return s;
        }

        private static void PerformToTeach(ScenarioSession s, string prepAction)
        {
            Assert.That(s.TryPerform("brief.accept"), Is.True);
            Assert.That(s.TryPerform(prepAction), Is.True);
            Assert.That(s.TryPerform("prep.enter_class"), Is.True);
            Assert.That(s.TryPerform("open.clear_task"), Is.True);
            Assert.That(s.TryPerform("open.check_whole_class"), Is.True);
            Assert.That(s.TryPerform("open.start_group_work"), Is.True);
        }

        private static void RestorePref(string key, string value)
        {
            if (string.IsNullOrEmpty(value)) PlayerPrefs.DeleteKey(key);
            else PlayerPrefs.SetString(key, value);
        }
    }
}
