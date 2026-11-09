#if UNITY_EDITOR
using System.Collections;
using System.Linq;
using NUnit.Framework;
using UnityEngine;
using UnityEngine.SceneManagement;
using UnityEngine.TestTools;
using JobSimu.Core;
using JobSimu.Dialogue;
using JobSimu.Interaction;
using JobSimu.NPC;
using JobSimu.UI;

namespace JobSimu.Tests
{
    public sealed class SharedFrameworkPlayTests
    {
        private SimulationRuntime runtime;
        private string savedMain, savedBackup;
        private const string SaveKey = "jobsimu.unity.v1.teacher.first-lesson";
        [UnitySetUp] public IEnumerator Setup()
        {
            savedMain = PlayerPrefs.GetString(SaveKey, ""); savedBackup = PlayerPrefs.GetString(SaveKey + ".backup", "");
            yield return UnityEditor.SceneManagement.EditorSceneManager.LoadSceneAsyncInPlayMode(
                "Assets/_JobSimu/Scenes/SharedFrameworkDemo.unity", new LoadSceneParameters(LoadSceneMode.Single));
            yield return null; runtime = Object.FindAnyObjectByType<SimulationRuntime>();
        }
        [TearDown] public void Cleanup()
        {
            if (runtime != null) { runtime.enabled = false; Object.DestroyImmediate(runtime); }
            if (savedMain == "") PlayerPrefs.DeleteKey(SaveKey); else PlayerPrefs.SetString(SaveKey, savedMain);
            if (savedBackup == "") PlayerPrefs.DeleteKey(SaveKey + ".backup"); else PlayerPrefs.SetString(SaveKey + ".backup", savedBackup);
            PlayerPrefs.Save();
        }
        [UnityTest] public IEnumerator PhysicalStationsCompleteLessonAndResumeExactConsequences()
        {
            Assert.That(runtime.ui.BlocksGameplay, Is.True);
            runtime.StartNew(); runtime.ui.SetPage(ScreenPage.Gameplay);
            Assert.That(Object.FindObjectsByType<ScheduledNpc>().Length, Is.EqualTo(3));
            runtime.player.enabled = false;
            var actor = runtime.player.GetComponent<PlayerInteractor>(); actor.enabled = false;
            Use(actor, "mai", "brief.accept", new Vector3(-3, .06f, -11));
            Use(actor, "printer", "prep.print", new Vector3(9.1f, .06f, 10.8f));
            Use(actor, "board", "lesson.begin", new Vector3(-12, .06f, 16.3f));
            yield return null;
            Assert.That(Object.FindAnyObjectByType<JobSimu.Gameplay.TeacherLessonVisuals>().printedMaterials.activeSelf, Is.True);
            Use(actor, "linh", "teach.scaffold", new Vector3(-12.6f, .06f, 9.5f));
            Use(actor, "tickets", "assess.tickets", new Vector3(-15, .06f, 16));
            Use(actor, "mai", "reflect.finish", new Vector3(-3, .06f, -11));
            Assert.That(runtime.Session.Complete, Is.True);
            Assert.That(runtime.ui.Page, Is.EqualTo(ScreenPage.Results));
            runtime.Reflection = "Kiểm tra từng nhóm trước khi kết luận."; runtime.Save();
            string report = runtime.Session.Report();
            Assert.That(runtime.Resume(), Is.True);
            Assert.That(runtime.Session.Report(), Is.EqualTo(report));
            Assert.That(runtime.Reflection, Does.Contain("từng nhóm"));
            Assert.That(Object.FindObjectsByType<ScheduledNpc>().Single(n => n.profile.id == "linh").Memory, Does.Contain("teach.scaffold"));
        }
        [UnityTest] public IEnumerator PauseStopsClockAndCorruptPrimaryFallsBackToBackup()
        {
            runtime.StartNew(); runtime.ui.SetPage(ScreenPage.Pause);
            int minute = runtime.Session.Minute;
            yield return new WaitForSeconds(.1f);
            Assert.That(runtime.Session.Minute, Is.EqualTo(minute));
            runtime.Perform("brief.accept"); runtime.Save();
            PlayerPrefs.SetString(SaveKey, "invalid");
            Assert.That(runtime.Resume(), Is.True);
            Assert.That(runtime.Session.StepId, Is.EqualTo("prepare"));
            Assert.That(runtime.SaveStatus, Does.Contain("dự phòng"));
        }
        private void Use(PlayerInteractor actor, string stationId, string actionId, Vector3 feet)
        {
            runtime.ui.SetPage(ScreenPage.Gameplay);
            DialogueStation station = Object.FindObjectsByType<DialogueStation>().Single(s => s.stationId == stationId);
            CharacterController cc = runtime.player.GetComponent<CharacterController>(); cc.enabled = false;
            runtime.player.transform.position = feet; cc.enabled = true;
            runtime.player.viewCamera.transform.LookAt(station.GetComponent<Collider>().bounds.center);
            Physics.SyncTransforms();
            Assert.That(actor.FindTarget(), Is.EqualTo(station), stationId + " must be visible/reachable");
            actor.FindTarget().Interact(actor);
            Assert.That(runtime.ui.Page, Is.EqualTo(ScreenPage.Dialogue));
            Assert.That(runtime.player.CanControl, Is.False);
            Assert.That(runtime.ui.Choose(actionId), Is.True, actionId);
        }
    }
}
#endif
