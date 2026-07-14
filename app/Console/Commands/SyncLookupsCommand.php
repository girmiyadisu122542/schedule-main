<?php

namespace App\Console\Commands;

use Database\Seeders\Lookup\LookupSeeder;
use Illuminate\Console\Command;

class SyncLookupsCommand extends Command {
    protected $signature = 'lookups:sync 
                            {--list : List all defined lookups without syncing}';

    protected $description = 'Sync defined lookups to the database';

    public function handle(): int {
        if ($this->option('list')) {
            return $this->listLookups();
        }

        $this->info('Syncing all defined lookups...');

        $seeder = new LookupSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        $this->newLine();
        $this->info('Lookups synced successfully.');

        return self::SUCCESS;
    }

    private function listLookups(): int {
        $allLookups = $this->loadLookups(config_path('lookups.php'));

        if (empty($allLookups)) {
            $this->warn('No lookups defined.');
            return self::SUCCESS;
        }

        $this->info('Defined Lookups:');
        $this->newLine();

        $grouped = [];
        foreach ($allLookups as $lookup) {
            $models = $lookup['applies_to_model'] ?? ['Unknown'];
            if (!is_array($models)) {
                $models = [$models];
            }

            foreach ($models as $model) {
                if (!isset($grouped[$model])) {
                    $grouped[$model] = [];
                }
                $grouped[$model][] = $lookup;
            }
        }

        foreach ($grouped as $model => $lookups) {
            $this->line("Model: <fg=cyan>{$model}</>");

            foreach ($lookups as $lookup) {
                $name = is_array($lookup['name']) ? ($lookup['name']['en'] ?? reset($lookup['name'])) : $lookup['name'];
                $this->line("  └─ {$name} ({$lookup['code']})");

                if (isset($lookup['values'])) {
                    $this->line("     Values: " . count($lookup['values']));
                }

                if (isset($lookup['transitions'])) {
                    $this->line("     Transitions: " . count($lookup['transitions']));
                }
            }

            $this->newLine();
        }

        $this->info("Total: " . count($allLookups) . " lookup types");

        return self::SUCCESS;
    }

    private function loadLookups(string $filePath): array {
        if (!file_exists($filePath)) {
            return [];
        }

        $lookups = require $filePath;

        return is_array($lookups) ? $lookups : [];
    }
}
