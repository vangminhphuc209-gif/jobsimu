using System;
using UnityEngine;
using JobSimu.Quests;
using JobSimu.Consequences;
using JobSimu.SaveSystem;
using JobSimu.Player;
using JobSimu.UI;

namespace JobSimu.Core
{
    public sealed class SimulationRuntime : MonoBehaviour
    {
        public ScenarioDefinition scenario;
        public FirstPersonController player;
        public FrameworkHUD ui;
        public ScenarioSession Session { get; private set; }
        public LocalSaveStore Store { get; private set; }
        public bool Started { get; private set; }
        public string Reflection { get; set; } = "";
        public string SaveStatus { get; private set; } = "Lưu trên thiết bị · chưa đồng bộ tài khoản";
        public event Action StateChanged;
        private float clockAccumulator;
        private void Awake()
        {
            Store = new LocalSaveStore(scenario.id);
            ReplaceSession(new ScenarioSession(scenario));
        }
        public void StartNew()
        {
            Reflection = ""; clockAccumulator = 0;
            ReplaceSession(new ScenarioSession(scenario));
            Started = true; player.Respawn(); Save(); StateChanged?.Invoke();
        }
        public bool Resume()
        {
            if (!Store.Read(scenario, out ScenarioSession session, out string reflection, out string message))
            { SaveStatus = message; return false; }
            ReplaceSession(session); Reflection = reflection; SaveStatus = message;
            Started = true; clockAccumulator = 0; player.Respawn(); StateChanged?.Invoke(); return true;
        }
        private void ReplaceSession(ScenarioSession session)
        {
            if (Session != null) Session.Changed -= OnSessionChanged;
            Session = session; Session.Changed += OnSessionChanged;
        }
        private void OnSessionChanged() { Save(); StateChanged?.Invoke(); }
        public bool Perform(string actionId) => Started && Session.TryPerform(actionId);
        public void Save() { Store.Write(Session, Reflection, out string message); SaveStatus = message; }
        private void OnApplicationPause(bool paused) { if (paused && Started) Save(); }
        private void OnApplicationFocus(bool focused) { if (!focused && Started) Save(); }
        private void OnDestroy() { if (Session != null) Session.Changed -= OnSessionChanged; }
        private void Update()
        {
            if (!Started || ui.BlocksGameplay || Session.Complete || !Application.isFocused) return;
            clockAccumulator += Time.deltaTime;
            if (clockAccumulator >= 12f) { clockAccumulator -= 12f; Session.AdvanceMinutes(1); }
        }
    }
}
