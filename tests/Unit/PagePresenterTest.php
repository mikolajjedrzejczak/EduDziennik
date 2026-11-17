<?php

namespace Tests\Unit;

use App\Support\PagePresenter;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PagePresenterTest extends TestCase
{
    public function test_large_lists_have_a_bounded_payload_and_project_only_page_items(): void
    {
        $presenter = new PagePresenter;
        foreach ([10, 100000] as $lastPage) {
            $page = new LengthAwarePaginator([['id' => 1, 'secret' => 'excluded']], $lastPage * 10, 10, 5, ['path' => '/list']);
            $result = $presenter->present($page, fn (array $row) => ['id' => (string) $row['id']]);

            $this->assertSame([['id' => '1']], $result['data']);
            $this->assertSame(['currentPage' => 5, 'perPage' => 10, 'total' => $lastPage * 10, 'lastPage' => $lastPage], $result['meta']);
            $this->assertCount(2, $result['links']);
            $this->assertLessThan(500, strlen(json_encode($result, JSON_THROW_ON_ERROR)));
        }
    }

    public static function pageNames(): array
    {
        return array_map(fn ($name) => [$name], ['page', 'assessment_page', 'enrollment_page', 'history_page']);
    }

    #[DataProvider('pageNames')]
    public function test_urls_keep_filters_other_paginators_and_fragment(string $pageName): void
    {
        $page = new LengthAwarePaginator([], 1000000, 10, 50000, ['path' => '/list', 'pageName' => $pageName]);
        $query = ['q' => 'Żółć %_', 'term_id' => '7', 'page' => 3, 'assessment_page' => 8, 'enrollment_page' => 4, 'history_page' => 2];
        $page->appends($query)->fragment('results');
        $links = (new PagePresenter)->navigation($page)['links'];
        foreach ([49999, 50001] as $index => $target) {
            parse_str(parse_url($links[$index]['url'], PHP_URL_QUERY), $actual);
            $this->assertEquals(array_map('strval', [...$query, $pageName => $target]), $actual);
            $this->assertSame('results', parse_url($links[$index]['url'], PHP_URL_FRAGMENT));
        }
    }

    public function test_empty_first_and_last_pages_disable_unavailable_links(): void
    {
        foreach ([[0, 1, true, true], [30, 1, true, false], [30, 3, false, true]] as [$total, $current, $noPrevious, $noNext]) {
            $page = new LengthAwarePaginator([], $total, 10, $current, ['path' => '/list']);
            $links = (new PagePresenter)->navigation($page)['links'];
            $this->assertSame(['Poprzednia', 'Następna'], array_column($links, 'label'));
            $this->assertSame([false, false], array_column($links, 'active'));
            $this->assertSame($noPrevious, $links[0]['url'] === null);
            $this->assertSame($noNext, $links[1]['url'] === null);
        }
    }
}
