<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Cloud Run terminates TLS and forwards HTTP with X-Forwarded-Proto.
 * Vite/@vite asset URLs must follow the forwarded scheme (https), not the
 * container's plain HTTP connection.
 */
it('treats X-Forwarded-Proto https as a secure request', function () {
    $response = $this
        ->withServerVariables([
            'HTTPS' => 'off',
            'SERVER_PORT' => '80',
            'REMOTE_ADDR' => '10.0.0.1',
        ])
        ->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-For' => '203.0.113.10',
        ])
        ->get('/');

    $response->assertOk();

    $html = $response->getContent();

    expect($html)
        ->toMatch('/(?:href|src)="https:\/\/[^"]*\/build\/assets\/[^"]+\.css"/')
        ->toMatch('/(?:href|src)="https:\/\/[^"]*\/build\/assets\/[^"]+\.js"/')
        ->not->toMatch('/(?:href|src)="http:\/\/[^"]*\/build\/assets\//');
});

it('keeps http asset URLs when the request is not behind a trusted https proxy', function () {
    $response = $this
        ->withServerVariables([
            'HTTPS' => 'off',
            'SERVER_PORT' => '80',
        ])
        ->get('/');

    $response->assertOk();

    $html = $response->getContent();

    expect($html)->toMatch('/(?:href|src)="http:\/\/[^"]*\/build\/assets\//');
});
