<?php

namespace App\Filament\Client\Schemas;

use Filament\Forms\Components\RichEditor;

/**
 * Text formatting only. Files go through TicketAttachmentUpload, so they stay private.
 */
class TicketTextEditor
{
    public const MAX_LENGTH = 20000;

    public static function make(string $name): RichEditor
    {
        return RichEditor::make($name)
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link'],
                ['h2', 'h3', 'blockquote', 'codeBlock'],
                ['bulletList', 'orderedList'],
                ['undo', 'redo'],
            ])
            ->maxLength(self::MAX_LENGTH)
            ->extraInputAttributes(['style' => 'min-height: 8rem;']);
    }
}
