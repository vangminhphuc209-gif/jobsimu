using System;
using System.IO;
using UnityEditor;
using UnityEditor.Build.Reporting;
using UnityEditor.SceneManagement;
using UnityEngine;

namespace JobSimu.Editor
{
    public static class FrameworkWebBuild
    {
        [MenuItem("Tools/JobSimu/Build Teacher Web Demo")]
        public static void Build()
        {
            if (EditorApplication.isPlaying) throw new InvalidOperationException("Exit Play Mode before building.");
            EditorSceneManager.OpenScene(FrameworkDemoGenerator.ScenePath);
            FrameworkValidator.ValidateBatch();
            string originalTemplate = PlayerSettings.WebGL.template;
            WebGLCompressionFormat originalCompression = PlayerSettings.WebGL.compressionFormat;
            int originalWidth = PlayerSettings.defaultWebScreenWidth, originalHeight = PlayerSettings.defaultWebScreenHeight;
            try
            {
                PlayerSettings.WebGL.template = "PROJECT:JobSimu";
                // Local QA server needs no special compression headers; production optimization is separate.
                PlayerSettings.WebGL.compressionFormat = WebGLCompressionFormat.Disabled;
                PlayerSettings.defaultWebScreenWidth = 1280; PlayerSettings.defaultWebScreenHeight = 720;
                BuildReport report = BuildPipeline.BuildPlayer(new BuildPlayerOptions {
                    scenes = new[] { FrameworkDemoGenerator.ScenePath }, locationPathName = "Builds/TeacherWeb",
                    target = BuildTarget.WebGL, options = BuildOptions.Development
                });
                File.WriteAllText("Logs/framework-web-build.txt", "Result: " + report.summary.result + "\nBytes: " + report.summary.totalSize +
                    "\nDuration: " + report.summary.totalTime + "\nErrors: " + report.summary.totalErrors + "\nWarnings: " + report.summary.totalWarnings);
                if (report.summary.result != BuildResult.Succeeded) throw new InvalidOperationException("Web build failed. See Logs/framework-web-build.log.");
                Debug.Log("Teacher Web development build ready at Builds/TeacherWeb. No hosting deployment performed.");
            }
            finally
            {
                PlayerSettings.WebGL.template = originalTemplate; PlayerSettings.WebGL.compressionFormat = originalCompression;
                PlayerSettings.defaultWebScreenWidth = originalWidth; PlayerSettings.defaultWebScreenHeight = originalHeight;
            }
        }
    }
}
