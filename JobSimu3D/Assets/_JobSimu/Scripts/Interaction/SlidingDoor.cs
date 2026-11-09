using UnityEngine;

namespace JobSimu.Interaction
{
    /// <summary>Surface-mounted sliding leaf; conservative swept occupancy prevents player crushing.</summary>
    [RequireComponent(typeof(BoxCollider))]
    public sealed class SlidingDoor : Interactable
    {
        public Vector3 localTravel = new Vector3(1.65f, 0f, 0f);
        [Min(0.1f)] public float speed = 1.8f;
        public bool IsOpen => wantsOpen;
        public override string Prompt => wantsOpen ? "Đóng cửa" : "Mở cửa";
        private Vector3 closedPosition;
        private BoxCollider leaf;
        private bool wantsOpen;

        private void Awake() => Initialize();
        private void Initialize()
        {
            if (leaf != null) return;
            closedPosition = transform.localPosition;
            leaf = GetComponent<BoxCollider>();
        }
        public override void Interact(PlayerInteractor actor) => wantsOpen = !wantsOpen;
        private void Update() => Advance(Time.deltaTime);

        public void Advance(float deltaTime)
        {
            Initialize();
            Vector3 target = closedPosition + (wantsOpen ? localTravel : Vector3.zero);
            Vector3 next = Vector3.MoveTowards(transform.localPosition, target, speed * deltaTime);
            Vector3 worldDelta = transform.parent == null ? next - transform.localPosition :
                transform.parent.TransformVector(next - transform.localPosition);
            // Do not discard sub-millimetre steps: headless/high-FPS frames still accumulate motion.
            if (worldDelta.sqrMagnitude == 0f) return;
            // Union of current and next bounds covers the whole translation, even on a slow frame.
            Bounds swept = leaf.bounds;
            swept.Encapsulate(new Bounds(leaf.bounds.center + worldDelta, leaf.bounds.size));
            swept.Expand(0.08f);
            foreach (Collider hit in Physics.OverlapBox(swept.center, swept.extents,
                Quaternion.identity, ~0, QueryTriggerInteraction.Ignore))
                if (hit.GetComponentInParent<CharacterController>() != null) return;
            transform.localPosition = next;
        }
    }
}
