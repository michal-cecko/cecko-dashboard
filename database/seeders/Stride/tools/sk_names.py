#!/usr/bin/env python3
"""Generate Slovak display names for the gym exercise catalogue.

Rule-based: each name is parsed into movement + equipment + position + grip +
modifiers, then composed in Slovak word order. Leftover (unconsumed) tokens are
reported so the dictionaries can be extended until nothing is dropped.
"""
import json, re, sys, collections

SRC = '/Users/michalcecko/Desktop/Webserver/synapps-dashboard/database/seeders/Stride/data/mns_gym_exercises.json'

# ── movement templates: english phrase → (slovak template, gender) ────────────
# {eq} = equipment phrase slot (already carries its leading space)
MOVES = [
    # presses
    ("guillotine press", ("Tlak{eq} na lavičke ku krku (guillotine)", 'm')),
    ("bradford press", ("Bradfordov tlak{eq}", 'm')),
    ("arnold press", ("Arnoldov tlak{eq}", 'm')),
    ("clean and press", ("Zdvih na hruď a tlak{eq} nad hlavu", 'm')),
    ("bench press", ("Tlak{eq} na lavičke", 'm')),
    ("chest press", ("Tlak{eq} na prsia", 'm')),
    ("shoulder press", ("Tlak{eq} nad hlavu", 'm')),
    ("military press", ("Vojenský tlak{eq} nad hlavu", 'm')),
    ("overhead press", ("Tlak{eq} nad hlavu", 'm')),
    ("leg press calf raise", ("Výpon na špičky na leg presse", 'm')),
    ("leg press", ("Leg press (tlak nohami)", 'm')),
    ("french press", ("Francúzsky tlak{eq}", 'm')),
    ("tate press", ("Tateov tlak{eq}", 'm')),
    ("floor press", ("Tlak{eq} na zemi", 'm')),
    ("press", ("Tlak{eq}", 'm')),
    # curls
    ("preacher curl", ("Bicepsový zdvih{eq} na modlitebníku", 'm')),
    ("hammer curl", ("Kladivový zdvih{eq}", 'm')),
    ("concentration curl", ("Koncentrovaný bicepsový zdvih{eq}", 'm')),
    ("zottman curl", ("Zottmanov zdvih{eq}", 'm')),
    ("drag curl", ("Drag curl — ťahaný bicepsový zdvih{eq}", 'm')),
    ("spider curl", ("Spider curl — zdvih{eq} cez šikmú lavičku", 'm')),
    ("pinwheel curl", ("Pinwheel zdvih{eq}", 'm')),
    ("wrist curl", ("Zdvih zápästí{eq}", 'm')),
    ("leg curl", ("Zakopávanie{eq}", 'n')),
    ("hamstring curl", ("Zakopávanie{eq}", 'n')),
    ("twisting curl", ("Bicepsový zdvih{eq} s rotáciou", 'm')),
    ("curl", ("Bicepsový zdvih{eq}", 'm')),
    # rows / pulls
    ("upright row", ("Príťah{eq} k brade", 'm')),
    ("inverted row", ("Príťah tela k hrazde (inverted row)", 'm')),
    ("t-bar row", ("Príťah na T-bare", 'm')),
    ("row to neck", ("Príťah{eq} ku krku", 'm')),
    ("row", ("Príťah{eq}", 'm')),
    ("lat pulldown", ("Sťahovanie hornej kladky", 'n')),
    ("pulldown", ("Sťahovanie kladky", 'n')),
    ("pull down", ("Sťahovanie kladky", 'n')),
    ("pushdown", ("Tricepsové sťahovanie kladky", 'n')),
    ("push down", ("Tricepsové sťahovanie kladky", 'n')),
    ("pullover", ("Pullover — preťahovanie{eq}", 'n')),
    ("pull up", ("Zhyb{eq}", 'm')),
    ("pull-up", ("Zhyb{eq}", 'm')),
    ("chin up", ("Zhyb podhmatom{eq}", 'm')),
    ("chin-up", ("Zhyb podhmatom{eq}", 'm')),
    ("knee push up", ("Klik na kolenách{eq}", 'm')),
    ("knee push-up", ("Klik na kolenách{eq}", 'm')),
    ("push up", ("Klik{eq}", 'm')),
    ("push-up", ("Klik{eq}", 'm')),
    # raises / flys
    ("lateral raise", ("Upažovanie{eq}", 'n')),
    ("front raise", ("Predpažovanie{eq}", 'n')),
    ("rear delt fly", ("Rozpažovanie{eq} v predklone (zadné deltové)", 'n')),
    ("rear delt raise", ("Rozpažovanie{eq} v predklone (zadné deltové)", 'n')),
    ("calf raise", ("Výpon na špičky{eq}", 'm')),
    ("toe raise", ("Zdvih špičiek{eq}", 'm')),
    ("heel raise", ("Výpon na päty{eq}", 'm')),
    ("leg raise", ("Zdvih nôh{eq}", 'm')),
    ("knee raise", ("Príťah kolien{eq}", 'm')),
    ("hip raise", ("Zdvih panvy{eq}", 'm')),
    ("shoulder raise", ("Zdvih ramien{eq}", 'm')),
    ("raise", ("Zdvih{eq}", 'm')),
    ("fly", ("Rozpažovanie{eq}", 'n')),
    ("flys", ("Rozpažovanie{eq}", 'n')),
    ("crossover", ("Sťahovanie kladiek pred telom (crossover)", 'n')),
    ("pec deck", ("Zapažovanie na pec-decku", 'n')),
    # legs
    ("bulgarian split squat", ("Bulharský drep{eq}", 'm')),
    ("split squat", ("Rozkročný drep{eq}", 'm')),
    ("front squat", ("Predný drep{eq}", 'm')),
    ("hack squat", ("Hack drep{eq}", 'm')),
    ("sissy squat", ("Sissy drep{eq}", 'm')),
    ("zercher squat", ("Zercherov drep{eq}", 'm')),
    ("box squat", ("Drep{eq} na debnu", 'm')),
    ("pistol squat", ("Pistol drep (na jednej nohe)", 'm')),
    ("plie squat", ("Plié drep{eq}", 'm')),
    ("squat jump", ("Výskok z drepu{eq}", 'm')),
    ("jump squat", ("Výskok z drepu{eq}", 'm')),
    ("wall squat", ("Drep{eq} pri stene", 'm')),
    ("frog squat", ("Žabí drep{eq}", 'm')),
    ("squat", ("Drep{eq}", 'm')),
    ("romanian deadlift", ("Rumunský mŕtvy ťah{eq}", 'm')),
    ("stiff leg deadlift", ("Mŕtvy ťah{eq} s vystretými nohami", 'm')),
    ("sumo deadlift", ("Sumo mŕtvy ťah{eq}", 'm')),
    ("deadlift", ("Mŕtvy ťah{eq}", 'm')),
    ("walking lunge", ("Chodiaci výpad{eq}", 'm')),
    ("lateral lunge", ("Bočný výpad{eq}", 'm')),
    ("rear lunge", ("Zanožný výpad{eq}", 'm')),
    ("reverse lunge", ("Zanožný výpad{eq}", 'm')),
    ("lunge", ("Výpad{eq}", 'm')),
    ("step up", ("Výstup na debnu{eq}", 'm')),
    ("leg extension", ("Predkopávanie{eq}", 'n')),
    ("hip thrust", ("Zdvih panvy{eq}", 'm')),
    ("glute bridge", ("Most na zadok{eq}", 'm')),
    ("good mornings", ("Predklon{eq} (good morning)", 'm')),
    ("good morning", ("Predklon{eq} (good morning)", 'm')),
    ("hyperextension", ("Hyperextenzia{eq}", 'f')),
    ("back extension", ("Hyperextenzia chrbta{eq}", 'f')),
    ("abduction", ("Unožovanie{eq}", 'n')),
    ("adduction", ("Priťahovanie nohy{eq}", 'n')),
    ("donkey calf raise", ("Somársky výpon na špičky", 'm')),
    # arms / triceps
    ("skullcrusher", ("Francúzsky tlak{eq} v ľahu (skullcrusher)", 'm')),
    ("skull crusher", ("Francúzsky tlak{eq} v ľahu (skullcrusher)", 'm')),
    ("glute kickback", ("Zanožovanie{eq} (sedacie svaly)", 'n')),
    ("glute kick back", ("Zanožovanie{eq} (sedacie svaly)", 'n')),
    ("kickback", ("Zapažovanie{eq} (triceps)", 'n')),
    ("kickbacks", ("Zapažovanie{eq} (triceps)", 'n')),
    ("tricep extension", ("Tricepsová extenzia{eq}", 'f')),
    ("extension", ("Extenzia{eq}", 'f')),
    ("bench dip", ("Kľuk na lavičke{eq} (triceps)", 'm')),
    ("dip", ("Kľuk na bradlách{eq}", 'm')),
    ("dips", ("Kľuky na bradlách{eq}", 'm')),
    # core
    ("crunch", ("Skracovačka{eq}", 'f')),
    ("crunches", ("Skracovačky{eq}", 'f')),
    ("sit up", ("Sed-ľah{eq}", 'm')),
    ("sit-up", ("Sed-ľah{eq}", 'm')),
    ("jackknife", ("Skladačka (jackknife)", 'f')),
    ("wood chop", ("Drevorubač na kladke (wood chop)", 'm')),
    ("russian twist", ("Ruská rotácia trupu{eq}", 'f')),
    ("twist", ("Rotácia trupu{eq}", 'f')),
    ("plank", ("Plank — doska{eq}", 'm')),
    ("side bends", ("Úklony do strán{eq}", 'n')),
    ("rollout", ("Rollout — výjazd s kolieskom", 'm')),
    ("rollouts", ("Rollout — výjazd s kolieskom", 'm')),
    ("knee tuck", ("Priťahovanie kolien{eq}", 'n')),
    ("toe touch", ("Dotyk špičiek{eq}", 'm')),
    ("toe touches", ("Dotyky špičiek{eq}", 'm')),
    ("leg roll", ("Rotácia nôh v ľahu", 'f')),
    ("scissor kick", ("Nožnice{eq}", 'f')),
    ("frog kick", ("Žabie prednožovanie", 'n')),
    ("pendulum", ("Kyvadlo — rotácia nôh v ľahu", 'n')),
    ("air bike", ("Bicykel (air bike)", 'm')),
    # other
    ("shrug", ("Zdvih ramien{eq}", 'm')),
    ("shrugs", ("Zdvihy ramien{eq}", 'm')),
    ("neck flexion", ("Predklon hlavy{eq}", 'm')),
    ("neck extension", ("Záklon hlavy{eq}", 'm')),
    ("wrist roller", ("Navíjanie závažia na zápästný valec", 'n')),
    ("wrist rollers", ("Navíjanie závažia na zápästný valec", 'n')),
    ("plate pinches", ("Držanie kotúčov v prstoch (plate pinch)", 'n')),
    ("jump", ("Výskok{eq}", 'm')),
    ("jumps", ("Výskoky{eq}", 'm')),
    ("reach and catch", ("Nadhoz a chyt lopty v ľahu", 'm')),
    ("stretch", ("Strečing{eq}", 'm')),
    ("lift", ("Zdvih{eq}", 'm')),
]

EQUIP = [
    ("smith machine", " na Smith stroji"),
    ("swiss ball", " na fitlopte"),
    ("exercise ball", " na fitlopte"),
    ("medicine ball", " s medicinbalom"),
    ("ez bar", " s EZ činkou"),
    ("ez-bar", " s EZ činkou"),
    ("v-bar", " s V-rukoväťou"),
    ("t-bar", " na T-bare"),
    ("straight bar", " s rovnou tyčou"),
    ("landmine", " s landmine"),
    ("roman chair", " na rímskej stoličke"),
    ("barbell", " s veľkou činkou"),
    ("dumbell", " s jednoručkami"),
    ("kettlebell", " s kettlebellom"),
    ("cable", " na kladke"),
    ("pulley", " na kladke"),
    ("rope", " s lanom"),
    ("machine", " na stroji"),
    ("plate", " s kotúčom"),
    ("weight belt", " so záťažovým opaskom"),
    ("belt", " so záťažovým opaskom"),
    ("ball", " s loptou"),
    ("bodyweight", " s vlastnou váhou"),
    ("weighted", " so záťažou"),
    ("bar", " na hrazde"),
    ("wall", " pri stene"),
    ("box", " na debne"),
]

POSITION = [
    ("bent-over", "v predklone"),
    ("bent over", "v predklone"),
    ("incline", "na šikmej lavičke"),
    ("decline", "na negatívnej lavičke"),
    ("flat", "na rovnej lavičke"),
    ("seated", "v sede"),
    ("standing", "v stoji"),
    ("lying", "v ľahu"),
    ("prone", "v ľahu na bruchu"),
    ("kneeling", "v kľaku"),
    ("hanging", "vo visení"),
    ("squatting", "v drepe"),
    ("floor", "na zemi"),
    ("on-the-wall", "pri stene"),
    ("bench", "na lavičke"),
    ("behind-the-neck", "za hlavou"),
    ("behind neck", "za hlavou"),
    ("behind the neck", "za hlavou"),
    ("behind-the-back", "za chrbtom"),
    ("behind the back", "za chrbtom"),
    ("overhead", "nad hlavou"),
    ("feet elevated", "s nohami vyššie"),
    ("elevated", "vyvýšené"),
]

GRIP = [
    ("close grip", "úzkym úchopom"),
    ("narrow grip", "úzkym úchopom"),
    ("wide grip", "širokým úchopom"),
    ("neutral grip", "neutrálnym úchopom"),
    ("reverse grip", "obráteným úchopom"),
    ("overhand", "nadhmatom"),
    ("pronated", "nadhmatom"),
    ("underhand", "podhmatom"),
    ("supinated", "podhmatom"),
    ("palms in", "dlaňami dnu"),
    ("wide", "širokým úchopom"),
    ("pronated grip", "nadhmatom"),
    ("overhand grip", "nadhmatom"),
    ("underhand grip", "podhmatom"),
    ("supinated grip", "podhmatom"),
    ("palms up", "dlaňami hore"),
    ("palms down", "dlaňami dole"),
    ("palm", "dlaňou"),
    ("wide stance", "širokým postojom"),
    ("narrow stance", "úzkym postojom"),
    ("sumo stance", "sumo postojom"),
]

MODIFIER = [                      # appended as-is, after grip
    ("one-arm", "jednoruč"),
    ("one arm", "jednoruč"),
    ("single arm", "jednoruč"),
    ("single-arm", "jednoruč"),
    ("two-arm", "obojruč"),
    ("two arm", "obojruč"),
    ("one-leg", "na jednej nohe"),
    ("one leg", "na jednej nohe"),
    ("single leg", "na jednej nohe"),
    ("crossbody", "cez telo"),
    ("cross body", "cez telo"),
    ("rotational", "s rotáciou"),
    ("twisting", "s rotáciou"),
    ("rotating", "s rotáciou"),
    ("speed", "rýchlo"),
    ("walking", "v chôdzi"),
    ("forward", "vpred"),
    ("half", "polovičný rozsah"),
    ("quarter", "štvrtinový rozsah"),
    ("deep", "hlboký"),
    ("high", "vysoko"),
    ("low", "nízko"),
    ("toes in", "špičky dnu"),
    ("toes out", "špičky von"),
    ("toes", "špičky"),
    ("straight leg", "s vystretými nohami"),
    ("straight legs", "s vystretými nohami"),
    ("bilateral", "obojstranne"),
    ("inner", "vnútorná časť"),
    ("oblique", "šikmé brušné"),
    ("glute", "sedacie svaly"),
    ("chest", "prsia"),
    ("shoulder", "ramená"),
    ("tricep", "triceps"),
    ("bicep", "biceps"),
    ("lat", "široký chrbtový sval"),
    ("hip", "bedrá"),
    ("knee", "kolená"),
    ("abdominal", "brucho"),
    ("abominal", "brucho"),
    ("body", "telo"),
    ("iron", "železo"),
    ("prisoner", "s rukami za hlavou"),
    ("straight arm", "s vystretými rukami"),
    ("sumo", "sumo postojom"),
    ("side", "na boku"),
    ("high to low", "zhora nadol"),
    ("low to high", "zdola nahor"),
    ("with jump", "s výskokom"),
    ("with twist", "s rotáciou"),
    ("with hip thrust", "so zdvihom panvy"),
    ("rocky", "Rocky"),
    ("dublin", "Dublin"),
    ("shotgun", "shotgun"),
    ("rocking", "hojdavo"),
    ("inline", "v jednej línii"),
    ("strength", "silový"),
    ("risers", "s podložkou"),
    ("degree", "stupňov"),
    ("45", "45"),
    ("1", "1"),
    ("three", "tri"),
    ("two", "dve"),
    ("one", "jedna"),
    ("stance", "postoj"),
    ("start", "štart"),
]

ADJ_ALT = {'m': 'Striedavý', 'f': 'Striedavá', 'n': 'Striedavé'}
ADJ_REV = {'m': 'Obrátený', 'f': 'Obrátená', 'n': 'Obrátené'}

# Body parts the movement name already implies — consumed, never printed.
DROP = {'triceps', 'biceps', 'brucho', 'prsia', 'ramená', 'bedrá', 'kolená',
        'široký chrbtový sval', 'sedacie svaly', 'telo', 'špičky', 'postoj', 'štart',
        '45', '1', 'jedna', 'dve', 'tri', 'stupňov', 'železo'}

# Proper nouns keep their capital when an adjective is prefixed.
PROPER = ('Arnold', 'Zottman', 'Bradford', 'Zercher', 'Tate', 'Rocky', 'Dublin',
          'Sumo', 'Plié', 'Hack', 'Sissy', 'Pistol', 'Leg press', 'Pullover',
          'Plank', 'Drag', 'Spider', 'Pinwheel', 'Rollout')

# English name → hand-written Slovak, for names the rules can't compose well.
OVERRIDES = {
    'Cable Iron Cross': 'Železný kríž na kladkách (iron cross)',
    'Decline Bench Abdominal Reach': 'Predpaženie v ľahu na negatívnej lavičke',
    'Exercise Ball Hip Roll': 'Rotácia bokov na fitlopte',
    'Glute Kick Back': 'Zanožovanie (sedacie svaly)',
    'Hip Flexion Machine': 'Prednožovanie na stroji',
    'Lower Abdominal Hip Roll': 'Rotácia bokov v ľahu (spodné brucho)',
    'Lying Alternate Heel Touches': 'Striedavé dotyky pät v ľahu',
    'Hammer Bar Curl': 'Kladivový zdvih na hammer stroji',
    'Barbell Pullover And Press': 'Pullover s veľkou činkou a tlak',
    'Bodyweight Squat Jump': 'Výskok z drepu s vlastnou váhou',
    'Barbell Split Squat With Jump': 'Rozkročný drep s veľkou činkou a výskokom',
    'Barbell Jumping Squats': 'Drepy s výskokom s veľkou činkou',
    'Behind Neck Lat Pull Down': 'Sťahovanie hornej kladky za hlavu',
    'Barbell Quarter Squat': 'Štvrtinový drep s veľkou činkou',
    'Barbell Half Squat': 'Polovičný drep s veľkou činkou',
    'Barbell Deep Squat': 'Hlboký drep s veľkou činkou',
    '1 Leg Push Up': 'Klik na jednej nohe',
    'Lying Rear Delt Barbell Raise': 'Rozpažovanie s veľkou činkou v ľahu (zadné deltové)',
    'Inline Bench French Press': 'Francúzsky tlak na rovnej lavičke',
    'Dublin Press': 'Dublinský tlak',
    'Hammer Strength Bench Press': 'Tlak na Hammer Strength stroji',
    'Hammer Bar Preacher Curl': 'Zdvih na modlitebníku na hammer stroji',
    'Hack Squat Calf Raise': 'Výpon na špičky na hack stroji',
    'One-Leg Hack Squat Calf Raise': 'Výpon na špičky na hack stroji na jednej nohe',
    'Lateral Pulldown Bicep Curl': 'Bicepsový zdvih na hornej kladke',
    'Cable Row to Neck': 'Príťah kladky ku krku',
    'Barbell Rear Delt Row To Neck': 'Príťah veľkej činky ku krku (zadné deltové)',
    'Lying Wide Dumbell Curl': 'Bicepsový zdvih s jednoručkami v ľahu širokým úchopom',
    'Zerchers Squat': 'Zercherov drep',
    'Single Bench Dip': 'Kľuk na lavičke (triceps)',
    'Three Bench Dip': 'Kľuk medzi tromi lavičkami (triceps)',
    'Weighted Three Bench Dip': 'Kľuk medzi tromi lavičkami so záťažou (triceps)',
    'Single Leg Curl': 'Zakopávanie jednou nohou',
    'Single Leg Extension': 'Predkopávanie jednou nohou',
    'Two-Arm Tricep Cable Extension': 'Tricepsová extenzia na kladke obojruč',
    'Rope Crossover Seated Row': 'Príťah kladiek pred telom v sede (crossover)',
    'Oblique Cable Crunch': 'Skracovačka na kladke na šikmé brušné svaly',
    'Side Crunch With Leg Lift': 'Skracovačka na boku so zdvihom nohy',
}

PAREN = {
    'over bench': 'cez lavičku',
    'over flat bench': 'cez rovnú lavičku',
    'rope extension': 's lanom',
    'single dumbell': 'jedna jednoručka',
    'feet on swiss ball': 'nohy na fitlopte',
    'crossbody': 'cez telo',
    'underhand': 'podhmatom',
    'skull crusher': 'skullcrusher',
    'aka overhead press': 'tlak nad hlavu',
    'aka dumbell bulgarian split squat': 'bulharský drep s jednoručkami',
    'aka crush press': 'crush press',
    'bilateral': 'obojstranne',
    'on risers': 'na podložke',
    'feet forward': 'nohy vpred',
    'on floor': 'na zemi',
    'floor toe reach': 'dosah na špičky na zemi',
    'with weight plate': 's kotúčom',
    'aka crush press': 'crush press',
    'pinwheel curls': 'pinwheel',
    'skullcrusher': 'skullcrusher',
    'aka bicycle': 'bicykel',
    'toes straight': 'špičky rovno',
    'palms up': 'dlaňami hore',
    'palms down': 'dlaňami dole',
    'aka bicycle': 'bicykel',
    'ez bar': 'EZ činka',
    'toes in': 'špičky dnu',
    'toes out': 'špičky von',
    'ez bar': 'EZ činka',
    'aka bicycle': 'bicykel',
    'high start': 'vysoký štart',
    'low start': 'nízky štart',
    'strict': 'prísne',
    'on bench': 'na lavičke',
    'legs on bench': 'nohy na lavičke',
    'aka romanian deadlift': 'rumunský mŕtvy ťah',
    'with dumbells': 's jednoručkami',
    'with barbell': 's veľkou činkou',
    'on the wall': 'pri stene',
}


def consume(text, table):
    """Longest-first phrase matcher: returns (hits, remaining text)."""
    hits = []
    for phrase, sk in sorted(table, key=lambda kv: -len(kv[0])):
        pat = r'(?<![a-z])' + re.escape(phrase) + r'(?![a-z])'
        if re.search(pat, text):
            text = re.sub(pat, ' ', text, count=1)
            hits.append(sk)
    return hits, text


def translate(name):
    if name in OVERRIDES:
        return OVERRIDES[name], name, ''
    raw = name
    paren = ''
    m = re.search(r'\(([^)]*)\)', raw)
    if m:
        inner = m.group(1).strip().lower()
        paren = PAREN.get(inner, inner)
        raw = re.sub(r'\s*\([^)]*\)\s*', ' ', raw)

    text = ' ' + raw.lower().replace('&', ' and ') + ' '
    degree45 = bool(re.search(r'45\s*degree', text))
    text = re.sub(r'45\s*degree', ' ', text)

    # movement (first, longest match wins)
    move = None
    gender = 'm'
    for phrase, (sk, g) in sorted(MOVES, key=lambda kv: -len(kv[0])):
        # tolerate the plural head ("squats", "step ups", "curls")
        pat = r'(?<![a-z])' + re.escape(phrase) + r's?(?![a-z])'
        if re.search(pat, text):
            move, gender = sk, g
            text = re.sub(pat, ' ', text, count=1)
            break
    if move is None:
        return None, raw, 'NO-MOVEMENT'

    grips, text = consume(text, GRIP)
    equips, text = consume(text, EQUIP)
    positions, text = consume(text, POSITION)

    alternating = bool(re.search(r'(?<![a-z])alternat(ing|e)(?![a-z])', text))
    text = re.sub(r'(?<![a-z])alternat(ing|e)(?![a-z])', ' ', text)
    reverse = bool(re.search(r'(?<![a-z])reverse(?![a-z])', text))
    text = re.sub(r'(?<![a-z])reverse(?![a-z])', ' ', text)

    mods, text = consume(text, MODIFIER)

    eq = equips[0] if equips else ''
    # "one-arm dumbell" reads better as a single dumbell
    if eq == ' s jednoručkami' and 'jednoruč' in mods:
        eq = ' s jednoručkou'
    out = move.replace('{eq}', eq)

    # A bench angle replaces the movement's generic "na lavičke" instead of
    # stacking onto it ("tlak na lavičke na šikmej lavičke").
    angle = next((p for p in positions if 'lavičke' in p), None)
    if angle and 'na lavičke' in out:
        out = out.replace('na lavičke', angle)
        positions = [p for p in positions if p != angle]
    if angle:
        positions = [p for p in positions if p != 'na lavičke']

    if alternating or reverse:
        adj = (ADJ_ALT if alternating else ADJ_REV)[gender]
        head = out if out.startswith(PROPER) else out[0].lower() + out[1:]
        out = adj + ' ' + head

    if 'za hlavou' in positions and 'nad hlavu' in out:
        out = out.replace('nad hlavu', 'za hlavu')
        positions = [p for p in positions if p != 'za hlavou']

    tail = []
    # the template may already carry a position ("… v predklone") — don't repeat it
    tail += [p for p in positions if p not in out]
    tail += [e.strip() for e in equips[1:] if e.strip() not in out]
    grips = [g for g in grips if g not in out]
    if len(grips) > 1 and all(g.endswith('úchopom') for g in grips):
        grips = [g[:-len(' úchopom')] for g in grips[:-1]] + [grips[-1]]
    tail += grips
    tail += [m_ for m_ in mods if m_ not in DROP and m_ not in out]
    if tail:
        out = out + ' ' + ' '.join(tail)
    if degree45:
        out = out + ' (45°)'
    if paren:
        out = out + f' ({paren})'
    out = re.sub(r'\s+', ' ', out).strip()
    out = out[0].upper() + out[1:]

    leftover = ' '.join(t for t in text.split() if t not in ('and', 'with', 'on', 'to', 'the', 'a'))
    return out, raw, leftover


def main():
    items = json.load(open(SRC))
    rows, leftovers = [], collections.Counter()
    for it in items:
        sk, raw, leftover = translate(it['name'])
        rows.append((it['slug'], it['name'], sk))
        if leftover:
            for t in leftover.split():
                leftovers[t] += 1
    if '--leftovers' in sys.argv:
        for t, c in leftovers.most_common():
            print(c, t)
        return
    if '--json' in sys.argv:
        json.dump({slug: sk for slug, en, sk in rows if sk}, open(sys.argv[-1], 'w'), ensure_ascii=False, indent=1)
        return
    for slug, en, sk in rows:
        print(f'{en}  →  {sk}')


main()
