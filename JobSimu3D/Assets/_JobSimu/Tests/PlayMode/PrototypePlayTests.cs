#if UNITY_EDITOR
using System.Collections;
using System.Linq;
using NUnit.Framework;
using UnityEngine;
using UnityEngine.SceneManagement;
using UnityEngine.TestTools;
using JobSimu.Core;
using JobSimu.Gameplay;
using JobSimu.Interaction;

namespace JobSimu.Tests
{
    public sealed class PrototypePlayTests
    {
        [UnityTest]
        public IEnumerator RuntimeControllerCanWalkRoutesAndCompleteOrientation()
        {
            yield return UnityEditor.SceneManagement.EditorSceneManager.LoadSceneAsyncInPlayMode(
                "Assets/_JobSimu/Scenes/SchoolFirstPlayable.unity", new LoadSceneParameters(LoadSceneMode.Single));
            yield return null;
            PrototypeContext runtime = Object.FindAnyObjectByType<PrototypeContext>();
            Assert.That(runtime, Is.Not.Null);
            // Drive the real CharacterController deterministically; headless has no mouse focus.
            runtime.player.enabled = false;
            PlayerInteractor actor = runtime.player.GetComponent<PlayerInteractor>();
            actor.enabled = false;
            CharacterController controller = runtime.player.GetComponent<CharacterController>();
            SlidingDoor[] doors = Object.FindObjectsByType<SlidingDoor>();
            Vector3[] closedPositions = doors.Select(d => d.transform.localPosition).ToArray();
            foreach (SlidingDoor door in doors)
                door.Interact(actor);
            yield return new WaitForSeconds(1.3f);
            for (int i = 0; i < doors.Length; i++)
                Assert.That(Vector3.Distance(doors[i].transform.localPosition, closedPositions[i] + doors[i].localTravel),
                    Is.LessThan(.05f), "Door must finish opening: " + doors[i].transform.position + " open=" + doors[i].IsOpen);
            foreach (Transform point in runtime.routeWaypoints)
            {
                for (int step = 0; step < 500; step++)
                {
                    Vector3 delta = point.position - runtime.player.transform.position;
                    delta.y = 0;
                    if (delta.magnitude < .12f) break;
                    controller.Move(Vector3.ClampMagnitude(delta, .1f) + Vector3.down * .03f);
                    if (step % 20 == 0) yield return null;
                }
                Vector3 remaining = point.position - runtime.player.transform.position;
                remaining.y = 0;
                Assert.That(remaining.magnitude, Is.LessThan(.15f), "CharacterController stuck before " + point.name);
                Assert.That(runtime.player.transform.position.y, Is.GreaterThan(-.15f));
            }

            var mentor = Object.FindAnyObjectByType<JobSimu.NPC.MentorNPC>();
            AimAndInteract(runtime, actor, mentor, new Vector3(-3, .06f, -11), new Vector3(-3, 1.3f, -9));
            Assert.That(runtime.orientation.Stage, Is.EqualTo(OrientationStage.CollectPlan));
            OrientationInteractable[] stations = Object.FindObjectsByType<OrientationInteractable>();
            var plan = stations.Single(s => s.action == OrientationAction.LessonPlan);
            AimAndInteract(runtime, actor, plan, new Vector3(10.5f, .06f, 10.8f), plan.transform.position);
            Assert.That(runtime.orientation.Stage, Is.EqualTo(OrientationStage.VisitClassroom));
            var board = stations.Single(s => s.action == OrientationAction.ClassroomBoard);
            AimAndInteract(runtime, actor, board, new Vector3(-12, .06f, 16.4f), board.transform.position);
            Assert.That(runtime.orientation.Stage, Is.EqualTo(OrientationStage.Debrief));
            AimAndInteract(runtime, actor, mentor, new Vector3(-3, .06f, -11), new Vector3(-3, 1.3f, -9));
            Assert.That(runtime.orientation.Stage, Is.EqualTo(OrientationStage.Complete));
            Assert.That(runtime.orientation.Evidence.Count, Is.EqualTo(4));

        }

        private static void AimAndInteract(PrototypeContext runtime, PlayerInteractor actor,
            Interactable expected, Vector3 feet, Vector3 lookAt)
        {
            runtime.hud.CloseDialogue();
            CharacterController controller = runtime.player.GetComponent<CharacterController>();
            controller.enabled = false;
            runtime.player.transform.position = feet;
            controller.enabled = true;
            runtime.playerCamera.transform.LookAt(lookAt);
            Physics.SyncTransforms();
            Assert.That(actor.FindTarget(), Is.EqualTo(expected), "Station must be reachable by unobstructed camera raycast.");
            actor.FindTarget().Interact(actor);
            Assert.That(runtime.hud.IsDialogueOpen, Is.True);
            Assert.That(runtime.player.CanControl, Is.False);
        }
    }
}
#endif
