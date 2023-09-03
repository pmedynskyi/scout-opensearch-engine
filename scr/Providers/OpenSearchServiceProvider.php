<?php
declare(strict_types=1);

namespace Pmedynskyi\ScoutOpensearchEngine\Providers;

use Pmedynskyi\ScoutOpenSearchEngine\Engines\OpenSearchEngine;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\Builder;
use Laravel\Scout\EngineManager;
use OpenSearchDSL\Sort\FieldSort;
use OpenSearch\Client;
use OpenSearch\ClientBuilder;

class OpenSearchServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../opensearch.php', 'opensearch');
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../opensearch.php' => config_path('opensearch.php'),
        ], 'opensearch-config');

        $this->app->make(EngineManager::class)->extend(OpenSearchEngine::class, function () {
            $opensearch = app(Client::class);

            return new OpenSearchEngine($opensearch);
        });
        $this->app->bind(Client::class, function () {
            return ClientBuilder::fromConfig(config('opensearch.client'));
        });

        Builder::macro('cursorPaginate', function (int $perPage = null, string $cursorName = 'cursor', $cursor = null): CursorPaginator {
            /**
             * @var Builder $this
             */
            $perPage = $perPage ?: $this->model->getPerPage();

            return $this->engine()->cursorPaginate($this, $perPage, $cursorName, $cursor);
        });

        Builder::macro('orderByRaw', function (FieldSort $sort) {
            /**
             * @var Builder $this
             */
            $this->orders[] = $sort;

            return $this;
        });
    }
}
