using UnityEngine;

namespace JobSimu.Core
{
    /// <summary>Keeps the depth-tested world-text shader in sync with Unity's dynamic font atlas.</summary>
    [ExecuteAlways, RequireComponent(typeof(TextMesh), typeof(MeshRenderer))]
    public sealed class WorldLabel : MonoBehaviour
    {
        private static readonly int MainTexture = Shader.PropertyToID("_MainTex");
        private TextMesh label;
        private MeshRenderer meshRenderer;
        private MaterialPropertyBlock properties;
        private void OnEnable()
        {
            label = GetComponent<TextMesh>();
            meshRenderer = GetComponent<MeshRenderer>();
            properties = new MaterialPropertyBlock();
            Font.textureRebuilt += RefreshAtlas;
            RefreshAtlas(label.font);
        }
        private void OnDisable() => Font.textureRebuilt -= RefreshAtlas;
        private void RefreshAtlas(Font font)
        {
            if (font == null || label == null || font != label.font || meshRenderer == null) return;
            meshRenderer.GetPropertyBlock(properties);
            properties.SetTexture(MainTexture, font.material.mainTexture);
            meshRenderer.SetPropertyBlock(properties);
        }
    }
}
