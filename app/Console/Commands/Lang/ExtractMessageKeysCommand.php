<?php

namespace App\Console\Commands\Lang;

use Illuminate\Console\Command;

class ExtractMessageKeysCommand extends Command {
    protected $signature = 'message 
        {controllerFile : Relative path to controller file}
        {--lang=all : Language key to update (en, am, etc or all)}';

    protected $description = 'Extract Message::get() keys from controller and append them to message translation files';

    public function handle(): int {
        $controllerFile = $this->argument('controllerFile');
        $langOption = strtolower($this->option('lang') ?? 'all');
        $controllerPath = base_path($controllerFile);

        if (!file_exists($controllerPath)) {
            $this->error("Controller file not found: {$controllerPath}");
            return self::FAILURE;
        }

        $keys = $this->extractKeys(file_get_contents($controllerPath));


        if (empty($keys)) {
            $this->warn('No translation keys found.');
            return self::SUCCESS;
        }

        [$msgDir, $langFiles] = $this->resolveMessageFiles($controllerFile);

        if (empty($langFiles)) {
            $this->error("No message files found in {$msgDir}");
            return self::FAILURE;
        }

        $filesToUpdate = $this->filterLanguages($langFiles, $langOption);
        if ($filesToUpdate === null) {
            return self::FAILURE;
        }

        foreach ($filesToUpdate as $langKey => $filePath) {
            $this->updateMessageFile($filePath, $langKey, $keys);
        }

        $this->info('✔ Message extraction completed.');
        return self::SUCCESS;
    }


    private function extractKeys(string $php): array {
        preg_match_all(
            '/(?:Message::get|getMessage)\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]\s*\)/',
            $php,
            $matches
        );

        return array_values(array_unique($matches[1] ?? []));
    }

    private function resolveMessageFiles(string $controllerFile): array {
        $dir = base_path('translations/Message');
        $ns = "Translation\\Message\\";

        $files = [];

        foreach (glob("{$dir}/*.php") ?: [] as $file) {
            $class = $ns . basename($file, '.php');

            if (class_exists($class) && method_exists($class, 'getKey')) {
                $lang = strtolower($class::getKey());
                $files[$lang] = $file;
            }
        }

        return [$dir, $files];
    }

    private function filterLanguages(array $langFiles, string $lang) {
        if ($lang === 'all') {
            return $langFiles;
        }

        if (!isset($langFiles[$lang])) {
            $this->error("Language '{$lang}' is not supported.");
            return null;
        }

        return [$lang => $langFiles[$lang]];
    }


    private function updateMessageFile(string $filePath, string $lang, array $keys): void {
        $content = file_get_contents($filePath);

        if (!preg_match('/return\s*\[(.*)\];/s', $content, $m)) {
            $this->error("Invalid translation file structure: {$filePath}");
            return;
        }

        $arrayBody = $m[1];
        preg_match_all('/[\'"]([^\'"]+)[\'"]\s*=>/', $arrayBody, $km);
        $existingKeys = $km[1] ?? [];

        $indent = $this->detectIndent($arrayBody);

        $newKeys = array_diff($keys, $existingKeys);
        if (empty($newKeys)) {
            $this->line("No new keys for {$filePath}");
            return;
        }

        $labels = $this->buildLabels($newKeys, $lang);

        $entries = [];
        foreach ($labels as $key => $value) {
            $value = str_replace("'", "\\'", $value);
            $entries[] = "{$indent}'{$key}' => '{$value}',";
        }

        $arrayBody = rtrim($arrayBody);
        if ($arrayBody !== '' && !str_ends_with(trim($arrayBody), ',')) {
            $arrayBody .= ',';
        }

        $arrayBody .= "\n" . implode("\n", $entries) . "\n";

        $newContent = preg_replace(
            '/return\s*\[.*\];/s',
            "return [{$arrayBody}];",
            $content
        );

        file_put_contents($filePath, $newContent);
        $this->info("✔ Updated {$filePath}");
    }

    private function detectIndent(string $arrayBody): string {
        if (preg_match('/\n([ \t]+)[\'"]/', $arrayBody, $m)) {
            return $m[1];
        }

        return "    ";
    }


    private function buildLabels(array $keys, string $lang): array {
        $labels = [];

        foreach ($keys as $key) {
            $labels[$key] = ucfirst(str_replace('_', ' ', $key));
        }

        if ($lang === 'en') {
            return $labels;
        }

        $translated = [];

        foreach ($labels as $key => $text) {
            try {
                $translatedText = autoTranslate($text, $lang);

                $translated[$key] = trim($translatedText) !== ''
                    ? $translatedText
                    : $text;
            } catch (\Throwable $e) {
                $translated[$key] = $text;
            }
        }

        return $translated;
    }
}
