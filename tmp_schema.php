<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$schema = [
    'qc_method' => Schema::connection('methods')->getColumnListing('qc_method'),
    'qc_orders' => Schema::connection('methods')->getColumnListing('qc_orders') ?? [],
    'qc_signal' => Schema::connection('methods')->getColumnListing('qc_signal'),
    'qc_logs' => Schema::connection('methods')->getColumnListing('qc_logs') ?? [],
];

echo json_encode($schema, JSON_PRETTY_PRINT);
