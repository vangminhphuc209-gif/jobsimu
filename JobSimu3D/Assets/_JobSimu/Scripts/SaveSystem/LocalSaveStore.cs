using System;
using System.Collections.Generic;
using System.Linq;
using UnityEngine;
using JobSimu.Quests;
using JobSimu.Consequences;

namespace JobSimu.SaveSystem
{
    [Serializable] public sealed class SaveEnvelope
    {
        public int schemaVersion;
        public string scenarioId, contentVersion, reflection;
        public List<SessionRecord> records;
    }
    public static class SaveCodec
    {
        public static string Encode(ScenarioSession session, string reflection) => JsonUtility.ToJson(new SaveEnvelope {
            schemaVersion = 1,
            scenarioId = session.Definition.id, contentVersion = session.Definition.contentVersion,
            reflection = (reflection ?? "").Substring(0, Math.Min(500, (reflection ?? "").Length)),
            records = session.Records.Select(r => new SessionRecord { actionId = r.actionId, minutes = r.minutes }).ToList()
        });
        public static bool TryDecode(string json, ScenarioDefinition definition, out ScenarioSession session, out string reflection, out string error)
        {
            session = null; reflection = ""; error = "Bản lưu không hợp lệ hoặc thuộc phiên bản khác.";
            if (string.IsNullOrEmpty(json) || json.Length > 200000) return false;
            try
            {
                SaveEnvelope data = JsonUtility.FromJson<SaveEnvelope>(json);
                if (data == null || data.schemaVersion != 1 || data.scenarioId != definition.id || data.contentVersion != definition.contentVersion ||
                    data.records == null || data.records.Count > 1024 || (data.reflection?.Length ?? 0) > 500) return false;
                var candidate = new ScenarioSession(definition);
                foreach (SessionRecord record in data.records)
                {
                    if (record == null || (record.actionId == "@clock" ? !candidate.AdvanceMinutes(record.minutes) :
                        record.minutes != 0 || !candidate.TryPerform(record.actionId))) return false;
                }
                session = candidate; reflection = data.reflection ?? ""; error = ""; return true;
            }
            catch (Exception exception) when (exception is ArgumentException || exception is InvalidOperationException)
            { return false; }
        }
    }
    public sealed class LocalSaveStore
    {
        private readonly string key;
        public LocalSaveStore(string scenarioId) => key = "jobsimu.unity.v1." + scenarioId;
        public bool HasSave => PlayerPrefs.HasKey(key) || PlayerPrefs.HasKey(key + ".backup");
        public bool Write(ScenarioSession session, string reflection, out string error)
        {
            try
            {
                string previous = PlayerPrefs.GetString(key, "");
                if (SaveCodec.TryDecode(previous, session.Definition, out _, out _, out _)) PlayerPrefs.SetString(key + ".backup", previous);
                PlayerPrefs.SetString(key, SaveCodec.Encode(session, reflection)); PlayerPrefs.Save();
                error = "Đã lưu trên thiết bị này · chưa đồng bộ tài khoản."; return true;
            }
            catch (Exception) { error = "Không thể lưu trên thiết bị. Bạn vẫn có thể chơi trong phiên này."; return false; }
        }
        public bool Read(ScenarioDefinition definition, out ScenarioSession session, out string reflection, out string message)
        {
            if (SaveCodec.TryDecode(PlayerPrefs.GetString(key, ""), definition, out session, out reflection, out message))
            { message = "Đã khôi phục bản lưu trên thiết bị."; return true; }
            if (SaveCodec.TryDecode(PlayerPrefs.GetString(key + ".backup", ""), definition, out session, out reflection, out _))
            { message = "Bản chính bị lỗi; đã khôi phục bản dự phòng gần nhất."; return true; }
            message = HasSave ? "Không đọc được bản lưu. Bản cũ được giữ lại; chọn Chơi mới nếu muốn bắt đầu lại." : "Chưa có bản lưu trên thiết bị này.";
            return false;
        }
    }
}
