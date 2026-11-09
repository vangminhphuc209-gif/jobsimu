using System;
using System.Collections.Generic;
using System.IO;
using UnityEditor;
using UnityEditor.SceneManagement;
using UnityEngine;
using UnityEngine.Rendering;
using UnityEngine.Rendering.Universal;
using UnityEngine.SceneManagement;
using JobSimu.Core;
using JobSimu.Player;
using JobSimu.Interaction;
using JobSimu.Gameplay;
using JobSimu.NPC;

namespace JobSimu.Editor
{
    public static class SchoolPrototypeGenerator
    {
        public const string Root = "Assets/_JobSimu";
        public const string ScenePath = Root + "/Scenes/SchoolFirstPlayable.unity";
        private static readonly Dictionary<string, Material> Materials = new Dictionary<string, Material>();
        private static GameObject deskPrefab, benchPrefab, cabinetPrefab, doorPrefab;
        private static Material worldTextMaterial;

        [MenuItem("Tools/JobSimu/Generate School Prototype")]
        public static void Generate()
        {
            if (EditorApplication.isPlayingOrWillChangePlaymode) return;
            if (!EditorSceneManager.SaveCurrentModifiedScenesIfUserWantsTo()) return;
            GenerateAndSave();
        }

        // Batch entry point: never writes the original SchoolPrototype or existing generated scenes.
        public static void GenerateAndValidateBatch()
        {
            GenerateAndSave();
            PrototypeValidator.ValidateBatch();
        }

        public static void GenerateAndSave()
        {
            Scene scene = EditorSceneManager.NewScene(NewSceneSetup.EmptyScene, NewSceneMode.Single);
            BuildScene(scene);
            string path = AssetDatabase.GenerateUniqueAssetPath(ScenePath);
            if (!EditorSceneManager.SaveScene(scene, path)) throw new IOException("Cannot save " + path);
            AssetDatabase.SaveAssets();
            Debug.Log("JobSimu generated: " + path + ". Original scene preserved. Press Play, then click the view.");
        }

        public static PrototypeContext BuildScene(Scene scene)
        {
            SceneManager.SetActiveScene(scene);
            foreach (string folder in new[] { "Materials", "Prefabs", "Scenes", "Scripts/Core", "Scripts/Player",
                "Scripts/Interaction", "Scripts/NPC", "Scripts/Gameplay", "Editor", "Tests/EditMode" })
                Directory.CreateDirectory(Root + "/" + folder);
            AssetDatabase.Refresh();
            PrepareAssets();
            Transform environment = Group("School Environment", null);
            Transform courtyard = Group("01 · Courtyard and Gate", environment);
            Transform building = Group("02 · Classroom Building", environment);
            Transform furnishing = Group("03 · Furniture", environment);
            Transform landscaping = Group("04 · Landscaping", environment);
            Transform boundaries = Group("05 · Safety Boundaries", environment);
            Transform systems = Group("School Systems", null);
            PrototypeContext context = systems.gameObject.AddComponent<PrototypeContext>();
            context.environment = environment;
            context.orientation = systems.gameObject.AddComponent<TeacherOrientation>();
            context.hud = systems.gameObject.AddComponent<PrototypeHUD>();
            context.hud.orientation = context.orientation;

            BuildCourtyard(courtyard, landscaping, boundaries);
            BuildBuilding(building, furnishing, context.orientation);
            BuildMentor(context.orientation, environment);
            Transform spawn = Group("Courtyard Spawn", systems);
            spawn.position = new Vector3(0, 0.06f, -17);
            context.spawn = spawn;
            BuildPlayer(context);
            BuildLighting();

            // Safe paths sampled by validator; room doors are opened temporarily during validation.
            var route = new List<Transform>();
            Vector3[] points = {
                new Vector3(0, 0.06f, -17), new Vector3(0, 0.06f, -9),
                new Vector3(0, 0.06f, 5.5f), new Vector3(8.2f, 0.06f, 5.5f),
                new Vector3(8.2f, 0.06f, 9.2f), new Vector3(10.5f, 0.06f, 9.2f),
                new Vector3(10.5f, 0.06f, 10.8f), new Vector3(10.5f, 0.06f, 9.2f),
                new Vector3(8.2f, 0.06f, 9.2f), new Vector3(8.2f, 0.06f, 5.5f),
                new Vector3(-15.8f, 0.06f, 5.5f), new Vector3(-15.8f, 0.06f, 16.4f),
                new Vector3(-12, 0.06f, 16.4f), new Vector3(-15.8f, 0.06f, 16.4f),
                new Vector3(-15.8f, 0.06f, 5.5f), new Vector3(-3.8f, 0.06f, 5.5f),
                new Vector3(-3.8f, 0.06f, 16.4f), new Vector3(-3.8f, 0.06f, 5.5f)
            };
            for (int i = 0; i < points.Length; i++)
            {
                Transform marker = Group("Route " + i.ToString("00"), systems);
                marker.position = points[i]; route.Add(marker);
            }
            context.routeWaypoints = route.ToArray();
            Physics.SyncTransforms();
            return context;
        }

        private static void PrepareAssets()
        {
            Materials.Clear();
            string textPath = Root + "/Materials/WorldText.mat";
            worldTextMaterial = AssetDatabase.LoadAssetAtPath<Material>(textPath);
            if (worldTextMaterial == null)
            {
                worldTextMaterial = new Material(Shader.Find("JobSimu/WorldText"));
                AssetDatabase.CreateAsset(worldTextMaterial, textPath);
            }
            MakeMaterial("Warm plaster", "E8D8AE"); MakeMaterial("Teal trim", "327F82");
            MakeMaterial("Paving", "AAA99F"); MakeMaterial("Floor", "D2CABC");
            MakeMaterial("Terracotta", "A75139"); MakeMaterial("Wood", "A26D3F");
            MakeMaterial("Steel", "36474A"); MakeMaterial("Glass", "80B3BF", 0.7f);
            MakeMaterial("Foliage", "527D48"); MakeMaterial("Grass", "77965A");
            MakeMaterial("Chalkboard", "214F45"); MakeMaterial("Paper", "F2EAD6");
            MakeMaterial("Red", "BC3036"); MakeMaterial("Gold", "F4CE55");
            MakeMaterial("Skin", "D6AA87"); MakeMaterial("Indigo", "485A82");
            deskPrefab = Prefab("StudentDesk", () => {
                Transform root = Group("StudentDesk", null);
                Cube("Desktop", root, new Vector3(0, .76f, 0), new Vector3(1.65f, .1f, .62f), "Wood");
                foreach (float x in new[] { -.67f, .67f })
                {
                    Cube("Desk leg", root, new Vector3(x, .36f, 0), new Vector3(.07f, .72f, .48f), "Steel");
                    Cube("Seat support", root, new Vector3(x, .23f, -.63f), new Vector3(.08f, .46f, .32f), "Steel");
                }
                Cube("Bench seat", root, new Vector3(0, .47f, -.63f), new Vector3(1.65f, .08f, .36f), "Wood");
                return root.gameObject;
            });
            benchPrefab = Prefab("CourtyardBench", () => {
                Transform root = Group("CourtyardBench", null);
                Cube("Seat", root, new Vector3(0, .48f, 0), new Vector3(2, .12f, .6f), "Wood");
                Cube("Back", root, new Vector3(0, .85f, .26f), new Vector3(2, .6f, .1f), "Wood");
                foreach (float x in new[] { -.75f, .75f })
                    Cube("Leg", root, new Vector3(x, .24f, 0), new Vector3(.16f, .48f, .5f), "Steel");
                return root.gameObject;
            });
            cabinetPrefab = Prefab("StaffCabinet", () => {
                Transform root = Group("StaffCabinet", null);
                Cube("Cabinet", root, new Vector3(0, 1, 0), new Vector3(1.6f, 2, .5f), "Teal trim");
                for (int i = 0; i < 2; i++)
                    Cube("Door panel", root, new Vector3(-.4f + i * .8f, 1, -.27f), new Vector3(.75f, 1.85f, .04f), "Warm plaster");
                return root.gameObject;
            });
            doorPrefab = Prefab("SlidingClassroomDoor", () => {
                Transform root = Group("SlidingClassroomDoor", null);
                BoxCollider collider = root.gameObject.AddComponent<BoxCollider>();
                collider.size = new Vector3(1.58f, 2.4f, .12f);
                root.gameObject.AddComponent<SlidingDoor>().localTravel = new Vector3(1.8f, 0, 0);
                Cube("Leaf", root, Vector3.zero, collider.size, "Teal trim", false);
                Cube("Inset", root, new Vector3(0, .25f, -.07f), new Vector3(1.3f, 1.45f, .025f), "Wood", false);
                Cube("Handle", root, new Vector3(-.58f, -.12f, -.12f), new Vector3(.05f, .32f, .08f), "Gold", false);
                return root.gameObject;
            });
        }

        private static void BuildCourtyard(Transform yard, Transform plants, Transform bounds)
        {
            Cube("Campus ground", yard, new Vector3(0, -.2f, 0), new Vector3(50, .4f, 48), "Paving");
            Cube("Main walkway", yard, new Vector3(0, .006f, -9), new Vector3(7, .012f, 26), "Floor", false);
            for (int z = -21; z < 3; z += 3)
                Cube("Paving joint", yard, new Vector3(0, .018f, z), new Vector3(7, .012f, .035f), "Steel", false);
            foreach (float x in new[] { -22f, 22f })
            {
                Cube("Garden strip", plants, new Vector3(x, .1f, -7), new Vector3(4, .2f, 28), "Grass");
                for (int z = -17; z <= 1; z += 9)
                {
                    Primitive(PrimitiveType.Cylinder, "Tree trunk", plants, new Vector3(x, 1.4f, z), new Vector3(.35f, 1.4f, .35f), "Wood");
                    Primitive(PrimitiveType.Sphere, "Tree crown", plants, new Vector3(x, 3.7f, z), new Vector3(3.5f, 3.6f, 3.5f), "Foliage", false);
                    Instance(benchPrefab, plants, new Vector3(Mathf.Sign(x) * 18.8f, 0, z), x < 0 ? 270 : 90);
                }
            }
            foreach (float x in new[] { -4.6f, 4.6f })
                Cube("Gate pillar", yard, new Vector3(x, 2, -22), new Vector3(.65f, 4, .65f), "Warm plaster");
            Cube("Gate lintel", yard, new Vector3(0, 4, -22), new Vector3(10, .7f, .65f), "Teal trim");
            Label("TRƯỜNG THPT AN HÒA", yard, new Vector3(0, 4, -21.65f), 180, .21f, Color.white);
            foreach (float x in new[] { -14.8f, 14.8f })
                Cube("Boundary front", bounds, new Vector3(x, 1, -22), new Vector3(20, 2, .25f), "Warm plaster");
            Cube("Boundary west", bounds, new Vector3(-24.8f, 1.2f, 0), new Vector3(.25f, 2.4f, 48), "Warm plaster");
            Cube("Boundary east", bounds, new Vector3(24.8f, 1.2f, 0), new Vector3(.25f, 2.4f, 48), "Warm plaster");
            Cube("Boundary rear", bounds, new Vector3(0, 1.2f, 23.8f), new Vector3(50, 2.4f, .25f), "Warm plaster");
            GameObject gateLimit = Cube("Campus limit behind gate", bounds, new Vector3(0, 1.5f, -23.5f), new Vector3(10, 3, .2f), "Glass");
            gateLimit.GetComponent<Renderer>().enabled = false;
            Cube("Flag plinth", yard, new Vector3(11, .16f, -7), new Vector3(2.4f, .32f, 2.4f), "Floor");
            Primitive(PrimitiveType.Cylinder, "Flagpole", yard, new Vector3(11, 3.6f, -7), new Vector3(.09f, 3.3f, .09f), "Steel");
            Cube("Flag", yard, new Vector3(11.8f, 6, -7), new Vector3(1.6f, 1.05f, .035f), "Red", false);
            Star(yard, new Vector3(11.8f, 6, -7.025f));
            Label("CỔNG TRƯỜNG · KHUÔN VIÊN MÔ PHỎNG", yard, new Vector3(0, 2.7f, -22), 180, .12f, Color.white);
        }

        private static void BuildBuilding(Transform building, Transform furniture, TeacherOrientation orientation)
        {
            Cube("Ground floor", building, new Vector3(0, -.1f, 11), new Vector3(36.4f, .2f, 14), "Floor");
            Cube("Upper floor slab", building, new Vector3(0, 3.55f, 11), new Vector3(36.8f, .22f, 14.5f), "Floor");
            Cube("Roof", building, new Vector3(0, 7.05f, 11), new Vector3(37.4f, .25f, 15.2f), "Terracotta");
            for (int i = 0; i <= 3; i++)
                Cube("Room division", building, new Vector3(-18 + i * 12, 3.5f, 13), new Vector3(.22f, 7, 10), "Warm plaster");
            for (int floor = 0; floor < 2; floor++)
            {
                float y = floor * 3.65f;
                Cube("Rear sill", building, new Vector3(0, y + .55f, 18), new Vector3(36, 1.1f, .22f), "Warm plaster");
                Cube("Rear lintel", building, new Vector3(0, y + 2.95f, 18), new Vector3(36, .9f, .22f), "Warm plaster");
                for (int x = -18; x <= 18; x += 3)
                    Cube("Window pier", building, new Vector3(x, y + 1.8f, 18), new Vector3(.24f, 1.4f, .24f), "Warm plaster");
                for (int x = -17; x < 18; x += 3)
                {
                    Cube("Window glass", building, new Vector3(x + .5f, y + 1.8f, 18), new Vector3(2.7f, 1.4f, .08f), "Glass");
                    Cube("Window crossbar", building, new Vector3(x + .5f, y + 1.8f, 17.94f), new Vector3(2.7f, .05f, .08f), "Teal trim", false);
                }
                foreach (float center in new[] { -12f, 0f, 12f })
                {
                    float doorX = center - 3.8f;
                    Cube("Front left wall", building, new Vector3(center - 5.35f, y + 1.7f, 8), new Vector3(1.3f, 3.4f, .22f), "Warm plaster");
                    Cube("Door lintel", building, new Vector3(doorX, y + 2.95f, 8), new Vector3(1.8f, .9f, .22f), "Warm plaster");
                    Cube("Front sill", building, new Vector3(center + 1.5f, y + .55f, 8), new Vector3(9, 1.1f, .22f), "Warm plaster");
                    Cube("Front lintel", building, new Vector3(center + 1.5f, y + 2.95f, 8), new Vector3(9, .9f, .22f), "Warm plaster");
                    Cube("Door pocket wall", building, new Vector3(center - 2, y + 1.8f, 8), new Vector3(2, 1.4f, .22f), "Warm plaster");
                    Cube("Front window", building, new Vector3(center + 2.5f, y + 1.8f, 8), new Vector3(7, 1.4f, .08f), "Glass");
                    for (float x = center - 1; x <= center + 6; x += 1.75f)
                        Cube("Window frame", building, new Vector3(x, y + 1.8f, 7.93f), new Vector3(.07f, 1.4f, .08f), "Teal trim", false);
                    if (floor == 0)
                    {
                        Instance(doorPrefab, building, new Vector3(doorX, 1.2f, 7.72f));
                        Cube("Door rail", building, new Vector3(doorX + .9f, 2.45f, 7.72f), new Vector3(3.5f, .07f, .1f), "Steel", false);
                        string name = center < -6 ? "LỚP 10A1" : center < 6 ? "LỚP 10A2" : "PHÒNG GIÁO VIÊN";
                        Label(name, building, new Vector3(doorX, 2.95f, 7.85f), 0, .12f, new Color(.07f, .22f, .24f));
                    }
                    else Cube("Upper locked door", building, new Vector3(doorX, y + 1.2f, 7.9f), new Vector3(1.8f, 2.4f, .12f), "Teal trim");
                }
                foreach (int x in new[] { -18, -12, -6, -2, 2, 6, 12, 18 })
                    Cube("Corridor column", building, new Vector3(x, y + 1.7f, 4.3f), new Vector3(.35f, 3.4f, .35f), "Teal trim");
            }
            Cube("Upper guardrail", building, new Vector3(0, 4.65f, 4.15f), new Vector3(36, .1f, .12f), "Teal trim");
            for (int x = -18; x <= 18; x++)
                Cube("Upper railing", building, new Vector3(x, 4.14f, 4.15f), new Vector3(.06f, 1, .06f), "Teal trim", false);
            // Stairway is visibly gated; upper floor is not advertised as navigable.
            Transform stairs = Group("Stairs · closed for prototype", building);
            for (int i = 0; i < 18; i++)
                Cube("Step", stairs, new Vector3(20.2f, (i + 1) * .1f, 6 + i * .32f), new Vector3(2.4f, (i + 1) * .2f, .34f), "Floor");
            Cube("Stair landing", stairs, new Vector3(20, 3.55f, 12), new Vector3(4.2f, .22f, 1.5f), "Floor");
            Cube("Stair gate", stairs, new Vector3(20.2f, .75f, 5.7f), new Vector3(2.7f, 1.5f, .15f), "Teal trim");
            Label("TẦNG 2\nCHƯA MỞ", stairs, new Vector3(20.2f, 1.05f, 5.6f), 0, .13f, Color.white);
            Cube("Stair side safety", stairs, new Vector3(21.5f, 2, 9), new Vector3(.15f, 4, 7), "Warm plaster");
            Cube("Stair inner safety", stairs, new Vector3(18.85f, 2, 9), new Vector3(.15f, 4, 7), "Warm plaster");

            foreach (float center in new[] { -12f, 0f })
            {
                Transform room = Group(center < 0 ? "Class 10A1" : "Class 10A2", furniture);
                for (int row = 0; row < 3; row++)
                    for (int col = 0; col < 3; col++)
                        Instance(deskPrefab, room, new Vector3(center - 2.1f + col * 3, 0, 10.5f + row * 2));
                GameObject board = Cube("Teaching board", room, new Vector3(center, 1.85f, 17.8f), new Vector3(5.5f, 1.6f, .12f), "Chalkboard");
                Label("CHÀO MỪNG ĐẾN LỚP\nLắng nghe · Tôn trọng · Cùng học", room, new Vector3(center, 1.9f, 17.72f), 0, .16f, Color.white);
                Instance(deskPrefab, room, new Vector3(center + 4, 0, 16.5f), 180);
                if (center < 0)
                {
                    OrientationInteractable interactable = board.AddComponent<OrientationInteractable>();
                    interactable.orientation = orientation; interactable.action = OrientationAction.ClassroomBoard;
                    interactable.title = "Lớp 10A1 · Hoạt động mở đầu"; interactable.label = "Chuẩn bị hoạt động trên bảng";
                }
                for (int z = 11; z <= 15; z += 4)
                {
                    Cube("Ceiling light", room, new Vector3(center, 3.35f, z), new Vector3(1.2f, .06f, .18f), "Paper", false);
                    Primitive(PrimitiveType.Cylinder, "Fan hub", room, new Vector3(center + 2, 3.1f, z), new Vector3(.16f, .17f, .16f), "Steel", false);
                    Cube("Fan blades", room, new Vector3(center + 2, 2.96f, z), new Vector3(1.3f, .035f, .15f), "Teal trim", false);
                }
            }
            Transform staff = Group("Staff Room", furniture);
            foreach (float x in new[] { 12f, 16f })
                foreach (float z in new[] { 11f, 14.5f }) Instance(deskPrefab, staff, new Vector3(x, 0, z));
            foreach (float x in new[] { 9f, 12f, 15f }) Instance(cabinetPrefab, staff, new Vector3(x, 0, 17.4f));
            GameObject plan = Cube("Lesson plan · interact here", staff, new Vector3(12, .84f, 11), new Vector3(.65f, .06f, .45f), "Paper");
            OrientationInteractable pickup = plan.AddComponent<OrientationInteractable>();
            pickup.orientation = orientation; pickup.action = OrientationAction.LessonPlan;
            Label("KẾ HOẠCH ĐẦU GIỜ [E]", staff, new Vector3(12, 1.35f, 11), 0, .095f, new Color(.07f, .22f, .24f));
        }

        private static void BuildMentor(TeacherOrientation orientation, Transform environment)
        {
            Transform npc = Group("Cô Mai · placeholder", environment);
            npc.position = new Vector3(-3, 0, -9);
            npc.gameObject.AddComponent<MentorNPC>().orientation = orientation;
            CapsuleCollider collider = npc.gameObject.AddComponent<CapsuleCollider>();
            collider.center = new Vector3(0, .85f, 0); collider.height = 1.7f; collider.radius = .3f;
            Primitive(PrimitiveType.Capsule, "Body", npc, new Vector3(0, .78f, 0), new Vector3(.48f, .55f, .32f), "Indigo", false);
            Primitive(PrimitiveType.Sphere, "Head", npc, new Vector3(0, 1.55f, 0), new Vector3(.34f, .4f, .34f), "Skin", false);
            foreach (float x in new[] { -.12f, .12f })
                Cube("Shoe", npc, new Vector3(x, .08f, -.05f), new Vector3(.16f, .16f, .28f), "Steel", false);
            Label("CÔ MAI\nGiáo viên hướng dẫn", npc, new Vector3(0, 2.15f, 0), 0, .12f, new Color(.07f, .2f, .22f));
        }

        private static void BuildPlayer(PrototypeContext context)
        {
            Transform player = Group("Player · First Person", null);
            player.position = context.spawn.position;
            player.gameObject.layer = 2; // Ignore Raycast; controller still collides with the world.
            CharacterController capsule = player.gameObject.AddComponent<CharacterController>();
            capsule.height = 1.8f; capsule.radius = .3f; capsule.center = new Vector3(0, .9f, 0);
            capsule.stepOffset = .25f; capsule.skinWidth = .025f; capsule.slopeLimit = 45;
            FirstPersonController controller = player.gameObject.AddComponent<FirstPersonController>();
            Transform cameraObject = Group("First Person Camera", player);
            cameraObject.localPosition = new Vector3(0, 1.65f, 0);
            Camera camera = cameraObject.gameObject.AddComponent<Camera>();
            camera.tag = "MainCamera"; camera.nearClipPlane = .06f; camera.farClipPlane = 100;
            camera.fieldOfView = 72; camera.allowHDR = false;
            camera.backgroundColor = new Color(.68f, .8f, .89f);
            camera.clearFlags = CameraClearFlags.SolidColor;
            cameraObject.gameObject.AddComponent<AudioListener>();
            camera.GetUniversalAdditionalCameraData().renderPostProcessing = false;
            controller.viewCamera = camera; controller.spawn = context.spawn; controller.hud = context.hud;
            PlayerInteractor interactor = player.gameObject.AddComponent<PlayerInteractor>();
            interactor.player = controller; interactor.viewCamera = camera; interactor.hud = context.hud;
            context.player = controller; context.playerCamera = camera;
        }

        private static void BuildLighting()
        {
            Transform sun = Group("Daylight", null);
            sun.rotation = Quaternion.Euler(48, -35, 0);
            Light light = sun.gameObject.AddComponent<Light>();
            light.type = LightType.Directional; light.intensity = 1.15f;
            light.color = new Color(1, .95f, .83f); light.shadows = LightShadows.Soft;
            light.shadowStrength = .65f; light.shadowBias = .05f;
            RenderSettings.sun = light;
            RenderSettings.ambientMode = AmbientMode.Trilight;
            RenderSettings.ambientSkyColor = new Color(.72f, .8f, .88f);
            RenderSettings.ambientEquatorColor = new Color(.55f, .59f, .6f);
            RenderSettings.ambientGroundColor = new Color(.36f, .34f, .3f);
            RenderSettings.fog = false;
        }

        private static Material MakeMaterial(string name, string hex, float smoothness = .15f)
        {
            string path = Root + "/Materials/" + name + ".mat";
            Material material = AssetDatabase.LoadAssetAtPath<Material>(path);
            if (material == null)
            {
                Shader shader = Shader.Find("Universal Render Pipeline/Lit");
                if (shader == null) throw new InvalidOperationException("URP/Lit shader is not installed.");
                material = new Material(shader) { name = name, enableInstancing = true };
                ColorUtility.TryParseHtmlString("#" + hex, out Color color);
                material.SetColor("_BaseColor", color); material.SetFloat("_Smoothness", smoothness);
                AssetDatabase.CreateAsset(material, path);
            }
            Materials[name] = material;
            return material;
        }

        private static GameObject Prefab(string name, Func<GameObject> factory)
        {
            string path = Root + "/Prefabs/" + name + ".prefab";
            GameObject existing = AssetDatabase.LoadAssetAtPath<GameObject>(path);
            if (existing != null) return existing; // Preserve user edits to generated reusable assets.
            GameObject source = factory();
            GameObject result = PrefabUtility.SaveAsPrefabAsset(source, path);
            UnityEngine.Object.DestroyImmediate(source);
            return result;
        }

        private static Transform Group(string name, Transform parent)
        {
            var obj = new GameObject(name); obj.transform.SetParent(parent, false); return obj.transform;
        }
        private static GameObject Cube(string name, Transform parent, Vector3 position, Vector3 scale,
            string material, bool collision = true) => Primitive(PrimitiveType.Cube, name, parent, position, scale, material, collision);
        private static GameObject Primitive(PrimitiveType type, string name, Transform parent, Vector3 position,
            Vector3 scale, string material, bool collision = true)
        {
            GameObject obj = GameObject.CreatePrimitive(type); obj.name = name;
            obj.transform.SetParent(parent, false); obj.transform.localPosition = position; obj.transform.localScale = scale;
            obj.GetComponent<Renderer>().sharedMaterial = Materials[material];
            if (!collision) UnityEngine.Object.DestroyImmediate(obj.GetComponent<Collider>());
            return obj;
        }
        private static GameObject Instance(GameObject prefab, Transform parent, Vector3 position, float yaw = 0)
        {
            var instance = (GameObject)PrefabUtility.InstantiatePrefab(prefab, parent);
            instance.transform.localPosition = position; instance.transform.localRotation = Quaternion.Euler(0, yaw, 0);
            return instance;
        }
        private static void Label(string text, Transform parent, Vector3 position, float yaw, float size, Color color)
        {
            Transform obj = Group("Sign · " + text.Replace('\n', ' '), parent);
            obj.localPosition = position; obj.localRotation = Quaternion.Euler(0, yaw, 0);
            TextMesh label = obj.gameObject.AddComponent<TextMesh>();
            label.text = text; label.fontSize = 64; label.characterSize = size * .25f;
            label.anchor = TextAnchor.MiddleCenter; label.alignment = TextAlignment.Center; label.color = color;
            label.font = Resources.GetBuiltinResource<Font>("LegacyRuntime.ttf");
            obj.GetComponent<MeshRenderer>().sharedMaterial = worldTextMaterial;
            obj.gameObject.AddComponent<WorldLabel>();
        }
        private static void Star(Transform parent, Vector3 position)
        {
            string path = Root + "/Materials/FlagStarFront.asset";
            Mesh mesh = AssetDatabase.LoadAssetAtPath<Mesh>(path);
            if (mesh == null)
            {
                var vertices = new Vector3[11]; var triangles = new int[30];
                for (int i = 0; i < 10; i++)
                {
                    float angle = (90 + i * 36) * Mathf.Deg2Rad, radius = i % 2 == 0 ? .31f : .125f;
                    vertices[i + 1] = new Vector3(Mathf.Cos(angle), Mathf.Sin(angle), 0) * radius;
                    int next = (i + 1) % 10 + 1;
                    triangles[i * 3] = 0; triangles[i * 3 + 1] = next; triangles[i * 3 + 2] = i + 1;
                }
                mesh = new Mesh { name = "Five point star", vertices = vertices, triangles = triangles };
                mesh.RecalculateNormals(); mesh.RecalculateBounds(); AssetDatabase.CreateAsset(mesh, path);
            }
            Transform obj = Group("Flag star", parent); obj.localPosition = position;
            obj.gameObject.AddComponent<MeshFilter>().sharedMesh = mesh;
            obj.gameObject.AddComponent<MeshRenderer>().sharedMaterial = Materials["Gold"];
            Transform back = Group("Flag star reverse", parent);
            back.localPosition = position + Vector3.forward * .05f;
            back.localRotation = Quaternion.Euler(0, 180, 0);
            back.gameObject.AddComponent<MeshFilter>().sharedMesh = mesh;
            back.gameObject.AddComponent<MeshRenderer>().sharedMaterial = Materials["Gold"];
        }
    }
}
