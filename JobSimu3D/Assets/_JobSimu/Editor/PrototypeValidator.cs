using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using UnityEditor;
using UnityEngine;
using UnityEngine.Rendering;
using UnityEngine.SceneManagement;
using JobSimu.Core;
using JobSimu.Interaction;
using JobSimu.NPC;

namespace JobSimu.Editor
{
    public static class PrototypeValidator
    {
        [MenuItem("Tools/JobSimu/Validate Prototype")]
        public static void ValidateMenu()
        {
            List<string> errors = Validate(SceneManager.GetActiveScene());
            foreach (string error in errors) Debug.LogError("JobSimu: " + error);
            if (errors.Count == 0) Debug.Log("JobSimu validation PASS: scene, input, references, spawn, doors, NPC, route clearance and missing scripts.");
            EditorUtility.DisplayDialog("JobSimu validation", errors.Count == 0 ?
                "PASS — kiểm tra cấu trúc và lối đi. Vẫn cần Play test và đo hiệu năng trên trình duyệt." :
                string.Join("\n", errors), "OK");
        }

        public static void ValidateBatch()
        {
            Scene scene = SceneManager.GetActiveScene();
            List<string> errors = Validate(scene);
            Directory.CreateDirectory("Logs");
            File.WriteAllText("Logs/JobSimu-validation.txt", "Scene: " + scene.path + "\n" +
                (errors.Count == 0 ? "PASS: structural and physics clearance checks\n" : string.Join("\n", errors)) +
                "\nRenderers: " + Components<Renderer>(scene).Count +
                "\nColliders: " + Components<Collider>(scene).Count +
                "\nThese checks do not measure browser FPS or verify visual quality.\n");
            if (errors.Count > 0) throw new InvalidOperationException(string.Join("\n", errors));
            Debug.Log("JobSimu validation PASS: " + scene.path);
        }

        public static List<string> Validate(Scene scene, bool requireLegacyContent = true)
        {
            var errors = new List<string>();
            if (!File.Exists("Assets/SchoolPrototype.unity")) errors.Add("Original main scene missing: Assets/SchoolPrototype.unity");
            if (!scene.IsValid() || !scene.isLoaded) { errors.Add("No loaded scene."); return errors; }
            List<PrototypeContext> contexts = Components<PrototypeContext>(scene);
            if (contexts.Count != 1) { errors.Add("Expected exactly one PrototypeContext."); return errors; }
            PrototypeContext context = contexts[0];
            if (context.environment == null) errors.Add("Environment root is unassigned.");
            if (context.player == null || context.playerCamera == null) errors.Add("Player or camera is unassigned.");
            if (context.hud == null || context.orientation == null) errors.Add("HUD or orientation is unassigned.");
            if (context.spawn == null) errors.Add("Spawn is unassigned.");
            if (Components<Camera>(scene).Count(c => c.enabled) != 1) errors.Add("Expected exactly one active camera.");
            if (Components<SlidingDoor>(scene).Count < 3) errors.Add("Expected classroom and staff-room doors.");
            if (requireLegacyContent && Components<MentorNPC>(scene).Count < 1) errors.Add("No mentor NPC.");
            if (GraphicsSettings.currentRenderPipeline == null) errors.Add("URP is not assigned in Graphics/Quality settings.");
#if !ENABLE_INPUT_SYSTEM
            errors.Add("Active Input Handling must enable the Input System package.");
#endif
            if (context.player != null)
            {
                CharacterController controller = context.player.GetComponent<CharacterController>();
                if (controller == null || !controller.enabled) errors.Add("Player CharacterController missing/disabled.");
                if (context.player.viewCamera != context.playerCamera || context.player.spawn != context.spawn || context.player.hud != context.hud)
                    errors.Add("Player references do not match PrototypeContext.");
                PlayerInteractor interaction = context.player.GetComponent<PlayerInteractor>();
                if (interaction == null || interaction.viewCamera != context.playerCamera || interaction.hud != context.hud || interaction.player != context.player)
                    errors.Add("Interactor references are invalid.");
            }
            foreach (GameObject root in scene.GetRootGameObjects())
                foreach (Transform child in root.GetComponentsInChildren<Transform>(true))
                    if (GameObjectUtility.GetMonoBehavioursWithMissingScriptCount(child.gameObject) > 0)
                        errors.Add("Missing script: " + child.name);
            foreach (Renderer renderer in Components<Renderer>(scene))
                if (renderer.sharedMaterials.Any(m => m == null || m.shader == null || m.shader.name == "Hidden/InternalErrorShader"))
                    errors.Add("Invalid material: " + renderer.name);
            Physics.SyncTransforms();
            if (context.spawn != null && context.player != null)
            {
                string obstruction = ObstructionAt(context, context.spawn.position);
                if (obstruction != null) errors.Add("Spawn obstructed by " + obstruction);
                if (!HasGround(context.spawn.position)) errors.Add("Spawn has no support.");
                ValidateRoutes(context, errors);
            }
            return errors;
        }

        private static void ValidateRoutes(PrototypeContext context, List<string> errors)
        {
            if (context.routeWaypoints == null || context.routeWaypoints.Length < 2)
            { errors.Add("Route markers missing."); return; }
            List<SlidingDoor> doors = Components<SlidingDoor>(context.gameObject.scene);
            Vector3[] positions = doors.Select(d => d.transform.localPosition).ToArray();
            try
            {
                // Simulate their open position without disabling collision: catches bad door pockets too.
                foreach (SlidingDoor door in doors) door.transform.localPosition += door.localTravel;
                Physics.SyncTransforms();
                for (int segment = 1; segment < context.routeWaypoints.Length; segment++)
                {
                    if (context.routeWaypoints[segment - 1] == null || context.routeWaypoints[segment] == null)
                    { errors.Add("Route contains an unassigned waypoint."); continue; }
                    Vector3 a = context.routeWaypoints[segment - 1].position, b = context.routeWaypoints[segment].position;
                    int samples = Mathf.CeilToInt(Vector3.Distance(a, b) / .2f);
                    for (int i = 0; i <= samples; i++)
                    {
                        Vector3 point = Vector3.Lerp(a, b, samples == 0 ? 0 : (float)i / samples);
                        string obstruction = ObstructionAt(context, point);
                        if (obstruction != null || !HasGround(point))
                        {
                            errors.Add("Route " + (segment - 1) + " → " + segment + " blocked at " + point +
                                ": " + (obstruction ?? "no ground")); break;
                        }
                    }
                }
            }
            finally
            {
                for (int i = 0; i < doors.Count; i++) doors[i].transform.localPosition = positions[i];
                Physics.SyncTransforms();
            }
        }

        public static string ObstructionAt(PrototypeContext context, Vector3 feet)
        {
            CharacterController controller = context.player.GetComponent<CharacterController>();
            float radius = controller.radius;
            Vector3 center = feet + controller.center;
            float offset = controller.height * .5f - radius;
            foreach (Collider collider in Physics.OverlapCapsule(center + Vector3.up * offset,
                center - Vector3.up * offset, radius, ~0, QueryTriggerInteraction.Ignore))
                if (!collider.transform.IsChildOf(context.player.transform)) return collider.name;
            return null;
        }
        private static bool HasGround(Vector3 feet) => Physics.Raycast(feet + Vector3.up * .15f,
            Vector3.down, .5f, Physics.DefaultRaycastLayers, QueryTriggerInteraction.Ignore);
        private static List<T> Components<T>(Scene scene) where T : Component => scene.GetRootGameObjects()
            .SelectMany(root => root.GetComponentsInChildren<T>(true)).ToList();
    }
}
