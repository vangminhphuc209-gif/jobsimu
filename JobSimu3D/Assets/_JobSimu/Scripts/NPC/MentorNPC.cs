using JobSimu.Interaction;
using JobSimu.Gameplay;

namespace JobSimu.NPC
{
    public sealed class MentorNPC : Interactable
    {
        public TeacherOrientation orientation;
        public override string Prompt => "Trò chuyện với cô Mai";
        public override void Interact(PlayerInteractor actor) =>
            actor.hud.ShowDialogue("Cô Mai · Giáo viên hướng dẫn", orientation.Act(OrientationAction.Mentor));
    }
}
