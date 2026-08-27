<?php

namespace App\Console\Commands\Commercial;

use App\Services\Commercial\CommercialEmailDispatchService;
use Illuminate\Console\Command;

class DispatchDueCommercialSequenceEmails extends Command
{
    protected $signature = 'commercial:emails:dispatch-due';

    protected $description = 'Enfileira os próximos e-mails de sequências comerciais que estão prontos para envio, respeitando limites e janela de envio.';

    public function __construct(protected CommercialEmailDispatchService $dispatchService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->dispatchService->dispatchDue();

        $this->info('Dispatch de e-mails comerciais: '.json_encode($result));

        return self::SUCCESS;
    }
}
