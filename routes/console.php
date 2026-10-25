<?php

use App\Services\SubstituteRequestService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('requests:expire', function (SubstituteRequestService $service) {
    $count = $service->expireStarted();
    $this->info("不成立にした代講依頼：{$count} 件");
})->purpose('授業が始まっても代講者が決まらなかった依頼を「不成立」にする');

Schedule::command('requests:expire')->everyFiveMinutes()->withoutOverlapping();
