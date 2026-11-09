using System.Linq;
using UnityEngine;
using JobSimu.Core;
using JobSimu.Dialogue;
using JobSimu.Consequences;
using JobSimu.Quests;

namespace JobSimu.NPC
{
    public enum NpcActivity { Idle, Walking, Seated, Listening, Reacting, Talking }
    public sealed class ScheduledNpc : MonoBehaviour
    {
        public NpcProfile profile;
        public SimulationRuntime runtime;
        public DialogueStation station;
        public Transform[] waypoints;
        public Transform visual, leftArm, rightArm, leftLeg, rightLeg;
        public NpcActivity Activity { get; private set; }
        public string[] Memory => runtime.Session.MemoryFor(profile.id).Select(e => e.actionId).ToArray();
        private Vector3 visualOrigin;
        private float decisionTimer, reactionUntil;
        private int lastEvidenceCount;
        private ScheduleEntry currentSchedule;
        private void Start() { visualOrigin = visual.localPosition; }
        private void Update()
        {
            if (!runtime.Started) return;
            if (runtime.ui.CurrentStation == station)
            {
                Activity = NpcActivity.Talking;
                Animate(); return;
            }
            if (runtime.ui.BlocksGameplay) return;
            decisionTimer -= Time.deltaTime;
            if (decisionTimer <= 0)
            {
                decisionTimer = .2f;
                currentSchedule = profile.schedule.Where(s => s.minute <= runtime.Session.Minute).OrderBy(s => s.minute).LastOrDefault();
                if (lastEvidenceCount != runtime.Session.Evidence.Count)
                { lastEvidenceCount = runtime.Session.Evidence.Count; reactionUntil = Time.time + 2; }
            }
            Activity = NpcActivity.Idle;
            if (currentSchedule != null && currentSchedule.waypoint >= 0 && currentSchedule.waypoint < waypoints.Length)
            {
                Vector3 target = waypoints[currentSchedule.waypoint].position;
                Vector3 delta = target - transform.position; delta.y = 0;
                if (delta.magnitude > .05f)
                {
                    Activity = NpcActivity.Walking;
                    float distance = Mathf.Min(profile.speed * Time.deltaTime, delta.magnitude);
                    // Waypoints are authored in clear aisles. Stop rather than walk through blockers.
                    if (!Physics.SphereCast(transform.position + Vector3.up * .85f, .22f, delta.normalized,
                        out _, distance + .02f, Physics.DefaultRaycastLayers, QueryTriggerInteraction.Ignore))
                        transform.position += delta.normalized * distance;
                    transform.rotation = Quaternion.Slerp(transform.rotation, Quaternion.LookRotation(delta), Time.deltaTime * 5);
                }
                else Activity = currentSchedule.seated ? NpcActivity.Seated : NpcActivity.Listening;
            }
            if (Activity != NpcActivity.Walking && Time.time < reactionUntil) Activity = NpcActivity.Reacting;
            Animate();
        }
        private void Animate()
        {
            if (runtime.ui.Settings.reduceMotion) return;
            float stride = Activity == NpcActivity.Walking ? Mathf.Sin(Time.time * 7) * 23 : 0;
            bool talk = Activity == NpcActivity.Talking || Activity == NpcActivity.Reacting;
            float gesture = talk ? Mathf.Sin(Time.time * 3) * 15 - 22 : 0;
            bool engaged = runtime.Session.Consequence(ConsequenceKind.Participation) > 1;
            leftLeg.localRotation = Quaternion.Euler(stride, 0, 0);
            rightLeg.localRotation = Quaternion.Euler(-stride, 0, 0);
            leftArm.localRotation = Quaternion.Euler(-stride, 0, talk ? -12 : 0);
            rightArm.localRotation = Quaternion.Euler(gesture + stride, 0, engaged && Activity == NpcActivity.Listening ? -45 : 0);
            // Procedural blockout posing, not a humanoid rig or authored character animation.
            visual.localPosition = visualOrigin + Vector3.up * (Activity == NpcActivity.Seated ? -.24f : Mathf.Sin(Time.time * 1.7f) * .008f);
        }
    }
}
