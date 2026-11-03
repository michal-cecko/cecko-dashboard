<?php

namespace App\Services\Tickets;

use App\Models\Tickets\Project;
use daacreators\CreatorsTicketing\Models\Form;
use daacreators\CreatorsTicketing\Models\TicketStatus;

/**
 * The statuses and the one ticket form the client panel relies on.
 * The plugin reads the ticket title and text from the project's first form.
 */
class TicketingDefaults
{
    public const FORM_SLUG = 'tiket';

    public static function install(): void
    {
        $statuses = [
            ['slug' => 'novy', 'name' => 'Nový', 'color' => '#3b82f6', 'is_default_for_new' => true, 'is_closing_status' => false],
            ['slug' => 'riesi-sa', 'name' => 'Rieši sa', 'color' => '#f59e0b', 'is_default_for_new' => false, 'is_closing_status' => false],
            ['slug' => 'caka-na-klienta', 'name' => 'Čaká na klienta', 'color' => '#a855f7', 'is_default_for_new' => false, 'is_closing_status' => false],
            ['slug' => 'vyrieseny', 'name' => 'Vyriešený', 'color' => '#22c55e', 'is_default_for_new' => false, 'is_closing_status' => true],
        ];

        foreach ($statuses as $order => $status) {
            TicketStatus::query()->firstOrCreate(['slug' => $status['slug']], $status + ['order_column' => $order]);
        }

        static::form();
    }

    public static function form(): Form
    {
        $form = Form::query()->firstOrCreate(
            ['slug' => self::FORM_SLUG],
            ['name' => 'Tiket', 'is_active' => true],
        );

        $fields = [
            ['name' => 'title', 'label' => 'Názov', 'type' => 'text', 'is_required' => true],
            ['name' => 'description', 'label' => 'Popis', 'type' => 'textarea', 'is_required' => true],
            ['name' => TicketAttachments::FIELD, 'label' => 'Prílohy', 'type' => 'file_multiple', 'is_required' => false],
        ];

        foreach ($fields as $order => $field) {
            $form->fields()->firstOrCreate(['name' => $field['name']], $field + ['order' => $order]);
        }

        return $form;
    }

    public static function attachForm(Project $project): void
    {
        $project->forms()->syncWithoutDetaching([static::form()->getKey()]);
    }
}
