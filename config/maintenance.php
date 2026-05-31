<?php

return [
    'enabled' => env('DRAGONFORTUNE_MAINTENANCE', false),
    'fail_closed' => env('DRAGONFORTUNE_MAINTENANCE_FAIL_CLOSED', true),
    'cache_ttl' => env('DRAGONFORTUNE_MAINTENANCE_CACHE_TTL', 15),
    'retry_after' => env('DRAGONFORTUNE_MAINTENANCE_RETRY_AFTER', 3600),
];
