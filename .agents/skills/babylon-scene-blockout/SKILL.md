---
name: babylon-scene-blockout
description: Blockout and render 4 P0 zones (Class9A, StaffRoom, Corridor, Yard) with Babylon.js, collision meshes, instancing, and multi-tier graphics presets.
---

# skill:babylon-scene-blockout

## Purpose
Constructs the initial geometric blockout, collision geometry, lighting, and camera bounds for the 4 core P0 zones of JOBSIMU 3D:
1. **Lớp 9A (Class9A):** Classroom with 4 student table groups, teacher desk, chalkboard, projector, windows.
2. **Phòng Giáo viên (StaffRoom):** Teacher desks, printer/paper supply, mentor desk (Cô Mai), clock, lockers.
3. **Hành lang (Corridor):** Connecting hallway between StaffRoom and Class9A, staircase, bulletin boards.
4. **Sân trường & Cổng (Yard):** School gate, flag pole, benches, trees, transition zone.

## Performance & Rendering Rules
- **Mesh Instancing:** All repeated objects (chairs, double desks, fluorescent ceiling lamps, floor tiles) must use Babylon.js `instancedMesh` or `thinInstance` to minimize draw calls.
- **Colliders:** Invisible bounding boxes (`checkCollisions = true`) for walls, closed doors, and furniture edges to prevent player character penetration.
- **Lighting:** Ambient HemisphericLight + 1 DirectionalLight with soft shadows for main zones. Low-end presets disable real-time dynamic shadows.
- **LOD & Optimization:** Maintain 3 graphics presets (`Low`, `Medium`, `High`) ensuring $\ge 30\text{ FPS}$ on low-end integrated graphics (Intel Iris Xe / AMD Vega).
