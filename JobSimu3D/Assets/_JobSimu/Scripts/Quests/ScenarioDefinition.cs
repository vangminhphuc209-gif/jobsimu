using System;
using System.Collections.Generic;
using System.Linq;
using UnityEngine;

namespace JobSimu.Quests
{
    public enum Profession { Teacher, Developer, Doctor, PublicSecurity }
    public enum EvidenceDimension { Analysis, Creativity, Empathy, DecisionMaking, Accuracy }
    public enum ConsequenceKind { Trust, Understanding, Participation, Preparation, TimePressure }
    public enum InteractionKind { Physical, Dialogue, Evidence }

    public enum LessonPhase
    {
        Briefing,
        Preparation,
        Opening,
        Facilitating,
        CheckingLearning,
        Reflection,
        Complete
    }

    [Serializable]
    public sealed class Consequence
    {
        public ConsequenceKind kind;
        public int amount;
    }

    [Serializable]
    public sealed class ConditionalEffect
    {
        public string requiresFlag;
        public Consequence[] effects = Array.Empty<Consequence>();
        public string feedback;
    }

    [Serializable]
    public sealed class ScenarioStep
    {
        public string id, objective;
        public LessonPhase phase;
        public bool terminal;
    }

    [Serializable]
    public sealed class ScenarioAction
    {
        public string id, stepId, stationId, nextStepId;
        public string label, tradeoff, feedback, evidence, npcId;
        public string targetGroupId;
        public string requiresFlag, forbidsFlag, setsFlag, clearsFlag;
        public string[] requiresAllFlags = Array.Empty<string>();
        public string[] requiresAnyFlags = Array.Empty<string>();
        public string[] forbidsAnyFlags = Array.Empty<string>();
        public string evidenceKey;
        public int minutes = 1, earliestMinute;
        public int minFacilitateActions;
        public bool oneTime = true;
        public bool grantsEvidence = true;
        public InteractionKind kind;
        public EvidenceDimension dimension;
        public Consequence[] effects = Array.Empty<Consequence>();
        public ConditionalEffect[] conditionalEffects = Array.Empty<ConditionalEffect>();
    }

    [Serializable]
    public sealed class TimedEvent
    {
        public string id, stepId, requiresFlag, forbidsFlag, anchorFlag, setsFlag, feedback;
        public string[] forbidsAnyFlags = Array.Empty<string>();
        public int delayMinutes = 2;
        public Consequence[] effects = Array.Empty<Consequence>();
    }

    [CreateAssetMenu(menuName = "JobSimu/Scenario")]
    public sealed class ScenarioDefinition : ScriptableObject
    {
        public string id, title, context, contentVersion = "2";
        public Profession profession;
        public string initialStepId;
        public int initialMinute = 430; // 07:10 briefing/prep start
        public int lessonNominalStartMinute = 450; // 07:30 class bell
        public int lessonEndMinute = 495; // 08:15 end of 45-minute period
        public string[] participants = Array.Empty<string>();
        public ScenarioStep[] steps = Array.Empty<ScenarioStep>();
        public ScenarioAction[] actions = Array.Empty<ScenarioAction>();
        public TimedEvent[] timedEvents = Array.Empty<TimedEvent>();

        public List<string> ValidateDefinition()
        {
            var errors = new List<string>();
            if (string.IsNullOrWhiteSpace(id) || string.IsNullOrWhiteSpace(contentVersion))
                errors.Add("Scenario identity missing.");
            if (steps == null || actions == null || timedEvents == null)
            {
                errors.Add("Null content arrays.");
                return errors;
            }
            if (steps.Any(s => s == null) || actions.Any(a => a == null) || timedEvents.Any(e => e == null))
            {
                errors.Add("Null content entry.");
                return errors;
            }
            var ids = new HashSet<string>();
            foreach (ScenarioStep step in steps)
                if (string.IsNullOrWhiteSpace(step.id) || !ids.Add(step.id))
                    errors.Add("Duplicate/empty step: " + step.id);
            if (!ids.Contains(initialStepId)) errors.Add("Initial step is missing.");
            if (!steps.Any(s => s.terminal)) errors.Add("No terminal step.");

            var actionIds = new HashSet<string>();
            foreach (ScenarioAction action in actions)
            {
                if (string.IsNullOrWhiteSpace(action.id) || action.id.StartsWith("@") || !actionIds.Add(action.id))
                    errors.Add("Invalid action ID: " + action?.id);
                if (!ids.Contains(action.stepId) || !ids.Contains(action.nextStepId))
                    errors.Add("Unknown step on " + action.id);
                if (string.IsNullOrWhiteSpace(action.stationId))
                    errors.Add("Missing station on " + action.id);
                if (action.grantsEvidence && string.IsNullOrWhiteSpace(action.evidence))
                    errors.Add("Missing evidence on " + action.id);
                if (action.minutes < 0 || action.minutes > 60)
                    errors.Add("Invalid duration on " + action.id);
                if (!string.IsNullOrEmpty(action.npcId) && !participants.Contains(action.npcId))
                    errors.Add("Unknown NPC " + action.npcId);
            }

            foreach (ScenarioStep step in steps.Where(s => !s.terminal))
            {
                bool hasRoute = actions.Any(a =>
                    a.stepId == step.id &&
                    a.nextStepId != step.id &&
                    string.IsNullOrEmpty(a.requiresFlag));
                if (!hasRoute && step.phase != LessonPhase.Opening && step.phase != LessonPhase.Facilitating && step.phase != LessonPhase.CheckingLearning)
                    errors.Add("No unconditional transition route from " + step.id);
            }

            var eventIds = new HashSet<string>();
            foreach (TimedEvent entry in timedEvents)
            {
                if (string.IsNullOrWhiteSpace(entry.id) || !eventIds.Add(entry.id) || !ids.Contains(entry.stepId) || entry.delayMinutes < 0)
                    errors.Add("Invalid timed event: " + entry?.id);
            }
            return errors;
        }
    }
}
