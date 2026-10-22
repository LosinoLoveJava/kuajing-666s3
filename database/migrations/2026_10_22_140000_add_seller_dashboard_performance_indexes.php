<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSellerDashboardPerformanceIndexes extends Migration
{
    private $indexes = [
        'products' => [
            'seller_perf_products_user_category' => ['user_id', 'category_id'],
            'seller_perf_products_category' => ['category_id'],
        ],
        'product_translations' => ['seller_perf_product_translations' => ['product_id', 'lang']],
        'product_taxes' => ['seller_perf_product_taxes' => ['product_id']],
        'orders' => [
            'seller_perf_orders_payment_date' => ['seller_id', 'payment_status', 'created_at'],
            'seller_perf_orders_delivery_date' => ['seller_id', 'delivery_status', 'created_at'],
        ],
        'order_details' => [
            'seller_perf_order_details_status' => ['seller_id', 'delivery_status'],
            'seller_perf_order_details_order' => ['order_id'],
        ],
        'refund_requests' => ['seller_perf_refund_details' => ['order_detail_id', 'refund_status']],
        'notifications' => ['seller_perf_notifications_unread' => ['notifiable_type', 'notifiable_id', 'read_at', 'created_at']],
        'conversations' => [
            'seller_perf_conversations_sender' => ['sender_id', 'sender_viewed'],
            'seller_perf_conversations_receiver' => ['receiver_id', 'receiver_viewed'],
            'seller_perf_conversations_tip' => ['receiver_id', 'is_tip'],
        ],
        'messages' => ['seller_perf_messages_conversation' => ['conversation_id', 'user_id']],
    ];

    public function up()
    {
        foreach ($this->indexes as $table => $definitions) {
            $existing = $this->existingIndexes($table);
            foreach ($definitions as $name => $columns) {
                foreach ($existing as $indexedColumns) {
                    // A longer existing index with the same leading columns is sufficient.
                    if (array_slice($indexedColumns, 0, count($columns)) === $columns) { continue 2; }
                }
                Schema::table($table, function (Blueprint $blueprint) use ($name, $columns) {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    public function down()
    {
        foreach (array_reverse($this->indexes, true) as $table => $definitions) {
            $existing = $this->existingIndexes($table);
            foreach ($definitions as $name => $columns) {
                if (!isset($existing[$name])) { continue; }
                Schema::table($table, function (Blueprint $blueprint) use ($name) { $blueprint->dropIndex($name); });
            }
        }
    }

    private function existingIndexes($table)
    {
        $indexes = [];
        if (DB::connection()->getDriverName() === 'sqlite') {
            foreach (DB::select('PRAGMA index_list("' . $table . '")') as $index) {
                $indexes[$index->name] = array_map(function ($column) { return $column->name; },
                    DB::select('PRAGMA index_info("' . $index->name . '")'));
            }
        } else {
            foreach (DB::select('SHOW INDEX FROM `' . $table . '`') as $column) {
                $indexes[$column->Key_name][(int) $column->Seq_in_index - 1] = $column->Column_name;
            }
            foreach ($indexes as &$columns) { ksort($columns); }
        }
        return $indexes;
    }
}
