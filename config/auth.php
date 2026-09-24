<?php

use App\Models\Organization;

return ['defaults' => ['guard' => 'api'], 'guards' => ['api' => ['driver' => 'session', 'provider' => 'organizations']], 'providers' => ['organizations' => ['driver' => 'eloquent', 'model' => Organization::class]]];
