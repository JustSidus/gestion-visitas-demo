<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

//  Limpieza automática de cache expirado (diaria a las 2:00 AM)
Schedule::command('cache:cleanup-expired')
    ->daily()
    ->at('02:00')
    ->onSuccess(function () {
        info(' Limpieza automática de cache completada');
    })
    ->onFailure(function () {
        info(' Error en limpieza automática de cache');
    });

//  Limpieza automática de failed_jobs antiguos (diaria a las 3:00 AM, >7 días)
Schedule::command('jobs:cleanup-old', ['--days' => 7])
    ->daily()
    ->at('03:00')
    ->onSuccess(function () {
        info(' Limpieza automática de failed_jobs completada');
    })
    ->onFailure(function () {
        info(' Error en limpieza automática de failed_jobs');
    });

//  Limpieza automática de audit_logs antiguos (semanal los domingos a las 4:00 AM, >90 días)
Schedule::command('audit-logs:cleanup-old', ['--days' => 90])
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->onSuccess(function () {
        info(' Limpieza automática de audit_logs completada');
    })
    ->onFailure(function () {
        info(' Error en limpieza automática de audit_logs');
    });

