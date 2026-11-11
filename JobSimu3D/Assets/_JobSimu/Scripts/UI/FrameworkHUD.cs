using System;
using System.Collections.Generic;
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
    public enum ScreenPage { MainMenu, Gameplay, Dialogue, Planner, Journal, Reflection, Pause, Settings, Results, ConfirmNew }

    public sealed class FrameworkHUD : InteractionUI
    {
        public SimulationRuntime runtime;
        public SimulationAudio audioCues;
        public ScreenPage Page { get; private set; } = ScreenPage.MainMenu;
        public DialogueStation CurrentStation { get; private set; }
        public SimulationSettings Settings { get; private set; }
        public override bool BlocksGameplay => Page != ScreenPage.Gameplay;

        private string prompt = "", response = "";
        private Vector2 scroll, actionScroll, reflectScroll;
        private GUIStyle body, title, button, small, textArea, sectionHeader, toggleStyle, topTitle;
        private Font uiFont;
        private Texture2D panelBgTex, highContrastBgTex;
        private ScreenPage returnPage = ScreenPage.MainMenu;
        private ReflectionInput reflectionDraft = new ReflectionInput();
        private bool timeoutBannerShown;

        private void Awake() => Settings = SimulationSettings.Load();

        public override void SetPrompt(string value) => prompt = value;

        public override void HandleCancel()
        {
            if (Page == ScreenPage.MainMenu) return;
            if (Page == ScreenPage.Gameplay) SetPage(ScreenPage.Pause);
            else if (Page == ScreenPage.Settings) { SaveSettings(); SetPage(returnPage); }
            else if (Page == ScreenPage.ConfirmNew) SetPage(ScreenPage.MainMenu);
            else SetPage(runtime != null && runtime.Started ? ScreenPage.Gameplay : ScreenPage.MainMenu);
        }

        private void Update()
        {
            if (runtime == null || runtime.player == null || Settings == null) return;
            runtime.player.sensitivity = Settings.sensitivity;

            if (runtime.Started && runtime.Session != null && runtime.Session.HasTimedOut &&
                !timeoutBannerShown && Page == ScreenPage.Gameplay)
            {
                timeoutBannerShown = true;
                OpenReflection();
                return;
            }

            if (!runtime.Started || Keyboard.current == null) return;

            if (Keyboard.current.jKey.wasPressedThisFrame && (Page == ScreenPage.Gameplay || Page == ScreenPage.Journal))
                SetPage(Page == ScreenPage.Journal ? ScreenPage.Gameplay : ScreenPage.Journal);

            if (Keyboard.current.tabKey.wasPressedThisFrame && (Page == ScreenPage.Gameplay || Page == ScreenPage.Planner))
                SetPage(Page == ScreenPage.Planner ? ScreenPage.Gameplay : ScreenPage.Planner);

            if (Keyboard.current.pKey.wasPressedThisFrame && runtime.Session != null && runtime.Session.Complete && Page == ScreenPage.Gameplay)
                SetPage(ScreenPage.Results);
        }

        public void SetPage(ScreenPage page)
        {
            Page = page;
            scroll = Vector2.zero;
            actionScroll = Vector2.zero;
            reflectScroll = Vector2.zero;
            if (page != ScreenPage.Dialogue) CurrentStation = null;
            if (page != ScreenPage.Gameplay) FirstPersonController.ReleaseCursor();
        }

        public void OpenStation(DialogueStation station)
        {
            if (station == null) return;
            if (runtime.Session != null && runtime.Session.Phase == LessonPhase.Reflection && station.stationId == "mai")
            {
                OpenReflection();
                return;
            }
            SetPage(ScreenPage.Dialogue);
            CurrentStation = station;
            response = station.Introduction;
            if (audioCues != null) audioCues.Cue();
        }

        public void OpenReflection()
        {
            if (runtime.Session != null)
                reflectionDraft = runtime.Session.Reflection.Clone();
            SetPage(ScreenPage.Reflection);
            if (audioCues != null) audioCues.Cue();
        }

        public bool Choose(string actionId)
        {
            if (Page != ScreenPage.Dialogue || CurrentStation == null || runtime == null || runtime.Session == null)
                return false;
            if (!runtime.Session.Available(CurrentStation.stationId).Any(a => a.id == actionId))
                return false;
            if (!runtime.Perform(actionId)) return false;

            response = runtime.Session.LastFeedback;
            actionScroll = Vector2.zero;
            if (audioCues != null) audioCues.Cue();

            if (runtime.Session.HasTimedOut && !timeoutBannerShown)
            {
                timeoutBannerShown = true;
                response = runtime.Session.LastFeedback;
            }
            else if (runtime.Session.Complete)
            {
                SetPage(ScreenPage.Results);
            }
            return true;
        }

        public bool SubmitReflection(ReflectionInput input)
        {
            if (runtime == null || runtime.Session == null) return false;
            if (runtime.Session.Phase != LessonPhase.Reflection) return false;
            runtime.UpdateReflection(input, recordForReplay: true);
            if (!runtime.Perform("reflect.finish")) return false;
            if (audioCues != null) audioCues.Cue();
            SetPage(ScreenPage.Results);
            return true;
        }

        private void SaveSettings()
        {
            try { Settings.Save(); }
            catch (PlayerPrefsException) { Debug.LogWarning("Settings storage unavailable; settings still apply this session."); }
        }

        private void OpenSettings()
        {
            returnPage = Page;
            SetPage(ScreenPage.Settings);
        }

        private void OnGUI()
        {
            if (runtime == null || runtime.Session == null || Settings == null) return;
            EnsureStyles();

            Font previousFont = GUI.skin.font;
            GUI.skin.font = uiFont;
            GUI.contentColor = Color.white;
            GUI.backgroundColor = Settings.highContrast ? Color.black : new Color(0.14f, 0.23f, 0.25f);
            Matrix4x4 previous = GUI.matrix;
            float scale = Mathf.Max(0.25f, Mathf.Min(Screen.width / 1280f, Screen.height / 720f));
            GUI.matrix = Matrix4x4.TRS(new Vector3((Screen.width - 1280f * scale) * 0.5f, (Screen.height - 720f * scale) * 0.5f, 0f), Quaternion.identity, new Vector3(scale, scale, 1f));

            if (Page == ScreenPage.MainMenu || Page == ScreenPage.ConfirmNew)
                DrawMain();
            else
            {
                DrawTopBar();
                if (Page == ScreenPage.Gameplay) DrawGameplay();
                else if (Page == ScreenPage.Dialogue) DrawDialogue();
                else if (Page == ScreenPage.Planner) DrawPlanner();
                else if (Page == ScreenPage.Journal) DrawJournal();
                else if (Page == ScreenPage.Reflection) DrawReflection();
                else if (Page == ScreenPage.Pause) DrawPause();
                else if (Page == ScreenPage.Settings) DrawSettings();
                else if (Page == ScreenPage.Results) DrawResults();
            }

            GUI.matrix = previous;
            GUI.skin.font = previousFont;
        }

        private void EnsureStyles()
        {
            if (body != null)
            {
                body.fontSize = Mathf.RoundToInt(17 * Settings.textScale);
                title.fontSize = 23;
                topTitle.fontSize = 20;
                sectionHeader.fontSize = Mathf.RoundToInt(18 * Settings.textScale);
                small.fontSize = Mathf.RoundToInt(15 * Settings.textScale);
                button.fontSize = Mathf.RoundToInt(16 * Settings.textScale);
                toggleStyle.fontSize = Mathf.RoundToInt(16 * Settings.textScale);
                return;
            }
            uiFont = Resources.Load<Font>("JobSimuUI");
            body = new GUIStyle(GUI.skin.label) { font = uiFont, wordWrap = true, richText = false };
            title = new GUIStyle(body) { fontStyle = FontStyle.Bold };
            topTitle = new GUIStyle(title);
            sectionHeader = new GUIStyle(body) { fontStyle = FontStyle.Bold };
            small = new GUIStyle(body);
            textArea = new GUIStyle(GUI.skin.textArea) { font = uiFont, fontSize = 16, wordWrap = true };
            toggleStyle = new GUIStyle(GUI.skin.toggle) { font = uiFont, wordWrap = true, padding = new RectOffset(26, 8, 4, 4) };
            button = new GUIStyle(GUI.skin.button)
            {
                font = uiFont,
                wordWrap = true,
                richText = false,
                alignment = TextAnchor.MiddleLeft,
                padding = new RectOffset(14, 12, 6, 6)
            };
        }

        private void DrawTopBar()
        {
            ScenarioSession s = runtime.Session;
            Backdrop(new Rect(20, 14, 1240, 102));
            string clockState = s.Phase == LessonPhase.Briefing || s.Phase == LessonPhase.Preparation
                ? "Chuẩn bị trước tiết"
                : s.Phase == LessonPhase.Reflection || s.Phase == LessonPhase.Complete
                    ? (s.HasTimedOut ? "Đã hết giờ (08:15)" : "Sau tiết học")
                    : $"Trong tiết 8A · còn {Mathf.Max(0, s.Definition.lessonEndMinute - s.Minute)} phút";
            string pausedBadge = BlocksGameplay ? " · [ĐỒNG HỒ TẠM DỪNG]" : "";
            GUI.Label(new Rect(36, 20, 1208, 32), $"THCS BÌNH MINH  |  {ScenarioSession.FormatClock(s.Minute)}  ·  {clockState}{pausedBadge}", topTitle);
            GUI.Label(new Rect(36, 56, 1208, 54), s.Objective, body);
        }

        private bool Button(float x, float y, float w, float h, string label) => GUI.Button(new Rect(x, y, w, h), label, button);

        private void Backdrop(Rect rect)
        {
            if (panelBgTex == null)
            {
                panelBgTex = new Texture2D(1, 1, TextureFormat.RGBA32, false);
                panelBgTex.SetPixel(0, 0, new Color(0.065f, 0.12f, 0.14f, 1f));
                panelBgTex.Apply();
                highContrastBgTex = new Texture2D(1, 1, TextureFormat.RGBA32, false);
                highContrastBgTex.SetPixel(0, 0, Color.black);
                highContrastBgTex.Apply();
            }
            Color previousColor = GUI.color;
            GUI.color = Color.white;
            GUI.DrawTexture(rect, Settings.highContrast ? highContrastBgTex : panelBgTex, ScaleMode.StretchToFill, false);
            GUI.color = previousColor;
        }

        private void Panel(string caption)
        {
            Backdrop(new Rect(100, 128, 1080, 564));
            GUI.Label(new Rect(126, 140, 1028, 34), caption, title);
        }

        private void DrawMain()
        {
            Backdrop(new Rect(230, 88, 820, 550));
            GUI.Label(new Rect(265, 114, 750, 40), "JOBSIMU · TIẾT KHOA HỌC TỰ NHIÊN LỚP 8A", title);
            GUI.Label(new Rect(265, 162, 750, 106),
                "Trường THCS Bình Minh (hư cấu). Bạn phụ trách tiết 45 phút tìm hiểu vì sao sân trường đọng nước sau mưa cùng cô Mai và 4 nhóm học sinh (12 em).\n" +
                "Quan sát bài làm, hỏi làm rõ, xử lý phát sinh và nhìn lại bằng chứng thực tế. Đây là tình huống thiết kế trải nghiệm nghề, chưa phải đánh giá tâm lý hay thẩm định chương trình.",
                body);

            if (Page == ScreenPage.ConfirmNew)
            {
                GUI.Label(new Rect(265, 290, 750, 74), "Bắt đầu lại sẽ thay bản lưu hiện tại trên thiết bị (bản lỗi nếu có vẫn được giữ riêng). Tiếp tục?", body);
                if (Button(370, 385, 540, 48, "Xác nhận bắt đầu lượt mới"))
                {
                    timeoutBannerShown = false;
                    runtime.StartNew(confirmOverwriteExisting: true);
                    SetPage(ScreenPage.Gameplay);
                }
                if (Button(370, 445, 540, 48, "Quay lại")) SetPage(ScreenPage.MainMenu);
                return;
            }

            if (Button(370, 295, 540, 48, "Chơi mới · Giáo viên THCS (Lớp 8A)"))
            {
                if (runtime.Store.HasSave) SetPage(ScreenPage.ConfirmNew);
                else { timeoutBannerShown = false; runtime.StartNew(confirmOverwriteExisting: true); SetPage(ScreenPage.Gameplay); }
            }

            GUI.enabled = runtime.Store.HasSave;
            if (Button(370, 355, 540, 48, "Tiếp tục bản lưu trên thiết bị"))
            {
                if (runtime.Resume())
                {
                    timeoutBannerShown = runtime.Session.HasTimedOut;
                    SetPage(runtime.Session.Complete ? ScreenPage.Results :
                        runtime.Session.Phase == LessonPhase.Reflection ? ScreenPage.Reflection : ScreenPage.Gameplay);
                }
            }
            GUI.enabled = true;

            if (Button(370, 415, 540, 48, "Cài đặt âm thanh & trợ năng")) OpenSettings();
            GUI.Label(new Rect(265, 486, 750, 44), "Phạm vi thử nghiệm: chỉ gồm một tiết KHTN Lớp 8A. Các nghề khác chưa mở trong bản này.", small);
            GUI.Label(new Rect(265, 538, 750, 64), runtime.SaveStatus, small);
        }

        private void DrawGameplay()
        {
            GUI.Label(new Rect(20, 676, 1240, 34),
                "WASD · Đi   Shift · Chạy   Chuột · Nhìn   E · Tương tác   Tab · Sổ lớp 4 nhóm   J · Nhật ký   Esc · Tạm dừng",
                small);

            if (Cursor.lockState != CursorLockMode.Locked)
            {
                Backdrop(new Rect(390, 320, 500, 62));
                GUI.Label(new Rect(410, 336, 460, 40), "Nhấp vào cảnh để điều khiển nhân vật", body);
            }
            else
            {
                GUI.Label(new Rect(632, 346, 25, 28), "+", body);
                if (!string.IsNullOrEmpty(prompt))
                {
                    Backdrop(new Rect(310, 395, 660, 50));
                    GUI.Label(new Rect(328, 407, 624, 38), prompt, body);
                }
            }
        }

        private void DrawDialogue()
        {
            if (CurrentStation == null) { SetPage(ScreenPage.Gameplay); return; }
            Panel(CurrentStation.title);

            // Upper area: live feedback / readable student worksheet
            Backdrop(new Rect(126, 182, 1028, 168));
            GUILayout.BeginArea(new Rect(138, 190, 1004, 152));
            scroll = GUILayout.BeginScrollView(scroll, false, true);
            GUILayout.Label(response, body, GUILayout.Width(965));
            GUILayout.EndScrollView();
            GUILayout.EndArea();

            // Lower area: all contextually available actions at this station
            ScenarioAction[] actions = runtime.Session.Available(CurrentStation.stationId).ToArray();
            string gateMessage = CurrentStation.stationId == "board" && runtime.Session.Phase == LessonPhase.Facilitating
                ? runtime.Session.ExplainTransitionGate("teach.to_check")
                : "";

            GUILayout.BeginArea(new Rect(126, 362, 1028, 258));
            actionScroll = GUILayout.BeginScrollView(actionScroll, false, true);
            if (actions.Length == 0)
            {
                GUILayout.Label("Chưa có thao tác mới tại điểm này lúc này.\nViệc hiện tại: " + runtime.Session.Objective, body, GUILayout.Width(980));
            }
            else
            {
                foreach (ScenarioAction act in actions)
                {
                    string costTag = act.minutes > 0 ? $" (~{act.minutes} phút)" : "";
                    string label = $"{act.label}{costTag}\n{act.tradeoff}";
                    if (GUILayout.Button(label, button, GUILayout.MinHeight(58), GUILayout.Width(985)))
                    {
                        Choose(act.id);
                        break;
                    }
                    GUILayout.Space(6);
                }
            }

            if (!string.IsNullOrEmpty(gateMessage))
            {
                GUILayout.Space(4);
                GUILayout.Label("[Điều kiện chuyển bước] " + gateMessage, small, GUILayout.Width(980));
            }

            if (runtime.Session.Phase == LessonPhase.Reflection)
            {
                GUILayout.Space(8);
                if (GUILayout.Button("Mở bảng Phản tư cuối tiết cùng cô Mai", button, GUILayout.Height(46), GUILayout.Width(985)))
                    OpenReflection();
            }
            GUILayout.EndScrollView();
            GUILayout.EndArea();

            if (Button(126, 634, 1028, 44, "Đóng [Esc] · Quay lại quan sát / di chuyển trong lớp"))
                SetPage(ScreenPage.Gameplay);
        }

        private void DrawPlanner()
        {
            ScenarioSession s = runtime.Session;
            Panel("Sổ lớp 8A & Học liệu đang mang (chỉ hiển thị thông tin đã quan sát)");
            GUILayout.BeginArea(new Rect(126, 184, 1028, 434));
            scroll = GUILayout.BeginScrollView(scroll, false, true);

            GUILayout.Label("1. Học liệu & thiết bị:", sectionHeader);
            ResourceState r = s.Resources;
            GUILayout.Label($"• Đọc bài tiết trước: {(r.readPastPapers ? "Đã xem" : "Chưa xem")}   |   Kiểm tra thiết bị: {(r.inspectedGear ? "Đã kiểm tra" : "Chưa kiểm tra")}", body, GUILayout.Width(980));
            GUILayout.Label($"• Bản in phiếu: {(r.printedSheets ? "Có" : "Không")}   |   Bộ thẻ & khay mẫu: {(r.groupCardsPrepared ? "Có" : "Không")}   |   Máy chiếu: {(r.projectorPrepared ? (r.projectorBroken ? "ĐÃ NGẮT LỖI" : "Đang bật") : "Không dùng")}", body, GUILayout.Width(980));
            GUILayout.Space(8);

            GUILayout.Label("2. Trạng thái quan sát được của 4 nhóm (không chấm điểm bí mật):", sectionHeader);
            foreach (GroupState g in s.Groups.Values)
            {
                string observedDisplay = g.observedByTeacher || s.Phase >= LessonPhase.CheckingLearning
                    ? g.worksheetContent
                    : "Chưa trực tiếp đến bàn xem bài làm.";
                GUILayout.Label($"• {g.groupName} ({string.Join(", ", g.members)}): {observedDisplay}", body, GUILayout.Width(980));
                if (!string.IsNullOrEmpty(g.teacherNote))
                    GUILayout.Label($"  Ghi chú: {g.teacherNote}", small, GUILayout.Width(980));
            }

            if (s.Issues.Count > 0)
            {
                GUILayout.Space(8);
                GUILayout.Label("3. Vấn đề đang diễn ra trong tiết:", sectionHeader);
                foreach (OngoingIssue issue in s.Issues)
                    GUILayout.Label($"• [{issue.status}] {issue.title}: {issue.summary}", small, GUILayout.Width(980));
            }

            GUILayout.EndScrollView();
            GUILayout.EndArea();

            if (Button(126, 634, 1028, 44, "Đóng [Tab / Esc]"))
                SetPage(ScreenPage.Gameplay);
        }

        private void DrawJournal()
        {
            Panel("Nhật ký quan sát · dòng thời gian và bằng chứng");
            GUILayout.BeginArea(new Rect(126, 184, 1028, 434));
            scroll = GUILayout.BeginScrollView(scroll, false, true);
            GUILayout.Label("Mục tiêu hiện tại: " + runtime.Session.Objective, sectionHeader, GUILayout.Width(980));
            GUILayout.Space(6);
            if (runtime.Session.Timeline.Count == 0)
                GUILayout.Label("Chưa có sự kiện nào được ghi nhận.", body);
            foreach (TimelineEntry entry in runtime.Session.Timeline)
                GUILayout.Label($"{ScenarioSession.FormatClock(entry.minute)} · {(entry.isEvent ? "[Phát sinh] " : "")}{entry.summary}", body, GUILayout.Width(980));

            if (runtime.Session.Evidence.Count > 0)
            {
                GUILayout.Space(10);
                GUILayout.Label("Bằng chứng hành vi đã ghi:", sectionHeader);
                foreach (EvidenceEntry ev in runtime.Session.Evidence)
                    GUILayout.Label($"• {ScenarioSession.FormatClock(ev.minute)} [{ev.categoryTag}] {ev.text}", small, GUILayout.Width(980));
            }
            GUILayout.EndScrollView();
            GUILayout.EndArea();

            if (Button(126, 634, 1028, 44, runtime.Session.Complete ? "Xem Hồ sơ trải nghiệm nghề" : "Quay lại lớp [J / Esc]"))
                SetPage(runtime.Session.Complete ? ScreenPage.Results : ScreenPage.Gameplay);
        }

        private void DrawReflection()
        {
            ScenarioSession s = runtime.Session;
            Panel(s.HasTimedOut ? "Hết giờ (08:15) · Phản tư cuối tiết cùng cô Mai" : "Phản tư cuối tiết cùng cô Mai");

            GUILayout.BeginArea(new Rect(126, 180, 1028, 440));
            reflectScroll = GUILayout.BeginScrollView(reflectScroll, false, true);

            if (s.HasTimedOut)
            {
                GUILayout.Label("Chuông hết tiết 45 phút đã vang lên. Những phần chưa kịp hoàn thành được ghi nhận trung thực dưới đây.", sectionHeader);
                GUILayout.Space(4);
            }

            IReadOnlyList<string> unfinished = s.ComputeUnfinishedTasks();
            GUILayout.Label("A. Tóm tắt diễn biến & việc còn dang dở:", sectionHeader);
            GUILayout.Label(unfinished.Count == 0
                ? "• Bạn đã quan sát, hỗ trợ và kiểm tra việc học ở cả 4 nhóm."
                : "• Còn dang dở: " + string.Join(" | ", unfinished), body, GUILayout.Width(980));
            GUILayout.Space(8);

            GUILayout.Label("B. Chọn 1–3 bài làm / sự kiện thực tế làm bằng chứng phản tư:", sectionHeader);
            if (s.Evidence.Count == 0)
            {
                GUILayout.Label("Chưa có sự kiện nào được ghi trong lượt này.", small);
            }
            else
            {
                foreach (EvidenceEntry ev in s.Evidence)
                {
                    bool selected = reflectionDraft.selectedEvidenceIds.Contains(ev.actionId);
                    bool toggled = GUILayout.Toggle(selected, $"{ScenarioSession.FormatClock(ev.minute)} · {ev.text}", toggleStyle, GUILayout.Width(970));
                    if (toggled != selected)
                    {
                        if (toggled)
                        {
                            if (reflectionDraft.selectedEvidenceIds.Count < 3)
                                reflectionDraft.selectedEvidenceIds.Add(ev.actionId);
                        }
                        else
                            reflectionDraft.selectedEvidenceIds.Remove(ev.actionId);
                    }
                }
            }

            GUILayout.Space(10);
            GUILayout.Label("C. Tự phản tư về công việc vừa trải nghiệm:", sectionHeader);
            GUILayout.Label("1. Bạn thích phần việc nào trong tiết dạy?", small);
            reflectionDraft.liked = GUILayout.TextField(reflectionDraft.liked ?? "", 250);
            GUILayout.Label("2. Bạn không thích hoặc thấy áp lực ở phần nào?", small);
            reflectionDraft.disliked = GUILayout.TextField(reflectionDraft.disliked ?? "", 250);
            GUILayout.Label("3. Điều gì khiến bạn thấy khó nhất?", small);
            reflectionDraft.difficulty = GUILayout.TextField(reflectionDraft.difficulty ?? "", 250);
            GUILayout.Label("4. Bạn muốn giữ lại điều gì ở tiết sau?", small);
            reflectionDraft.keep = GUILayout.TextField(reflectionDraft.keep ?? "", 250);
            GUILayout.Label("5. Bạn muốn thay đổi hoặc thử cách khác ở đâu?", small);
            reflectionDraft.change = GUILayout.TextField(reflectionDraft.change ?? "", 250);
            GUILayout.Label("6. Điều muốn kiểm tra thêm ở tiết sau:", small);
            reflectionDraft.testNext = GUILayout.TextField(reflectionDraft.testNext ?? "", 250);
            GUILayout.Label("Ghi chú thêm (tối đa 500 ký tự):", small);
            reflectionDraft.freeNote = GUILayout.TextArea(reflectionDraft.freeNote ?? "", 500, textArea, GUILayout.Height(58));

            GUILayout.EndScrollView();
            GUILayout.EndArea();

            if (Button(126, 634, 540, 44, "Hoàn tất phản tư & nhận trao đổi của cô Mai"))
                SubmitReflection(reflectionDraft);
            if (Button(680, 634, 474, 44, "Tạm đóng để xem lại lớp / nhật ký"))
                SetPage(ScreenPage.Gameplay);
        }

        private void DrawPause()
        {
            Panel("Tạm dừng (đồng hồ tiết học đang dừng)");
            if (Button(350, 225, 580, 48, "Tiếp tục")) SetPage(ScreenPage.Gameplay);
            if (Button(350, 285, 580, 48, "Sổ lớp 4 nhóm & học liệu [Tab]")) SetPage(ScreenPage.Planner);
            if (Button(350, 345, 580, 48, "Nhật ký & bằng chứng [J]")) SetPage(ScreenPage.Journal);
            if (Button(350, 405, 580, 48, "Lưu cục bộ trên thiết bị")) runtime.Save(allowOverwriteWithEmpty: false);
            if (Button(350, 465, 580, 48, "Cài đặt âm thanh & trợ năng")) OpenSettings();
            if (Button(350, 525, 580, 48, "Về màn hình chính"))
            {
                runtime.Save(allowOverwriteWithEmpty: false);
                SetPage(ScreenPage.MainMenu);
            }
            GUI.Label(new Rect(175, 596, 930, 60), runtime.SaveStatus, small);
        }

        private void DrawSettings()
        {
            Panel("Âm thanh & trợ năng");
            GUILayout.BeginArea(new Rect(160, 195, 960, 400));
            GUILayout.Label("Độ nhạy chuột", body); Settings.sensitivity = GUILayout.HorizontalSlider(Settings.sensitivity, 0.02f, 0.3f);
            GUILayout.Label("Cỡ chữ", body); Settings.textScale = GUILayout.HorizontalSlider(Settings.textScale, 1f, 1.35f);
            GUILayout.Label("Âm nền", body); Settings.ambience = GUILayout.HorizontalSlider(Settings.ambience, 0f, 1f);
            GUILayout.Label("Hiệu ứng tương tác & chuông tiết", body); Settings.effects = GUILayout.HorizontalSlider(Settings.effects, 0f, 1f);
            GUILayout.Label("Nhạc nền nhẹ", body); Settings.music = GUILayout.HorizontalSlider(Settings.music, 0f, 1f);
            Settings.highContrast = GUILayout.Toggle(Settings.highContrast, "Tăng tương phản giao diện");
            Settings.reduceMotion = GUILayout.Toggle(Settings.reduceMotion, "Giảm chuyển động nhân vật (tư thế tĩnh phù hợp)");
            GUILayout.EndArea();
            if (Button(126, 634, 1028, 44, "Lưu cài đặt và quay lại"))
            {
                SaveSettings();
                SetPage(returnPage);
            }
        }

        private void DrawResults()
        {
            Panel("Hồ sơ trải nghiệm nghề giáo viên — phiên thử nghiệm");
            GUILayout.BeginArea(new Rect(126, 180, 1028, 405));
            scroll = GUILayout.BeginScrollView(scroll, false, true);
            GUILayout.Label("TRAO ĐỔI CỦA CÔ MAI (dẫn chiếu sự kiện trong phiên):", sectionHeader);
            GUILayout.Label(runtime.Session.BuildMentorDebrief(), body, GUILayout.Width(980));
            GUILayout.Space(12);
            GUILayout.Label(runtime.Session.Report(), body, GUILayout.Width(980));
            GUILayout.EndScrollView();
            GUILayout.EndArea();

            if (Button(126, 600, 330, 44, "Lưu hồ sơ trên thiết bị")) runtime.Save(allowOverwriteWithEmpty: false);
            if (Button(472, 600, 340, 44, "Quay lại trường · [P] mở lại")) { runtime.Save(allowOverwriteWithEmpty: false); SetPage(ScreenPage.Gameplay); }
            if (Button(828, 600, 326, 44, "Về màn hình chính")) { runtime.Save(allowOverwriteWithEmpty: false); SetPage(ScreenPage.MainMenu); }
            GUI.Label(new Rect(126, 650, 1028, 28), runtime.SaveStatus, small);
        }

        private void OnDestroy()
        {
            if (panelBgTex != null) Destroy(panelBgTex);
            if (highContrastBgTex != null) Destroy(highContrastBgTex);
        }
    }
}
