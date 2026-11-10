using System;
using System.Linq;
using UnityEngine;
using UnityEngine.Profiling;
using JobSimu.Dialogue;

namespace JobSimu.Core
{
    /// <summary>Read-only development-build probe. No teleport, action execution, save edits or credentials.</summary>
    public sealed class BrowserDiagnostics : MonoBehaviour
    {
        public SimulationRuntime runtime;
        private readonly float[] frameTimes = new float[120];
        private int cursor, samples;
        private void Update()
        {
            if (!Debug.isDebugBuild) return;
            frameTimes[cursor++ % frameTimes.Length] = Time.unscaledDeltaTime;
            samples = Mathf.Min(samples + 1, frameTimes.Length);
        }
        [Serializable] private sealed class StationInfo { public string id; public Vector3 position; }
        [Serializable] private sealed class Snapshot
        {
            public string tag, step, page, station, saveStatus;
            public Vector3 player, camera, angles;
            public bool ready, locked, complete;
            public int evidence, reflectionLength;
            public float sampledFps, allocatedMemoryMB;
            public string[] flags;
            public StationInfo[] stations;
        }
        public void Dump(string tag)
        {
            if (!Debug.isDebugBuild || runtime.Session == null) return;
            var snapshot = new Snapshot {
                ready = UnityEngine.Rendering.SplashScreen.isFinished,
                tag = tag, step = runtime.Session.StepId, page = runtime.ui.Page.ToString(),
                station = runtime.ui.CurrentStation != null ? runtime.ui.CurrentStation.stationId : "",
                saveStatus = runtime.SaveStatus, player = runtime.player.transform.position,
                camera = runtime.player.viewCamera.transform.position, angles = runtime.player.viewCamera.transform.eulerAngles,
                locked = Cursor.lockState == CursorLockMode.Locked, complete = runtime.Session.Complete,
                evidence = runtime.Session.Evidence.Count, reflectionLength = runtime.Reflection.Length,
                sampledFps = samples == 0 ? 0 : samples / frameTimes.Take(samples).Sum(),
                allocatedMemoryMB = Profiler.GetTotalAllocatedMemoryLong() / (1024f * 1024f),
                flags = runtime.Session.Flags.ToArray(),
                stations = FindObjectsByType<DialogueStation>().Select(s => new StationInfo { id = s.stationId, position = s.GetComponent<Collider>().bounds.center }).ToArray()
            };
            Debug.Log("JOBSIMU_SNAPSHOT " + JsonUtility.ToJson(snapshot));
        }
    }
}
