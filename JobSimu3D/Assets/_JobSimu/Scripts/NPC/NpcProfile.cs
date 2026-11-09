using System;
using UnityEngine;

namespace JobSimu.NPC
{
    [Serializable] public sealed class ScheduleEntry
    {
        public int minute;
        public int waypoint;
        public bool seated;
    }
    [CreateAssetMenu(menuName = "JobSimu/NPC profile")]
    public sealed class NpcProfile : ScriptableObject
    {
        public string id, displayName, role;
        [TextArea] public string introduction;
        public ScheduleEntry[] schedule = Array.Empty<ScheduleEntry>();
        public float speed = .7f;
    }
}
