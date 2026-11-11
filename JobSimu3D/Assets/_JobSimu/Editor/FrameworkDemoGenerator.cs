using System;
using System.Collections.Generic;
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
            Generate(forceRebuild: false);
        }

        [MenuItem("Tools/JobSimu/Rebuild Shared Framework Demo (Class 8A)")]
        public static void RebuildMenu()
        {
            if (EditorApplication.isPlayingOrWillChangePlaymode || !EditorSceneManager.SaveCurrentModifiedScenesIfUserWantsTo()) return;
            Generate(forceRebuild: true);
        }

        public static void RebuildAndValidateBatch() => Generate(forceRebuild: true);

        public static void Generate(bool forceRebuild = false)
        {
            if (!forceRebuild && File.Exists(ScenePath))
            {
                EditorSceneManager.OpenScene(ScenePath);
                FrameworkValidator.ValidateBatch();
                Debug.Log("Existing framework demo reused; run Rebuild to regenerate Class 8A scene.");
                return;
            }

            var definition = TeacherScenarioAssets.GetOrCreate(forceRebuild: true);
            Scene scene = EditorSceneManager.OpenScene(SchoolPrototypeGenerator.ScenePath, OpenSceneMode.Single);
            PrototypeContext context = UnityEngine.Object.FindAnyObjectByType<PrototypeContext>();

            context.hud.enabled = false;
            context.orientation.enabled = false;
            foreach (OrientationInteractable old in UnityEngine.Object.FindObjectsByType<OrientationInteractable>())
                UnityEngine.Object.DestroyImmediate(old);
            foreach (MentorNPC old in UnityEngine.Object.FindObjectsByType<MentorNPC>())
                UnityEngine.Object.DestroyImmediate(old.gameObject);

            // Replace Class 8A legacy 3x3 desks with the 4-group collaborative layout (12 students).
            Transform class8A = scene.GetRootGameObjects()
                .SelectMany(g => g.GetComponentsInChildren<Transform>(true))
                .First(t => t.name == "Class 8A");
            foreach (Transform child in class8A.Cast<Transform>().ToArray())
            {
                if (child.name.StartsWith("StudentDesk"))
                    UnityEngine.Object.DestroyImmediate(child.gameObject);
            }

            runtime = context.gameObject.AddComponent<SimulationRuntime>();
            runtime.scenario = definition;
            runtime.player = context.player;

            FrameworkHUD ui = context.gameObject.AddComponent<FrameworkHUD>();
            ui.runtime = runtime;
            runtime.ui = ui;

            SimulationAudio audio = context.gameObject.AddComponent<SimulationAudio>();
            audio.runtime = runtime;
            ui.audioCues = audio;

            context.player.interactionUI = ui;
            context.player.GetComponent<PlayerInteractor>().interactionUI = ui;
            context.gameObject.AddComponent<BrowserDiagnostics>().runtime = runtime;
            context.gameObject.name = "Shared Simulation";

            Transform root = new GameObject("Teacher Lesson · Class 8A").transform;

            // Courtyard field sample
            BuildCourtyardPuddle(root);

            // Staff Room stations (placed cleanly on existing staff desks & clear wall stands)
            Station("prev_work", "Bàn bài làm tiết trước · Lớp 8A",
                "Xấp bài kiểm tra tuần trước của Lớp 8A. Đọc trước giúp nhận ra kiểu hiểu nhầm hay gặp ở Minh và Nam.",
                new Vector3(12f, 0.90f, 11f), root, "Paper", new Vector3(0.65f, 0.12f, 0.42f), withSupport: false);

            Station("projector", "Máy chiếu · Soạn trình chiếu",
                "Đèn nguồn báo vàng nhấp nháy. Chuẩn bị trình chiếu giúp nhanh gọn nhưng có nguy cơ ngắt giữa tiết.",
                new Vector3(16f, 0.90f, 11f), root, "Steel", new Vector3(0.65f, 0.16f, 0.42f), withSupport: false);

            Station("printer", "Máy in · In phiếu & ảnh mẫu",
                "In phiếu bảng 2 cột và ảnh sân trường sau mưa cho cả 4 nhóm. Có tài liệu dự phòng khi thiết bị hỏng.",
                new Vector3(12f, 0.94f, 14.5f), root, "Paper", new Vector3(0.70f, 0.22f, 0.45f), withSupport: false);

            Station("group-kit", "Bộ thẻ & khay mẫu theo nhóm",
                "4 khay đất trồng, gạch lát, ống nhỏ giọt và thẻ phân lượt nói giúp học sinh ít phát biểu dễ tham gia.",
                new Vector3(16f, 0.92f, 14.5f), root, "Gold", new Vector3(0.70f, 0.18f, 0.45f), withSupport: false);

            Station("gear_check", "Khay mẫu & sổ thiết bị",
                "Kiểm tra bộ mẫu đất - gạch và sổ theo dõi kỹ thuật máy chiếu trước khi chọn phương án.",
                new Vector3(8.8f, 0.88f, 14.8f), root, "Teal trim", new Vector3(0.62f, 0.16f, 0.45f), withSupport: true);

            Station("staff_exit", "Bảng chuẩn bị · Sang Lớp 8A",
                "Mang toàn bộ học liệu bạn đã chuẩn bị qua hành lang để bắt đầu tiết học Lớp 8A.",
                new Vector3(7.0f, 0.90f, 10.4f), root, "Wood", new Vector3(0.55f, 0.18f, 0.40f), withSupport: true);

            // Classroom 8A: Board, Projection Screen, Teacher Desk & Tickets
            GameObject board = class8A.Cast<Transform>().First(t => t.name == "Teaching board").gameObject;
            AddStation(board, "board", "Bảng Lớp 8A",
                "Bảng phấn Lớp 8A. Tại đây bạn nêu nhiệm vụ, kiểm tra mức hiểu yêu cầu và minh họa sơ đồ khi cần.");

            // Clean Teacher Desk on front-left (-15.2, 16.5) facing class
            GameObject deskPrefab = AssetDatabase.LoadAssetAtPath<GameObject>(SchoolPrototypeGenerator.Root + "/Prefabs/StudentDesk.prefab");
            var teacherDesk = (GameObject)PrefabUtility.InstantiatePrefab(deskPrefab, class8A);
            teacherDesk.name = "Teacher Desk 8A";
            teacherDesk.transform.localPosition = new Vector3(-15.2f, 0f, 16.5f);
            teacherDesk.transform.localRotation = Quaternion.Euler(0, 180f, 0);

            Station("tickets", "Góc phiếu & câu hỏi cuối tiết",
                "Thu phiếu viết của 4 nhóm hoặc phát phiếu ngắn kiểm tra vận dụng ở cuối tiết KHTN Lớp 8A.",
                new Vector3(-15.2f, 0.90f, 16.45f), root, "Paper", new Vector3(0.62f, 0.14f, 0.40f), withSupport: false);

            var visuals = root.gameObject.AddComponent<TeacherLessonVisuals>();
            visuals.runtime = runtime;
            visuals.neutralMat = LoadMat("Paper");
            visuals.activeMat = LoadMat("Gold");
            visuals.warningMat = LoadMat("Red");
            visuals.verifiedMat = LoadMat("Foliage");

            visuals.printedMaterials = Prop("Printed materials on teacher desk", new Vector3(-14.65f, 0.85f, 16.5f), new Vector3(0.38f, 0.06f, 0.30f), "Paper", root);
            visuals.groupMaterials = Prop("Sample kit on teacher desk", new Vector3(-15.75f, 0.86f, 16.5f), new Vector3(0.38f, 0.08f, 0.30f), "Gold", root);

            // Projector screen & signal indicators on front wall
            GameObject screenObj = Prop("Projector Screen 8A", new Vector3(-8.0f, 2.0f, 17.72f), new Vector3(2.2f, 1.3f, 0.05f), "Paper", root);
            visuals.projectorScreen = screenObj;
            visuals.projectorScreenText = CreateWorldText("Projector Screen Text", "SO SÁNH BỀ MẶT", new Vector3(-8.0f, 2.0f, 17.67f), 0f, 0.024f, new Color(0.08f, 0.16f, 0.2f), root);
            visuals.projectorSignal = Prop("Projector active light", new Vector3(-8.0f, 2.75f, 17.68f), new Vector3(0.22f, 0.14f, 0.08f), "Foliage", root);
            visuals.projectorFaultSignal = Prop("Projector fault light", new Vector3(-8.0f, 2.75f, 17.68f), new Vector3(0.24f, 0.16f, 0.09f), "Red", root);

            visuals.boardText = board.transform.parent.GetComponentsInChildren<TextMesh>().First(t => t.text.StartsWith("CHÀO MỪNG"));

            // Build 4 Group Islands + 12 Students in Class 8A
            BuildGroupIsland(root, visuals, 0, "group1", "Nhóm 1", new Vector3(-14.5f, 0f, 13.8f),
                ("an", "An", false), ("linh", "Linh", true), ("binh", "Bình", false),
                "Nhóm 1", "Em ghi bảng so sánh ở nháp nhưng hơi ngại nói trước cả lớp.");

            BuildGroupIsland(root, visuals, 1, "group2", "Nhóm 2", new Vector3(-9.5f, 0f, 13.8f),
                ("vy", "Vy", false), ("nam", "Nam", true), ("khanh", "Khánh", false),
                "Nhóm 2", "Sân bê tông không thấm nên đọng nước, đất thấm hết ạ!");

            BuildGroupIsland(root, visuals, 2, "group3", "Nhóm 3", new Vector3(-14.5f, 0f, 10.6f),
                ("trang", "Trang", false), ("ha", "Hà", true), ("duc", "Đức", false),
                "Nhóm 3", "Em vẽ xong sơ đồ khe hạt đất rồi, để em giải thích thêm thành câu.");

            BuildGroupIsland(root, visuals, 3, "group4", "Nhóm 4", new Vector3(-9.5f, 0f, 10.6f),
                ("hung", "Hùng", false), ("minh", "Minh", true), ("thao", "Thảo", false),
                "Nhóm 4", "Nhóm em đang xem chỗ nào bẩn nhiều lá rụng để ghi vào phiếu...");

            // Cô Mai in Courtyard
            BuildNpc("mai", "Cô Mai", "Giáo viên hướng dẫn",
                "Cô sẽ cùng bạn nhận mục tiêu tiết KHTN Lớp 8A, chuẩn bị học liệu và nhìn lại bằng chứng cuối tiết.",
                new Vector3(-3f, 0f, -9f), new Vector3(-3f, 0f, -8f), 145f, seated: false, isBackground: false, groupId: "", root);

            // Rebuild full navigation clearance route including Staff Room & all 4 Class 8A group aisles
            RebuildRoutes(context);

            Physics.SyncTransforms();
            EditorSceneManager.SaveScene(scene, ScenePath);
            AssetDatabase.SaveAssets();
            EditorSceneManager.OpenScene(ScenePath);
            FrameworkValidator.ValidateBatch();
        }

        private static void BuildCourtyardPuddle(Transform parent)
        {
            Transform cluster = new GameObject("Courtyard Sample · Puddle").transform;
            cluster.SetParent(parent, false);

            // Shallow rain puddle on paving next to soil curb box
            GameObject puddle = Prop("Vũng nước mẫu sân trường", new Vector3(4.2f, 0.03f, -10.5f), new Vector3(1.25f, 0.05f, 1.05f), "Glass", cluster, collider: true);
            Prop("Viền đất tơi cạnh vũng", new Vector3(5.2f, 0.09f, -10.5f), new Vector3(0.75f, 0.18f, 1.05f), "Grass", cluster, collider: false);
            AddStation(puddle, "yard_puddle", "Vũng nước mẫu sân trường",
                "Một vũng nước nông còn đọng trên phần sân lát kín ngay cạnh bồn đất tơi sau cơn mưa sáng.");
            CreateWorldText("Sign · Mẫu sân trường", "Vũng nước mẫu [E]", new Vector3(4.2f, 0.65f, -10.5f), 0f, 0.025f, new Color(0.08f, 0.2f, 0.23f), cluster, faceCamera: true);
        }

        private static void BuildGroupIsland(
            Transform parent, TeacherLessonVisuals visuals, int index,
            string groupId, string groupName, Vector3 tableCenter,
            (string id, string name, bool focus) s0,
            (string id, string name, bool focus) s1,
            (string id, string name, bool focus) s2,
            string focusTitle, string focusIntro)
        {
            Transform island = new GameObject("Island · " + groupName).transform;
            island.SetParent(parent, false);

            // Group table (2.0 x 0.88m, tabletop y = 0.74m)
            Prop(groupName + " tabletop", tableCenter + Vector3.up * 0.74f, new Vector3(2.0f, 0.08f, 0.88f), "Wood", island, collider: true);
            foreach (float dx in new[] { -0.84f, 0.84f })
                Prop(groupName + " leg", tableCenter + new Vector3(dx, 0.35f, 0f), new Vector3(0.08f, 0.70f, 0.72f), "Steel", island, collider: true);

            // 3 student chairs behind table (z = tableCenter.z - 0.88)
            float seatZ = tableCenter.z - 0.88f;
            float[] xs = { tableCenter.x - 0.70f, tableCenter.x, tableCenter.x + 0.70f };
            for (int i = 0; i < 3; i++)
            {
                Prop(groupName + " seat " + i, new Vector3(xs[i], 0.44f, seatZ), new Vector3(0.46f, 0.06f, 0.42f), "Wood", island, collider: true);
                Prop(groupName + " seat leg " + i, new Vector3(xs[i], 0.21f, seatZ), new Vector3(0.36f, 0.42f, 0.34f), "Steel", island, collider: false);
                Prop(groupName + " seat back " + i, new Vector3(xs[i], 0.65f, seatZ - 0.19f), new Vector3(0.44f, 0.40f, 0.05f), "Wood", island, collider: false);
            }

            // Inspectable worksheet station on tabletop
            string sheetStationId = groupId + "_sheet";
            GameObject sheetObj = Prop("Phiếu " + groupName, tableCenter + new Vector3(0f, 0.81f, 0.12f), new Vector3(0.54f, 0.06f, 0.38f), "Paper", island, collider: true);
            DialogueStation sheetStation = AddStation(sheetObj, sheetStationId, "Phiếu & mẫu " + groupName,
                "Bài làm hiện tại của " + groupName + ".");
            sheetStation.groupId = groupId;

            // Visual marker cube + world cue label (no numeric scores!)
            GameObject marker = Prop(groupName + " status badge", tableCenter + new Vector3(0.68f, 0.81f, 0.20f), new Vector3(0.22f, 0.06f, 0.22f), "Paper", island, collider: false);
            visuals.groupStatusMarkers[index] = marker.GetComponent<Renderer>();

            GameObject printedExtra = Prop(groupName + " printed sheet", tableCenter + new Vector3(-0.60f, 0.80f, 0.10f), new Vector3(0.34f, 0.03f, 0.28f), "Paper", island, collider: false);
            visuals.groupPrintedSheets[index] = printedExtra;

            GameObject sampleTray = Prop(groupName + " sample tray", tableCenter + new Vector3(-0.58f, 0.82f, -0.18f), new Vector3(0.42f, 0.06f, 0.24f), "Gold", island, collider: false);
            visuals.groupSampleTrays[index] = sampleTray;

            visuals.groupBoardLabels[index] = CreateWorldText(
                groupName + " cue",
                groupName,
                tableCenter + new Vector3(0f, 1.05f, 0.35f),
                0f, 0.019f, new Color(0.07f, 0.20f, 0.23f), island, faceCamera: true);

            // Spawn the 3 students properly seated on the 3 chairs facing +z (yaw = 0)
            var members = new[] { s0, s1, s2 };
            for (int i = 0; i < 3; i++)
            {
                Vector3 seatPos = new Vector3(xs[i], 0f, seatZ);
                bool bg = !members[i].focus;
                BuildNpc(
                    members[i].id,
                    members[i].name,
                    bg ? "Học sinh " + groupName + " (Lớp 8A)" : focusTitle,
                    bg ? "Đang làm việc cùng " + groupName + "." : focusIntro,
                    seatPos, seatPos, 0f,
                    seated: true, isBackground: bg, groupId: groupId, parent);
            }
        }

        private static void BuildNpc(
            string id, string name, string role, string introduction,
            Vector3 from, Vector3 to, float yaw, bool seated, bool isBackground, string groupId, Transform parent)
        {
            Transform npc = new GameObject(name).transform;
            npc.SetParent(parent, false);
            npc.position = from;
            npc.rotation = Quaternion.Euler(0f, yaw, 0f);

            if (!isBackground)
            {
                CapsuleCollider collider = npc.gameObject.AddComponent<CapsuleCollider>();
                collider.radius = 0.24f;
                collider.height = 1.65f;
                collider.center = new Vector3(0f, 0.82f, 0f);

                DialogueStation station = AddStation(npc.gameObject, id, name + " · " + role, introduction);
                station.groupId = groupId;
                if (id == "mai")
                {
                    station.variantFlag = "assess.lesson_ended";
                    station.variantIntroduction = "Tiết học Lớp 8A vừa khép lại. Hãy mở bảng Phản tư để chọn sự kiện làm bằng chứng và trao đổi với cô.";
                }
            }

            NpcProfile profile = TeacherScenarioAssets.Profile(id, name, role, introduction, seated);

            Transform visual = new GameObject("Procedural blockout body").transform;
            visual.SetParent(npc, false);
            visual.localPosition = Vector3.zero;

            // Torso & head & limbs in local space relative to visual
            GameObject torso = GameObject.CreatePrimitive(PrimitiveType.Cube);
            torso.name = "Torso";
            torso.transform.SetParent(visual, false);
            torso.transform.localPosition = new Vector3(0f, 1.04f, 0f);
            torso.transform.localScale = new Vector3(0.36f, 0.56f, 0.22f);
            torso.GetComponent<Renderer>().sharedMaterial = LoadMat(seated ? "Paper" : "Indigo");
            UnityEngine.Object.DestroyImmediate(torso.GetComponent<Collider>());

            Transform headPivot = new GameObject("Head").transform;
            headPivot.SetParent(visual, false);
            headPivot.localPosition = new Vector3(0f, 1.48f, 0f);
            GameObject headMesh = GameObject.CreatePrimitive(PrimitiveType.Cube);
            headMesh.name = "Head mesh";
            headMesh.transform.SetParent(headPivot, false);
            headMesh.transform.localPosition = Vector3.zero;
            headMesh.transform.localScale = new Vector3(0.25f, 0.29f, 0.25f);
            headMesh.GetComponent<Renderer>().sharedMaterial = LoadMat("Skin");
            UnityEngine.Object.DestroyImmediate(headMesh.GetComponent<Collider>());

            ScheduledNpc brain = npc.gameObject.AddComponent<ScheduledNpc>();
            brain.profile = profile;
            brain.groupId = groupId;
            brain.isBackgroundStudent = isBackground;
            brain.runtime = runtime;
            brain.station = npc.GetComponent<DialogueStation>();
            brain.visual = visual;
            brain.head = headPivot;
            brain.leftArm = Part("Left arm", visual, new Vector3(-0.24f, 1.28f, 0f), new Vector3(0.11f, 0.48f, 0.11f), seated ? "Paper" : "Indigo");
            brain.rightArm = Part("Right arm", visual, new Vector3(0.24f, 1.28f, 0f), new Vector3(0.11f, 0.48f, 0.11f), seated ? "Paper" : "Indigo");
            brain.leftLeg = Part("Left leg", visual, new Vector3(-0.10f, 0.74f, 0f), new Vector3(0.13f, 0.68f, 0.14f), "Steel");
            brain.rightLeg = Part("Right leg", visual, new Vector3(0.10f, 0.74f, 0f), new Vector3(0.13f, 0.68f, 0.14f), "Steel");

            Transform wp0 = new GameObject(id + " waypoint 0").transform;
            wp0.SetParent(parent, false);
            wp0.position = from;
            wp0.rotation = Quaternion.Euler(0f, yaw, 0f);

            Transform wp1 = new GameObject(id + " waypoint 1").transform;
            wp1.SetParent(parent, false);
            wp1.position = to;
            wp1.rotation = Quaternion.Euler(0f, yaw, 0f);

            brain.waypoints = new[] { wp0, wp1 };

            if (!isBackground)
                CreateWorldText("Sign · " + name, name, new Vector3(0f, seated ? 1.66f : 1.92f, 0f), 0f, 0.022f, new Color(0.08f, 0.2f, 0.23f), npc, faceCamera: true);
        }

        private static void RebuildRoutes(PrototypeContext context)
        {
            foreach (Transform old in context.routeWaypoints ?? Array.Empty<Transform>())
                if (old != null) UnityEngine.Object.DestroyImmediate(old.gameObject);

            Vector3[] points =
            {
                new Vector3(0f, 0.06f, -17f),
                new Vector3(0f, 0.06f, -10.5f),
                new Vector3(2.8f, 0.06f, -10.5f),
                new Vector3(0f, 0.06f, -10.5f),
                new Vector3(0f, 0.06f, -9f),
                new Vector3(-1.8f, 0.06f, -9f),
                new Vector3(0f, 0.06f, -9f),
                new Vector3(0f, 0.06f, 5.5f),
                new Vector3(8.2f, 0.06f, 5.5f),
                new Vector3(8.2f, 0.06f, 9.2f),
                new Vector3(10.2f, 0.06f, 9.2f),
                new Vector3(10.2f, 0.06f, 12.5f),
                new Vector3(14.5f, 0.06f, 12.5f),
                new Vector3(10.2f, 0.06f, 12.5f),
                new Vector3(10.2f, 0.06f, 9.2f),
                new Vector3(8.2f, 0.06f, 9.2f),
                new Vector3(8.2f, 0.06f, 5.5f),
                new Vector3(-15.8f, 0.06f, 5.5f),
                new Vector3(-15.8f, 0.06f, 8.85f),
                new Vector3(-16.7f, 0.06f, 8.85f),
                new Vector3(-16.7f, 0.06f, 11.86f),
                new Vector3(-12.0f, 0.06f, 11.86f),
                new Vector3(-7.3f, 0.06f, 11.86f),
                new Vector3(-12.0f, 0.06f, 11.86f),
                new Vector3(-12.0f, 0.06f, 15.2f),
                new Vector3(-15.2f, 0.06f, 15.2f),
                new Vector3(-12.0f, 0.06f, 15.2f),
                new Vector3(-12.0f, 0.06f, 11.86f),
                new Vector3(-16.7f, 0.06f, 11.86f),
                new Vector3(-16.7f, 0.06f, 8.85f),
                new Vector3(-15.8f, 0.06f, 8.85f),
                new Vector3(-15.8f, 0.06f, 5.5f)
            };

            var route = new List<Transform>();
            for (int i = 0; i < points.Length; i++)
            {
                var marker = new GameObject("Route " + i.ToString("00")).transform;
                marker.SetParent(context.transform, false);
                marker.position = points[i];
                route.Add(marker);
            }
            context.routeWaypoints = route.ToArray();
        }

        private static Material LoadMat(string name) =>
            AssetDatabase.LoadAssetAtPath<Material>(SchoolPrototypeGenerator.Root + "/Materials/" + name + ".mat");

        private static GameObject Prop(string name, Vector3 position, Vector3 scale, string material, Transform parent, bool collider = false)
        {
            GameObject value = GameObject.CreatePrimitive(PrimitiveType.Cube);
            value.name = name;
            value.transform.SetParent(parent, false);
            value.transform.position = position;
            value.transform.localScale = scale;
            value.GetComponent<Renderer>().sharedMaterial = LoadMat(material);
            if (!collider) UnityEngine.Object.DestroyImmediate(value.GetComponent<Collider>());
            return value;
        }

        private static DialogueStation AddStation(GameObject obj, string id, string title, string introduction)
        {
            DialogueStation station = obj.AddComponent<DialogueStation>();
            station.stationId = id;
            station.title = title;
            station.introduction = introduction;
            station.runtime = runtime;
            return station;
        }

        private static void Station(string id, string title, string introduction, Vector3 position, Transform parent, string material, Vector3 scale, bool withSupport)
        {
            GameObject obj = Prop(title, position, scale, material, parent, collider: true);
            AddStation(obj, id, title, introduction);
            if (withSupport)
                Prop(title + " support", position + Vector3.down * 0.45f, new Vector3(0.14f, 0.78f, 0.14f), "Steel", parent, collider: true);
            CreateWorldText("Sign · " + title, title, position + Vector3.up * 0.48f, 0f, 0.020f, new Color(0.08f, 0.2f, 0.23f), parent, faceCamera: true);
        }

        private static Transform Part(string name, Transform parent, Vector3 localPosition, Vector3 scale, string material)
        {
            Transform pivot = new GameObject(name).transform;
            pivot.SetParent(parent, false);
            pivot.localPosition = localPosition;
            GameObject mesh = GameObject.CreatePrimitive(PrimitiveType.Cube);
            mesh.name = name + " mesh";
            mesh.transform.SetParent(pivot, false);
            mesh.transform.localScale = scale;
            mesh.transform.localPosition = new Vector3(0f, -scale.y * 0.5f, 0f);
            mesh.GetComponent<Renderer>().sharedMaterial = LoadMat(material);
            UnityEngine.Object.DestroyImmediate(mesh.GetComponent<Collider>());
            return pivot;
        }

        private static TextMesh CreateWorldText(string objectName, string text, Vector3 localOrWorldPos, float yaw, float charSize, Color color, Transform parent, bool faceCamera = false)
        {
            var label = new GameObject(objectName);
            label.transform.SetParent(parent, false);
            label.transform.localPosition = localOrWorldPos;
            label.transform.localRotation = Quaternion.Euler(0f, yaw, 0f);
            TextMesh mesh = label.AddComponent<TextMesh>();
            mesh.text = text;
            mesh.fontSize = 64;
            mesh.characterSize = charSize;
            mesh.font = Resources.GetBuiltinResource<Font>("LegacyRuntime.ttf");
            mesh.anchor = TextAnchor.MiddleCenter;
            mesh.alignment = TextAlignment.Center;
            mesh.color = color;
            label.GetComponent<MeshRenderer>().sharedMaterial = LoadMat("WorldText");
            label.AddComponent<WorldLabel>().faceCamera = faceCamera;
            return mesh;
        }
    }
}
