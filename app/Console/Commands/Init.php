<?php

namespace App\Console\Commands;

use App\Models\User;
use Helper\Cache\RoleCacheHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class Init extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:init';
    protected $phpArtisan = 'php artisan ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'It will initialize the project for the first time';

    /**
     * Execute the console command.
     */
    public function handle() {
        // On a new database the users table doesn't exist yet, so the
        // pre-wipe revoke call would crash with a relation-does-not-exist
        // error from postgres. There can't be any user cache to revoke on a
        // first init anyway — skip the step until the table is in place.
        if (Schema::hasTable(User::getTableName())) {
            RoleCacheHandler::revokeUserCache(User::all());
        }

        passthru('composer dump-autoload');
        $this->artisan('migrate:fresh');
        $this->artisan('db:seed');
        $this->artisan('passport:keys --force');
        $this->registerPassportPersonalClients();
        $this->artisan('optimize:clear');

        $this->info('INITIATED');

        $users = User::all();
        RoleCacheHandler::updateUserCache($users);
    }

    /**
     * Runs an artisan command
     *
     * @param string $command
     * @return void
     */
    protected function artisan($command): void {
        passthru($this->phpArtisan . $command);
    }

    /**
     * Loads and registers each auth provider
     *
     * @return void
     */
    protected function registerPassportPersonalClients(): void {
        $authProviders = array_keys(config('auth')['providers']);
        foreach ($authProviders as $provider) {
            $this->artisan('passport:client --personal --name=' . $provider . ' --provider=' . $provider);
        }
    }
}
