using UnityEngine;
using JobSimu.Core;
using JobSimu.Interaction;

namespace JobSimu.Dialogue
{
    /// <summary>World object or NPC presenting structured, deterministic actions from content.</summary>
    public sealed class DialogueStation : Interactable
    {
        public string stationId, title, introduction;
        public string variantFlag, variantIntroduction;
        public SimulationRuntime runtime;
        public override string Prompt => title;
        public string Introduction => !string.IsNullOrEmpty(variantFlag) && runtime.Session.HasFlag(variantFlag) ? variantIntroduction : introduction;
        public override void Interact(PlayerInteractor actor)
        {
            if (runtime.Started) runtime.ui.OpenStation(this);
        }
    }
}
