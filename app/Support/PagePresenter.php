<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Bounded transport projection; URLs use the paginator's configured pageName and query. */
class PagePresenter
{
    /**
     * @template T
     *
     * @param  LengthAwarePaginator<int, T>  $page
     * @param  callable(T): array<string, mixed>  $present
     * @return array<string, mixed>
     */
    public function present(LengthAwarePaginator $page, callable $present): array
    {
        return [
            'data' => array_map($present, $page->items()),
            ...$this->navigation($page),
        ];
    }

    /**
     * @template T
     *
     * @param  LengthAwarePaginator<int, T>  $page
     * @return array<string, mixed>
     */
    public function navigation(LengthAwarePaginator $page): array
    {
        return [
            'meta' => ['currentPage' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'links' => [
                ['label' => 'Poprzednia', 'url' => $page->previousPageUrl(), 'active' => false],
                ['label' => 'Następna', 'url' => $page->nextPageUrl(), 'active' => false],
            ],
        ];
    }
}
