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
        public string groupId;
        public bool isBackgroundStudent;
        public SimulationRuntime runtime;
        public DialogueStation station;
        public Transform[] waypoints;
        public Transform visual, head, leftArm, rightArm, leftLeg, rightLeg;
        public float seatDrop = 0.28f;
        public NpcActivity Activity { get; private set; }
        public string[] Memory => runtime != null && runtime.Session != null && profile != null
            ? runtime.Session.MemoryFor(profile.id).Select(e => e.actionId).ToArray()
            : System.Array.Empty<string>();

        private Vector3 visualOrigin;
        private Quaternion headOrigin = Quaternion.identity;
        private float decisionTimer, reactionUntil;
        private int lastEvidenceCount;
        private ScheduleEntry currentSchedule;

        private void Start()
        {
            if (visual != null) visualOrigin = visual.localPosition;
            if (head != null) headOrigin = head.localRotation;
        }

        private void Update()
        {
            if (runtime == null || !runtime.Started || runtime.Session == null || profile == null) return;
            if (station != null && runtime.ui != null && runtime.ui.CurrentStation == station)
            {
                Activity = NpcActivity.Talking;
                Animate();
                return;
            }
            if (runtime.ui != null && runtime.ui.BlocksGameplay) return;

            decisionTimer -= Time.deltaTime;
            if (decisionTimer <= 0f)
            {
                decisionTimer = 0.2f;
                currentSchedule = profile.schedule
                    .Where(s => s.minute <= runtime.Session.Minute)
                    .OrderBy(s => s.minute)
                    .LastOrDefault();
                if (lastEvidenceCount != runtime.Session.Evidence.Count)
                {
                    lastEvidenceCount = runtime.Session.Evidence.Count;
                    reactionUntil = Time.time + 1.8f;
                }
            }

            Activity = NpcActivity.Idle;
            if (currentSchedule != null && waypoints != null &&
                currentSchedule.waypoint >= 0 && currentSchedule.waypoint < waypoints.Length &&
                waypoints[currentSchedule.waypoint] != null)
            {
                Vector3 target = waypoints[currentSchedule.waypoint].position;
                Vector3 delta = target - transform.position;
                delta.y = 0f;
                if (delta.magnitude > 0.05f)
                {
                    Activity = NpcActivity.Walking;
                    float distance = Mathf.Min(profile.speed * Time.deltaTime, delta.magnitude);
                    if (!Physics.SphereCast(transform.position + Vector3.up * 0.85f, 0.22f, delta.normalized,
                        out _, distance + 0.02f, Physics.DefaultRaycastLayers, QueryTriggerInteraction.Ignore))
                        transform.position += delta.normalized * distance;
                    transform.rotation = Quaternion.Slerp(transform.rotation, Quaternion.LookRotation(delta), Time.deltaTime * 5f);
                }
                else
                {
                    Activity = currentSchedule.seated ? NpcActivity.Seated : NpcActivity.Listening;
                    Quaternion targetRot = waypoints[currentSchedule.waypoint].rotation;
                    transform.rotation = Quaternion.Slerp(transform.rotation, targetRot, Time.deltaTime * 4f);
                }
            }

            if (Activity != NpcActivity.Walking && Time.time < reactionUntil) Activity = NpcActivity.Reacting;
            Animate();
        }

        private void Animate()
        {
            if (visual == null) return;
            bool seated = Activity == NpcActivity.Seated || (currentSchedule != null && currentSchedule.seated && Activity != NpcActivity.Walking);
            bool reduceMotion = runtime.ui != null && runtime.ui.Settings != null && runtime.ui.Settings.reduceMotion;

            // Always apply clean static base pose when reduceMotion is active.
            if (reduceMotion)
            {
                visual.localPosition = visualOrigin + Vector3.down * (seated ? seatDrop : 0f);
                if (head != null) head.localRotation = headOrigin;
                if (leftLeg != null) leftLeg.localRotation = seated ? Quaternion.Euler(-82f, 0f, 0f) : Quaternion.identity;
                if (rightLeg != null) rightLeg.localRotation = seated ? Quaternion.Euler(-82f, 0f, 0f) : Quaternion.identity;
                if (leftArm != null) leftArm.localRotation = seated ? Quaternion.Euler(-38f, 0f, 0f) : Quaternion.identity;
                if (rightArm != null) rightArm.localRotation = seated ? Quaternion.Euler(-38f, 0f, 0f) : Quaternion.identity;
                return;
            }

            StudentPosture posture = ResolvePosture();
            float t = Time.time + (profile.id.GetHashCode() & 7) * 0.35f;

            if (Activity == NpcActivity.Walking)
            {
                float stride = Mathf.Sin(t * 7f) * 22f;
                if (leftLeg != null) leftLeg.localRotation = Quaternion.Euler(stride, 0f, 0f);
                if (rightLeg != null) rightLeg.localRotation = Quaternion.Euler(-stride, 0f, 0f);
                if (leftArm != null) leftArm.localRotation = Quaternion.Euler(-stride, 0f, 0f);
                if (rightArm != null) rightArm.localRotation = Quaternion.Euler(stride, 0f, 0f);
                if (head != null) head.localRotation = headOrigin;
                visual.localPosition = visualOrigin;
                return;
            }

            if (seated)
            {
                visual.localPosition = visualOrigin + Vector3.down * seatDrop;
                if (leftLeg != null) leftLeg.localRotation = Quaternion.Euler(-82f, 0f, 0f);
                if (rightLeg != null) rightLeg.localRotation = Quaternion.Euler(-82f, 0f, 0f);
            }
            else
            {
                visual.localPosition = visualOrigin + Vector3.up * (Mathf.Sin(t * 1.6f) * 0.008f);
                if (leftLeg != null) leftLeg.localRotation = Quaternion.identity;
                if (rightLeg != null) rightLeg.localRotation = Quaternion.identity;
            }

            bool talking = Activity == NpcActivity.Talking;
            float baseArmX = seated ? -40f : 0f;
            float leftX = baseArmX, rightX = baseArmX, rightZ = 0f, leftZ = 0f, headYaw = 0f, headPitch = 0f;

            if (talking)
            {
                rightX = baseArmX - 24f + Mathf.Sin(t * 3.4f) * 12f;
                leftZ = -8f;
                headPitch = Mathf.Sin(t * 2.5f) * 4f;
            }
            else if (posture == StudentPosture.RaisingHand)
            {
                rightX = -150f + Mathf.Sin(t * 2f) * 5f;
                rightZ = -10f;
            }
            else if (posture == StudentPosture.IdleLookingAround)
            {
                headYaw = Mathf.Sin(t * 1.4f) * 24f;
                leftX = seated ? -22f : 0f;
                rightX = seated ? -18f : 0f;
            }
            else if (posture == StudentPosture.Discussing)
            {
                headYaw = Mathf.Sin(t * 2.2f) * 14f;
                rightX = baseArmX - 15f + Mathf.Sin(t * 3.2f) * 8f;
            }
            else if (posture == StudentPosture.SeatedWorking && seated)
            {
                headPitch = 14f;
                rightX = -48f + Mathf.Sin(t * 4.5f) * 4f;
                leftX = -42f;
            }

            if (leftArm != null) leftArm.localRotation = Quaternion.Euler(leftX, 0f, leftZ);
            if (rightArm != null) rightArm.localRotation = Quaternion.Euler(rightX, 0f, rightZ);
            if (head != null) head.localRotation = headOrigin * Quaternion.Euler(headPitch, headYaw, 0f);
        }

        private StudentPosture ResolvePosture()
        {
            if (runtime == null || runtime.Session == null) return StudentPosture.Listening;
            FocusStudentState student = runtime.Session.GetStudent(profile.id);
            if (student != null) return student.posture;

            if (!string.IsNullOrEmpty(groupId))
            {
                GroupState group = runtime.Session.GetGroup(groupId);
                if (group != null)
                {
                    if (group.workStatus == GroupWorkStatus.IdleDrifting) return StudentPosture.IdleLookingAround;
                    if (group.workStatus == GroupWorkStatus.CrossChecking || group.workStatus == GroupWorkStatus.WorkingMisaligned)
                        return StudentPosture.Discussing;
                    if (runtime.Session.Phase == LessonPhase.Facilitating)
                        return StudentPosture.SeatedWorking;
                }
            }
            return StudentPosture.Listening;
        }
    }
}
