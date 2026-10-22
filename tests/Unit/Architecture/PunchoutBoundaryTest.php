<?php

/*
 * Punchout is a bounded context inside the Laravel monolith. These checks keep
 * its classes in the Punchout namespaces and stop storefront domains from
 * depending on punchout internals.
 */

arch('punchout actions live in the punchout namespace')
    ->expect('App\Actions\Punchout')
    ->toBeClasses();

arch('catalog services do not depend on punchout services')
    ->expect('App\Services\Catalog')
    ->not->toUse('App\Services\Punchout');

arch('storefront catalog controllers do not use the punchout session model')
    ->expect('App\Http\Controllers\V1\Catalog')
    ->not->toUse('App\Models\PunchoutSession');
