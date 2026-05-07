<?php

declare(strict_types=1);

namespace Signori\Responses;

/**
 * Wraps a paginated list response.
 *
 * @template T of BaseResponse
 */
final class PaginatedResponse extends BaseResponse
{
    /** @var T[] */
    public readonly array   $items;
    public readonly int     $total;
    public readonly ?string $nextCursor;
    public readonly ?string $prevCursor;

    /**
     * @param array          $data    Raw API response array
     * @param callable       $factory Callable that maps one raw item array → T
     */
    public static function from(array $data, callable $factory): self
    {
        $r = new self($data);

        $rawItems       = is_array($data['items'] ?? null) ? $data['items'] : [];
        $r->items       = array_map($factory, $rawItems);
        $r->total       = $r->int('total');
        $r->nextCursor  = $r->nullable('next_cursor');
        $r->prevCursor  = $r->nullable('prev_cursor');

        return $r;
    }

    /** @return T|null */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }
}
