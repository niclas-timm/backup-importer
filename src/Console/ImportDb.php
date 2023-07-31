<?php

namespace NiclasTimm\LaravelDbImporter\Console;

use Illuminate\Console\Command;
use NiclasTimm\LaravelDbImporter\Importer;

/**
 *
 */
class ImportDb extends Command
{
    /**
     * The signature.
     *
     * @var string
     */
    protected $signature = 'dbimporter:import';

    /**
     * The command description.
     *
     * @var string
     */
    protected $description = 'Install backup from S3';

    public function __construct(protected Importer $importer)
    {
        parent::__construct();
    }

    /**
     * Handle the command.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function handle(): void
    {
        $this->info('Starting the magic 🧙');

        $this->importer->handle();

        $this->info('Tada 🎉');
    }

}