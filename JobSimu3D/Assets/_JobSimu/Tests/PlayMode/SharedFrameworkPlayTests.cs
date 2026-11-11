#if UNITY_EDITOR
using System.Collections;
using System.Collections.Generic;
using System.Linq;
using NUnit.Framework;
using UnityEngine;
using UnityEngine.SceneManagement;
using UnityEngine.TestTools;
using JobSimu.Core;
using JobSimu.Consequences;
using JobSimu.Dialogue;
using JobSimu.Gameplay;
using JobSimu.Interaction;
using JobSimu.NPC;
using JobSimu.Quests;
using JobSimu.UI;

namespace JobSimu.Tests
{
    /// <summary>Scene integration and real CharacterController walking PlayMode tests for Class 8A.</summary>
    public sealed class SharedFrameworkPlayTests
    {
        private SimulationRuntime runtime;
        private string savedMain, savedBackup, savedCorrupt;
        private const string BaseKey = "jobsimu.unity.v2.teacher.first-lesson";

        [UnitySetUp]
        public IEnumerator Setup()
        {
            savedMain = PlayerPrefs.GetString(BaseKey, "");
            savedBackup = PlayerPrefs.GetString(BaseKey + ".backup", "");
            savedCorrupt = PlayerPrefs.GetString(BaseKey + ".corrupt", "");
            PlayerPrefs.DeleteKey(BaseKey);
            PlayerPrefs.DeleteKey(BaseKey + ".backup");
            PlayerPrefs.DeleteKey(BaseKey + ".corrupt");
            PlayerPrefs.Save();

            yield return UnityEditor.SceneManagement.EditorSceneManager.LoadSceneAsyncInPlayMode(
                "Assets/_JobSimu/Scenes/SharedFrameworkDemo.unity", new LoadSceneParameters(LoadSceneMode.Single));
            yield return null;
            runtime = Object.FindAnyObjectByType<SimulationRuntime>();
            Assert.That(runtime, Is.Not.Null);
        }

        [TearDown]
        public void Cleanup()
        {
            if (runtime != null) { runtime.enabled = false; Object.DestroyImmediate(runtime); }
            RestorePref(BaseKey, savedMain);
            RestorePref(BaseKey + ".backup", savedBackup);
            RestorePref(BaseKey + ".corrupt", savedCorrupt);
            PlayerPrefs.Save();
        }

        [UnityTest]
        public IEnumerator RealWalkingReachesEveryStationAndCompletesClass8ALoop()
        {
            Assert.That(runtime.ui.BlocksGameplay, Is.True);
            runtime.StartNew(confirmOverwriteExisting: true);
            runtime.ui.SetPage(ScreenPage.Gameplay);

            // 13 NPCs = Cô Mai + 12 Class 8A students
            Assert.That(Object.FindObjectsByType<ScheduledNpc>().Length, Is.EqualTo(13));

            runtime.player.enabled = false;
            PlayerInteractor actor = runtime.player.GetComponent<PlayerInteractor>();
            actor.enabled = false;
            CharacterController cc = runtime.player.GetComponent<CharacterController>();

            // Open all sliding doors physically
            foreach (SlidingDoor door in Object.FindObjectsByType<SlidingDoor>())
                door.Interact(actor);
            yield return new WaitForSeconds(1.3f);

            // Walk to Courtyard Puddle & Cô Mai
            yield return WalkTo(cc, new Vector3(2.8f, 0.06f, -10.5f));
            UseStation(actor, "yard_puddle", "yard.inspect_puddle");

            yield return WalkTo(cc, new Vector3(-1.8f, 0.06f, -9f));
            UseStation(actor, "mai", "brief.ask_class", "brief.accept");

            // Walk through Courtyard -> Corridor -> Staff Room
            yield return WalkTo(cc, new Vector3(0f, 0.06f, 5.5f));
            yield return WalkTo(cc, new Vector3(8.2f, 0.06f, 5.5f));
            yield return WalkTo(cc, new Vector3(8.2f, 0.06f, 9.2f));
            yield return WalkTo(cc, new Vector3(10.2f, 0.06f, 9.2f));
            yield return WalkTo(cc, new Vector3(10.2f, 0.06f, 12.2f));
            UseStation(actor, "prev_work", "prep.read_prev_work");

            yield return WalkTo(cc, new Vector3(10.2f, 0.06f, 14.5f));
            UseStation(actor, "gear_check", "prep.inspect_gear");
            UseStation(actor, "printer", "prep.print");

            yield return WalkTo(cc, new Vector3(14.5f, 0.06f, 12.5f));
            UseStation(actor, "group-kit", "prep.group_cards");

            yield return WalkTo(cc, new Vector3(10.2f, 0.06f, 12.2f));
            yield return WalkTo(cc, new Vector3(8.4f, 0.06f, 10.4f));
            UseStation(actor, "staff_exit", "prep.enter_class");

            // Walk through Corridor -> Class 8A aisles
            yield return WalkTo(cc, new Vector3(8.2f, 0.06f, 9.2f));
            yield return WalkTo(cc, new Vector3(8.2f, 0.06f, 5.5f));
            yield return WalkTo(cc, new Vector3(-15.8f, 0.06f, 5.5f));
            yield return WalkTo(cc, new Vector3(-15.8f, 0.06f, 8.85f));
            yield return WalkTo(cc, new Vector3(-16.7f, 0.06f, 8.85f));
            yield return WalkTo(cc, new Vector3(-16.7f, 0.06f, 11.86f));
            yield return WalkTo(cc, new Vector3(-12.0f, 0.06f, 11.86f));
            yield return WalkTo(cc, new Vector3(-12.0f, 0.06f, 15.2f));

            // Opening phase on board: state task + check understanding + start group work
            UseStation(actor, "board", "open.clear_task", "open.check_pair_prompt", "open.start_group_work");
            yield return null;

            var visuals = Object.FindAnyObjectByType<TeacherLessonVisuals>();
            Assert.That(visuals.printedMaterials.activeSelf, Is.True);
            Assert.That(visuals.groupMaterials.activeSelf, Is.True);

            // Walk to Group 1 (front-left) from front podium aisle (unobstructed view of sheet) & side/cross aisle for Linh
            yield return WalkTo(cc, new Vector3(-14.5f, 0.06f, 15.1f));
            UseStation(actor, "group1_sheet", "g1.inspect_sheet");
            yield return WalkTo(cc, new Vector3(-12.0f, 0.06f, 15.1f));
            yield return WalkTo(cc, new Vector3(-12.0f, 0.06f, 11.86f));
            yield return WalkTo(cc, new Vector3(-14.5f, 0.06f, 11.86f));
            UseStation(actor, "linh", "g1.ask_private");

            // Group 3 (back-left) right behind/in front of cross-aisle
            UseStation(actor, "group3_sheet", "g3.inspect_sheet");
            yield return WalkTo(cc, new Vector3(-16.7f, 0.06f, 11.86f));
            yield return WalkTo(cc, new Vector3(-16.7f, 0.06f, 8.85f));
            yield return WalkTo(cc, new Vector3(-14.5f, 0.06f, 8.85f));
            UseStation(actor, "ha", "g3.bridge_verbal");

            // Group 4 (back-right) from back aisle & cross aisle
            yield return WalkTo(cc, new Vector3(-9.5f, 0.06f, 8.85f));
            UseStation(actor, "minh", "g4.clarify_cause", "g4.scaffold_steps");
            yield return WalkTo(cc, new Vector3(-7.3f, 0.06f, 8.85f));
            yield return WalkTo(cc, new Vector3(-7.3f, 0.06f, 11.86f));
            yield return WalkTo(cc, new Vector3(-9.5f, 0.06f, 11.86f));
            UseStation(actor, "group4_sheet", "g4.inspect_sheet");

            // Group 2 (front-right) from cross aisle & front aisle
            UseStation(actor, "nam", "g2.ask_reasoning", "g2.design_test");
            yield return WalkTo(cc, new Vector3(-12.0f, 0.06f, 11.86f));
            yield return WalkTo(cc, new Vector3(-12.0f, 0.06f, 15.1f));
            yield return WalkTo(cc, new Vector3(-9.5f, 0.06f, 15.1f));
            UseStation(actor, "group2_sheet", "g2.inspect_sheet");

            // Return to re-check Group 4 with new example!
            yield return WalkTo(cc, new Vector3(-7.3f, 0.06f, 15.1f));
            yield return WalkTo(cc, new Vector3(-7.3f, 0.06f, 8.85f));
            yield return WalkTo(cc, new Vector3(-9.5f, 0.06f, 8.85f));
            UseStation(actor, "minh", "g4.recheck_new_example");
            Assert.That(runtime.Session.GetGroup("group4").workStatus, Is.EqualTo(GroupWorkStatus.RecheckedVerified));

            // Return to board & teacher tickets desk
            yield return WalkTo(cc, new Vector3(-7.3f, 0.06f, 8.85f));
            yield return WalkTo(cc, new Vector3(-7.3f, 0.06f, 11.86f));
            yield return WalkTo(cc, new Vector3(-12.0f, 0.06f, 11.86f));
            yield return WalkTo(cc, new Vector3(-12.0f, 0.06f, 15.2f));
            UseStation(actor, "board", "teach.to_check");

            yield return WalkTo(cc, new Vector3(-14.8f, 0.06f, 15.2f));
            UseStation(actor, "tickets", "assess.collect_sheets");

            yield return WalkTo(cc, new Vector3(-12.0f, 0.06f, 15.2f));
            UseStation(actor, "board", "assess.end_lesson");

            // Submit reflection & verify exact local save replay
            Assert.That(runtime.Session.Phase, Is.EqualTo(LessonPhase.Reflection));
            runtime.ui.OpenReflection();
            Assert.That(runtime.ui.SubmitReflection(new ReflectionInput
            {
                liked = "Đi từng bàn xem bài thật",
                keep = "Kiểm tra lại Nhóm 4 bằng ví dụ đất nén",
                selectedEvidenceIds = new List<string> { "g4.recheck_new_example", "g1.ask_private" }
            }), Is.True);

            Assert.That(runtime.Session.Complete, Is.True);
            Assert.That(runtime.ui.Page, Is.EqualTo(ScreenPage.Results));

            string reportBefore = runtime.Session.Report();
            Assert.That(runtime.Resume(), Is.True);
            Assert.That(runtime.Session.Report(), Is.EqualTo(reportBefore));
        }

        [UnityTest]
        public IEnumerator PauseFocusLossDialogueStopClockAndNoDoubleCounting()
        {
            runtime.StartNew(confirmOverwriteExisting: true);
            runtime.Perform("brief.accept");
            int startMin = runtime.Session.Minute;

            // Dialogue / Pause / Lost focus MUST stop passive clock
            runtime.ui.SetPage(ScreenPage.Pause);
            runtime.TickClock(60f, isFocused: true);
            Assert.That(runtime.Session.Minute, Is.EqualTo(startMin));

            runtime.ui.SetPage(ScreenPage.Dialogue);
            runtime.TickClock(60f, isFocused: true);
            Assert.That(runtime.Session.Minute, Is.EqualTo(startMin));

            runtime.ui.SetPage(ScreenPage.Gameplay);
            runtime.TickClock(60f, isFocused: false);
            Assert.That(runtime.Session.Minute, Is.EqualTo(startMin));

            // 20s active gameplay accumulates without ticking yet, then action resets accumulator so no double-counting
            runtime.TickClock(20f, isFocused: true);
            Assert.That(runtime.ClockAccumulator, Is.EqualTo(20f).Within(0.01f));
            Assert.That(runtime.Perform("prep.inspect_gear"), Is.True);
            Assert.That(runtime.ClockAccumulator, Is.EqualTo(0f));
            Assert.That(runtime.Session.Minute, Is.EqualTo(startMin + 2));

            // Corrupt primary falls back to backup while preserving corrupt payload
            runtime.Save();
            PlayerPrefs.SetString(BaseKey, "{broken");
            Assert.That(runtime.Resume(), Is.True);
            Assert.That(PlayerPrefs.GetString(BaseKey + ".corrupt"), Is.EqualTo("{broken"));
            Assert.That(runtime.Session.Minute, Is.EqualTo(startMin + 2));
            yield return null;
        }

        [UnityTest]
        public IEnumerator HiddenAndOccludedObjectsCannotBeTargeted()
        {
            runtime.StartNew(confirmOverwriteExisting: true);
            runtime.ui.SetPage(ScreenPage.Gameplay);

            PlayerInteractor actor = runtime.player.GetComponent<PlayerInteractor>();
            CharacterController cc = runtime.player.GetComponent<CharacterController>();

            // Hidden visual renderer cannot be raycasted
            var prop = GameObject.CreatePrimitive(PrimitiveType.Cube);
            prop.transform.position = new Vector3(0f, 1.5f, -15f);
            var station = prop.AddComponent<DialogueStation>();
            station.stationId = "hidden_probe";
            station.title = "Hidden";
            station.runtime = runtime;

            cc.enabled = false;
            runtime.player.transform.position = new Vector3(0f, 0.06f, -17f);
            cc.enabled = true;
            runtime.player.viewCamera.transform.LookAt(prop.transform.position);
            Physics.SyncTransforms();

            // Force CanControl true via lock simulation? Test FindTarget directly (FindTarget checks raycast + CanInteract + Renderer!)
            Assert.That(actor.FindTarget(), Is.EqualTo(station));

            // Disable Renderer (hidden object): must NOT be targetable!
            prop.GetComponent<Renderer>().enabled = false;
            Assert.That(actor.FindTarget(), Is.Null);

            // Re-enable Renderer but deactivate GameObject: must NOT be targetable!
            prop.GetComponent<Renderer>().enabled = true;
            prop.SetActive(false);
            Physics.SyncTransforms();
            Assert.That(actor.FindTarget(), Is.Null);

            Object.DestroyImmediate(prop);
            yield return null;
        }

        [UnityTest]
        public IEnumerator TimeOutDuringGameplayTransitionsCleanlyToReflection()
        {
            runtime.StartNew(confirmOverwriteExisting: true);
            runtime.Perform("brief.accept");
            runtime.Perform("prep.print");
            runtime.Perform("prep.enter_class");
            runtime.Perform("open.quick_task");
            runtime.Perform("open.check_whole_class");
            runtime.Perform("open.start_group_work");
            runtime.ui.SetPage(ScreenPage.Gameplay);

            // Simulate active gameplay ticking across remaining lesson minutes until 08:15 (495)
            int remaining = runtime.Session.Definition.lessonEndMinute - runtime.Session.Minute;
            runtime.TickClock(remaining * SimulationRuntime.PassiveMinuteRealSeconds + 1f, isFocused: true);
            yield return null;

            Assert.That(runtime.Session.HasTimedOut, Is.True);
            Assert.That(runtime.Session.Phase, Is.EqualTo(LessonPhase.Reflection));
            Assert.That(runtime.ui.Page, Is.EqualTo(ScreenPage.Reflection));
            Assert.That(runtime.ui.SubmitReflection(new ReflectionInput { change = "Phân bổ thời gian đều hơn" }), Is.True);
            Assert.That(runtime.Session.Complete, Is.True);
            Assert.That(runtime.Session.Report(), Does.Contain("ĐÃ HẾT GIỜ 08:15"));
        }

        private static IEnumerator WalkTo(CharacterController cc, Vector3 destination)
        {
            for (int step = 0; step < 360; step++)
            {
                Vector3 delta = destination - cc.transform.position;
                delta.y = 0f;
                if (delta.magnitude < 0.14f) yield break;
                cc.Move(Vector3.ClampMagnitude(delta, 0.12f) + Vector3.down * 0.03f);
                if (step % 15 == 0) yield return null;
            }
            Vector3 rem = destination - cc.transform.position;
            rem.y = 0f;
            Assert.That(rem.magnitude, Is.LessThan(0.25f), "CharacterController blocked reaching " + destination);
        }

        private void UseStation(PlayerInteractor actor, string stationId, params string[] actionIds)
        {
            runtime.ui.SetPage(ScreenPage.Gameplay);
            DialogueStation station = Object.FindObjectsByType<DialogueStation>().Single(s => s.stationId == stationId);
            runtime.player.viewCamera.transform.LookAt(station.GetComponent<Collider>().bounds.center);
            Physics.SyncTransforms();
            Assert.That(actor.FindTarget(), Is.EqualTo(station), "Station unreachable by raycast from walked spot: " + stationId);
            actor.FindTarget().Interact(actor);
            Assert.That(runtime.ui.Page, Is.EqualTo(ScreenPage.Dialogue));
            foreach (string actionId in actionIds)
                Assert.That(runtime.ui.Choose(actionId), Is.True, "Failed action " + actionId + " at " + stationId);
            runtime.ui.SetPage(ScreenPage.Gameplay);
        }

        private static void RestorePref(string key, string value)
        {
            if (string.IsNullOrEmpty(value)) PlayerPrefs.DeleteKey(key);
            else PlayerPrefs.SetString(key, value);
        }
    }
}
#endif
