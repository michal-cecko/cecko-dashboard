<?php

namespace Database\Seeders\Garaz;

use App\Enums\Garaz\ConcernCheckInputEnum;
use App\Enums\Garaz\ConcernTriggerEnum;
use App\Enums\Garaz\VehicleTypeEnum;
use App\Models\Garaz\ConcernCheck;
use App\Models\Garaz\MaintenanceConcern;
use Illuminate\Database\Seeder;

class MaintenanceConcernSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTimingChainB14XFT();
        $this->seedOilLevelVisualCar();
        $this->seedBrakePadVisualCar();
        $this->seedSummerCheckupCar();
        $this->seedBicycleChainWear();
    }

    private function seedTimingChainB14XFT(): void
    {
        $concern = MaintenanceConcern::updateOrCreate(
            ['name' => 'Predbežná kontrola rozvodovej reťaze (B14XFT)'],
            [
                'description' => 'Známa slabina motora B14XFT — táto kontrola odhalí príznaky predĺženia reťaze pred drahou návštevou servisu.',
                'trigger_type' => ConcernTriggerEnum::MILEAGE,
                'trigger_config' => ['threshold_km' => 130_000],
                'vehicle_type_match' => VehicleTypeEnum::CAR->value,
                'engine_code_match' => 'B14XFT',
                'shop_diagnostic_cost_min_eur' => 60,
                'shop_diagnostic_cost_max_eur' => 100,
                'self_check_minutes' => 30,
                'recheck_after_km' => 10_000,
                'recheck_after_days' => 180,
                'is_active' => true,
            ]
        );

        $concern->checks()->delete();

        $checks = [
            ['order' => 1, 'name' => 'Studený štart — kovový rachot', 'instruction' => 'Ráno pred prvým štartom: nahraj video so zvukom, naštartuj a nechaj bežať 10 sekúnd. Hodnoť hlasitosť rachotu.', 'input_type' => ConcernCheckInputEnum::RATING, 'pass_criteria' => '0–1: bez rachotu alebo veľmi slabý', 'fail_criteria' => '4–5: zreteľný kovový rachot trvajúci viac ako 2 sekundy'],
            ['order' => 2, 'name' => 'Hladina a kondícia oleja', 'instruction' => 'Vytiahni mierku po zahriatí motora. Odfoť pri dobrom svetle.', 'input_type' => ConcernCheckInputEnum::PHOTO, 'pass_criteria' => 'Hladina medzi MIN/MAX, jantárová farba bez kovových čiastočiek', 'fail_criteria' => 'Pod MIN, čierna ako decht alebo prítomnosť kovových triesok'],
            ['order' => 3, 'name' => 'História oleja', 'instruction' => 'Automatická kontrola posledného záznamu oleja zo servisných záznamov.', 'input_type' => ConcernCheckInputEnum::AUTO_LOOKUP, 'pass_criteria' => 'Posledný olej v rámci intervalu (15 000 km / 1 rok)'],
            ['order' => 4, 'name' => 'OBD2 chybové kódy', 'instruction' => 'Pripoj OBD2 čítačku. Sleduj kódy P0008, P0009, P0011, P0014, P0016, P0017 (cam-crank correlation = predĺženie reťaze).', 'input_type' => ConcernCheckInputEnum::OBD_CODES, 'pass_criteria' => 'Žiadne kódy z uvedenej skupiny', 'fail_criteria' => 'Akýkoľvek z P0008/9/11/14/16/17', 'is_required' => false],
            ['order' => 5, 'name' => 'Voľnobeh — zvuk z prednej časti motora', 'instruction' => 'Po jazde na voľnobehu otvor kapotu a nahraj 10 s zvuku spredu motora.', 'input_type' => ConcernCheckInputEnum::RATING, 'pass_criteria' => '0–1: motor beží hladko', 'fail_criteria' => '3–5: počuteľné cvakanie alebo rachot'],
        ];

        foreach ($checks as $check) {
            ConcernCheck::create(['maintenance_concern_id' => $concern->id, ...$check]);
        }
    }

    private function seedOilLevelVisualCar(): void
    {
        $concern = MaintenanceConcern::updateOrCreate(
            ['name' => 'Vizuálna kontrola hladiny oleja'],
            [
                'description' => 'Rýchla DIY kontrola pred jazdou alebo medzi servisnými intervalmi.',
                'trigger_type' => ConcernTriggerEnum::TIME,
                'trigger_config' => ['interval_days' => 30],
                'vehicle_type_match' => VehicleTypeEnum::CAR->value,
                'self_check_minutes' => 5,
                'recheck_after_days' => 30,
                'shop_diagnostic_cost_min_eur' => 0,
                'shop_diagnostic_cost_max_eur' => 0,
                'is_active' => true,
            ]
        );

        $concern->checks()->delete();

        ConcernCheck::create([
            'maintenance_concern_id' => $concern->id,
            'order' => 1,
            'name' => 'Foto mierky oleja',
            'instruction' => 'Auto musí stáť na rovine, motor zahriaty a vypnutý 5 min. Vytiahni mierku, utri, zasuň, znova vytiahni a odfoť.',
            'input_type' => ConcernCheckInputEnum::PHOTO,
            'pass_criteria' => 'Hladina medzi MIN a MAX, jantárová farba',
            'fail_criteria' => 'Pod MIN alebo nad MAX',
            'uncertain_criteria' => 'Tmavá ale nie čierna — sleduj ďalšie kontroly',
        ]);
    }

    private function seedBrakePadVisualCar(): void
    {
        $concern = MaintenanceConcern::updateOrCreate(
            ['name' => 'Vizuálna kontrola brzdových platničiek'],
            [
                'description' => 'Cez špice kolesa: zhrubnutie platničky a kotúča rozhoduje, či treba do servisu.',
                'trigger_type' => ConcernTriggerEnum::TIME,
                'trigger_config' => ['interval_days' => 180],
                'vehicle_type_match' => VehicleTypeEnum::CAR->value,
                'self_check_minutes' => 10,
                'shop_diagnostic_cost_min_eur' => 30,
                'shop_diagnostic_cost_max_eur' => 60,
                'recheck_after_days' => 180,
                'is_active' => true,
            ]
        );

        $concern->checks()->delete();

        $checks = [
            ['order' => 1, 'name' => 'Foto prednej brzdy cez špice', 'instruction' => 'Otoč volant naplno, odfoť tak, aby bola vidieť hrúbka platničky a kotúča.', 'input_type' => ConcernCheckInputEnum::PHOTO],
            ['order' => 2, 'name' => 'Hrúbka platničky (mm)', 'instruction' => 'Odhadni alebo zmeraj hrúbku trecieho materiálu (bez nosiča).', 'input_type' => ConcernCheckInputEnum::NUMBER, 'pass_criteria' => '> 4 mm', 'fail_criteria' => '< 3 mm', 'uncertain_criteria' => '3–4 mm'],
        ];

        foreach ($checks as $check) {
            ConcernCheck::create(['maintenance_concern_id' => $concern->id, ...$check]);
        }
    }

    private function seedSummerCheckupCar(): void
    {
        $concern = MaintenanceConcern::updateOrCreate(
            ['name' => 'Letný servis — DIY checklist'],
            [
                'description' => 'Bundle DIY úkonov pred letnou sezónou. Ak všetko prejde zelene, nemusíš platiť dealerovi za ročnú prehliadku.',
                'trigger_type' => ConcernTriggerEnum::SEASONAL,
                'trigger_config' => ['month' => 5],
                'vehicle_type_match' => VehicleTypeEnum::CAR->value,
                'self_check_minutes' => 90,
                'shop_diagnostic_cost_min_eur' => 250,
                'shop_diagnostic_cost_max_eur' => 400,
                'recheck_after_days' => 365,
                'is_active' => true,
            ]
        );

        $concern->checks()->delete();

        $checks = [
            ['order' => 1, 'name' => 'Olej + filter (svojpomocne)', 'instruction' => 'Vymeň motorový olej + olejový filter podľa špecifikácie vozidla.', 'input_type' => ConcernCheckInputEnum::CHOICE, 'input_options' => ['done', 'skip']],
            ['order' => 2, 'name' => 'Vzduchový filter', 'instruction' => 'Odklopiť kryt, vybrať filter, vyfúkať alebo vymeniť.', 'input_type' => ConcernCheckInputEnum::CHOICE, 'input_options' => ['done', 'skip']],
            ['order' => 3, 'name' => 'Kabínový filter', 'instruction' => 'Pod schránkou pred spolujazdcom — výmena za 2 minúty.', 'input_type' => ConcernCheckInputEnum::CHOICE, 'input_options' => ['done', 'skip']],
            ['order' => 4, 'name' => 'Tlak a dezén pneumatík', 'instruction' => 'Pridaj záznam o tlaku a hrúbke dezénu (min 1.6 mm zákonne, odporúčane 3 mm).', 'input_type' => ConcernCheckInputEnum::TEXT],
            ['order' => 5, 'name' => 'Brzdy — vizuálna kontrola', 'instruction' => 'Cez špice — hrúbka platničky a kotúča.', 'input_type' => ConcernCheckInputEnum::PHOTO],
            ['order' => 6, 'name' => 'Stierače a kvapalina', 'instruction' => 'Stierače: trhliny / pruhovanie? Nádrž ostrekovača plná?', 'input_type' => ConcernCheckInputEnum::CHOICE, 'input_options' => ['done', 'replace']],
        ];

        foreach ($checks as $check) {
            ConcernCheck::create(['maintenance_concern_id' => $concern->id, ...$check]);
        }
    }

    private function seedBicycleChainWear(): void
    {
        $concern = MaintenanceConcern::updateOrCreate(
            ['name' => 'Opotrebovanie reťaze (bicykel)'],
            [
                'description' => 'Reťaz nad 0.75 % opotrebovania začína žrať kazety a prevodníky. Skoro vymeniť = lacné. Neskoro = drahé.',
                'trigger_type' => ConcernTriggerEnum::MILEAGE,
                'trigger_config' => ['threshold_km' => 2_500],
                'vehicle_type_match' => VehicleTypeEnum::BICYCLE->value,
                'self_check_minutes' => 5,
                'shop_diagnostic_cost_min_eur' => 5,
                'shop_diagnostic_cost_max_eur' => 15,
                'recheck_after_km' => 500,
                'recheck_after_days' => 60,
                'is_active' => true,
            ]
        );

        $concern->checks()->delete();

        $checks = [
            ['order' => 1, 'name' => 'Meranie meradlom reťaze', 'instruction' => 'Použi 0.5/0.75/1 % meradlo. Vlož do reťaze a skontroluj zapadnutie.', 'input_type' => ConcernCheckInputEnum::CHOICE, 'input_options' => ['under_0_5', '0_5_to_0_75', 'over_0_75'], 'pass_criteria' => 'under_0_5', 'uncertain_criteria' => '0_5_to_0_75', 'fail_criteria' => 'over_0_75'],
        ];

        foreach ($checks as $check) {
            ConcernCheck::create(['maintenance_concern_id' => $concern->id, ...$check]);
        }
    }
}
