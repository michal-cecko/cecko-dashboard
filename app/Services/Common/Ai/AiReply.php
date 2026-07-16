<?php

namespace App\Services\Common\Ai;

/**
 * Normalised provider response — shared by every panel.
 *
 * @param  array<int, array{id: string, name: string, input: array}>  $toolUses
 */
readonly class AiReply
{
    public function __construct(
        public ?string $text,
        public array $toolUses,
        public string $stopReason,
        public AiTokenUsage $usage,
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
            $block = [
                'type' => 'tool_use',
                'id' => $tool['id'],
                'name' => $tool['name'],
                'input' => (object) $tool['input'],
            ];

            // Gemini 3.x requires the functionCall's thoughtSignature to be echoed
            // back on the next turn. Providers that don't set it are unaffected.
            if (! empty($tool['signature'])) {
                $block['signature'] = $tool['signature'];
            }

            $content[] = $block;
        }

        return $content;
    }
}
