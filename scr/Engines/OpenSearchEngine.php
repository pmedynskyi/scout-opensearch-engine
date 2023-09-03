<?php
declare(strict_types=1);

namespace Pmedynskyi\OpenSearch\Engines;

use Pmedynskyi\OpenSearch\Paginator\ScrollPaginator;
use Pmedynskyi\OpenSearch\SearchFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use OpenSearchDSL\Query\MatchAllQuery;
use OpenSearchDSL\Sort\FieldSort;
use OpenSearch\Client;

class OpenSearchEngine extends Engine
{
    /**
     * @param Client $client
     */
    public function __construct(public Client $client)
    {
    }

    /**
     * @inheritDoc
     */
    public function update($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $payload = $models->reduce(function ($payload, $model) {
            if (empty($searchableData = $model->toSearchableArray())) {
                return $payload;
            }

            $payload[] = [
                'index' => [
                    '_index' => $model->searchableAs(),
                    '_id' => $model->getScoutKey(),
                    ...$model->scoutMetadata()
                ]
            ];

            $payload[] = $searchableData;

            return $payload;
        }, []);

        $this->client->bulk(['body' => $payload]);
    }

    /**
     * @param Builder $builder
     * @param $results
     * @param Model $model
     *
     * @return \Illuminate\Database\Eloquent\Collection|mixed
     */
    public function map(Builder $builder, $results, $model)
    {
        if ($results === null) {
            return $model->newCollection();
        }

        if (!isset($results['hits'])) {
            return $model->newCollection();
        }

        if ($results['hits'] === []) {
            return $model->newCollection();
        }

        $objectIds = $this->mapIds($results)->toArray();

        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds($builder, $objectIds)
            ->filter(function ($model) use ($objectIds): bool {
                return in_array($model->getScoutKey(), $objectIds, false);
            })->sortBy(function ($model) use ($objectIdPositions): int {
                return $objectIdPositions[$model->getScoutKey()];
            })->values();
    }

    /**
     * @param array{items: mixed[]|null}|null  $results
     */
    public function mapIds($results): Collection
    {
        if ($results === null) {
            return collect();
        }

        return collect($results['hits']['hits'])->pluck('_id');
    }

    /**
     * @return mixed
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return mixed
     */
    protected function performSearch(
        Builder $builder,
        array   $options = [],
        Cursor  $cursor = null
    )
    {
        $searchBody = SearchFactory::create($builder, $options, $cursor);
        if ($builder->callback) {
            /** @var callable */
            $callback = $builder->callback;

            return call_user_func(
                $callback,
                $this->client,
                $searchBody
            );
        }

        $model = $builder->model;
        $indexName = $builder->index ?: $model->searchableAs();
        return $this->client->search(['index' => $indexName, 'body' => $searchBody->toArray()]);
    }

    /**
     * @param mixed $perPage
     * @param mixed $page
     *
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, array_filter([
            'from' => $perPage * ($page - 1),
            'size' => $perPage,
        ]));
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param Builder $builder
     * @param $results
     * @param Model $model
     *
     * @return mixed
     */
    public function lazyMap(Builder $builder, $results, $model): mixed
    {
        if ($results === null) {
            return LazyCollection::make($model->newCollection());
        }

        if (!isset($results['hits'])) {
            return LazyCollection::make($model->newCollection());
        }

        if ($results['hits'] === []) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = $this->mapIds($results)->toArray();

        $objectIdPositions = array_flip($objectIds);

        return $model->queryScoutModelsByIds($builder, $objectIds)
            ->cursor()
            ->filter(function ($model) use ($objectIds): bool {
                return in_array($model->getScoutKey(), $objectIds, false);
            })->sortBy(function ($model) use ($objectIdPositions): int {
                return $objectIdPositions[$model->getScoutKey()];
            })->values();
    }

    /**
     * @param array<string, mixed>|null $results
     *
     * @return mixed
     */
    public function getTotalCount($results): mixed
    {
        return $results['hits']['total']['value'] ?? 0;
    }

    /**
     * @param Model $model
     */
    public function flush($model): void
    {
        $this->client->deleteByQuery([
            'index' => $model->searchableAs(),
            'body' => [
                'query' => (new MatchAllQuery())->toArray()
            ]
        ]);
    }

    /**
     * Create a search index.
     *
     * @param string $name
     * @param array<string, mixed> $options
     */
    public function createIndex($name, array $options = []): array
    {
        $body = array_replace_recursive(
            Config::get('client.indices.default') ?? [],
            Config::get('client.indices.' . $name) ?? []);

        return $this->client->indices()->create(['index' => $name, 'body' => $body]);
    }

    /**
     * Delete a search index.
     *
     * @param string $name
     */
    public function deleteIndex($name): array
    {
        return $this->client->indices()->delete(['index' => $name]);
    }

    /**
     * @inheritDoc
     */
    public function delete($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $payload = $models->reduce(function ($payload, $model) {
            $payload[] = [
                'delete' => [
                    '_index' => $model->searchableAs(),
                    '_id' => $model->getScoutKey()
                ]
            ];

            return $payload;
        }, []);

        $this->client->bulk(['body' => $payload]);
    }

    /**
     * @see https://client.org/docs/latest/client/search/paginate/#the-search_after-parameter
     *
     * @param Builder $builder
     * @param integer $perPage
     * @param string $cursorName
     * @param Cursor|string|null $cursor
     *
     * @return CursorPaginator
     */
    public function cursorPaginate(
        Builder $builder,
        int     $perPage,
        string  $cursorName = 'cursor',
                $cursor = null
    ): CursorPaginator
    {
        if (empty($builder->orders)) {
            $builder->orderBy("_id");
        }

        $cursor = $this->resolveCursor($cursor, $cursorName);
        $cols = array_map([$this, 'orderCol'], $builder->orders);

        $response = $this->performCursorSearch($builder, $perPage, $cols, $cursor);

        $items = $builder->model->newCollection(
            $this->map($builder, $response, $builder->model)->all()
        );

        $options = [
            'path' => Paginator::resolveCurrentPath(),
            'cursorName' => $cursorName,
            'parameters' => $cols
        ];

        return new ScrollPaginator($items, $perPage, $response, $cursor, $options);
    }

    /**
     * @param $cursor
     * @param $cursorName
     *
     * @return Cursor|null
     */
    private function resolveCursor($cursor, $cursorName): ?Cursor
    {
        if ($cursor instanceof Cursor) {
            return $cursor;
        }

        return is_string($cursor)
            ? Cursor::fromEncoded($cursor)
            : CursorPaginator::resolveCurrentCursor($cursorName, $cursor);
    }

    /**
     * @param array|FieldSort $order
     *
     * @return string
     */
    private function orderCol(array|FieldSort $order): string
    {
        return is_array($order) ? $order['column'] : $order->getField();
    }

    /**
     * @param Builder $builder
     * @param int $perPage
     * @param array $cols
     * @param $cursor
     *
     * @return mixed
     */
    private function performCursorSearch(
        Builder $builder,
        int     $perPage,
        array   $cols,
                $cursor
    )
    {
        $searchAfter = $cursor?->parameters($cols);

        return $this->performSearch(
            $builder,
            array_filter([
                'size' => $perPage + 1,
                'searchAfter' => $searchAfter
            ]),
            $cursor
        );
    }
}
