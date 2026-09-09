<?php

namespace App\Jobs;

use App\Services\PortalDataService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportUsers implements ShouldBeEncrypted, ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 60;

    public int $tries = 2;

    /**
     * @param  array<int, array<string, mixed>>  $users
     */
    public function __construct(public array $users) {}

    public function handle(PortalDataService $portal): void
    {
        $portal->importUsers($this->users);
    }
}
