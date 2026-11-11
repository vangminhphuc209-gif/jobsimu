using UnityEngine;
using JobSimu.Quests;
using JobSimu.UI;

namespace JobSimu.Core
{
    /// <summary>Synthesized placeholder audio cues (ambience, interaction cue, school bell); no imported recordings.</summary>
    public sealed class SimulationAudio : MonoBehaviour
    {
        public SimulationRuntime runtime;
        private AudioSource ambience, music, effects;
        private AudioClip cue, bell, wind, pad;
        private LessonPhase lastPhase;
        private bool warnedProjector;

        private void Awake()
        {
            ambience = gameObject.AddComponent<AudioSource>();
            music = gameObject.AddComponent<AudioSource>();
            effects = gameObject.AddComponent<AudioSource>();
            wind = MakeClip("School air · synthesized", 3f, 0);
            pad = MakeClip("Quiet pad · synthesized", 4f, 1);
            cue = MakeClip("Interaction cue", 0.16f, 2);
            bell = MakeClip("School bell cue", 0.55f, 3);
            ambience.clip = wind; ambience.loop = true;
            music.clip = pad; music.loop = true;
        }

        public void Cue() { if (runtime != null && runtime.Started && cue != null) effects.PlayOneShot(cue); }
        public void BellCue() { if (runtime != null && runtime.Started && bell != null) effects.PlayOneShot(bell); }

        private void Update()
        {
            if (runtime == null || runtime.ui == null || runtime.ui.Settings == null) return;
            SimulationSettings settings = runtime.ui.Settings;
            ambience.volume = settings.ambience * 0.16f;
            music.volume = settings.music * 0.10f;
            effects.volume = settings.effects * 0.22f;

            if (runtime.Started && !runtime.ui.BlocksGameplay)
            {
                if (!ambience.isPlaying) ambience.Play();
                if (!music.isPlaying) music.Play();
            }
            else
            {
                ambience.Pause();
                music.Pause();
            }

            if (runtime.Session != null && runtime.Started)
            {
                LessonPhase current = runtime.Session.Phase;
                if (current != lastPhase)
                {
                    if (current == LessonPhase.Opening || (current == LessonPhase.Reflection && runtime.Session.HasTimedOut))
                        BellCue();
                    lastPhase = current;
                }
                if (!warnedProjector && runtime.Session.Resources.projectorBroken)
                {
                    warnedProjector = true;
                    Cue();
                }
            }
        }

        private static AudioClip MakeClip(string name, float seconds, int kind)
        {
            const int rate = 22050;
            var data = new float[(int)(rate * seconds)];
            var noise = new System.Random(37);
            for (int i = 0; i < data.Length; i++)
            {
                float t = (float)i / rate;
                float fade = Mathf.Sin(Mathf.PI * i / data.Length);
                if (kind == 0)
                    data[i] = (float)(noise.NextDouble() - 0.5) * 0.12f * fade;
                else if (kind == 3) // Bell tone
                    data[i] = (Mathf.Sin(2f * Mathf.PI * 520f * t) * 0.22f + Mathf.Sin(2f * Mathf.PI * 1040f * t) * 0.10f) * Mathf.Exp(-t * 4f);
                else
                    data[i] = (Mathf.Sin(2f * Mathf.PI * (kind == 1 ? 220f : 660f) * t) + 0.3f * Mathf.Sin(2f * Mathf.PI * 330f * t)) * 0.18f * fade;
            }
            AudioClip clip = AudioClip.Create(name, data.Length, 1, rate, false);
            clip.SetData(data, 0);
            return clip;
        }

        private void OnDestroy()
        {
            Destroy(cue);
            Destroy(bell);
            Destroy(wind);
            Destroy(pad);
        }
    }
}
