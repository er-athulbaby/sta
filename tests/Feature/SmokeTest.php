<?php

it('serves both panel login pages', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/admin/login', '/portal/login']);

it('uses a 30 minute session', function () {
    expect(config('session.lifetime'))->toBe(30);
});
