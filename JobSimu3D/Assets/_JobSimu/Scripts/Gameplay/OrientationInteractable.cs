using JobSimu.Interaction;

namespace JobSimu.Gameplay
{
    public sealed class OrientationInteractable : Interactable
    {
        public TeacherOrientation orientation;
        public OrientationAction action;
        public string label = "Đọc kế hoạch";
        public string title = "Kế hoạch đầu giờ";
        public override string Prompt => label;
        public override void Interact(PlayerInteractor actor)
        {
            actor.hud.ShowDialogue(title, orientation.Act(action));
        }
    }
}
