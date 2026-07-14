<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\ServiceProvider;

class MacroServiceProvider extends ServiceProvider {
    /**
     * Register services.
     */
    public function register(): void {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot() {

        Collection::macro('selectLocalize', function (?array $columns = null) {
            $language = getCurrentLanguage(request());
            return $this->map(function ($item) use ($columns, $language) {
                $localizable = $item->localizable ?? [];
                $columnsToReturn = $columns ?? $localizable;

                $newItem = [];

                foreach ($columnsToReturn as $col) {
                    if (!isset($item->{$col})) {
                        continue;
                    }
                    if (in_array($col, $localizable, true) && is_array($item->{$col})) {
                        $newItem[$col] = $item->{$col}[$language] ?? reset($item->{$col});
                    } else {
                        $newItem[$col] = $item->{$col};
                    }
                }
                return (object) $newItem;
            });
        });


        Collection::macro('localize', function (?array $columns = null) {
            $language = getCurrentLanguage(request());

            return $this->map(function ($item) use ($columns, $language) {
                $localizable = $item->localizable ?? [];
                $columnsToLocalize = $columns ?? $localizable;

                foreach ($columnsToLocalize as $col) {
                    if (!isset($item->{$col})) {
                        continue;
                    }

                    if (in_array($col, $localizable, true) && is_array($item->{$col})) {
                        $item->{$col} = $item->{$col}[$language] ?? reset($item->{$col});
                    }
                }

                return $item;
            });
        });

    }
}
