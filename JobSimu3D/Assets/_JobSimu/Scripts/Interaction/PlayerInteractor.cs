using UnityEngine;
using JobSimu.Player;
using JobSimu.Gameplay;

namespace JobSimu.Interaction
{
    public sealed class PlayerInteractor : MonoBehaviour
    {
        public FirstPersonController player;
        public Camera viewCamera;
        public PrototypeHUD hud;
        public JobSimu.Core.InteractionUI interactionUI;
        [Min(0.5f)] public float reach = 2.8f;
        public LayerMask interactionMask = Physics.DefaultRaycastLayers;

        private void Update()
        {
            var ui = interactionUI != null ? interactionUI : hud;
            if (ui == null || player == null) return;
            ui.SetPrompt(string.Empty);
            if (!player.CanControl) return;
            Interactable target = FindTarget();
            if (target == null) return;
            ui.SetPrompt("[E] " + target.Prompt);
            if (player.InteractPressed) target.Interact(this);
        }

        // The first solid hit occludes all objects behind it, including through walls, and ignores hidden/disabled targets.
        public Interactable FindTarget()
        {
            if (viewCamera == null) return null;
            Ray ray = new Ray(viewCamera.transform.position, viewCamera.transform.forward);
            if (!Physics.Raycast(ray, out RaycastHit hit, reach, interactionMask, QueryTriggerInteraction.Ignore))
                return null;
            Interactable candidate = hit.collider.GetComponentInParent<Interactable>();
            if (candidate == null || !candidate.CanInteract) return null;

            Renderer hitRenderer = hit.collider.GetComponent<Renderer>();
            if (hitRenderer != null && !hitRenderer.enabled)
                return null;

            if (hitRenderer == null)
            {
                bool anyVisibleChild = false;
                foreach (Renderer r in candidate.GetComponentsInChildren<Renderer>(false))
                    if (r.enabled) { anyVisibleChild = true; break; }
                if (!anyVisibleChild) return null;
            }
            return candidate;
        }
    }
}
