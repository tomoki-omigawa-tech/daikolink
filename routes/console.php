<?php

use App\Services\SubstituteNoticeService;
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

Artisan::command('requests:remind', function (SubstituteNoticeService $service) {
    $count = $service->remindTomorrow();
    $this->info("リマインドを送った代講：{$count} 件");
})->purpose('明日の代講が確定している先生に、前日の夜にリマインドを送る');

Artisan::command('requests:notify-unfilled', function (SubstituteNoticeService $service) {
    $count = $service->notifyUnfilledToday();
    $this->info("至急の連絡をした代講依頼：{$count} 件");
})->purpose('当日の正午に未確定の依頼を、授業のない先生とスタッフへ一斉に知らせる');

Schedule::command('requests:remind')->dailyAt('20:00')->withoutOverlapping();
Schedule::command('requests:notify-unfilled')->dailyAt('12:00')->withoutOverlapping();
