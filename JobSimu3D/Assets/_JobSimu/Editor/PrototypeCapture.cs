using System.IO;
using UnityEditor.SceneManagement;
using UnityEngine;
using JobSimu.Core;

namespace JobSimu.Editor
{
    /// <summary>Optional local render evidence; requires a graphics device, never runs a build.</summary>
    public static class PrototypeCapture
    {
        public static void CaptureBatch()
        {
            EditorSceneManager.OpenScene(SchoolPrototypeGenerator.ScenePath, OpenSceneMode.Single);
            PrototypeContext context = Object.FindAnyObjectByType<PrototypeContext>();
            Camera camera = context.playerCamera;
            Capture(camera, "Logs/JobSimu-courtyard.png");
            camera.transform.SetPositionAndRotation(new Vector3(-15, 1.65f, 9.5f), Quaternion.Euler(0, 25, 0));
            Capture(camera, "Logs/JobSimu-classroom.png");
            camera.transform.position = new Vector3(27, 23, -28);
            camera.transform.LookAt(new Vector3(0, 1, 5));
            Capture(camera, "Logs/JobSimu-campus.png");
            Debug.Log("JobSimu rendered three local preview images under Logs. HUD is not included in camera renders.");
        }
        private static void Capture(Camera camera, string path)
        {
            var target = new RenderTexture(1280, 720, 24);
            var texture = new Texture2D(1280, 720, TextureFormat.RGB24, false);
            RenderTexture previous = RenderTexture.active;
            try
            {
                camera.targetTexture = target;
                // Warm up URP's camera/light state before reading the first captured frame.
                camera.Render();
                camera.Render();
                RenderTexture.active = target;
                texture.ReadPixels(new Rect(0, 0, 1280, 720), 0, 0);
                texture.Apply();
                File.WriteAllBytes(path, texture.EncodeToPNG());
            }
            finally
            {
                camera.targetTexture = null; RenderTexture.active = previous;
                Object.DestroyImmediate(texture); target.Release(); Object.DestroyImmediate(target);
            }
        }
    }
}
