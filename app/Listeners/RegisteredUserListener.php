<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\RegisteredUserNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class RegisteredUserListener implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $admins = User::where('is_admin', 1)->get();
        Notification::send($admins, new RegisteredUserNotification($event->user));
    }
}
