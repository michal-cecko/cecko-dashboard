<?php

namespace Database\Seeders\Garaz;

use App\Enums\Garaz\DrivetrainEnum;
use App\Enums\Garaz\EmissionStandardEnum;
use App\Enums\Garaz\FuelTypeEnum;
use App\Enums\Garaz\KnowledgeSourceEnum;
use App\Enums\Garaz\ServiceCategoryEnum;
use App\Enums\Garaz\ServiceSourceEnum;
use App\Enums\Garaz\TransmissionEnum;
use App\Enums\Garaz\VehicleTypeEnum;
use App\Models\Common\User;
use App\Models\Garaz\KnowledgeNote;
use App\Models\Garaz\ServiceRecord;
use App\Models\Garaz\Vehicle;
use Illuminate\Database\Seeder;

class AstraServiceHistorySeeder extends Seeder
{
    private const OWNER_EMAIL = 'michal.cecko@gmail.com';

    private const VIN = 'W0LBE8EB3G8111655';

    public function run(): void
    {
        $owner = User::query()->where('email', self::OWNER_EMAIL)->first();

        if ($owner === null) {
            $this->command?->warn('Používateľ '.self::OWNER_EMAIL.' neexistuje — seeder preskočený.');

            return;
        }

        $vehicle = $this->seedVehicle($owner);
        $this->seedServiceRecords($vehicle);
        $this->seedKnowledgeNotes($owner, $vehicle);
    }

    private function seedVehicle(User $owner): Vehicle
    {
        $vehicle = Vehicle::query()->updateOrCreate(
            ['vin_or_serial' => self::VIN],
            [
                'user_id' => $owner->id,
                'type' => VehicleTypeEnum::CAR,
                'nickname' => 'Astra',
                'make' => 'Opel',
                'model' => 'Astra K Sports Tourer',
                'trim' => 'Innovation',
                'year_of_manufacture' => 2016,
                'first_registration_date' => '2016-09-28',
                'current_odometer_km' => 128_117,
                'current_odometer_at' => '2025-08-11',
                'notes' => 'Kúpené ako nové od Autotip Žilina (28.09.2016). Servisná história prenesená zo skenov servisnej knižky (storage/app/private/repair-history). VIN prepísaný zo skenu — overiť podľa TP.',
            ]
        );

        $vehicle->carSpec()->updateOrCreate(
            ['vehicle_id' => $vehicle->id],
            [
                'fuel_type' => FuelTypeEnum::PETROL,
                'engine_code' => 'B14XFT',
                'displacement_l' => 1.4,
                'power_kw' => 92,
                'transmission' => TransmissionEnum::MANUAL,
                'gear_count' => 6,
                'drivetrain' => DrivetrainEnum::FWD,
                'oil_spec' => 'GM dexos1 Gen 2',
                'oil_viscosity' => '5W-30',
                'oil_capacity_l' => 4.0,
                'emission_standard' => EmissionStandardEnum::EURO6,
            ]
        );

        return $vehicle;
    }

    /**
     * Ročné servisné prehliadky prepísané zo servisnej knižky (pečiatky autorizovaných servisov).
     */
    private function seedServiceRecords(Vehicle $vehicle): void
    {
        $mah = 'M a H Trenčín, s.r.o. (Panónska cesta 43, Bratislava)';
        $becchi = 'Auto Becchi, s.r.o. (Pri Celulózke 3631, Žilina)';

        $records = [
            ['2017-07-21', 14_580, $mah, 'Prvá ročná servisná prehliadka — výmena motorového oleja 5W-30 (dexos1) a olejového filtra, kontrola vozidla.', null],
            ['2018-07-18', 31_615, $mah, 'Ročný servis — výmena motorového oleja 5W-30 a olejového filtra, kontrola podľa servisného plánu.', null],
            ['2019-07-29', 49_800, $mah, 'Ročný servis — výmena motorového oleja 5W-30 a olejového filtra, kontrola podľa servisného plánu.', 'Pridané palivové aditívum.'],
            ['2020-07-31', 64_118, $mah, 'Ročný servis — výmena motorového oleja 5W-30 a olejového filtra, kontrola podľa servisného plánu.', null],
            ['2021-08-02', 77_224, $mah, 'Ročný servis — výmena motorového oleja 5W-30 a olejového filtra, kontrola podľa servisného plánu.', 'Pridané palivové aditívum.'],
            ['2022-07-25', 92_506, $mah, 'Ročný servis — výmena motorového oleja 5W-30 a olejového filtra, výmena peľového filtra, kontrola podľa servisného plánu.', 'Pridané palivové aditívum.'],
            ['2023-08-08', 107_891, $mah, 'Ročný servis — výmena motorového oleja 5W-30 a olejového filtra, kontrola podľa servisného plánu.', null],
            ['2024-07-29', 118_575, $becchi, 'Ročný servis — výmena motorového oleja 5W-30 a olejového filtra, kontrola podľa servisného plánu.', 'Nemrznúca ochrana chladiacej kvapaliny nameraná na −40 °C.'],
            ['2025-08-11', 128_117, $becchi, 'Ročný servis — výmena motorového oleja 5W-30 a olejového filtra, kontrola podľa servisného plánu.', 'Nemrznúca ochrana chladiacej kvapaliny nameraná na −38 °C.'],
        ];

        foreach ($records as [$performedAt, $mileageKm, $shopName, $workSummary, $notes]) {
            ServiceRecord::query()->updateOrCreate(
                [
                    'vehicle_id' => $vehicle->id,
                    'performed_at' => $performedAt,
                ],
                [
                    'mileage_km' => $mileageKm,
                    'category' => ServiceCategoryEnum::OIL_CHANGE,
                    'source' => ServiceSourceEnum::SHOP,
                    'shop_name' => $shopName,
                    'work_summary' => $workSummary,
                    'parts' => [
                        ['name' => 'Motorový olej 5W-30 (dexos1)', 'quantity' => '4.0 l'],
                        ['name' => 'Olejový filter', 'quantity' => '1 ks'],
                    ],
                    'notes' => $notes,
                ]
            );
        }
    }

    /**
     * Poznatky z rešeršu verejných zdrojov (fóra, katalógy, oficiálny Opel manuál) — júl 2026.
     */
    private function seedKnowledgeNotes(User $owner, Vehicle $vehicle): void
    {
        $notes = [
            [
                'title' => 'Motorový olej: dexos1 Gen 2/3 5W-30, 4,0 l, interval max 15 000 km / 1 rok',
                'body' => "Oficiálny Opel manuál pre benzínové motory Astra K predpisuje GM dexos1 Gen 2 (dnes nahradený spätne kompatibilným Gen 3), SAE 5W-30. Náplň s filtrom 4,0 l (MIN→MAX na mierke = 1,0 l). Továrenský interval je 30 000 km / 1 rok, ale konsenzus majiteľov na opel-forum.cz je max 15 000 km, pri krátkych mestských jazdách radšej 10-12 000 km — správny olej a krátky interval sú priamo previazané s prevenciou LSPI a naťahovania rozvodovej reťaze. Nikdy nedolievať iný než dexos1. Cena DIY: olej ~30-45 €/4 l, u nezávislého servisu SK kompletná výmena 90-150 €, u dílera 150-300 €.\n\nZdroje: public-servicebox.opel.com (manuál, str. 282-297), opel-forum.cz/viewtopic.php?t=7465, filtreaoleje.sk",
                'source_url' => 'https://www.opel-forum.cz/viewtopic.php?t=7465',
                'tags' => ['olej', 'interval', 'diy'],
            ],
            [
                'title' => 'Olejový filter: MANN W 7056 (skrutkovací) — POZOR na zámenu s dielom z Astry J',
                'body' => "Overené vo viacerých katalógoch (MANN oficiálny katalóg, Spareto, ws-autoteile): Astra K 1.4 Turbo (92 aj 110 kW) má SKRUTKOVACÍ filter — OEM GM 12640445, MANN W 7056, Mahle OC 1421 (uťahovací moment 20 Nm), Bosch F 026 407 213, Filtron OP 570/2. Cena ~8-13 €.\n\nVAROVANIE: často citovaná kartušová vložka GM 55594651 / MANN HU 612/2 x patrí motorom Family 0 (Astra J A14NET) a na toto auto NEPASUJE — niektoré návody (aj autodoc club) ich miešajú. Pred objednaním vizuálne over na aute: skrutkovací valec vs. plastové veko kartuše.\n\nZdroje: mann-filter.com (W 7056), spareto.com, oilfilter-crossreference.com/convert/GENERAL-MOTORS/55594651",
                'source_url' => 'https://www.mann-filter.com/en/catalog/international/search-results/product.html/w7056_mann-filter.html',
                'tags' => ['filtre', 'diely', 'olej'],
            ],
            [
                'title' => 'Vzduchový filter: MANN C 16 012, interval 60 000 km / 4 roky',
                'body' => "Valcová vložka Ø155/140 × 172 mm. OEM GM 13367308 / 39030321 (neskôr 13489640), MANN C 16 012, Mahle LX 3015/14. Cena ~12-18 €. Výmena je triviálne DIY (10-15 min, veko airboxu hore na motore). Interval podľa Opel plánu 60 000 km / 4 roky — pri aktuálnom nájazde over podľa faktúr, či bol menený po ~2022; ak nie, je na výmenu.\n\nZdroje: mann-filter.com (C 16 012), spareto.com, garage.wiki",
                'source_url' => 'https://www.mann-filter.com/en/catalog/international/search-results/product.html/c16012_mann-filter.html',
                'tags' => ['filtre', 'diely', 'diy'],
            ],
            [
                'title' => 'Peľový filter: MANN CUK 24 003 (aktívne uhlie), DIY za kastlíkom',
                'body' => "Rozmer 240×204×31 mm, za kastlíkom spolujazdca. OEM GM 13356914/13356916, MANN CUK 24 003 (aktívne uhlie, ~27 €), Mahle LA 1123 / LAK 1123 s uhlím (~10-15 €), lacnejšie ekvivalenty 8-10 €. DIY 15-30 min: odskrutkovať bočný panel (~5 skrutiek 7 mm), sklopiť kastlík, vymeniť. Odporúčanie: meniť každý rok (oficiálne 2 roky / 60 000 km). Podľa servisnej knižky menený naposledy 07/2022!\n\nZdroje: mann-filter.com (CUK 24 003), astrakforums.co.uk/threads/pollen-filter-removal.2873",
                'source_url' => 'https://www.mann-filter.com/en/catalog/international/search-results/product.html/cuk24003_mann-filter.html',
                'tags' => ['filtre', 'diely', 'diy'],
            ],
            [
                'title' => 'Zapaľovacie sviečky: NGK ILNAR8B7G (91970), 60 000 km / 4 roky',
                'body' => "OEM GM 55490097 = NGK ILNAR8B7G Laser Iridium (stock 91970), ACDelco 41-156. Žiadny overený Denso/Bosch ekvivalent neexistuje — držať sa NGK/GM. M12×1.25, kľúč 14 mm (tenkostenná hlavica), moment ~20 Nm, irídiové sviečky sa NEPREGAPUJÚ (z výroby 0,7 mm). Sada 4 ks ~80-92 € (Autodoc). Interval 60 000 km / 4 roky. V servisnej knižke nie je jednoznačný záznam o výmene — pri ~138 000 km pravdepodobne po intervale, over podľa faktúr alebo rovno vymeň (DIY ~1 h: kryt motora dole, 4 cievky po 1 skrutke 10 mm).\n\nZdroje: gsparkplug.com, astrakforums.co.uk/threads/how-to-guide-spark-plugs-astra-k-2017-1-4t.6904, autodoc.de",
                'source_url' => 'https://www.astrakforums.co.uk/threads/how-to-guide-spark-plugs-astra-k-2017-1-4t.6904/',
                'tags' => ['sviecky', 'diely', 'diy'],
            ],
            [
                'title' => 'DIY výmena oleja: T45 zátka 14 Nm, reset indikátora cez SET/CLR',
                'body' => "Postup pre Astra K 1.4T: predná časť podvozkového krytu dole (stačí predné/bočné skrutky), výpustná zátka Torx T45, moment 14 Nm, nová podložka (~10 €). Filter skrutkovací (pozri samostatnú poznámku o W 7056). Naliať tesne pod 4 l a doladiť podľa mierky po 0,1 l. Reset ukazovateľa životnosti oleja: zapaľovanie ON bez štartu → na displeji zobraz 'Remaining Oil Life' → podržať SET/CLR na páčke (niektoré verzie SET/CLR + brzdový pedál). DIY náklady ~45-60 € vs. 90-150 € servis — úspora ~50-100 €/rok.\n\nZdroje: club.autodoc.co.uk (Astra K oil change guide), astrakforums.co.uk/threads/oil-change-procedure-info.6866, youtube.com/watch?v=arbGYta9eGE",
                'source_url' => 'https://www.astrakforums.co.uk/threads/oil-change-procedure-info.6866/',
                'tags' => ['olej', 'diy'],
            ],
            [
                'title' => 'LSPI riziko na 1.4T: overiť ECU update, jazdiť len na dexos1',
                'body' => "LSPI (predčasné zapálenie pri nízkych otáčkach pod záťažou) vie na tomto motore prasknúť/prepáliť piest — zdokumentované aj na 92 kW verzii (auto.cz, tipcars recenzia: prasknutý piest ~72 000 km na kuse z 2016). Opel vydal na autá 2015-2017 ECU kampaň (úprava vstrekovania proti LSPI). AKCIA: overiť u dílera podľa VIN, či bola kampaň vykonaná. Prevencia: výhradne dexos1 Gen 2/3 (aditíva proti LSPI), neťahať vysoké prevody v nízkych otáčkach pod plnou záťažou. Súvisí aj s trhaním/cukaním pri akcelerácii 2200-3500 ot./min — rieši ten istý dealer flash.\n\nZdroje: auto.cz/ojety-opel-astra-k-od-2015, magazin.tipcars.com (7-ročná recenzia majiteľa), astrakforums.co.uk",
                'source_url' => 'https://www.auto.cz/ojety-opel-astra-k-od-2015-jaky-je-zivot-ve-stinu-137026',
                'tags' => ['poruchy', 'motor'],
            ],
            [
                'title' => 'Rozvodová reťaz B14: počúvať rachot pri studenom štarte, kódy P0016/P0017',
                'body' => "Známa slabina — reťaz sa naťahuje, urýchľujú to dlhé olejové intervaly. Príznaky: kovový rachot pár sekúnd po studenom štarte, neskôr kódy P0016/P0017 (cam-crank korelácia). Dobre servisované motory vydržia 200 000+ km; výmena sa v CZ/PL praxi robí okolo 180-220 000 km alebo pri prvom rachote. Cena kompletu (reťaz, napinák, vodítka): diely ~50-300 €, práca celkom ~600-1000 € u nezávislého. Pri ~138 000 km: zatiaľ len monitorovať — presne na to je v Garáži kontrola 'Predbežná kontrola rozvodovej reťaze (B14XFT)'.\n\nZdroje: kfz-dietrich.com/blog/opel-astra-k-typische-probleme-diagnose, astraklub.pl/viewtopic.php?t=848432, youtube.com/watch?v=VQjl8zwaITc (výmena krok za krokom)",
                'source_url' => 'https://kfz-dietrich.com/blog/opel-astra-k-typische-probleme-diagnose/',
                'tags' => ['rozvody', 'poruchy'],
            ],
            [
                'title' => 'Chladenie: strata kvapaliny (pumpa, nádobka), termostat P0128, Dexcool výmena po ~5-6 rokoch',
                'body' => "Na 10-ročnom aute sú aktuálne tri veci: (1) Pomalý úbytok chladiacej kvapaliny bez mláky — typicky presakujúca vodná pumpa (stopy na bloku; diel 50-120 €, výmena 150-300 €, stredné DIY — je na remeni), prasknutá expanzná nádobka (~30-50 €, biele kryštály zospodu) alebo zvetrané viečko (~10-15 €). (2) Elektronický termostat — pomalé ohrievanie, slabé kúrenie, kód P0128, diel 60-150 €. (3) Kvapalina je Dexcool OAT (oranžová, OEM 93165162, 5 l ~25-35 €) — oficiálne 'long-life', ale opelácky mechanik odporúča výmenu po 5-6 rokoch → na aute z 2016 pravdepodobne PO TERMÍNE, ak sa nikdy nemenila. Nemiešať so silikátovou (G11). Servisná knižka: mrazuvzdornosť −40 °C (2024) → −38 °C (2025) — mierny pokles, sledovať. STK/EK tip: viditeľný únik chladiacej kvapaliny je závada.\n\nZdroje: astrakforums.co.uk/viewtopic.php?t=6214, kfz-dietrich.com, originalcarparts.com (93165162)",
                'source_url' => 'https://www.astrakforums.co.uk/viewtopic.php?t=6214',
                'tags' => ['chladenie', 'poruchy', 'interval'],
            ],
            [
                'title' => 'PCV membrána a karbón na ventiloch — typické neduhy DI motora po 100 000 km',
                'body' => "Motor je priamovstrek (LE2 rodina — platí aj pre 125 PS verziu!), takže: (1) PCV membrána vo veku ventilov a spätný ventil v sacom potrubí vekom krehnú — príznaky: pískanie/syčanie, nepokojný voľnobeh, kódy P0171/P1101/P0299, zvýšená spotreba oleja. Fix: nové veko ventilov 60-150 €, jednoduché DIY. (2) Karbón na sacích ventiloch — DI motor ventily neumýva palivom; prejavy: horší voľnobeh, slabšia reakcia. Walnut blasting 250-400 €. Prevencia: kvalitné palivo, občas vytočiť na diaľnici, zdravé PCV. (3) Turbo: aktuátor wastegate vie viaznuť (P0299/P0234) — často stačí vyčistiť/vymeniť aktuátor (150-400 €), nie celé turbo.\n\nZdroje: cruzekits.com (PCV fix kit GM 1.4T), youtube.com/watch?v=4GfW_2owimI, kfz-dietrich.com",
                'source_url' => 'https://kfz-dietrich.com/blog/opel-astra-k-typische-probleme-diagnose/',
                'tags' => ['poruchy', 'motor', 'diy'],
            ],
            [
                'title' => 'Brzdová kvapalina DOT4 každé 2 roky — radšej servis než DIY',
                'body' => "Interval: každé 2 roky bez ohľadu na km. V SK servisoch 30-50 € (AutoČiernik od ~30 €, BestDrive balíček). DIY sa neoplatí: treba tlakovú odvzdušňovačku alebo druhú osobu, správne poradie a chyba (vzduch v ABS) je bezpečnostný problém — úspora pár eur. Podľa servisnej knižky nie je jasný posledný dátum výmeny → ak nebola v 2024/2025, spraviť tento rok spolu s ročným servisom.\n\nZdroje: garage.wiki (Astra K intervaly), autociernik.sk/cennik, bestdrive.sk",
                'source_url' => 'https://garage.wiki/opel/astra-sports-tourer/k/2016/service-intervals.html',
                'tags' => ['brzdy', 'interval'],
            ],
            [
                'title' => 'STK/EK príprava: brzdy + svetlá = 78 % závad; kombi špecialita — zatekanie na BCM',
                'body' => "STK ~40 € + EK ~35 € (balíky 65-95 €, BA až 130 €). Najčastejšie závady: brzdy (~60 %), osvetlenie (~18 %). Pred kontrolou over: všetky žiarovky vrátane osvetlenia EČV, zadné kotúče (na málo jazdených autách koródujú/glazujú — ručná brzda na valcoch), vôle ramien/stabilizátorov, žiadna MIL kontrolka. Pred EK 15-20 min svižnej jazdy prečistí hodnoty. ŠPECIFICKÉ PRE SPORTS TOURER: hadička zadného ostrekovača vie zatekať na riadiacu jednotku (BCM) → náhodné elektrické poruchy; skontrolovať trasu hadičky, lacné DIY. Ostatné: IntelliLink občas mrzne, zlyhania AC kompresora hlásené od 60-80 000 km, predné kotúče miznú za 40-50 000 km pri ostrejšej jazde.\n\nZdroje: stkonline.sk/informacny-servis/ceny-technickej-a-emisnej-kontroly, topspeed.sk, kfz-dietrich.com",
                'source_url' => 'https://www.stkonline.sk/informacny-servis/ceny-technickej-a-emisnej-kontroly',
                'tags' => ['stk', 'brzdy', 'elektrika'],
            ],
            [
                'title' => 'Kód motora: v dokladoch B14XFT, predajný kód 92 kW verzie je B14XFL — diely sedia na obe',
                'body' => "Oficiálny Opel manuál uvádza pre 92 kW/125 PS motor predajný kód B14XFL (od MY2018 D14XFL) s inžinierskym kódom B14XFT — preto je v dokladoch a servisnej knižke B14XFT. 110 kW/150 PS verzia má predajný kód B14XFT. Prakticky: je to ten istý 1399 cm³ priamovstrekový turbo motor (GM LE2/SGE) a všetky servisné diely (filtre, sviečky, olej) sú pre obe verzie IDENTICKÉ — katalógy MANN/Mahle/NGK uvádzajú obe výkonové verzie pod rovnakými číslami. Pri objednávaní dielov voliť podľa 'Astra K 1.4 Turbo' + rok 2016, nie slepo podľa kódu motora.\n\nZdroje: public-servicebox.opel.com (manuál str. 289), en.wikipedia.org/wiki/GM_small_gasoline_engine, mann-filter.com",
                'source_url' => 'https://en.wikipedia.org/wiki/GM_small_gasoline_engine',
                'tags' => ['motor', 'diely'],
            ],
            [
                'title' => 'Stierače: Bosch Aerotwin A 965 S (700+600 mm) predné',
                'body' => "Predné: Bosch Aerotwin A 965 S, sada 700 mm + 600 mm, ~28-31 € (Autodoc/Winparts, overené vo viacerých obchodoch). Zadný (Sports Tourer): OE ~250 mm; na fórach sa spomína Bosch H311 (300 mm) ako upgrade, ale overené primárne pre hatchback — pri objednávke skontrolovať uchytenie. Palivový filter na benzíne neexistuje ako servisný diel (doživotný v nádrži) — záznamy 'palivový filter' v servisnej knižke boli len aditívum.\n\nZdroje: autodoc.de, winparts.eu, micksgarage.com",
                'source_url' => 'https://www.winparts.eu/windscreens-accessories/wiper-blades/c469/bosch-windscreen-wipers-aerotwin-a965s-length-700-600-mm-set-of-wiper-blades-for/p256673.html',
                'tags' => ['diely', 'diy'],
            ],
        ];

        foreach ($notes as $note) {
            KnowledgeNote::query()->updateOrCreate(
                [
                    'user_id' => $owner->id,
                    'title' => $note['title'],
                ],
                [
                    'vehicle_id' => $vehicle->id,
                    'body' => $note['body'],
                    'source_url' => $note['source_url'],
                    'source' => KnowledgeSourceEnum::FORUM,
                    'tags' => $note['tags'],
                    'captured_at' => '2026-07-16',
                ]
            );
        }
    }
}
