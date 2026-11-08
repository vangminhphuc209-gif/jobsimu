import { afterEach, describe, expect, it, vi } from 'vitest';
import { NullEngine } from '@babylonjs/core/Engines/nullEngine';
import { Scene } from '@babylonjs/core/scene';
import { SchoolWorld } from '../src/world/SchoolWorld';

// Only canvas text is stubbed; Babylon constructs the actual scene geometry.
class TextCanvas {
  width = 768; height = 192;
  getContext() { return { fillRect(){}, clearRect(){}, fillText(){}, measureText(){return {width:150};}, save(){}, restore(){}, translate(){}, scale(){} }; }
}
afterEach(() => vi.unstubAllGlobals());
describe('school geometry (not GPU rendering)', () => {
  it('builds connected ground, accessible targets and divergent teaching props', () => {
    vi.stubGlobal('OffscreenCanvas', TextCanvas);
    const engine = new NullEngine(); const scene = new Scene(engine);
    try {
      const world = new SchoolWorld(scene);
      expect(world.groupMeshes).toHaveLength(4);
      expect(scene.getMeshByName('corridorRailing')).toBeNull();
      const ground = scene.getMeshByName('yardGround')!;
      ground.computeWorldMatrix(true);
      const bounds = ground.getBoundingInfo().boundingBox;
      expect(bounds.minimumWorld.x).toBeLessThan(-20);
      expect(bounds.maximumWorld.x).toBeGreaterThan(20);
      expect(bounds.minimumWorld.z).toBeLessThan(-40);
      expect(bounds.maximumWorld.z).toBeGreaterThan(20);
      world.setLessonVisuals('handouts',false,false,[]);
      const handouts = scene.meshes.filter(m=>m.isEnabled() && m.isVisible).map(m=>m.name);
      world.setLessonVisuals('groups',true,true,[0]);
      const groups = scene.meshes.filter(m=>m.isEnabled() && m.isVisible).map(m=>m.name);
      expect(groups).not.toEqual(handouts);
      expect(world.exitTicketStackMesh.isEnabled()).toBe(false);
      expect(world.groupMeshes[0].material).not.toBe(world.groupMeshes[1].material);
    } finally { scene.dispose(); engine.dispose(); }
  });
});
