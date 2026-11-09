using UnityEngine;
using JobSimu.Player;

namespace JobSimu.Gameplay
{
    /// <summary>Small IMGUI prototype HUD; no EventSystem or legacy Input API dependency.</summary>
    public sealed class PrototypeHUD : JobSimu.Core.InteractionUI
    {
        public TeacherOrientation orientation;
        public bool IsDialogueOpen { get; private set; }
        public override bool BlocksGameplay => IsDialogueOpen;
        public override void HandleCancel() => CloseDialogue();
        private string prompt = "", title = "", dialogue = "";
        private GUIStyle body, heading, centered;
        private Vector2 scroll;

        public override void SetPrompt(string value) => prompt = value;
        public void ShowDialogue(string speaker, string text)
        {
            title = speaker; dialogue = text; scroll = Vector2.zero;
            IsDialogueOpen = true; prompt = "";
            FirstPersonController.ReleaseCursor();
        }
        public void CloseDialogue() => IsDialogueOpen = false;

        private void OnGUI()
        {
            if (body == null)
            {
                body = new GUIStyle(GUI.skin.label) { fontSize = 19, wordWrap = true, richText = false };
                heading = new GUIStyle(body) { fontSize = 25, fontStyle = FontStyle.Bold };
                centered = new GUIStyle(body) { alignment = TextAnchor.MiddleCenter };
            }
            float scale = Mathf.Max(0.25f, Mathf.Min(Screen.width / 1280f, Screen.height / 720f));
            Matrix4x4 previous = GUI.matrix;
            GUI.matrix = Matrix4x4.TRS(Vector3.zero, Quaternion.identity, new Vector3(scale, scale, 1));
            float w = Screen.width / scale, h = Screen.height / scale;
            GUI.Box(new Rect(20, 20, 610, 115), GUIContent.none);
            GUI.Label(new Rect(38, 28, 570, 36), "JOBSIMU  /  BUỔI ĐẦU ĐẾN TRƯỜNG", heading);
            GUI.Label(new Rect(38, 72, 570, 58), orientation != null ? orientation.Objective : "Khám phá trường", body);
            GUI.Box(new Rect(20, h - 58, w - 40, 40), GUIContent.none);
            GUI.Label(new Rect(30, h - 56, w - 60, 36),
                "WASD · Di chuyển    Shift · Chạy    Chuột · Nhìn    E · Tương tác    Esc · Thả chuột / Đóng", centered);
            if (IsDialogueOpen)
            {
                float panelW = Mathf.Min(w - 80, 900), panelH = Mathf.Min(h - 190, 440);
                Rect panel = new Rect((w - panelW) / 2, (h - panelH) / 2 + 35, panelW, panelH);
                GUI.Box(panel, GUIContent.none);
                GUILayout.BeginArea(new Rect(panel.x + 24, panel.y + 18, panel.width - 48, panel.height - 36));
                GUILayout.Label(title, heading);
                scroll = GUILayout.BeginScrollView(scroll);
                GUILayout.Label(dialogue, body);
                GUILayout.EndScrollView();
                if (GUILayout.Button("Đóng [Esc] — nhấp chuột vào cảnh để tiếp tục", GUILayout.Height(40))) CloseDialogue();
                GUILayout.EndArea();
            }
            else if (Cursor.lockState == CursorLockMode.Locked)
            {
                GUI.Label(new Rect(w / 2 - 12, h / 2 - 16, 24, 32), "+", centered);
                if (!string.IsNullOrEmpty(prompt))
                {
                    GUI.Box(new Rect(w / 2 - 240, h / 2 + 35, 480, 42), GUIContent.none);
                    GUI.Label(new Rect(w / 2 - 230, h / 2 + 37, 460, 38), prompt, centered);
                }
            }
            else GUI.Label(new Rect(w / 2 - 300, h / 2, 600, 50), "Nhấp chuột để bắt đầu / tiếp tục", centered);
            GUI.matrix = previous;
        }
    }
}
