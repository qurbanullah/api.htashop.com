<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

/**
 * Splits a knowledge entry body into retrievable passages.
 *
 * Deterministic and dependency-free: the same body always produces the same
 * chunks, so re-indexing is idempotent and cheap.
 */
class KnowledgeChunker
{
    /**
     * @return array<int, array{content: string, position: int}>
     */
    public function chunk(string $text): array
    {
        $max = max(200, (int) config('knowledge.chunk.max_characters', 1200));
        $overlap = max(0, min((int) config('knowledge.chunk.overlap_characters', 150), (int) ($max / 2)));
        $min = max(0, (int) config('knowledge.chunk.min_characters', 40));

        $normalized = trim((string) preg_replace("/\r\n?/", "\n", $text));

        if ($normalized === '') {
            return [];
        }

        $chunks = [];
        $buffer = '';

        foreach ($this->segments($normalized, $max, $overlap) as $segment) {
            $candidate = $buffer === '' ? $segment : $buffer."\n\n".$segment;

            if (mb_strlen($candidate) > $max && $buffer !== '') {
                $chunks[] = trim($buffer);
                $buffer = ($overlap > 0 ? mb_substr($buffer, -$overlap)."\n\n" : '').$segment;
            } else {
                $buffer = $candidate;
            }
        }

        if (trim($buffer) !== '') {
            $chunks[] = trim($buffer);
        }

        $result = [];

        foreach ($chunks as $content) {
            $content = trim($content);

            if (mb_strlen($content) < $min) {
                continue;
            }

            $result[] = ['content' => $content, 'position' => count($result)];
        }

        return $result;
    }

    /**
     * Paragraphs, with any paragraph longer than the window hard-split.
     *
     * @return array<int, string>
     */
    protected function segments(string $text, int $max, int $overlap): array
    {
        $segments = [];

        foreach (preg_split('/\n{2,}/', $text) ?: [] as $paragraph) {
            $paragraph = trim((string) $paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) <= $max) {
                $segments[] = $paragraph;

                continue;
            }

            $offset = 0;
            $length = mb_strlen($paragraph);
            $step = max(1, $max - $overlap);

            while ($offset < $length) {
                $segments[] = trim(mb_substr($paragraph, $offset, $max));
                $offset += $step;
            }
        }

        return $segments;
    }
}
