using UnityEngine;
using JobSimu.Core;

namespace JobSimu.Gameplay
{
    /// <summary>Teacher-specific presentation of the shared reducer state.</summary>
    public sealed class TeacherLessonVisuals : MonoBehaviour
    {
        public SimulationRuntime runtime;
        public GameObject printedMaterials, groupMaterials, projectorSignal;
        public TextMesh boardText;
        private string lastStep;
        private void Update()
        {
            if (runtime.Session == null) return;
            printedMaterials.SetActive(runtime.Session.HasFlag("printed"));
            groupMaterials.SetActive(runtime.Session.HasFlag("group-kit"));
            projectorSignal.SetActive(runtime.Session.HasFlag("projector") && runtime.Session.StepId != "begin");
            if (lastStep == runtime.Session.StepId) return;
            lastStep = runtime.Session.StepId;
            boardText.text = lastStep == "brief" || lastStep == "prepare" || lastStep == "begin" ?
                "NƯỚC MƯA ĐI ĐÂU?\nQuan sát · So sánh · Giải thích" :
                lastStep == "teach" ? "CÙNG LƯỢNG MƯA\nĐất trồng / Sân lát kín\nĐiều gì cần kiểm chứng?" :
                "CUỐI TIẾT\nNêu một giải thích và một bằng chứng\nĐiều gì còn cần tìm hiểu?";
        }
    }
}
