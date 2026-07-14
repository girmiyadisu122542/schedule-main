<?php

namespace App\Console\Commands;

use App\Models\Permission\Permission;
use Helper\Permission\PermissionActionHelper;
use Illuminate\Console\Command;

class ListPermissionsCommand extends Command {
    /**
     * The command signature.
     */
    protected $signature = 'permissions
        {filter? : Filter by permission key (e.g. role:see)}
        {--front : Show only frontend action names (can...)}
        {--f : Shortcut for --front}
        {--vue : Output as TypeScript enum for Vue frontend}
        {--v : Shortcut for --vue}
        {--encrypt : Output encrypted action names}
        {--e : Shortcut for --encrypt}
        {--db : Output raw permission keys from database}
        {--d : Shortcut for --db}
        {--back : Output backend usage examples (useCanUpdateGroupPermission)}
        {--b : Shortcut for --back}';

    /**
     * Description for Artisan help.
     */
    protected $description = "List permissions from the database, optionally filtered by key and output mode.\n\n"
        . "Examples:\n"
        . "  php artisan permissions\n"
        . "  php artisan permissions role --vue\n"
        . "  php artisan permissions --encrypt\n";

    /**
     * Execute the command.
     */
    public function handle() {
        $filter = $this->argument('filter');
        $frontOnly = $this->option('front') || $this->option('f');
        $vueEnum = $this->option('vue') || $this->option('v');
        $encrypt = $this->option('encrypt') || $this->option('e');
        $dbRaw = $this->option('db') || $this->option('d');
        $backUsage = $this->option('back') || $this->option('b');

        $query = Permission::query();

        if ($filter) {
            $query->where('key', 'like', "%$filter%");
        }

        $permissions = $query->pluck('key')->toArray();

        if (empty($permissions)) {
            $this->error('No permissions found for the given filters.');
            return 1;
        }

        $actions = PermissionActionHelper::convertPermissionsToActions($permissions);

        if ($dbRaw) {
            foreach ($permissions as $perm) {
                $this->line($perm);
            }
        } else if ($vueEnum) {
            $this->line('export type AllowedAction =');
            foreach ($actions as $i => $action) {
                $separator = ($i === count($actions) - 1) ? ';' : '';
                $this->line("  | '$action'$separator");
            }
        } else if ($encrypt) {
            $key = env('PERMISSION_ACTION_KEY', 'your-plain-string-key');
            $encryptedActions = PermissionActionHelper::convertAndEncryptPermissions($permissions, $key);
            foreach ($encryptedActions as $encrypted) {
                $this->line($encrypted);
            }
        } else if ($backUsage) {
            $examples = PermissionActionHelper::convertPermissionsToBackendUsage($permissions);
            foreach ($examples as $example) {
                $this->line($example);
            }
        } else if ($frontOnly) {
            foreach ($actions as $action) {
                $this->line($action);
            }
        } else {
            foreach ($permissions as $i => $perm) {
                $this->line($perm . ' => ' . ($actions[$i] ?? ''));
            }
        }

        return 0;
    }
}
