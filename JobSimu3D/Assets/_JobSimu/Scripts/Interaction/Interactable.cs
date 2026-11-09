using UnityEngine;

namespace JobSimu.Interaction
{
    public abstract class Interactable : MonoBehaviour
    {
        public abstract string Prompt { get; }
        public abstract void Interact(PlayerInteractor actor);
    }
}
