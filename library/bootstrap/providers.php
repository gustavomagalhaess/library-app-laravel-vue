<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    DomainServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,
    TelescopeServiceProvider::class,
];
