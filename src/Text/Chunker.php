<?php

declare(strict_types=1);

namespace Displace\AI\Toolkit\Text;

/**
 * Splits text into chunks suitable for embedding.
 *
 * Sizes throughout the chunkers are measured in **characters**
 * (multibyte-aware), not tokens — character budgets are
 * model-independent and close enough for retrieval chunking. As a rule
 * of thumb, one token is roughly four characters of English text.
 */
interface Chunker
{
    /**
     * Split `$text` into chunks. Implementations never return empty
     * chunks, and chunking an empty or whitespace-only text yields an
     * empty list.
     *
     * @return list<string>
     */
    public function chunk(string $text): array;
}
