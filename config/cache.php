<?php

return ['default' => env('CACHE_STORE', 'file'), 'stores' => ['file' => ['driver' => 'file', 'path' => storage_path('framework/cache/data')], 'array' => ['driver' => 'array', 'serialize' => false], 'mongodb' => ['driver' => 'mongodb', 'connection' => 'mongodb', 'collection' => 'cache', 'lock_collection' => 'cache_locks']], 'prefix' => 'nabaku_'];
