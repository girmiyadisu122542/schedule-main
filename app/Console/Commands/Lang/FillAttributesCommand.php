<?php

namespace App\Console\Commands\Lang;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;

class FillAttributesCommand extends Command {
    protected $signature = 'attributes {requestClass} {--lang=} {--file=} {--on-duplicate=skip}';
    protected $description = 'Generate attribute translations from a FormRequest rules() array (static parsing, no execution)';

    public function handle() {
        $fs = new Filesystem();
        $classInput = trim($this->argument('requestClass'));
        $langOption = $this->option('lang');
        $fileOption = $this->option('file');
        $onDuplicate = $this->option('on-duplicate') ?? 'skip';

        $class = $this->normalizeClassName($classInput);

        if (!class_exists($class)) {
            $this->error("Request class not found: {$class}");
            return 1;
        }

        $fields = $this->extractFieldsFromRules($class);
        if (!$fields) {
            $this->error("No validation fields found in rules() for {$class}");
            return 1;
        }

        $attributes = $this->generateLabels($fields);

        $langDirs = [];
        $base = base_path('lang');

        foreach ($fs->directories($base) as $dir) {
            $code = basename($dir);
            if ($langOption && $langOption !== 'all' && $langOption !== $code) {
                continue;
            }
            $langDirs[$code] = $dir;
        }

        if (!$langDirs) {
            $this->warn('No language directories found.');
            return 0;
        }

        $translatedByLang = [];
        foreach ($langDirs as $lang => $dir) {
            if ($lang === 'en') {
                $translatedByLang[$lang] = $attributes;
                continue;
            }

            $this->line("[INFO] Translating attributes → {$lang}");
            $translated = [];
            foreach ($attributes as $key => $label) {
                $translated[$key] = autoTranslate($label, $lang);
            }
            $translatedByLang[$lang] = $translated;
        }

        foreach ($langDirs as $lang => $dir) {
            $file = $fileOption ?: "{$dir}/attributes.php";
            $fs->ensureDirectoryExists(dirname($file));

            $content = file_exists($file) ? file_get_contents($file) : '';

            if (!$content) {
                $content = "<?php\n\nreturn [\n];\n";
            }

            preg_match('/\n(\s*)\'[^\']+\'\s*=>/', $content, $m);
            $indent = $m[1] ?? "    ";

            foreach ($translatedByLang[$lang] as $key => $value) {

                if (str_contains($content, "'$key' =>") && $onDuplicate === 'skip') {
                    continue;
                }

                $line = "{$indent}'{$key}' => '" . str_replace("'", "\\'", $value) . "',\n";

                $content = preg_replace(
                    '/\n(\s*)\](?=[^\]]*$)/',
                    "\n{$line}$1]",
                    $content,
                    1
                );
            }

            file_put_contents($file, $content);
            $this->info("✔ Written: {$file}");
        }

        return 0;
    }



    protected function normalizeClassName(string $input): string {
        if (str_contains($input, '/') || str_ends_with($input, '.php')) {
            $input = str_replace(['/', '.php'], ['\\', ''], $input);
            $input = preg_replace('~^app\\\\~i', 'App\\', $input);
        }
        return ltrim($input, '\\');
    }

    protected function extractFieldsFromRules(string $class): array {
        try {
            $ref = new ReflectionClass($class);
            $method = $ref->getMethod('rules');
            $file = file($method->getFileName());
            $start = $method->getStartLine() - 1;
            $end = $method->getEndLine();
            $code = implode("", array_slice($file, $start, $end - $start));

            preg_match_all("/['\"]([\w.*]+)['\"]\s*=>/", $code, $matches);
            return array_unique($matches[1]);
        } catch (\Throwable $e) {
            $this->error("Failed to parse rules() for {$class}: " . $e->getMessage());
            return [];
        }
    }

    protected function generateLabels(array $fields): array {
        $out = [];
        foreach ($fields as $field) {
            $labelField = preg_replace('/\.\*/', '', $field);
            $labelField = str_replace('.', ' ', $labelField);
            $labelField = Str::replaceLast('_id', '', $labelField);
            $labelField = str_replace('_', ' ', $labelField);
            $label = ucfirst(mb_strtolower(trim($labelField)));
            $out[$field] = $label;
        }
        return $out;
    }

    protected function parseLangFile(string $file): array {
        $php = file_get_contents($file);
        $indent = "\t";

        preg_match('/return\s*\[(.*?)\];/s', $php, $match);
        $body = $match[1] ?? '';

        preg_match('/\n([ \t]+)[\'"]/', $body, $i);
        if (!empty($i[1])) {
            $indent = $i[1];
        }

        preg_match_all('/[\'"](.+?)[\'"]\s*=>\s*[\'"](.*?)[\'"]/', $body, $pairs);
        return [array_combine($pairs[1], $pairs[2]) ?: [], $indent];
    }

    protected function buildLangFile(array $data, string $indent): string {
        $out = "<?php\n\nreturn [\n";
        foreach ($data as $k => $v) {
            $out .= "{$indent}'{$k}' => '" . str_replace("'", "\\'", $v) . "',\n";
        }
        return $out . "];\n";
    }
}
