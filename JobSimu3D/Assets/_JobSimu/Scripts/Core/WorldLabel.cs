using UnityEngine;

namespace JobSimu.Core
{
    /// <summary>Keeps the depth-tested world-text shader in sync with Unity's dynamic font atlas.</summary>
    [ExecuteAlways, RequireComponent(typeof(TextMesh), typeof(MeshRenderer))]
    public sealed class WorldLabel : MonoBehaviour
    {
        private static readonly int MainTexture = Shader.PropertyToID("_MainTex");
        public bool faceCamera;
        private TextMesh label;
        private MeshRenderer meshRenderer;
        private MaterialPropertyBlock properties;

        private void OnEnable()
        {
            label = GetComponent<TextMesh>();
            // Bundle Vietnamese glyphs for Web, where OS font fallback is unavailable.
            // Runtime only: opening legacy scenes must not change their serialized assets.
            if (Application.isPlaying)
            {
                Font bundledFont = Resources.Load<Font>("JobSimuUI");
                if (bundledFont != null) label.font = bundledFont;
            }
            meshRenderer = GetComponent<MeshRenderer>();
            properties = new MaterialPropertyBlock();
            Font.textureRebuilt += RefreshAtlas;
            RefreshAtlas(label.font);
        }

        private void OnDisable() => Font.textureRebuilt -= RefreshAtlas;

        private void LateUpdate()
        {
            if (!faceCamera || !Application.isPlaying) return;
            Camera cam = Camera.main;
            if (cam == null) return;
            Vector3 toLabel = transform.position - cam.transform.position;
            toLabel.y = 0f;
            if (toLabel.sqrMagnitude > 0.001f)
                transform.rotation = Quaternion.LookRotation(toLabel.normalized, Vector3.up);
        }

        private void RefreshAtlas(Font font)
        {
            if (font == null || label == null || font != label.font || meshRenderer == null) return;
            meshRenderer.GetPropertyBlock(properties);
            properties.SetTexture(MainTexture, font.material.mainTexture);
            meshRenderer.SetPropertyBlock(properties);
        }
    }
}
