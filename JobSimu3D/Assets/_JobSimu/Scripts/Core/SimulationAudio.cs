using UnityEngine;
using JobSimu.UI;

namespace JobSimu.Core
{
    /// <summary>Original synthesized placeholder cues; no imported/licensed recordings.</summary>
    public sealed class SimulationAudio : MonoBehaviour
    {
        public SimulationRuntime runtime;
        private AudioSource ambience, music, effects;
        private AudioClip cue, wind, pad;
        private void Awake()
        {
            ambience = gameObject.AddComponent<AudioSource>(); music = gameObject.AddComponent<AudioSource>(); effects = gameObject.AddComponent<AudioSource>();
            wind = MakeClip("School air · synthesized", 3, 0); pad = MakeClip("Quiet pad · synthesized", 4, 1); cue = MakeClip("Interaction cue", .16f, 2);
            ambience.clip = wind; ambience.loop = true; music.clip = pad; music.loop = true;
        }
        public void Cue() { if (runtime.Started) effects.PlayOneShot(cue); }
        private void Update()
        {
            SimulationSettings settings = runtime.ui.Settings;
            if (settings == null) return;
            ambience.volume = settings.ambience * .16f; music.volume = settings.music * .1f; effects.volume = settings.effects * .2f;
            if (runtime.Started && !runtime.ui.BlocksGameplay)
            { if (!ambience.isPlaying) ambience.Play(); if (!music.isPlaying) music.Play(); }
            else { ambience.Pause(); music.Pause(); }
        }
        private static AudioClip MakeClip(string name, float seconds, int kind)
        {
            const int rate = 22050;
            var data = new float[(int)(rate * seconds)]; var noise = new System.Random(37);
            for (int i = 0; i < data.Length; i++)
            {
                float t = (float)i / rate;
                float fade = Mathf.Sin(Mathf.PI * i / data.Length);
                data[i] = kind == 0 ? (float)(noise.NextDouble() - .5) * .12f * fade :
                    (Mathf.Sin(2 * Mathf.PI * (kind == 1 ? 220 : 660) * t) + .3f * Mathf.Sin(2 * Mathf.PI * 330 * t)) * .18f * fade;
            }
            AudioClip clip = AudioClip.Create(name, data.Length, 1, rate, false); clip.SetData(data, 0); return clip;
        }
        private void OnDestroy() { Destroy(cue); Destroy(wind); Destroy(pad); }
    }
}
