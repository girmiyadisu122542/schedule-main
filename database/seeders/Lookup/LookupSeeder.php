<?php

namespace Database\Seeders\Lookup;

use App\Models\Common\Lookup\LookupTransition;
use App\Models\Common\Lookup\LookupType;
use App\Models\Common\Lookup\LookupValue;
use App\Models\User;
use Constants\AppConstant;
use Exception;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Translation\Back\Amharic;
use Translation\Back\English;
use Translation\BackLang;

class LookupSeeder extends Seeder {
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void {
        $english = English::getKey();
        $amharic = Amharic::getKey();

        $user = User::first();
        if (!$user) {
            consoleError('You need to create a user before running LookupSeeder.');
            return;
        }

        $lookups = $this->loadLookups(config_path('lookups.php'));

        if (empty($lookups)) {
            return;
        }

        try {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->beginTransaction();

            foreach ($lookups as $lookupConfig) {

                $appliesToModel = $lookupConfig['applies_to_model'] ?? [];
                if (!is_array($appliesToModel)) {
                    $appliesToModel = [$appliesToModel];
                }

                $name = $this->resolveTranslation($lookupConfig['name']);
                $description = isset($lookupConfig['description'])
                    ? $this->resolveTranslation($lookupConfig['description'])
                    : [$english => '', $amharic => ''];

                $lookupType = LookupType::updateOrCreate(
                    ['code' => $lookupConfig['code']],
                    [
                        'name' => $name,
                        'description' => $description,
                        'applies_to_model' => $appliesToModel,
                        'is_system' => $lookupConfig['is_system'] ?? false,
                        'user_id' => $user->id,
                        'state' => STATE_ACTIVE,
                    ]
                );

                if (isset($lookupConfig['values']) && is_array($lookupConfig['values'])) {
                    foreach ($lookupConfig['values'] as $valueConfig) {
                        $valueName = $this->resolveTranslation($valueConfig['name']);

                        LookupValue::updateOrCreate(
                            [
                                'lookup_type_id' => $lookupType->id,
                                'code' => $valueConfig['code'],
                            ],
                            [
                                'name' => $valueName,
                                'order' => $valueConfig['order'] ?? 0,
                                'color' => $valueConfig['color'] ?? null,
                                'icon' => $valueConfig['icon'] ?? null,
                                'is_default' => $valueConfig['is_default'] ?? false,
                                'user_id' => $user->id,
                                'state' => STATE_ACTIVE,
                            ]
                        );
                    }
                }

                if (isset($lookupConfig['transitions']) && is_array($lookupConfig['transitions'])) {
                    foreach ($lookupConfig['transitions'] as $transitionConfig) {
                        $fromValue = LookupValue::query()
                            ->where('lookup_type_id', $lookupType->id)
                            ->where('code', $transitionConfig['from'])
                            ->first();

                        $toValue = LookupValue::query()
                            ->where('lookup_type_id', $lookupType->id)
                            ->where('code', $transitionConfig['to'])
                            ->first();

                        if ($fromValue && $toValue) {
                            LookupTransition::updateOrCreate(
                                [
                                    'lookup_type_id' => $lookupType->id,
                                    'from_value_id' => $fromValue->id,
                                    'to_value_id' => $toValue->id,
                                ],
                                [
                                    'state' => STATE_ACTIVE,
                                ]
                            );
                        }
                    }
                }
            }

            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->commit();
        } catch (Exception $exception) {
            DB::connection(AppConstant::SCHEDULE_DATABASE_CONNECTION)->rollBack();

            $this->command->error("Unable to seed lookups: " . $exception->getMessage());
        }
    }

    /**
     * Load lookups from a file
     *
     * @param string $filePath
     * @return array
     */
    private function loadLookups(string $filePath): array {
        if (!file_exists($filePath)) {
            return [];
        }

        $lookups = require $filePath;

        return is_array($lookups) ? $lookups : [];
    }

    /**
     * Resolve translation key to all available languages
     *
     * @param string|array $value
     * @return array
     */
    private function resolveTranslation($value): array {
        if (is_array($value)) {
            return $value;
        }

        $availableLanguages = BackLang::getAvailableLangKeys();

        $translations = [];
        foreach ($availableLanguages as $langKey) {
            $allTranslations = BackLang::getAllTranslations($langKey);

            $translations[$langKey] = $allTranslations[$value] ?? $value;
        }

        return $translations;
    }
}
