using System;
using UnityEngine;

namespace JobSimu.UI
{
    [Serializable] public sealed class SimulationSettings
    {
        public float sensitivity = .09f, textScale = 1f, ambience = .2f, effects = .5f, music = .1f;
        public bool highContrast, reduceMotion;
        public static SimulationSettings Load()
        {
            SimulationSettings value;
            try { value = JsonUtility.FromJson<SimulationSettings>(PlayerPrefs.GetString("jobsimu.settings.v1", "{}")) ?? new SimulationSettings(); }
            catch (ArgumentException) { value = new SimulationSettings(); }
            value.sensitivity = FiniteClamp(value.sensitivity, .02f, .3f, .09f);
            value.textScale = FiniteClamp(value.textScale, 1, 1.35f, 1);
            value.ambience = FiniteClamp(value.ambience, 0, 1, .2f);
            value.effects = FiniteClamp(value.effects, 0, 1, .5f);
            value.music = FiniteClamp(value.music, 0, 1, .1f);
            return value;
        }
        private static float FiniteClamp(float value, float min, float max, float fallback) => float.IsNaN(value) || float.IsInfinity(value) ? fallback : Mathf.Clamp(value, min, max);
        public void Save() { PlayerPrefs.SetString("jobsimu.settings.v1", JsonUtility.ToJson(this)); PlayerPrefs.Save(); }
    }
}
