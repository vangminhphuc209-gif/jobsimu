using UnityEngine;

namespace JobSimu.Core
{
    /// <summary>Shared player/UI boundary. Controllers know nothing about a profession.</summary>
    public abstract class InteractionUI : MonoBehaviour
    {
        public abstract bool BlocksGameplay { get; }
        public abstract void SetPrompt(string value);
        public abstract void HandleCancel();
    }
}
