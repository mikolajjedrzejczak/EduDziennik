<?php

namespace App\Services\Grades;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Shared transport projection for the teacher's paginated read views. */
class ReadPage
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
        $links = [['label' => 'Poprzednia', 'url' => $page->previousPageUrl(), 'active' => false]];
        // A bounded window avoids a huge payload on large lists.
        for ($i = max(1, $page->currentPage() - 2); $i <= min($page->lastPage(), $page->currentPage() + 2); $i++) {
            $links[] = ['label' => (string) $i, 'url' => $page->url($i), 'active' => $i === $page->currentPage()];
        }
        $links[] = ['label' => 'Następna', 'url' => $page->nextPageUrl(), 'active' => false];

        return [
            'data' => array_map($present, $page->items()),
            'meta' => ['currentPage' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
            'links' => $links,
        ];
    }
}
