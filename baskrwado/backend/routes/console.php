<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('baskrwado:about', function (): void {
    $this->info('BasKarwaDo platform API');
});
