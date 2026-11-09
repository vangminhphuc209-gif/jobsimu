using System.IO;
using System.Collections;
using System.Linq;
using NUnit.Framework;
using UnityEditor.SceneManagement;
using UnityEngine;
using UnityEngine.SceneManagement;
using UnityEngine.TestTools;
using JobSimu.Core;
using JobSimu.Editor;
using JobSimu.Gameplay;
using JobSimu.Interaction;
using JobSimu.Player;

namespace JobSimu.Tests
{
    public sealed class PrototypeTests
    {
        private Scene scene;
        private PrototypeContext context;
        [SetUp]
        public void Setup()
        {
            scene = EditorSceneManager.OpenScene(SchoolPrototypeGenerator.ScenePath, OpenSceneMode.Single);
            context = Object.FindAnyObjectByType<PrototypeContext>();
            Assert.That(context, Is.Not.Null);
            Physics.SyncTransforms();
        }
        [UnityTearDown]
        public IEnumerator Cleanup()
        {
            if (UnityEditor.EditorApplication.isPlaying) yield return new ExitPlayMode();
            EditorSceneManager.NewScene(NewSceneSetup.EmptyScene, NewSceneMode.Single);
        }

        [Test]
        public void GeneratedSceneHasClearSpawnAndRoutes()
        {
            Assert.That(PrototypeValidator.Validate(scene), Is.Empty);
        }

        [Test]
        public void ValidatorDetectsBlockedSpawn()
        {
            var blocker = GameObject.CreatePrimitive(PrimitiveType.Cube);
            blocker.transform.position = context.spawn.position + Vector3.up;
            Physics.SyncTransforms();
            Assert.That(PrototypeValidator.Validate(scene).Any(e => e.Contains("Spawn obstructed")), Is.True);
        }

        [Test]
        public void ValidatorDetectsBlockedClassroomRoute()
        {
            var blocker = GameObject.CreatePrimitive(PrimitiveType.Cube);
            blocker.transform.position = new Vector3(-15.8f, 1, 8);
            Physics.SyncTransforms();
            Assert.That(PrototypeValidator.Validate(scene).Any(e => e.StartsWith("Route ")), Is.True);
        }

        [Test]
        public void OrientationRequiresSequenceAndDoesNotDuplicateEvidence()
        {
            TeacherOrientation flow = context.orientation;
            flow.Act(OrientationAction.ClassroomBoard);
            Assert.That(flow.Stage, Is.EqualTo(OrientationStage.MeetMentor));
            flow.Act(OrientationAction.Mentor);
            flow.Act(OrientationAction.Mentor);
            Assert.That(flow.Evidence.Count, Is.EqualTo(1));
            flow.Act(OrientationAction.LessonPlan);
            flow.Act(OrientationAction.ClassroomBoard);
            flow.Act(OrientationAction.Mentor);
            Assert.That(flow.Stage, Is.EqualTo(OrientationStage.Complete));
            Assert.That(flow.Evidence.Count, Is.EqualTo(4));
            flow.Act(OrientationAction.Mentor);
            Assert.That(flow.Evidence.Count, Is.EqualTo(4));
        }

        [Test]
        public void MousePitchIsClampedInBothDirections()
        {
            context.player.ApplyLook(new Vector2(0, 100000));
            Assert.That(context.player.Pitch, Is.EqualTo(-80));
            context.player.ApplyLook(new Vector2(0, -100000));
            Assert.That(context.player.Pitch, Is.EqualTo(80));
        }

        [Test]
        public void WallOccludesInteraction()
        {
            Camera camera = context.playerCamera;
            camera.transform.SetPositionAndRotation(new Vector3(0, 1.5f, -15), Quaternion.identity);
            var target = GameObject.CreatePrimitive(PrimitiveType.Cube);
            target.transform.position = new Vector3(0, 1.5f, -13);
            var action = target.AddComponent<OrientationInteractable>();
            var interactor = context.player.GetComponent<PlayerInteractor>();
            Physics.SyncTransforms();
            Assert.That(interactor.FindTarget(), Is.EqualTo(action));
            var wall = GameObject.CreatePrimitive(PrimitiveType.Cube);
            wall.transform.position = new Vector3(0, 1.5f, -14);
            wall.transform.localScale = new Vector3(1, 2, .1f);
            Physics.SyncTransforms();
            Assert.That(interactor.FindTarget(), Is.Null);
        }

        [Test]
        public void ClosingDoorPausesForPlayerThenClosesWhenClear()
        {
            SlidingDoor door = Object.FindObjectsByType<SlidingDoor>()[0];
            Vector3 closed = door.transform.localPosition;
            door.Interact(null); door.Advance(10);
            Physics.SyncTransforms();
            Assert.That(door.transform.localPosition, Is.EqualTo(closed + door.localTravel));
            CharacterController controller = context.player.GetComponent<CharacterController>();
            controller.enabled = false;
            context.player.transform.position = new Vector3(closed.x, .06f, closed.z);
            controller.enabled = true;
            Physics.SyncTransforms();
            door.Interact(null); door.Advance(10);
            Assert.That(door.transform.localPosition, Is.EqualTo(closed + door.localTravel));
            controller.enabled = false; context.player.transform.position = context.spawn.position; controller.enabled = true;
            Physics.SyncTransforms(); door.Advance(10);
            Assert.That(door.transform.localPosition, Is.EqualTo(closed));
        }

        [Test]
        public void DoorAccumulatesMotionAtHighFrameRates()
        {
            SlidingDoor door = Object.FindObjectsByType<SlidingDoor>()[0];
            Vector3 closed = door.transform.localPosition;
            door.Interact(null);
            for (int i = 0; i < 100; i++) door.Advance(.0001f);
            Assert.That(door.transform.localPosition.x - closed.x, Is.GreaterThan(.015f));
        }

        [Test]
        public void DialogueReleasesCursorAndBlocksPlayer()
        {
            context.hud.ShowDialogue("Cô Mai", "Chào bạn");
            Assert.That(context.hud.IsDialogueOpen, Is.True);
            Assert.That(context.player.CanControl, Is.False);
            Assert.That(Cursor.lockState, Is.EqualTo(CursorLockMode.None));
            context.hud.CloseDialogue();
            Assert.That(context.hud.IsDialogueOpen, Is.False);
        }

        [Test]
        public void GeneratorPreservesExistingScenesAndReusableAssets()
        {
            byte[] original = File.ReadAllBytes("Assets/SchoolPrototype.unity");
            byte[] generated = File.ReadAllBytes(SchoolPrototypeGenerator.ScenePath);
            byte[] prefab = File.ReadAllBytes(SchoolPrototypeGenerator.Root + "/Prefabs/StudentDesk.prefab");
            Scene scratch = EditorSceneManager.NewScene(NewSceneSetup.EmptyScene, NewSceneMode.Single);
            SchoolPrototypeGenerator.BuildScene(scratch);
            Assert.That(File.ReadAllBytes("Assets/SchoolPrototype.unity"), Is.EqualTo(original));
            Assert.That(File.ReadAllBytes(SchoolPrototypeGenerator.ScenePath), Is.EqualTo(generated));
            Assert.That(File.ReadAllBytes(SchoolPrototypeGenerator.Root + "/Prefabs/StudentDesk.prefab"), Is.EqualTo(prefab));
            Assert.That(PrototypeValidator.Validate(scratch), Is.Empty);
        }

    }
}
