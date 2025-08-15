<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config(['app.timezone' => 'Asia/Damascus']);
        date_default_timezone_set('Asia/Damascus');
        Relation::morphMap([
            'Post' => \App\Models\Post::class,
            'Ad' => \App\Models\Ad::class,
            'Comment' => \App\Models\Comment::class,
        ]);
    }
}
