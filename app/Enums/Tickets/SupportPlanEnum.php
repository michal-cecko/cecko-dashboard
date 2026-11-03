<?php

namespace App\Enums\Tickets;

use App\Traits\Common\EnumHelper;

/**
 * The perks follow the price list "Starostlivosť o vašu aplikáciu" (cecko.dev).
 */
enum SupportPlanEnum: string
{
    use EnumHelper;

    case NONE = 'none';
    case START = 'start';
    case STANDARD = 'standard';
    case PRO = 'pro';

    public function rank(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    public function isHigherThan(self $plan): bool
    {
        return $this->rank() > $plan->rank();
    }

    public function tagline(): string
    {
        return match ($this) {
            self::NONE => 'Aplikácia je hotová a mení sa zriedka.',
            self::START => 'Aplikácia beží, občas treba niečo upraviť.',
            self::STANDARD => 'Aplikácia je dôležitá pre denný chod firmy.',
            self::PRO => 'Aplikácia stále rastie o nové funkcie.',
        };
    }

    public function monthlyPrice(): string
    {
        return match ($this) {
            self::NONE => '0 €',
            self::START => '190 € / 4 750 CZK',
            self::STANDARD => '390 € / 9 750 CZK',
            self::PRO => '790 € / 19 750 CZK',
        };
    }

    /**
     * ISO weekdays (1 = Monday) on which the 16:00 Telegram batch goes out.
     *
     * @return list<int>
     */
    public function digestDays(): array
    {
        return match ($this) {
            self::NONE => [1],
            self::START => [1, 4],
            self::STANDARD, self::PRO => [1, 2, 3, 4, 5],
        };
    }

    /**
     * Critical failures outside working hours (6:00–23:00), in EUR per hour. Billed per started half-hour.
     */
    public function urgentRate(): int
    {
        return match ($this) {
            self::NONE => 120,
            self::START => 100,
            self::STANDARD => 80,
            self::PRO => 60,
        };
    }

    public function criticalStart(): string
    {
        return match ($this) {
            self::NONE => 'začnem do 2 pracovných dní',
            self::START => 'začnem nasledujúci pracovný deň',
            self::STANDARD => 'začnem v ten istý deň',
            self::PRO => 'začnem v ten istý deň, prednostne',
        };
    }

    public function urgentFeeHint(): string
    {
        return "Balík {$this->translation()}: kritickú poruchu v pracovnej dobe (po–pi 9:00–17:00) {$this->criticalStart()}. "
            ."Mimo nej (6:00–23:00) {$this->urgentRate()} € / hod., účtuje sa každá začatá polhodina.";
    }

    /**
     * The price list's comparison table: one row per perk, with a value for each plan.
     *
     * @return list<array{label: string, hint: ?string, values: array<string, string>}>
     */
    public static function perkRows(): array
    {
        $row = fn (string $label, ?string $hint, string $none, string $start, string $standard, string $pro): array => [
            'label' => $label,
            'hint' => $hint,
            'values' => [
                self::NONE->value => $none,
                self::START->value => $start,
                self::STANDARD->value => $standard,
                self::PRO->value => $pro,
            ],
        ];

        $urgent = fn (self $plan): string => $plan->urgentRate().' € / hod';
        $critical = fn (self $plan): string => $plan->criticalStart();

        return [
            $row('Cena ročne', '2 mesiace zdarma', '0 €', '1 900 € / 47 500 CZK', '3 900 € / 97 500 CZK', '7 900 € / 197 500 CZK'),
            $row('Cena mesačne', null, self::NONE->monthlyPrice(), self::START->monthlyPrice(), self::STANDARD->monthlyPrice(), self::PRO->monthlyPrice()),
            $row('Hosting webovej aplikácie, ročne', 'server, SSL, denné zálohy, sledovanie dostupnosti', '150 € / 3 750 CZK', '110 € / 2 750 CZK (−27 %)', '75 € / 1 875 CZK (−50 %)', 'v cene'),
            $row('Bezpečnostné aktualizácie', 'PHP, Laravel, knižnice', 'kritické v cene hostingu, ostatné podľa ponuky', 'raz mesačne', 'priebežne', 'kritické do 1 pracovného dňa, ostatné priebežne'),
            $row('Záruka na dodanú prácu', null, '3 mesiace od dodania', 'počas plánu + 3 mesiace po ňom', 'počas plánu + 3 mesiace po ňom', 'počas plánu + 3 mesiace po ňom'),
            $row('Predplatené hodiny mesačne', 'neprenášajú sa', '0', '1,5 hod', '3 hod', '5 hod'),
            $row('Kredit na nové funkcie mesačne', 'neprenáša sa', '—', '—', '—', '200 € / 5 000 CZK'),
            $row('Zľava na prácu navyše', null, '—', '—', '5 %', '10 %'),
            $row('Odpoveď na požiadavky a e-maily', null, 'raz týždenne (pondelok)', '2× týždenne (pondelok, štvrtok)', 'každý pracovný deň', 'každý pracovný deň + krátky telefonát do 10 min v ten istý deň'),
            $row('Kritická porucha v pracovnej dobe', null, $critical(self::NONE), $critical(self::START), $critical(self::STANDARD), $critical(self::PRO)),
            $row('Kritická porucha mimo pracovnej doby', '6:00–23:00, začnem podľa mojej dostupnosti', $urgent(self::NONE), $urgent(self::START), $urgent(self::STANDARD), $urgent(self::PRO)),
            $row('Telefonáty a stretnutia', 'každých začatých 30 min', '30 € / 750 CZK', '25 € / 625 CZK', '30 min mesačne v cene + plánovanie rozvoja (60 min) raz za štvrťrok, potom 20 € / 500 CZK', '60 min mesačne v cene + plánovanie rozvoja (60 min) každý mesiac, potom 20 € / 500 CZK'),
            $row('Mesačné zhrnutie prác a zmien e-mailom', null, '—', '✓', '✓', '✓'),
        ];
    }

    /**
     * @return list<array{label: string, hint: ?string, value: string}>
     */
    public function perks(): array
    {
        return array_map(fn (array $row): array => [
            'label' => $row['label'],
            'hint' => $row['hint'],
            'value' => $row['values'][$this->value],
        ], self::perkRows());
    }
}
