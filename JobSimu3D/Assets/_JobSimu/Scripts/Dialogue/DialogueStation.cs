using UnityEngine;
using JobSimu.Core;
using JobSimu.Interaction;
using JobSimu.Consequences;

namespace JobSimu.Dialogue
{
    /// <summary>World object or NPC presenting structured, deterministic actions and readable artifacts.</summary>
    public sealed class DialogueStation : Interactable
    {
        public string stationId, title, introduction;
        public string variantFlag, variantIntroduction;
        public string groupId;
        public string visibleOnlyIfFlag;
        public SimulationRuntime runtime;

        public override bool CanInteract
        {
            get
            {
                if (!base.CanInteract) return false;
                if (!string.IsNullOrEmpty(visibleOnlyIfFlag))
                {
                    if (runtime == null || runtime.Session == null || !runtime.Session.HasFlag(visibleOnlyIfFlag))
                        return false;
                }
                return true;
            }
        }

        public override string Prompt => title;

        public string Introduction
        {
            get
            {
                if (runtime != null && runtime.Session != null)
                {
                    if (!string.IsNullOrEmpty(groupId))
                    {
                        GroupState g = runtime.Session.GetGroup(groupId);
                        if (g != null)
                            return $"{g.worksheetTitle}\n• Hiện trạng trên bàn: {g.worksheetContent}\n• Ghi chú của bạn: {g.teacherNote}";
                    }
                    if (!string.IsNullOrEmpty(variantFlag) && runtime.Session.HasFlag(variantFlag))
                        return variantIntroduction;
                }
                return introduction;
            }
        }

        public override void Interact(PlayerInteractor actor)
        {
            if (runtime != null && runtime.Started && CanInteract) runtime.ui.OpenStation(this);
        }
    }
}
