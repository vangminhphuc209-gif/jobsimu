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
        public const float PassiveMinuteRealSeconds = 25f;

        public ScenarioDefinition scenario;
        public FirstPersonController player;
        public FrameworkHUD ui;
        public ScenarioSession Session { get; private set; }
        public LocalSaveStore Store { get; private set; }
        public bool Started { get; private set; }
        public bool ApplicationPaused { get; private set; }
        public string SaveStatus { get; private set; } = "Lưu cục bộ trên thiết bị · chưa đồng bộ tài khoản";
        public event Action StateChanged;

        private float clockAccumulator;
        public float ClockAccumulator => clockAccumulator;

        private void Awake()
        {
            Store = new LocalSaveStore(scenario.id);
            ReplaceSession(new ScenarioSession(scenario));
        }

        public void StartNew(bool confirmOverwriteExisting = true)
        {
            clockAccumulator = 0f;
            ReplaceSession(new ScenarioSession(scenario));
            Started = true;
            if (player != null) player.Respawn();
            if (confirmOverwriteExisting) Save(allowOverwriteWithEmpty: true);
            StateChanged?.Invoke();
        }

        public bool Resume()
        {
            if (!Store.Read(scenario, out ScenarioSession session, out float restoredAccumulator, out string message))
            {
                SaveStatus = message;
                return false;
            }
            ReplaceSession(session);
            clockAccumulator = restoredAccumulator;
            SaveStatus = message;
            Started = true;
            if (player != null) player.Respawn();
            StateChanged?.Invoke();
            return true;
        }

        private void ReplaceSession(ScenarioSession session)
        {
            if (Session != null) Session.Changed -= OnSessionChanged;
            Session = session;
            Session.Changed += OnSessionChanged;
        }

        private void OnSessionChanged()
        {
            if (Started && Session.Records.Count > 0) Save(allowOverwriteWithEmpty: false);
            StateChanged?.Invoke();
        }

        public bool Perform(string actionId)
        {
            if (!Started) return false;
            bool ok = Session.TryPerform(actionId);
            // Reset passive drift whenever an action consumes time so the same period is never double-counted.
            if (ok) clockAccumulator = 0f;
            return ok;
        }

        public void UpdateReflection(ReflectionInput input, bool recordForReplay = true)
        {
            if (!Started || Session == null) return;
            Session.SetReflection(input, recordForReplay);
        }

        public void Save(bool allowOverwriteWithEmpty = false)
        {
            if (Store == null || Session == null) return;
            Store.Write(Session, clockAccumulator, out string message, allowOverwriteWithEmpty);
            SaveStatus = message;
        }

        private void OnApplicationPause(bool paused)
        {
            ApplicationPaused = paused;
            if (paused && Started && Session != null && Session.Records.Count > 0)
                Save(allowOverwriteWithEmpty: false);
        }

        private void OnApplicationFocus(bool focused)
        {
            if (!focused && Started && Session != null && Session.Records.Count > 0)
                Save(allowOverwriteWithEmpty: false);
        }

        private void OnDestroy()
        {
            if (Session != null) Session.Changed -= OnSessionChanged;
        }

        private void Update() => TickClock(Time.deltaTime, Application.isFocused);

        /// <summary>Deterministic clock step exposed for PlayMode/EditMode tests.</summary>
        public void TickClock(float deltaTime, bool isFocused)
        {
            if (!Started || Session == null || Session.Complete || Session.Phase == LessonPhase.Reflection) return;
            if (ApplicationPaused || !isFocused || (ui != null && ui.BlocksGameplay)) return;

            clockAccumulator += Mathf.Max(0f, deltaTime);
            if (clockAccumulator >= PassiveMinuteRealSeconds)
            {
                int wholeMinutes = Mathf.FloorToInt(clockAccumulator / PassiveMinuteRealSeconds);
                clockAccumulator -= wholeMinutes * PassiveMinuteRealSeconds;
                Session.AdvancePassiveMinutes(wholeMinutes);
            }
        }
    }
}
