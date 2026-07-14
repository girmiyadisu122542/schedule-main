# Helper Commands

## Initialize

Initalize the project for a new setup after configuring your db connections.

```bash
php artisan app:init
```

---

A collection of useful commands for development and maintenance tasks.

---

## Database

**Refresh the database and run all migrations:**

```bash
php artisan migrate:fresh --seed \
    && php artisan passport:keys --force \
    && php artisan passport:client --personal --name=users
```

---

**Seed new permissions:**

```bash
php artisan db:seed --class=Database\\Seeders\\Permission\\PermissionSeeder
```

## Git

**Fetch latest changes from remote and prune deleted branches:**

```bash
git fetch --prune
```

**Reset to the `dev` branch:**

```bash
git reset --hard origin/dev
```

---

## Utilities

**Generate a new 32-character base64 string for encryption key (PowerShell):**

```powershell
-join ((48..57) + (65..90) + (97..122) | Get-Random -Count 32 | % {[char]$_})
```

# Generate messages from controller

php artisan message modules/Student/app/Http/Controllers/Student/StudentEmergencyContactController.php

# Generate attributes from request

php artisan attribute modules/Teacher/app/Http/Requests/Job/JobPostRequest.php

````

---

### module:list

Display module information with various filtering and display options.

**Syntax:**

```bash
php artisan module:list [options]
````

**Options:**

-   `--enabled` - Show only enabled modules
-   `--disabled` - Show only disabled modules
-   `--stats` - Show module statistics and load order
-   `--deps` - Show dependency information

**Examples:**

````bash
# Show all modules
php artisan module:list

# Show only enabled modules
php artisan module:list --enabled

# Show statistics and load order
php artisan module:list --stats

# Show dependency information
php artisan module:list --deps


### module:toggle
Enable or disable modules with dependency validation.

**Syntax:**
```bash
php artisan module:toggle {module} {action} [options]
````

**Parameters:**

-   `module` - Module name
-   `action` - Either `enable` or `disable`

**Options:**

-   `--force` - Bypass dependency checks

**Examples:**

```bash
# Enable a module
php artisan module:toggle Student enable

# Disable a module (with dependency checking)
php artisan module:toggle Teacher disable

# Force disable (bypass dependency validation)
php artisan module:toggle Student disable --force
```

### module:frontend

Manage and view module frontend configurations.

**Syntax:**

```bash
php artisan module:frontend {action} [module] [options]
```

**Actions:**

-   `routes` - Show frontend routes
-   `sidebar` - Show sidebar menu configuration
-   `paths` - Show frontend path constants
-   `clear-cache` - Clear frontend configuration cache

**Options:**

-   `--refresh` - Refresh frontend cache
-   `--validate` - Validate frontend configurations
-   `--show-merged` - Show merged configurations (core + modules)

**Examples:**

**Route Management:**

```bash
# Show routes for specific module
php artisan module:frontend routes Student

# Show merged routes (core + all modules)
php artisan module:frontend routes --show-merged

# Validate route configurations
php artisan module:frontend routes --validate
```

**Sidebar Management:**

```bash
# Show sidebar for specific module
php artisan module:frontend sidebar Student

# Show merged sidebar
php artisan module:frontend sidebar --show-merged
```

**Path Management:**

```bash
# Show frontend paths for specific module
php artisan module:frontend paths Student

# Show all module paths
php artisan module:frontend paths --show-merged
```

## Migration Management Commands

### module:migrate

Run migrations for a specific module.

**Syntax:**

```bash
php artisan module:migrate {module} [options]
```

**Parameters:**

-   `module` - Module name

**Options:**

-   `--force` - Skip confirmation prompt
-   `--dry-run` - Preview what would be executed

**Examples:**

```bash
# Run migrations with confirmation
php artisan module:migrate Student

# Run migrations without confirmation
php artisan module:migrate Student --force

# Preview migrations
php artisan module:migrate Student --dry-run
```

### module:migrate:all

Run migrations for all enabled modules in dependency order.

**Syntax:**

```bash
php artisan module:migrate:all [options]
```

**Options:**

-   `--force` - Skip confirmation prompt
-   `--dry-run` - Preview what would be executed

**Examples:**

```bash
# Run all module migrations
php artisan module:migrate:all --force

# Preview all pending migrations
php artisan module:migrate:all --dry-run
```

### module:migrate:rollback

Rollback migrations for a specific module.

**Syntax:**

```bash
php artisan module:migrate:rollback {module} [options]
```

**Parameters:**

-   `module` - Module name

**Options:**

-   `--step=N` - Number of migrations to rollback (default: 1)
-   `--force` - Skip confirmation prompt
-   `--dry-run` - Preview what would be rolled back

**Examples:**

```bash
# Rollback last migration
php artisan module:migrate:rollback Student

# Rollback last 3 migrations
php artisan module:migrate:rollback Student --step=3

# Preview rollback
php artisan module:migrate:rollback Student --step=2 --dry-run
```

### module:migrate:rollback-batch

**NEW!** Rollback migrations for a specific module by batch number. - when multiple migrations were run together and you want to rollback them as a group.

**Syntax:**

```bash
php artisan module:migrate:rollback-batch {module} [options]
```

**Parameters:**

-   `module` - Module name

**Options:**

-   `--batch=N` - Specific batch number to rollback
-   `--last-batch` - Rollback the most recent batch
-   `--force` - Skip confirmation prompt
-   `--dry-run` - Preview what would be rolled back

**Examples:**

```bash
# Rollback the last batch
php artisan module:migrate:rollback-batch Student --last-batch

# Rollback a specific batch number
php artisan module:migrate:rollback-batch Student --batch=4

# Preview batch rollback
php artisan module:migrate:rollback-batch Student --last-batch --dry-run
```

### module:migrate:rollback-all

Rollback migrations for all enabled modules at once

**Syntax:**

```bash
php artisan module:migrate:rollback-all [options]
```

**Options:**

-   `--step=N` - Number of migrations to rollback per module (default: 0 = all)
-   `--batch=N` - Number of batches to rollback per module (default: 0 = all)
-   `--reverse-order` - Rollback modules in reverse dependency order (dependencies first)
-   `--force` - Skip confirmation prompt
-   `--dry-run` - Preview what would be rolled back

**Examples:**

```bash
# Rollback ALL migrations in ALL modules (DANGER!)
php artisan module:migrate:rollback-all --force

# Rollback last migration in each module
php artisan module:migrate:rollback-all --step=1

# Rollback last batch in each module
php artisan module:migrate:rollback-all --batch=1

# Rollback in reverse dependency order (useful for proper cleanup)
php artisan module:migrate:rollback-all --reverse-order --batch=1

# Preview all rollbacks
php artisan module:migrate:rollback-all --dry-run
```

### module:migrate:status

Show migration status for modules.

**Syntax:**

```bash
php artisan module:migrate:status [module] [options]
```

**Parameters:**

-   `module` - Optional module name for specific status

**Options:**

-   `--pending` - Show only pending migrations
-   `--executed` - Show only executed migrations
-   `--summary` - Show summary table only
-   `--show-batches` - Show batch information for migrations

**Examples:**

```bash
# Show status for all modules
php artisan module:migrate:status

# Show status for specific module
php artisan module:migrate:status Student

# Show summary for all modules
php artisan module:migrate:status --summary

# Show only pending migrations
php artisan module:migrate:status --pending

# Show batch information
php artisan module:migrate:status Student --show-batches
```

### make:module-resource

Create resources inside modules

**Syntax:**

```bash
php artisan make:module-resource [module] [name]
```

**Options:**

-   `--force` - To replace existing one.
