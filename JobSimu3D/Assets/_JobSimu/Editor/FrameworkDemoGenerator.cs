using System;
using System.IO;
using System.Linq;
using UnityEditor;
using UnityEditor.SceneManagement;
using UnityEngine;
using UnityEngine.SceneManagement;
using JobSimu.Core;
using JobSimu.Dialogue;
using JobSimu.Gameplay;
using JobSimu.Interaction;
using JobSimu.NPC;
using JobSimu.UI;

namespace JobSimu.Editor
{
    public static class FrameworkDemoGenerator
    {
        public const string ScenePath = "Assets/_JobSimu/Scenes/SharedFrameworkDemo.unity";
        private static SimulationRuntime runtime;
        [MenuItem("Tools/JobSimu/Generate Shared Framework Demo")]
        public static void Menu()
        {
            if (EditorApplication.isPlayingOrWillChangePlaymode || !EditorSceneManager.SaveCurrentModifiedScenesIfUserWantsTo()) return;
            Generate();
        }
        public static void Generate()
        {
            if (File.Exists(ScenePath))
            {
                EditorSceneManager.OpenScene(ScenePath); FrameworkValidator.ValidateBatch();
                Debug.Log("Existing framework demo reused; no duplicate objects or assets created."); return;
            }
            var definition = TeacherScenarioAssets.GetOrCreate();
            Scene scene = EditorSceneManager.OpenScene(SchoolPrototypeGenerator.ScenePath, OpenSceneMode.Single);
            PrototypeContext context = UnityEngine.Object.FindAnyObjectByType<PrototypeContext>();
            // All changes are saved to the new scene; the source's bytes and GUID are preserved.
            context.hud.enabled = false; context.orientation.enabled = false;
            foreach (OrientationInteractable old in UnityEngine.Object.FindObjectsByType<OrientationInteractable>()) UnityEngine.Object.DestroyImmediate(old);
            foreach (MentorNPC old in UnityEngine.Object.FindObjectsByType<MentorNPC>()) UnityEngine.Object.DestroyImmediate(old.gameObject);
            runtime = context.gameObject.AddComponent<SimulationRuntime>(); runtime.scenario = definition; runtime.player = context.player;
            FrameworkHUD ui = context.gameObject.AddComponent<FrameworkHUD>(); ui.runtime = runtime; runtime.ui = ui;
            SimulationAudio audio = context.gameObject.AddComponent<SimulationAudio>(); audio.runtime = runtime; ui.audioCues = audio;
            context.player.interactionUI = ui;
            context.player.GetComponent<PlayerInteractor>().interactionUI = ui;
            context.gameObject.AddComponent<BrowserDiagnostics>().runtime = runtime;
            context.gameObject.name = "Shared Simulation";
            Transform root = new GameObject("Teacher Lesson · World Interactions").transform;
            Station("printer", "Máy in · ảnh và phiếu", "Ảnh hai bề mặt cùng nhận một lượng mưa. In trước giúp cả lớp có tài liệu khi thiết bị gặp trục trặc.", new Vector3(9.1f, .9f, 12.4f), root, "Paper");
            Station("projector", "Máy chiếu · trình chiếu", "Đèn báo máy chiếu chập chờn. Trình chiếu tiết kiệm chuẩn bị nhưng có rủi ro gián đoạn.", new Vector3(12, .9f, 11), root, "Steel");
            Station("group-kit", "Bộ thẻ · học theo nhóm", "Thẻ ảnh đất, bê tông và nước đọng. Có thể dùng để phân loại và giải thích theo nhóm.", new Vector3(9.1f, .9f, 15.2f), root, "Gold");
            GameObject board = scene.GetRootGameObjects().SelectMany(g => g.GetComponentsInChildren<Transform>()).First(t => t.name == "Teaching board" && t.position.x < -6).gameObject;
            AddStation(board, "board", "Bảng lớp 10A1", "Câu hỏi hôm nay: vì sao sân lát kín và đất trồng có lượng nước đọng khác nhau sau mưa?");
            Station("tickets", "Phiếu cuối tiết", "Hai câu hỏi: nước mưa đi đâu trên đất và trên sân bê tông? Dẫn một quan sát hỗ trợ câu trả lời.", new Vector3(-15, 1.2f, 17.6f), root, "Paper");
            Npc("mai", "Cô Mai", "Giáo viên hướng dẫn", "Cô sẽ cùng bạn chuẩn bị và nhìn lại tiết dạy. Học sinh cần được trao đổi và kiểm tra hiểu bài.", new Vector3(-3, 0, -9), new Vector3(-3, 0, -8), false, root);
            Npc("linh", "Linh", "Học sinh lớp 10A1", "Em muốn xem một ví dụ cụ thể trước khi tự giải thích. Mình có thể cùng so sánh từng bước không?", new Vector3(-12.6f, 0, 11.1f), new Vector3(-12.6f, 0, 11.6f), true, root);
            Npc("nam", "Nam", "Học sinh lớp 10A1", "Nếu lượng mưa bằng nhau, mình cần quan sát thêm gì để so sánh hai bề mặt?", new Vector3(-9.6f, 0, 13.1f), new Vector3(-9.6f, 0, 13.6f), true, root);
            var visuals = root.gameObject.AddComponent<TeacherLessonVisuals>(); visuals.runtime = runtime;
            visuals.printedMaterials = Prop("Printed materials on classroom desk", new Vector3(-14.1f, .84f, 10.5f), new Vector3(.7f, .04f, .4f), "Paper", root);
            visuals.groupMaterials = Prop("Group cards on classroom desk", new Vector3(-11.1f, .84f, 10.5f), new Vector3(.7f, .04f, .4f), "Gold", root);
            visuals.projectorSignal = Prop("Projector status indicator", new Vector3(-8, 2.6f, 17.65f), new Vector3(.2f, .2f, .1f), "Red", root);
            visuals.boardText = board.transform.parent.GetComponentsInChildren<TextMesh>().First(t => t.text.StartsWith("CHÀO MỪNG"));
            Physics.SyncTransforms();
            EditorSceneManager.SaveScene(scene, ScenePath); AssetDatabase.SaveAssets();
            // Asset import can replace newly created ScriptableObject instances; verify serialized GUIDs after reopening.
            EditorSceneManager.OpenScene(ScenePath); FrameworkValidator.ValidateBatch();
        }
        private static GameObject Prop(string name, Vector3 position, Vector3 scale, string material, Transform parent, bool collider = false)
        {
            GameObject value = GameObject.CreatePrimitive(PrimitiveType.Cube); value.name = name;
            value.transform.SetParent(parent, false); value.transform.position = position; value.transform.localScale = scale;
            value.GetComponent<Renderer>().sharedMaterial = AssetDatabase.LoadAssetAtPath<Material>(SchoolPrototypeGenerator.Root + "/Materials/" + material + ".mat");
            if (!collider) UnityEngine.Object.DestroyImmediate(value.GetComponent<Collider>());
            return value;
        }
        private static DialogueStation AddStation(GameObject obj, string id, string title, string introduction)
        {
            DialogueStation station = obj.AddComponent<DialogueStation>(); station.stationId = id; station.title = title;
            station.introduction = introduction; station.runtime = runtime; return station;
        }
        private static void Station(string id, string title, string introduction, Vector3 position, Transform parent, string material)
        {
            GameObject obj = Prop(title, position, new Vector3(.7f, .15f, .45f), material, parent, true);
            AddStation(obj, id, title, introduction);
            if (id != "projector") Prop(title + " support", position + Vector3.down * .45f, new Vector3(.12f, .75f, .12f), "Steel", parent);
            Label(title, position + Vector3.up * .55f, parent);
        }
        private static Transform Part(string name, Transform parent, Vector3 localPosition, Vector3 scale, string material)
        {
            Transform pivot = new GameObject(name).transform; pivot.SetParent(parent, false); pivot.localPosition = localPosition;
            GameObject mesh = Prop(name + " mesh", Vector3.zero, scale, material, pivot);
            mesh.transform.localPosition = new Vector3(0, -scale.y * .5f, 0); return pivot;
        }
        private static void Npc(string id, string name, string role, string introduction, Vector3 from, Vector3 to, bool seated, Transform parent)
        {
            Transform npc = new GameObject(name).transform; npc.SetParent(parent, false); npc.position = from;
            CapsuleCollider collider = npc.gameObject.AddComponent<CapsuleCollider>(); collider.radius = .24f; collider.height = 1.7f; collider.center = new Vector3(0, .85f, 0);
            DialogueStation station = AddStation(npc.gameObject, id, name + " · " + role, introduction);
            station.variantFlag = id == "linh" ? "scaffolded" : id == "nam" ? "compared" : "tickets-collected";
            station.variantIntroduction = id == "mai" ? "Bạn đã thu sản phẩm cuối giờ. Ta có thể dựa vào đó để lên kế hoạch tiết sau." : "Em nhớ lần trao đổi vừa rồi. Em muốn thử giải thích lại bằng ví dụ của nhóm.";
            NpcProfile profile = TeacherScenarioAssets.Profile(id, name, role, introduction, seated);
            Transform visual = new GameObject("Procedural blockout body").transform; visual.SetParent(npc, false);
            Prop("Torso", from + Vector3.up * 1.05f, new Vector3(.4f, .6f, .24f), seated ? "Paper" : "Indigo", visual);
            Prop("Head", from + Vector3.up * 1.55f, new Vector3(.29f, .34f, .28f), "Skin", visual);
            ScheduledNpc brain = npc.gameObject.AddComponent<ScheduledNpc>(); brain.profile = profile; brain.runtime = runtime; brain.station = station; brain.visual = visual;
            brain.leftArm = Part("Left arm", visual, new Vector3(-.28f, 1.3f, 0), new Vector3(.13f, .55f, .13f), "Indigo");
            brain.rightArm = Part("Right arm", visual, new Vector3(.28f, 1.3f, 0), new Vector3(.13f, .55f, .13f), "Indigo");
            brain.leftLeg = Part("Left leg", visual, new Vector3(-.12f, .75f, 0), new Vector3(.15f, .75f, .16f), "Steel");
            brain.rightLeg = Part("Right leg", visual, new Vector3(.12f, .75f, 0), new Vector3(.15f, .75f, .16f), "Steel");
            brain.waypoints = new[] { new GameObject(id + " morning waypoint").transform, new GameObject(id + " lesson waypoint").transform };
            brain.waypoints[0].SetParent(parent); brain.waypoints[0].position = from;
            brain.waypoints[1].SetParent(parent); brain.waypoints[1].position = to;
            Label(name, from + Vector3.up * 1.95f, npc);
        }
        private static void Label(string text, Vector3 position, Transform parent)
        {
            var label = new GameObject("Sign · " + text); label.transform.SetParent(parent, false); label.transform.position = position;
            TextMesh mesh = label.AddComponent<TextMesh>(); mesh.text = text; mesh.fontSize = 64; mesh.characterSize = .027f;
            mesh.font = Resources.GetBuiltinResource<Font>("LegacyRuntime.ttf"); mesh.anchor = TextAnchor.MiddleCenter; mesh.color = new Color(.08f, .2f, .23f);
            label.GetComponent<MeshRenderer>().sharedMaterial = AssetDatabase.LoadAssetAtPath<Material>(SchoolPrototypeGenerator.Root + "/Materials/WorldText.mat"); label.AddComponent<WorldLabel>();
        }
    }
}
