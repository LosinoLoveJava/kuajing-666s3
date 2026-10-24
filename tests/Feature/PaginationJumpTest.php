<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class PaginationJumpTest extends TestCase
{
    /** @dataProvider portalPaths */
    public function testJumpIsAvailableAcrossPortalsAndPreservesPaginatorMetadata($path)
    {
        $this->app->instance('request', Request::create($path));
        $this->app->setLocale('cn');
        $paginator = new LengthAwarePaginator(range(1, 10), 125, 10, 3, [
            'path' => $path,
            'pageName' => 'transactions_page',
        ]);
        $paginator->appends(['search' => 'test product', 'wallet_page' => 2])->fragment('transactions');

        $html = $paginator->links()->toHtml();

        $this->assertStringContainsString('data-pagination-jump-source', $html);
        $this->assertStringContainsString('data-current="3"', $html);
        $this->assertStringContainsString('data-last="13"', $html);
        $this->assertStringContainsString('data-page-name="transactions_page"', $html);
        $this->assertStringContainsString('search=test%20product', $html);
        $this->assertStringContainsString('wallet_page=2', $html);
        $this->assertStringContainsString('#transactions', $html);
        $this->assertStringContainsString('跳转', $html);
    }

    public function portalPaths()
    {
        return [
            ['/search'], ['/purchase_history'], ['/admin/products'],
            ['/seller/products'], ['/salesman/customers'],
        ];
    }

    public function testSinglePageDoesNotRenderPaginationOrJump()
    {
        $paginator = new LengthAwarePaginator(range(1, 5), 5, 10);

        $this->assertSame('', trim($paginator->links()->toHtml()));
    }
}
