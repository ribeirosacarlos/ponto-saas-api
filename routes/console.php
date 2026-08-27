<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('swagger:generate-safe', function () {
    // app/Swagger/*.php tem várias classes por arquivo (não segue PSR-4), então
    // class_exists() falha no autoload pro ReflectionAnalyser do swagger-php e
    // ele loga um warning "Skipping unknown ..." e segue (comportamento esperado).
    // Em produção o handler de erro do Laravel converte esse warning em exceção
    // fatal e aborta o comando inteiro. Suprime só E_USER_WARNING aqui, só durante
    // a geração, pra esse warning ser ignorado como o swagger-php já espera.
    $previousLevel = error_reporting();
    error_reporting($previousLevel & ~E_USER_WARNING);

    try {
        $this->call('l5-swagger:generate');
    } finally {
        error_reporting($previousLevel);
    }
})->purpose('Gera a doc Swagger sem deixar os warnings inofensivos de classes não-PSR4 derrubarem o comando');

Schedule::command('subscriptions:sync-expired-access')->everyFiveMinutes();

Schedule::command('commercial:emails:dispatch-due')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onOneServer();
