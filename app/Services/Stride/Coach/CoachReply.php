<?php

namespace App\Services\Stride\Coach;

/**
 * Normalised provider response.
 *
 * @param  array<int, array{id: string, name: string, input: array}>  $toolUses
 */
readonly class CoachReply
{
    public function __construct(
        public ?string $text,
        public array $toolUses,
        public string $stopReason,
        public CoachUsage $usage,
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolUses !== [];
    }

    /** Rebuild the assistant content blocks to echo back into the next turn. */
    public function assistantContent(): array
    {
        $content = [];

        if ($this->text !== null && $this->text !== '') {
            $content[] = ['type' => 'text', 'text' => $this->text];
        }

        foreach ($this->toolUses as $tool) {
            $content[] = [
                'type' => 'tool_use',
                'id' => $tool['id'],
                'name' => $tool['name'],
                'input' => (object) $tool['input'],
            ];
        }

        return $content;
    }
}
