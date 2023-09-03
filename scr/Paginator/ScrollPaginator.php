<?php
declare(strict_types=1);

namespace Pmedynskyi\ScoutOpenSearchEngine\Paginator;

use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;

final class ScrollPaginator extends CursorPaginator
{
    private ?Cursor $nextCursor = null;
    private ?Cursor $previousCursor = null;

    /**
     * Create a new paginator instance.
     *
     * @param  mixed  $items
     * @param  int  $perPage
     * @param array $response
     * @param Cursor|null  $cursor
     * @param  array  $options  (path, query, fragment, pageName)
     * @return void
     */
    public function __construct(
        $items,
        $perPage,
        array $response,
        $cursor = null,
        $options = []
    ) {
        parent::__construct($items, $perPage, $cursor, $options);

        $this->initCursors(
            array_slice($response['hits']['hits'], 0, $perPage)
        );
    }

    /**
     * @inheritDoc
     */
    public function previousCursor()
    {
        return $this->previousCursor;
    }

    /**
     * @inheritDoc
     */
    public function nextCursor()
    {
        return $this->nextCursor;
    }

    /**
     * @param array $rawItems
     */
    private function initCursors(array $rawItems): void
    {
        if (! $this->onLastPage() &&
            count($rawItems) > 0
        ) {
            $nextItem = $this->pointsToPrevoiusItems()
                ? array_shift($rawItems)
                : array_pop($rawItems);

            $this->nextCursor = new Cursor(
                array_combine($this->parameters, $nextItem['sort'])
            );
        }

        if (! $this->onFirstPage() &&
            count($rawItems) > 0
        ) {
            $previousItem = $this->pointsToPrevoiusItems()
                ? array_pop($rawItems)
                : array_shift($rawItems);

            $this->previousCursor = new Cursor(
                array_combine($this->parameters, $previousItem['sort']),
                false
            );
        }
    }

    /**
     * @return bool
     */
    private function pointsToPrevoiusItems(): bool
    {
        if (! $this->cursor) {
            return false;
        }

        return $this->cursor->pointsToPreviousItems();
    }
}
