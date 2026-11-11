using System;
using System.Collections.Generic;
using System.IO;
using System.Linq;
using UnityEditor;
using UnityEngine;
using UnityEngine.SceneManagement;
using JobSimu.Core;
using JobSimu.Dialogue;
using JobSimu.Gameplay;
using JobSimu.NPC;

namespace JobSimu.Editor
{
    public static class FrameworkValidator
    {
        [MenuItem("Tools/JobSimu/Validate Current Scene")]
        public static void Menu()
        {
            if (UnityEngine.Object.FindAnyObjectByType<SimulationRuntime>() == null) { PrototypeValidator.ValidateMenu(); return; }
            ValidateBatch();
        }

        public static List<string> Validate(Scene scene)
        {
            List<string> errors = PrototypeValidator.Validate(scene, false);
            SimulationRuntime runtime = scene.GetRootGameObjects().SelectMany(g => g.GetComponentsInChildren<SimulationRuntime>()).SingleOrDefault();
            if (runtime == null) { errors.Add("Shared runtime missing."); return errors; }
            if (runtime.scenario == null) { errors.Add("Scenario missing."); return errors; }
            errors.AddRange(runtime.scenario.ValidateDefinition());
            if (runtime.ui == null || runtime.player == null || runtime.player.interactionUI != runtime.ui)
                errors.Add("Shared UI/player wiring invalid.");

            DialogueStation[] stations = scene.GetRootGameObjects().SelectMany(g => g.GetComponentsInChildren<DialogueStation>()).ToArray();
            foreach (var action in runtime.scenario.actions)
                if (!stations.Any(s => s.stationId == action.stationId))
                    errors.Add("No world station for " + action.id + " (" + action.stationId + ")");

            if (stations.Select(s => s.stationId).Distinct().Count() != stations.Length)
                errors.Add("Duplicate station IDs.");
            foreach (DialogueStation station in stations)
                if (station.runtime != runtime || station.GetComponent<Collider>() == null)
                    errors.Add("Station wiring/collider missing: " + station.stationId);

            ScheduledNpc[] npcs = scene.GetRootGameObjects().SelectMany(g => g.GetComponentsInChildren<ScheduledNpc>()).ToArray();
            foreach (string id in runtime.scenario.participants)
                if (!npcs.Any(n => n.profile != null && n.profile.id == id))
                    errors.Add("NPC participant missing: " + id);

            foreach (ScheduledNpc npc in npcs)
            {
                bool stationMissing = !npc.isBackgroundStudent && npc.station == null;
                if (npc.profile == null || stationMissing || npc.runtime != runtime || npc.visual == null ||
                    npc.leftArm == null || npc.rightArm == null || npc.leftLeg == null || npc.rightLeg == null)
                {
                    errors.Add("NPC wiring invalid: " + npc.name);
                    continue;
                }
                if (npc.waypoints == null || npc.profile.schedule.Any(s => s.waypoint < 0 || s.waypoint >= npc.waypoints.Length || npc.waypoints[s.waypoint] == null))
                    errors.Add("NPC schedule points invalid: " + npc.name);
            }

            TeacherLessonVisuals visuals = scene.GetRootGameObjects().SelectMany(g => g.GetComponentsInChildren<TeacherLessonVisuals>()).SingleOrDefault();
            if (visuals == null || visuals.boardText == null || visuals.projectorScreen == null ||
                visuals.groupBoardLabels == null || visuals.groupBoardLabels.Length != 4 || visuals.groupBoardLabels.Any(l => l == null))
                errors.Add("TeacherLessonVisuals references incomplete.");

            foreach (TextMesh label in scene.GetRootGameObjects().SelectMany(g => g.GetComponentsInChildren<TextMesh>(true)))
            {
                string t = label.text ?? "";
                if (t.Contains("10A1") || t.Contains("10A2") || t.Contains("THPT") || t.Contains("An Hòa") || t.Contains("9A"))
                    errors.Add("Stale inconsistent school/class label found: " + t);
            }

            return errors;
        }

        public static void ValidateBatch()
        {
            List<string> errors = Validate(SceneManager.GetActiveScene());
            Directory.CreateDirectory("Logs");
            File.WriteAllText("Logs/framework-validation.txt", errors.Count == 0
                ? "PASS: shared scene, Class 8A content, 13 NPCs, stations, visuals, spawn and routes.\n"
                : string.Join("\n", errors));
            if (errors.Count != 0) throw new InvalidOperationException(string.Join("\n", errors));
            Debug.Log("JobSimu framework validation PASS.");
        }
    }
}
