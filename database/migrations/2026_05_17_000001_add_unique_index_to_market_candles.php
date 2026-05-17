<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $indexName = 'market_candles_unique_market_time';

    public function up(): void
    {
        if (!Schema::hasTable('market_candles')) {
            return;
        }

        DB::statement(<<<SQL
            DELETE mc1 FROM market_candles mc1
            INNER JOIN market_candles mc2
                ON mc1.exchange = mc2.exchange
                AND mc1.type = mc2.type
                AND mc1.symbol = mc2.symbol
                AND mc1.timeframe = mc2.timeframe
                AND mc1.timestamp = mc2.timestamp
                AND mc1.id > mc2.id
        SQL);

        if ($this->equivalentUniqueIndexExists()) {
            return;
        }

        Schema::table('market_candles', function (Blueprint $table) {
            $table->unique(
                ['exchange', 'type', 'symbol', 'timeframe', 'timestamp'],
                $this->indexName
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('market_candles') || !$this->namedIndexExists($this->indexName)) {
            return;
        }

        Schema::table('market_candles', function (Blueprint $table) {
            $table->dropUnique($this->indexName);
        });
    }

    private function equivalentUniqueIndexExists(): bool
    {
        $database = DB::getDatabaseName();

        $indexes = DB::table('information_schema.statistics')
            ->selectRaw('index_name as index_name, GROUP_CONCAT(column_name ORDER BY seq_in_index) as columns')
            ->where('table_schema', $database)
            ->where('table_name', 'market_candles')
            ->where('non_unique', 0)
            ->groupBy('index_name')
            ->pluck('columns', 'index_name');

        return $indexes->contains('exchange,type,symbol,timeframe,timestamp');
    }

    private function namedIndexExists(string $indexName): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'market_candles')
            ->where('index_name', $indexName)
            ->exists();
    }
};
