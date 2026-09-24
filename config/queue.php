<?php

// Durable mail uses the MongoDB outbox, processed by fundraiser:outbox.
return ['default' => 'sync', 'connections' => ['sync' => ['driver' => 'sync']], 'failed' => ['driver' => 'null']];
