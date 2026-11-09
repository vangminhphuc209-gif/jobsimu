using UnityEngine;
using JobSimu.Player;
using JobSimu.Gameplay;

namespace JobSimu.Core
{
    /// <summary>Explicit scene references shared by runtime and editor validation.</summary>
    public sealed class PrototypeContext : MonoBehaviour
    {
        public Transform environment;
        public Transform spawn;
        public FirstPersonController player;
        public Camera playerCamera;
        public PrototypeHUD hud;
        public TeacherOrientation orientation;
        public Transform[] routeWaypoints;
    }
}
