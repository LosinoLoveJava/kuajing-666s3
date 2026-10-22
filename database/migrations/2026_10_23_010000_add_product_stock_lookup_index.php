<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddProductStockLookupIndex extends Migration
{
    public function up()
    {
        if ($this->hasProductIndex()) { return; }
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->index('product_id', 'product_stocks_product_lookup');
        });
    }

    public function down()
    {
        // This supporting index is also required by search and product pages.
        // Retain it on code rollback to avoid exhausting the PHP worker pool.
    }

    private function hasProductIndex()
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            foreach (DB::select('PRAGMA index_list("product_stocks")') as $index) {
                $columns = DB::select('PRAGMA index_info("'.str_replace('"', '""', $index->name).'")');
                if ($columns && $columns[0]->name === 'product_id') { return true; }
            }
        } else {
            foreach (DB::select('SHOW INDEX FROM `product_stocks`') as $index) {
                if ((int)$index->Seq_in_index === 1 && $index->Column_name === 'product_id') { return true; }
            }
        }
        return false;
    }
}
