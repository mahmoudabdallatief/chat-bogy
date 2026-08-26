<?php

namespace Database\Seeders;

use App\Services\SettingsService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        $settingsService = app(SettingsService::class);
        $settingsService->seedDefaults();
    }
}
