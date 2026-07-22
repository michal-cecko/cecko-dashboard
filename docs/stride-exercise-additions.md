# Stride — Calisthenics exercise additions (for approval)

**Status:** proposed — nothing seeded yet. Approve all / none / edits, then I add them via an
**idempotent `firstOrCreate` seeder** (`AddCalisthenicsExercisesSeeder`) that ONLY inserts new rows
by `slug` — it never touches or overwrites existing exercises, and it's NOT a full reseed.

## Schema each row fills
`['Name', category, group, tag, difficulty, equipment_label, primaryMuscle, [secondary...], metric_type]`
- **`slug`** auto-derived from `Name` (`Str::slug`), it's the unique match key.
- **`category`** — `calisthenics` (skills) or new **`freestyle calisthenics`** (dynamics).
- **`group`** decides which day the plan generator offers it on (this is the key rule):
  Push → `Shoulders`/`Chest`/`Triceps`, Pull → `Back`/`Biceps`, Legs → `Legs`. Skills below are
  grouped so HSPU lands on Push, front-lever/one-arm-pull-up on Pull, pistol on Legs.
- **`metric_type`** — `hold` for static holds, `reps` for dynamic, `load` for weighted.
- Freestyle dynamics use category `freestyle calisthenics` → **library-only** (browsable) and are NOT
  auto-programmed by the AI (its pool is `strength`+`calisthenics` only). That's intended — freestyle
  tricks are skill practice, not programmed sets. Say the word if you want them programmable too.

Already in the catalog (NOT re-added): Handstand Hold, Front Lever, Pistol Squat, L-sit, Weighted Dips,
Chin-up, Pull-up (Strict), Hanging Leg Raise, Plank, Bulgarian Split Squat.

---

## A. Skill calisthenics — `category: calisthenics`

### A1. Push — Handstand / HSPU / Planche  (group → Shoulders/Chest, lands on Push day)
| Name | group | tag | difficulty | equipment | primary | metric |
|---|---|---|---|---|---|---|
| Wall Handstand Hold | Shoulders | Compound | Beginner | Bodyweight | Shoulders | hold |
| Pike Push-up | Shoulders | Compound | Beginner | Bodyweight | Shoulders | reps |
| Wall Handstand Push-up | Shoulders | Compound | Intermediate | Bodyweight | Shoulders | reps |
| Freestanding Handstand Push-up | Shoulders | Compound | Advanced | Bodyweight | Shoulders | reps |
| Handstand Push-up (Parallettes) | Shoulders | Compound | Advanced | Parallettes | Shoulders | reps |
| Deficit Handstand Push-up | Shoulders | Compound | Advanced | Parallettes | Shoulders | reps |
| 90 Degree Handstand Push-up (Floor) | Shoulders | Compound | Advanced | Bodyweight | Shoulders | reps |
| 90 Degree Handstand Push-up (Parallettes) | Shoulders | Compound | Advanced | Parallettes | Shoulders | reps |
| 90 Degree Handstand Hold | Shoulders | Compound | Advanced | Bodyweight | Shoulders | hold |
| Pseudo Planche Push-up | Chest | Compound | Intermediate | Bodyweight | Chest | reps |
| Tuck Planche | Shoulders | Compound | Intermediate | Parallettes | Shoulders | hold |
| Advanced Tuck Planche | Shoulders | Compound | Advanced | Parallettes | Shoulders | hold |
| Straddle Planche | Shoulders | Compound | Advanced | Parallettes | Shoulders | hold |
| Full Planche | Shoulders | Compound | Advanced | Parallettes | Shoulders | hold |
| Planche Push-up | Shoulders | Compound | Advanced | Parallettes | Shoulders | reps |
| Ring Dips | Chest | Compound | Intermediate | Gymnastic rings | Chest | reps |

### A2. Pull — Front Lever / One-Arm Pull-up / Muscle-up  (group → Back, lands on Pull day)
| Name | group | tag | difficulty | equipment | primary | metric |
|---|---|---|---|---|---|---|
| Tuck Front Lever | Back | Compound | Intermediate | Pull-up bar | Lats | hold |
| Advanced Tuck Front Lever | Back | Compound | Advanced | Pull-up bar | Lats | hold |
| One-Leg Front Lever | Back | Compound | Advanced | Pull-up bar | Lats | hold |
| Straddle Front Lever | Back | Compound | Advanced | Pull-up bar | Lats | hold |
| Touch Front Lever | Back | Compound | Advanced | Pull-up bar | Lats | hold |
| Tuck Front Lever Raise | Back | Compound | Intermediate | Pull-up bar | Lats | reps |
| Front Lever Raise | Back | Compound | Advanced | Pull-up bar | Lats | reps |
| Tuck Front Lever Pull-up | Back | Compound | Advanced | Pull-up bar | Lats | reps |
| Front Lever Pull-up | Back | Compound | Advanced | Pull-up bar | Lats | reps |
| Ice Cream Maker | Back | Compound | Advanced | Pull-up bar | Lats | reps |
| Archer Pull-up | Back | Compound | Intermediate | Pull-up bar | Lats | reps |
| Assisted One-Arm Pull-up (Band) | Back | Compound | Advanced | Pull-up bar | Lats | reps |
| One-Arm Negative Pull-up | Back | Compound | Advanced | Pull-up bar | Lats | reps |
| One-Arm Pull-up | Back | Compound | Advanced | Pull-up bar | Lats | reps |
| Typewriter Pull-up | Back | Compound | Advanced | Pull-up bar | Lats | reps |
| Weighted Pull-up | Back | Compound | Intermediate | Dip bar + belt | Lats | load |
| Bar Muscle-up | Back | Compound | Advanced | Pull-up bar | Lats | reps |
| Ring Muscle-up | Back | Compound | Advanced | Gymnastic rings | Lats | reps |
| Skin the Cat | Back | Compound | Intermediate | Gymnastic rings | Lats | reps |
| Tuck Back Lever | Back | Compound | Intermediate | Pull-up bar | Lats | hold |
| Back Lever | Back | Compound | Advanced | Pull-up bar | Lats | hold |

### A3. Legs  (group → Legs)
| Name | group | tag | difficulty   | equipment | primary | metric |
|---|---|---|--------------|---|---|---|
| Assisted Pistol Squat | Legs | Compound | Beginner     | Bodyweight | Quads | reps |
| Shrimp Squat | Legs | Compound | Advanced     | Bodyweight | Quads | reps |
| Nordic Hamstring Curl | Legs | Compound | Advanced     | Bodyweight | Hamstrings | reps |
| Sissy Squat | Legs | Isolation | Intermediate | Bodyweight | Quads | reps |
| Pistol Squat | Legs | Compound | Intermediate | Bodyweight | Quads | reps |

### A4. Core / whole-body statics  (group → Core; appear on Full-body days)
| Name | group | tag | difficulty | equipment | primary | metric |
|---|---|---|---|---|---|---|
| Hollow Body Hold | Core | Isolation | Beginner | Bodyweight | Core | hold |
| V-sit | Core | Compound | Advanced | Bodyweight | Core | hold |
| Dragon Flag | Core | Compound | Advanced | Bench | Core | reps |
| Human Flag | Core | Compound | Advanced | Pull-up bar | Obliques | hold |

---

## B. Freestyle dynamics — new `category: freestyle calisthenics`  (library-only)

Sourced from community tricklists (bodyweightsports.eu, calisthenics-101) + known street-workout moves.
NOTE: I could not read @freestyle_tricklist (Instagram blocks scraping) — this is the well-known set;
tell me any specific ones from your friends' list that are missing and I'll add them.

### B1. Swings & spins  (group: Swings)
| Name | difficulty | note | metric |
|---|---|---|---|
| Swing 180 | Intermediate | first free spin, release + recatch | reps |
| Swing 360 | Advanced | full rotation from a hard back-swing | reps |
| Swing 540 | Advanced | 1.5 rotations with leg kick | reps |
| Swing 720 | Advanced | double rotation (first landed 2018) | reps |
| Swing 900 | Advanced | 2.5 rotations, very few athletes | reps |
| Giant Swing | Advanced | stretched full rotation over the bar | reps |
| Baby Giant | Intermediate | partial-body giant | reps |
| Muscle-up 360 | Advanced | muscle-up into a full-release twist | reps |
| 360 Pull-up | Advanced | pull-up with a full spin at the top | reps |

### B2. Flips & releases  (group: Flips)
| Name | difficulty | note | metric |
|---|---|---|---|
| Shrimp Flip | Advanced | legs flip between arms, release + recatch | reps |
| Geinger | Advanced | back half-twist flip off a forward swing | reps |
| Frisbee | Advanced | side flip off the back-swing, recatch | reps |
| Frontflip Regrab | Advanced | front somersault at dead center, recatch | reps |
| Alley Oop | Advanced | sideways flip over the bar, catch inside | reps |
| Cast Backflip | Advanced | backflip dismount off the bar | reps |
| Toothbrush | Advanced | dynamic over-bar release trick | reps |

### B3. Bar transitions  (group: Transitions)
| Name | difficulty | note | metric |
|---|---|---|---|
| 180 Over the Bar | Intermediate | flip over a high bar to the far side | reps |
| Bar Hop | Intermediate | legs over the bar, backward knee rotation | reps |
| Switchblade | Advanced | 180-over-the-bar + bar hop combo | reps |
| Tornado Grip Switch | Intermediate | mid-swing hand-grip switch for flow | reps |

### B4. Dynamic strength pulls  (group: Dynamic pulls)
| Name | difficulty | note | metric |
|---|---|---|---|
| Hefesto | Advanced | explosive pull to a bent-arm behind-back position | reps |
| Katchev | Advanced | dynamic pull from back lever to front support | reps |
| Impossible Dip | Advanced | straight-arm press dynamic on parallel bars | reps |
| Around the World | Advanced | full body circle around the bar | reps |

---

---

## C. Videos (openable/viewable per trick)

Fully supported by the schema: `stride_exercises.video_url` is already returned by the library API,
and there's a **public S3 bucket** (`public-synapps-dashboard`, endpoint `nbg1.your-objectstorage.com`).
So each trick/exercise can have a tap-to-play video. The only missing piece is the **video files**:

- I **can't** download @freestyle_tricklist's clips — Instagram blocks scraping, and it's your friends'
  content (shouldn't rip it). The clean paths:
  1. **You/your friends share the clips** (a folder / drive / the actual files) → I upload each to S3
     (`public-synapps-dashboard/stride/exercises/<slug>.mp4`) and set `video_url` to the public URL.
     Best outcome — self-hosted, always available, credited to them.
  2. **Public tutorial links** — set `video_url` to a good public demo (e.g. YouTube) per move. Fast,
     no hosting, but external and can rot.
- Recommended: seed the exercises **now** (video_url null), then batch-fill videos via option 1 once
  you have the files — I'll write the S3 upload + `video_url` update as a one-shot script.

**Counts:** ~45 skill exercises (A) + ~24 freestyle dynamics (B) ≈ 69 new rows.

**Bonus fix available (say yes/no):** the existing seeded **Push-up** has `group: 'Push'`, which the
generator never matches (Push day looks for Chest/Shoulders/Triceps), so it's currently un-programmable.
I can re-group it to `Chest` in the same seeder while I'm here.
