<?php
declare(strict_types=1);

namespace Pmedynskyi\ScoutOpensearchEngine;

use Illuminate\Pagination\Cursor;
use Laravel\Scout\Builder;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\QueryStringQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Query\TermLevel\TermsQuery;
use OpenSearchDSL\Search;
use OpenSearchDSL\Sort\FieldSort;

final class SearchFactory
{
    /**
     * @param Builder $builder
     * @param array $options
     * @param Cursor|null $cursor
     *
     * @return Search
     */
    public static function create(Builder $builder, array $options = [], Cursor $cursor = null): Search
    {
        $search = new Search();

        $query = $builder->query ? new QueryStringQuery($builder->query) : null;

        if (self::hasWhereFilters($builder)) {
            $boolQuery = new BoolQuery();
            $boolQuery = self::addWheres($builder, $boolQuery);
            $boolQuery = self::addWhereIns($builder, $boolQuery);

            if ($query) {
                $boolQuery->add($query);
            }
            $search->addQuery($boolQuery);
        } elseif($query) {
            $search->addQuery($query);
        }

        if (array_key_exists('from', $options)) {
            $search->setFrom($options['from']);
        }

        if (array_key_exists('size', $options)) {
            $search->setSize($options['size']);
        }

        if (array_key_exists('searchAfter', $options)) {
            $search->setSearchAfter($options['searchAfter']);
        }

        if (! empty($builder->orders)) {
            $search = self::addOrders($builder, $cursor, $search);
        }

        return $search;
    }

    /**
     * @param Builder $builder
     *
     * @return bool
     */
    private static function hasWhereFilters(Builder $builder): bool
    {
        return self::hasWheres($builder) || self::hasWhereIns($builder);
    }

    /**
     * @param Builder $builder
     *
     * @return bool
     */
    private static function hasWheres(Builder $builder): bool
    {
        return ! empty($builder->wheres);
    }

    /**
     * @param Builder $builder
     *
     * @return bool
     */
    private static function hasWhereIns(Builder $builder): bool
    {
        return ! empty($builder->whereIns);
    }

    /**
     * @param Builder $builder
     * @param BoolQuery $boolQuery
     *
     * @return BoolQuery
     */
    private static function addWheres(Builder $builder, BoolQuery $boolQuery): BoolQuery
    {
        if (self::hasWheres($builder)) {
            foreach ($builder->wheres as $field => $value) {
                $boolQuery->add(new TermQuery((string) $field, $value), BoolQuery::FILTER);
            }
        }

        return $boolQuery;
    }

    /**
     * @param $builder
     * @param $boolQuery
     *
     * @return BoolQuery
     */
    private static function addWhereIns($builder, $boolQuery): BoolQuery
    {
        if (self::hasWhereIns($builder)) {
            foreach ($builder->whereIns as $field => $arrayOfValues) {
                $boolQuery->add(new TermsQuery((string) $field, $arrayOfValues), BoolQuery::FILTER);
            }
        }

        return $boolQuery;
    }

    /**
     * @param Builder $builder
     * @param Cursor|null $cursor
     * @param Search $search
     *
     * @return Search
     */
    private static function addOrders(Builder $builder, ?Cursor $cursor, Search $search): Search
    {
        $sorts = array_map(
            function ($order) use ($cursor) {
                $sort = is_array($order)
                    ? new FieldSort($order['column'], $order['direction'])
                    : $order;

                $direction = $sort->getOrder() ?? 'asc';

                if ($cursor && $cursor->pointsToPreviousItems()) {
                    $direction = $direction === 'asc' ? 'desc' : 'asc';
                    $sort->setOrder($direction);
                }

                return $sort;
            },
            $builder->orders
        );

        foreach ($sorts as $sort) {
            $search->addSort($sort);
        }

        return $search;
    }
}
