using System.Linq;
using UnityEngine;
using UnityEngine.InputSystem;
using JobSimu.Core;
using JobSimu.Dialogue;
using JobSimu.Player;
using JobSimu.Quests;
using JobSimu.Consequences;

namespace JobSimu.UI
{
    public enum ScreenPage { MainMenu, Gameplay, Dialogue, Pause, Journal, Settings, Results, ConfirmNew }
    public sealed class FrameworkHUD : InteractionUI
    {
        public SimulationRuntime runtime;
        public SimulationAudio audioCues;
        public ScreenPage Page { get; private set; } = ScreenPage.MainMenu;
        public DialogueStation CurrentStation { get; private set; }
        public SimulationSettings Settings { get; private set; }
        public override bool BlocksGameplay => Page != ScreenPage.Gameplay;
        private string prompt = "", response = "";
        private bool actionChosen;
        private Vector2 scroll;
        private GUIStyle body, title, button, small, textArea;
        private Font uiFont;
        private ScreenPage returnPage;
        private void Awake() => Settings = SimulationSettings.Load();
        public override void SetPrompt(string value) => prompt = value;
        public override void HandleCancel()
        {
            if (Page == ScreenPage.MainMenu) return;
            if (Page == ScreenPage.Gameplay) SetPage(ScreenPage.Pause);
            else if (Page == ScreenPage.Settings) { SaveSettings(); SetPage(returnPage); }
            else SetPage(runtime.Started ? ScreenPage.Gameplay : ScreenPage.MainMenu);
        }
        private void Update()
        {
            runtime.player.sensitivity = Settings.sensitivity;
            if (!runtime.Started || Keyboard.current == null) return;
            if (Keyboard.current.jKey.wasPressedThisFrame && (Page == ScreenPage.Gameplay || Page == ScreenPage.Journal))
                SetPage(Page == ScreenPage.Journal ? ScreenPage.Gameplay : ScreenPage.Journal);
            if (Keyboard.current.pKey.wasPressedThisFrame && runtime.Session.Complete && Page == ScreenPage.Gameplay) SetPage(ScreenPage.Results);
        }
        public void SetPage(ScreenPage page)
        {
            Page = page; scroll = Vector2.zero;
            if (page != ScreenPage.Dialogue) CurrentStation = null;
            if (page != ScreenPage.Gameplay) FirstPersonController.ReleaseCursor();
        }
        public void OpenStation(DialogueStation station)
        {
            SetPage(ScreenPage.Dialogue); CurrentStation = station; actionChosen = false;
            response = station.Introduction; audioCues.Cue();
        }
        public bool Choose(string actionId)
        {
            if (Page != ScreenPage.Dialogue || CurrentStation == null || actionChosen ||
                !runtime.Session.Available(CurrentStation.stationId).Any(a => a.id == actionId)) return false;
            if (!runtime.Perform(actionId)) return false;
            response = runtime.Session.LastFeedback; actionChosen = true; audioCues.Cue();
            if (runtime.Session.Complete) SetPage(ScreenPage.Results);
            return true;
        }
        private void SaveSettings()
        {
            try { Settings.Save(); } catch (PlayerPrefsException) { Debug.LogWarning("Settings storage unavailable; settings still apply this session."); }
        }
        private void OpenSettings() { returnPage = Page; SetPage(ScreenPage.Settings); }
        private void OnGUI()
        {
            if (runtime.Session == null || Settings == null) return;
            if (body == null)
            {
                uiFont = Resources.Load<Font>("JobSimuUI");
                body = new GUIStyle(GUI.skin.label) { font = uiFont, wordWrap = true, richText = false };
                title = new GUIStyle(body) { fontStyle = FontStyle.Bold };
                small = new GUIStyle(body);
                textArea = new GUIStyle(GUI.skin.textArea) { font = uiFont, fontSize = 18, wordWrap = true };
                button = new GUIStyle(GUI.skin.button) { font = uiFont, wordWrap = true, richText = false, alignment = TextAnchor.MiddleLeft, padding = new RectOffset(18, 12, 8, 8) };
            }
            body.fontSize = Mathf.RoundToInt(19 * Settings.textScale); title.fontSize = 27;
            small.fontSize = 16; button.fontSize = Mathf.RoundToInt(18 * Settings.textScale);
            Font previousFont = GUI.skin.font;
            GUI.skin.font = uiFont;
            GUI.contentColor = Color.white;
            GUI.backgroundColor = Settings.highContrast ? Color.black : new Color(.14f, .23f, .25f);
            Matrix4x4 previous = GUI.matrix;
            float scale = Mathf.Max(.25f, Mathf.Min(Screen.width / 1280f, Screen.height / 720f));
            GUI.matrix = Matrix4x4.TRS(new Vector3((Screen.width - 1280 * scale) / 2, (Screen.height - 720 * scale) / 2), Quaternion.identity, new Vector3(scale, scale, 1));
            if (Page == ScreenPage.MainMenu || Page == ScreenPage.ConfirmNew) DrawMain();
            else
            {
                Backdrop(new Rect(20, 18, 880, 112));
                GUI.Label(new Rect(38, 25, 840, 38), "JOBSIMU  /  " + runtime.Session.Minute / 60 + ":" + (runtime.Session.Minute % 60).ToString("00"), title);
                GUI.Label(new Rect(38, 68, 840, 58), runtime.Session.Objective, body);
                if (Page == ScreenPage.Gameplay) DrawGameplay();
                else if (Page == ScreenPage.Dialogue) DrawDialogue();
                else if (Page == ScreenPage.Pause) DrawPause();
                else if (Page == ScreenPage.Journal) DrawJournal();
                else if (Page == ScreenPage.Settings) DrawSettings();
                else if (Page == ScreenPage.Results) DrawResults();
            }
            GUI.matrix = previous;
            GUI.skin.font = previousFont;
        }
        private bool Button(float x, float y, float w, float h, string label) => GUI.Button(new Rect(x, y, w, h), label, button);
        private void Backdrop(Rect rect)
        {
            Color previousColor = GUI.color;
            GUI.color = Settings.highContrast ? Color.black : new Color(.055f, .105f, .125f, 1);
            GUI.DrawTexture(rect, Texture2D.whiteTexture);
            GUI.color = previousColor;
        }
        private void Panel(string caption)
        {
            Backdrop(new Rect(150, 145, 980, 520));
            GUI.Label(new Rect(175, 160, 930, 40), caption, title);
        }
        private void DrawMain()
        {
            Backdrop(new Rect(270, 110, 740, 510));
            GUI.Label(new Rect(310, 140, 660, 45), "JOBSIMU · MỘT TIẾT DẠY ĐẦU TIÊN", title);
            GUI.Label(new Rect(310, 202, 660, 88), "Đến trường, chuẩn bị học liệu, lắng nghe học sinh và kiểm tra việc học. Các lựa chọn để lại hệ quả và bằng chứng riêng.", body);
            if (Page == ScreenPage.ConfirmNew)
            {
                GUI.Label(new Rect(310, 298, 660, 80), "Bắt đầu lại sẽ thay bản lưu cục bộ hiện tại. Bạn muốn tiếp tục?", body);
                if (Button(400, 395, 480, 48, "Bắt đầu lại")) { runtime.StartNew(); SetPage(ScreenPage.Gameplay); }
                if (Button(400, 455, 480, 48, "Quay lại")) SetPage(ScreenPage.MainMenu);
                return;
            }
            if (Button(400, 300, 480, 48, "Chơi mới · Nghề giáo viên"))
            { if (runtime.Store.HasSave) SetPage(ScreenPage.ConfirmNew); else { runtime.StartNew(); SetPage(ScreenPage.Gameplay); } }
            GUI.enabled = runtime.Store.HasSave;
            if (Button(400, 360, 480, 48, "Tiếp tục bản lưu trên thiết bị"))
            { if (runtime.Resume()) SetPage(runtime.Session.Complete ? ScreenPage.Results : ScreenPage.Gameplay); }
            GUI.enabled = true;
            if (Button(400, 420, 480, 48, "Cài đặt âm thanh & trợ năng")) OpenSettings();
            GUI.Label(new Rect(310, 495, 660, 50), "Lập trình viên · Bác sĩ · Công an: chưa có bản chơi trong demo này.", small);
            GUI.Label(new Rect(310, 560, 660, 50), runtime.SaveStatus, small);
        }
        private void DrawGameplay()
        {
            GUI.Label(new Rect(20, 670, 1240, 40), "WASD · Đi   Shift · Chạy   Chuột · Nhìn   E · Tương tác   J · Nhật ký   Esc · Tạm dừng", small);
            if (Cursor.lockState != CursorLockMode.Locked)
            {
                Backdrop(new Rect(390, 320, 500, 62));
                GUI.Label(new Rect(410, 335, 460, 40), "Nhấp vào cảnh để điều khiển nhân vật", body);
            }
            else
            {
                GUI.Label(new Rect(632, 346, 25, 28), "+", body);
                if (!string.IsNullOrEmpty(prompt)) { Backdrop(new Rect(350, 395, 580, 50)); GUI.Label(new Rect(370, 407, 540, 38), prompt, body); }
            }
        }
        private void DrawDialogue()
        {
            if (CurrentStation == null) { SetPage(ScreenPage.Gameplay); return; }
            Panel(CurrentStation.title);
            GUI.Label(new Rect(175, 208, 930, actionChosen ? 325 : 100), response, body);
            ScenarioAction[] actions = runtime.Session.Available(CurrentStation.stationId).ToArray();
            if (!actionChosen)
            {
                if (actions.Length == 0) GUI.Label(new Rect(175, 335, 930, 150), "Việc tiếp theo: " + runtime.Session.Objective, body);
                for (int i = 0; i < actions.Length; i++)
                    if (Button(175, 320 + i * 80, 930, 72, actions[i].label + "\n" + actions[i].tradeoff)) { Choose(actions[i].id); break; }
            }
            if (Button(175, 597, 930, 46, "Đóng [Esc] · Nhấp vào cảnh để tiếp tục")) SetPage(ScreenPage.Gameplay);
        }
        private void DrawPause()
        {
            Panel("Tạm dừng");
            if (Button(350, 245, 580, 48, "Tiếp tục")) SetPage(ScreenPage.Gameplay);
            if (Button(350, 305, 580, 48, "Lưu trên thiết bị")) runtime.Save();
            if (Button(350, 365, 580, 48, "Nhật ký & bằng chứng")) SetPage(ScreenPage.Journal);
            if (Button(350, 425, 580, 48, "Cài đặt")) OpenSettings();
            if (Button(350, 485, 580, 48, "Về màn hình chính")) { runtime.Save(); SetPage(ScreenPage.MainMenu); }
            GUI.Label(new Rect(175, 580, 930, 60), runtime.SaveStatus, small);
        }
        private void DrawJournal()
        {
            Panel("Nhật ký nghề nghiệp · bằng chứng quan sát");
            GUILayout.BeginArea(new Rect(175, 215, 930, 345));
            scroll = GUILayout.BeginScrollView(scroll);
            GUILayout.Label(runtime.Session.Objective, body);
            foreach (var entry in runtime.Session.Evidence)
                GUILayout.Label($"{entry.minute / 60:00}:{entry.minute % 60:00} · {entry.text}", body);
            GUILayout.Label("Quan hệ: " + (runtime.Session.Consequence(ConsequenceKind.Trust) >= 2 ? "Đã có thêm trao đổi hỗ trợ." : "Cần tiếp tục tìm hiểu."), body);
            GUILayout.EndScrollView(); GUILayout.EndArea();
            if (Button(175, 597, 930, 46, runtime.Session.Complete ? "Xem phản tư cuối tiết" : "Quay lại trường")) SetPage(runtime.Session.Complete ? ScreenPage.Results : ScreenPage.Gameplay);
        }
        private void DrawSettings()
        {
            Panel("Âm thanh & trợ năng");
            GUILayout.BeginArea(new Rect(185, 215, 900, 340));
            GUILayout.Label("Độ nhạy chuột", body); Settings.sensitivity = GUILayout.HorizontalSlider(Settings.sensitivity, .02f, .3f);
            GUILayout.Label("Cỡ chữ", body); Settings.textScale = GUILayout.HorizontalSlider(Settings.textScale, 1, 1.35f);
            GUILayout.Label("Âm nền", body); Settings.ambience = GUILayout.HorizontalSlider(Settings.ambience, 0, 1);
            GUILayout.Label("Hiệu ứng tương tác", body); Settings.effects = GUILayout.HorizontalSlider(Settings.effects, 0, 1);
            GUILayout.Label("Nhạc", body); Settings.music = GUILayout.HorizontalSlider(Settings.music, 0, 1);
            Settings.highContrast = GUILayout.Toggle(Settings.highContrast, "Tăng tương phản giao diện");
            Settings.reduceMotion = GUILayout.Toggle(Settings.reduceMotion, "Giảm chuyển động nhân vật trang trí");
            GUILayout.EndArea();
            if (Button(175, 597, 930, 46, "Lưu cài đặt và quay lại")) { SaveSettings(); SetPage(returnPage); }
        }
        private void DrawResults()
        {
            Panel("Kết thúc tiết học · nhìn lại cách làm");
            GUILayout.BeginArea(new Rect(175, 212, 930, 240));
            scroll = GUILayout.BeginScrollView(scroll); GUILayout.Label(runtime.Session.Report(), body);
            GUILayout.EndScrollView(); GUILayout.EndArea();
            GUI.Label(new Rect(175, 465, 930, 30), "Bạn sẽ giữ lại hoặc thay đổi điều gì ở tiết sau? (tối đa 500 ký tự)", small);
            runtime.Reflection = GUI.TextArea(new Rect(175, 500, 930, 70), runtime.Reflection, 500, textArea);
            if (Button(175, 585, 450, 48, "Lưu phản tư trên thiết bị")) runtime.Save();
            if (Button(645, 585, 460, 48, "Tiếp tục khám phá · P để xem lại")) { runtime.Save(); SetPage(ScreenPage.Gameplay); }
            GUI.Label(new Rect(175, 638, 930, 24), runtime.SaveStatus, small);
        }
    }
}
