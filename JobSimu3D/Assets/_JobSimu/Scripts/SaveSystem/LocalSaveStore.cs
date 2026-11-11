using System;
using System.Collections.Generic;
using System.Linq;
using UnityEngine;
using JobSimu.Quests;
using JobSimu.Consequences;

namespace JobSimu.SaveSystem
{
    [Serializable]
    public sealed class SaveEnvelope
    {
        public int schemaVersion;
        public string scenarioId, contentVersion;
        public int seed;
        public int expectedMinute;
        public string expectedPhase;
        public float clockAccumulatorSeconds;
        public string[] firedEvents;
        public ReflectionInput reflection;
        public List<SessionRecord> records;
    }

    public static class SaveCodec
    {
        public const int CurrentSchemaVersion = 2;

        public static string Encode(ScenarioSession session, float clockAccumulatorSeconds = 0f)
        {
            if (session == null) throw new ArgumentNullException(nameof(session));
            var envelope = new SaveEnvelope
            {
                schemaVersion = CurrentSchemaVersion,
                scenarioId = session.Definition.id,
                contentVersion = session.Definition.contentVersion,
                seed = session.Seed,
                expectedMinute = session.Minute,
                expectedPhase = session.Phase.ToString(),
                clockAccumulatorSeconds = Mathf.Clamp(clockAccumulatorSeconds, 0f, 60f),
                firedEvents = session.Flags.Where(f => f.StartsWith("event.") || f.StartsWith("equipment.") || f.StartsWith("group3.") || f == "period.timed_out").ToArray(),
                reflection = session.Reflection.Clone(),
                records = session.Records.Select(r => new SessionRecord
                {
                    actionId = r.actionId,
                    minutes = r.minutes,
                    payload = r.payload ?? ""
                }).ToList()
            };
            return JsonUtility.ToJson(envelope);
        }

        public static bool TryDecode(string json, ScenarioDefinition definition, out ScenarioSession session, out float clockAccumulatorSeconds, out string error)
        {
            session = null;
            clockAccumulatorSeconds = 0f;
            error = "Bản lưu không hợp lệ hoặc thuộc phiên bản khác.";
            if (string.IsNullOrEmpty(json) || json.Length > 350000 || definition == null) return false;

            try
            {
                SaveEnvelope data = JsonUtility.FromJson<SaveEnvelope>(json);
                if (data == null || data.schemaVersion != CurrentSchemaVersion ||
                    data.scenarioId != definition.id || data.contentVersion != definition.contentVersion ||
                    data.records == null || data.records.Count > 1024)
                    return false;

                int seed = data.seed != 0 ? data.seed : 801;
                var candidate = new ScenarioSession(definition, seed);
                foreach (SessionRecord record in data.records)
                {
                    if (record == null || string.IsNullOrEmpty(record.actionId)) return false;
                    if (record.actionId == "@clock")
                    {
                        if (!candidate.AdvancePassiveMinutes(record.minutes)) return false;
                    }
                    else if (record.actionId == "@reflect")
                    {
                        candidate.ApplyReplayReflection(record.payload);
                    }
                    else
                    {
                        if (record.minutes != 0 || !candidate.TryPerform(record.actionId)) return false;
                    }
                }

                if (data.expectedMinute > 0 && candidate.Minute != data.expectedMinute) return false;
                if (!string.IsNullOrEmpty(data.expectedPhase) && candidate.Phase.ToString() != data.expectedPhase) return false;

                if (data.reflection != null)
                {
                    ReflectionInput restored = data.reflection.Clone();
                    restored.liked = ClampText(restored.liked, 300);
                    restored.disliked = ClampText(restored.disliked, 300);
                    restored.difficulty = ClampText(restored.difficulty, 300);
                    restored.keep = ClampText(restored.keep, 300);
                    restored.change = ClampText(restored.change, 300);
                    restored.testNext = ClampText(restored.testNext, 300);
                    restored.freeNote = ClampText(restored.freeNote, 500);
                    candidate.SetReflection(restored, recordForReplay: false);
                }

                clockAccumulatorSeconds = float.IsNaN(data.clockAccumulatorSeconds) || float.IsInfinity(data.clockAccumulatorSeconds)
                    ? 0f : Mathf.Clamp(data.clockAccumulatorSeconds, 0f, 60f);
                session = candidate;
                error = "";
                return true;
            }
            catch (Exception exception) when (exception is ArgumentException || exception is InvalidOperationException)
            {
                return false;
            }
        }

        private static string ClampText(string text, int max) =>
            string.IsNullOrEmpty(text) ? "" : text.Substring(0, Math.Min(max, text.Length));
    }

    public sealed class LocalSaveStore
    {
        private readonly string key;
        public string PrimaryKey => key;
        public string BackupKey => key + ".backup";
        public string CorruptKey => key + ".corrupt";

        public LocalSaveStore(string scenarioId) => key = "jobsimu.unity.v2." + scenarioId;

        public bool HasSave => PlayerPrefs.HasKey(key) || PlayerPrefs.HasKey(BackupKey);
        public bool HasCorruptArchive => PlayerPrefs.HasKey(CorruptKey);

        public bool Write(ScenarioSession session, float clockAccumulatorSeconds, out string message, bool allowOverwriteWithEmpty = false)
        {
            if (session == null)
            {
                message = "Chưa có phiên chơi để lưu.";
                return false;
            }
            try
            {
                string previous = PlayerPrefs.GetString(key, "");
                if (!string.IsNullOrEmpty(previous))
                {
                    if (SaveCodec.TryDecode(previous, session.Definition, out _, out _, out _))
                    {
                        if (session.Records.Count == 0 && !allowOverwriteWithEmpty)
                        {
                            message = "Giữ nguyên bản lưu trước đó (không ghi đè bằng phiên trống).";
                            return false;
                        }
                        PlayerPrefs.SetString(BackupKey, previous);
                    }
                    else
                    {
                        // Never silently lose a corrupt save via autosave or empty session overwrite.
                        PlayerPrefs.SetString(CorruptKey, previous);
                        if (!allowOverwriteWithEmpty)
                        {
                            message = "Phát hiện bản lưu cũ bị lỗi (đã bảo toàn); không tự ghi đè bằng autosave.";
                            PlayerPrefs.Save();
                            return false;
                        }
                    }
                }
                else if (session.Records.Count == 0 && !allowOverwriteWithEmpty)
                {
                    message = "Lưu cục bộ trên thiết bị · chưa đồng bộ tài khoản.";
                    return true;
                }

                PlayerPrefs.SetString(key, SaveCodec.Encode(session, clockAccumulatorSeconds));
                PlayerPrefs.Save();
                message = "Đã lưu cục bộ trên thiết bị · chưa đồng bộ tài khoản.";
                return true;
            }
            catch (Exception)
            {
                message = "Không thể ghi dữ liệu cục bộ. Bạn vẫn tiếp tục chơi được trong phiên này.";
                return false;
            }
        }

        public bool Read(ScenarioDefinition definition, out ScenarioSession session, out float clockAccumulatorSeconds, out string message)
        {
            string primary = PlayerPrefs.GetString(key, "");
            if (SaveCodec.TryDecode(primary, definition, out session, out clockAccumulatorSeconds, out _))
            {
                message = "Đã khôi phục bản lưu cục bộ trên thiết bị.";
                return true;
            }

            if (!string.IsNullOrEmpty(primary))
            {
                // Quarantine corrupt primary payload without erasing it.
                PlayerPrefs.SetString(CorruptKey, primary);
                PlayerPrefs.Save();
            }

            string backup = PlayerPrefs.GetString(BackupKey, "");
            if (SaveCodec.TryDecode(backup, definition, out session, out clockAccumulatorSeconds, out _))
            {
                message = "Bản lưu chính bị lỗi (đã giữ nguyên bản lỗi để kiểm tra); đã khôi phục bản dự phòng gần nhất.";
                return true;
            }

            message = HasSave || HasCorruptArchive
                ? "Không đọc được bản lưu. Bản lưu lỗi được giữ lại (không tự xóa); chọn Chơi mới nếu muốn bắt đầu lượt mới."
                : "Chưa có bản lưu trên thiết bị này.";
            return false;
        }
    }
}
