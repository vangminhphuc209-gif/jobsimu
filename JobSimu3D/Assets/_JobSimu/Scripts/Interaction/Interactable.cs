using UnityEngine;

namespace JobSimu.Interaction
{
    public abstract class Interactable : MonoBehaviour
    {
        public virtual bool CanInteract => isActiveAndEnabled && gameObject.activeInHierarchy;
        public abstract string Prompt { get; }
        public abstract void Interact(PlayerInteractor actor);
    }
}
