using UnityEngine;
using UnityEngine.InputSystem;
using JobSimu.Gameplay;

namespace JobSimu.Player
{
    [DefaultExecutionOrder(-100)]
    [RequireComponent(typeof(CharacterController))]
    public sealed class FirstPersonController : MonoBehaviour
    {
        public Camera viewCamera;
        public PrototypeHUD hud;
        public JobSimu.Core.InteractionUI interactionUI;
        private JobSimu.Core.InteractionUI UI => interactionUI != null ? interactionUI : hud;
        public Transform spawn;
        [Min(0.1f)] public float walkSpeed = 3.4f;
        [Min(0.1f)] public float sprintSpeed = 5.4f;
        [Range(0.02f, 0.5f)] public float sensitivity = 0.09f;
        public float gravity = -20f;
        public bool CanControl => isActiveAndEnabled && Application.isFocused &&
            Cursor.lockState == CursorLockMode.Locked && (UI == null || !UI.BlocksGameplay);
        public bool InteractPressed => actions != null && interact.WasPressedThisFrame();
        public float Pitch => pitch;

        private CharacterController controller;
        private InputActionMap actions;
        private InputAction move, look, sprint, interact, cancel, capture;
        private float pitch, verticalSpeed;
        private int capturedFrame = -1;

        private void Awake()
        {
            controller = GetComponent<CharacterController>();
            actions = new InputActionMap("SchoolFPP");
            move = actions.AddAction("Move", InputActionType.Value);
            move.AddCompositeBinding("2DVector")
                .With("Up", "<Keyboard>/w").With("Down", "<Keyboard>/s")
                .With("Left", "<Keyboard>/a").With("Right", "<Keyboard>/d");
            look = actions.AddAction("Look", InputActionType.Value, "<Mouse>/delta");
            sprint = actions.AddAction("Sprint", InputActionType.Button, "<Keyboard>/leftShift");
            interact = actions.AddAction("Interact", InputActionType.Button, "<Keyboard>/e");
            cancel = actions.AddAction("Cancel", InputActionType.Button, "<Keyboard>/escape");
            capture = actions.AddAction("Capture", InputActionType.Button, "<Mouse>/leftButton");
#if UNITY_WEBGL && !UNITY_EDITOR
            WebGLInput.stickyCursorLock = false;
#endif
        }

        private void OnEnable() => actions?.Enable();
        private void OnDisable() { actions?.Disable(); ReleaseCursor(); }
        private void OnDestroy() => actions?.Dispose();
        private void OnApplicationFocus(bool focused) { if (!focused) ReleaseCursor(); }

        private void Update()
        {
            if (cancel.WasPressedThisFrame())
            {
                if (UI != null) UI.HandleCancel();
                ReleaseCursor();
                return;
            }
            if (capture.WasPressedThisFrame() && (UI == null || !UI.BlocksGameplay) &&
                Cursor.lockState != CursorLockMode.Locked)
            {
                Cursor.lockState = CursorLockMode.Locked;
                Cursor.visible = false;
                capturedFrame = Time.frameCount;
            }
            if (!CanControl) return;
            if (Time.frameCount > capturedFrame)
                ApplyLook(look.ReadValue<Vector2>());
            Vector2 input = Vector2.ClampMagnitude(move.ReadValue<Vector2>(), 1f);
            Vector3 velocity = (transform.right * input.x + transform.forward * input.y) *
                (sprint.IsPressed() ? sprintSpeed : walkSpeed);
            if (controller.isGrounded && verticalSpeed < 0f) verticalSpeed = -2f;
            verticalSpeed = Mathf.Max(verticalSpeed + gravity * Time.deltaTime, -35f);
            controller.Move((velocity + Vector3.up * verticalSpeed) * Time.deltaTime);
            if (transform.position.y < -6f && spawn != null) Respawn();
        }

        public void ApplyLook(Vector2 delta)
        {
            pitch = Mathf.Clamp(pitch - delta.y * sensitivity, -80f, 80f);
            transform.Rotate(Vector3.up, delta.x * sensitivity, Space.World);
            if (viewCamera != null) viewCamera.transform.localRotation = Quaternion.Euler(pitch, 0f, 0f);
        }

        public void Respawn()
        {
            if (spawn == null) return;
            controller.enabled = false;
            transform.SetPositionAndRotation(spawn.position, spawn.rotation);
            controller.enabled = true;
            pitch = verticalSpeed = 0f;
            if (viewCamera != null) viewCamera.transform.localRotation = Quaternion.identity;
        }

        public static void ReleaseCursor()
        {
            Cursor.lockState = CursorLockMode.None;
            Cursor.visible = true;
        }
    }
}
