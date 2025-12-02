<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ScanUnused extends Command
{
    protected $signature = 'scan:unused {--apply : Move unused files into _trash folders}';

    protected $description = 'Scan for potentially unused Blade views, controller methods, and models. Optionally move files.';

    protected Filesystem $files;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    public function handle(): int
    {
        $root = base_path();
        $apply = (bool) $this->option('apply');
        $timestamp = now()->format('Ymd-His');

        $this->info('Scanning for unused resources...');

        [$unusedViews, $viewRefs] = $this->scanViews($root);
        [$unusedControllers, $controllerRefs] = $this->scanControllers($root);
        [$unusedModels, $modelRefs] = $this->scanModels($root);
        [$unusedSupport, $supportRefs] = $this->scanNamespace($root, app_path('Support'), 'App\\Support');
        [$unusedJobs, $jobRefs] = $this->scanNamespace($root, app_path('Jobs'), 'App\\Jobs');

        $this->line('');
        $this->info('Results');
        $this->table(['Type', 'Path/Dot', 'References'], array_merge(
            array_map(fn($v) => ['view', $v, $viewRefs[$v] ?? 0], $unusedViews),
            array_map(fn($c) => ['controller', $c, $controllerRefs[$c] ?? 0], $unusedControllers),
            array_map(fn($m) => ['model', $m, $modelRefs[$m] ?? 0], $unusedModels),
            array_map(fn($s) => ['support', $s, $supportRefs[$s] ?? 0], $unusedSupport),
            array_map(fn($j) => ['job', $j, $jobRefs[$j] ?? 0], $unusedJobs),
        ));

        if ($apply) {
            $this->warn('Applying changes: moving files to _trash folders');
            $trashBase = $root . DIRECTORY_SEPARATOR . '_trash' . DIRECTORY_SEPARATOR . 'unused-' . $timestamp;
            $this->files->ensureDirectoryExists($trashBase);

            foreach ($unusedViews as $dot) {
                $src = resource_path('views/' . str_replace('.', '/', $dot) . '.blade.php');
                if ($this->files->exists($src)) {
                    $dest = $trashBase . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $dot) . '.blade.php';
                    $this->moveFile($src, $dest);
                }
            }
            foreach ($unusedControllers as $path) {
                $src = $root . DIRECTORY_SEPARATOR . $path;
                if ($this->files->exists($src)) {
                    $dest = $trashBase . DIRECTORY_SEPARATOR . $path;
                    $this->moveFile($src, $dest);
                }
            }
            foreach ($unusedModels as $path) {
                $src = $root . DIRECTORY_SEPARATOR . $path;
                if ($this->files->exists($src)) {
                    $dest = $trashBase . DIRECTORY_SEPARATOR . $path;
                    $this->moveFile($src, $dest);
                }
            }
            foreach ($unusedSupport as $path) {
                $src = $root . DIRECTORY_SEPARATOR . $path;
                if ($this->files->exists($src)) {
                    $dest = $trashBase . DIRECTORY_SEPARATOR . $path;
                    $this->moveFile($src, $dest);
                }
            }
            foreach ($unusedJobs as $path) {
                $src = $root . DIRECTORY_SEPARATOR . $path;
                if ($this->files->exists($src)) {
                    $dest = $trashBase . DIRECTORY_SEPARATOR . $path;
                    $this->moveFile($src, $dest);
                }
            }

            $this->info('Moved unused files. Review _trash folder and run tests.');
        } else {
            $this->comment('Run again with --apply to move the unused files into _trash/ for review.');
        }

        return self::SUCCESS;
    }

    protected function scanViews(string $root): array
    {
        $viewsDir = resource_path('views');
        $bladeFiles = collect($this->files->allFiles($viewsDir))
            ->filter(fn($f) => Str::endsWith($f->getFilename(), '.blade.php'))
            ->values();

        $dotNames = $bladeFiles->map(function ($file) use ($viewsDir) {
            $rel = str_replace($viewsDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $rel = str_replace(['\\', '/'], '.', $rel);
            return Str::beforeLast($rel, '.blade.php');
        })->all();

        // Aggregate all project files text for reference scanning
        $projectFiles = collect($this->files->allFiles($root))
            ->filter(function ($f) {
                $path = $f->getPathname();
                // Skip vendor and storage
                if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) return false;
                if (str_contains($path, DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)) return false;
                // Only scan php and blade and js
                return Str::endsWith($path, ['.php', '.blade.php', '.js']);
            })
            ->values();

        $refs = [];
        foreach ($dotNames as $dot) {
            $needle1 = "'{$dot}'";
            $needle2 = '"' . $dot . '"';
            $count = 0;
            foreach ($projectFiles as $f) {
                $content = $this->files->get($f->getPathname());
                // Count @include/@extends/view('dot') appearances
                $count += substr_count($content, $needle1);
                $count += substr_count($content, $needle2);
            }
            $refs[$dot] = $count;
        }

        // Consider a view unused if references == 0 and it's not the welcome or layout fallback
        $unused = array_values(array_filter($dotNames, function ($dot) use ($refs) {
            if (Str::startsWith($dot, 'layouts.') || Str::startsWith($dot, 'partials.')) return false;
            return ($refs[$dot] ?? 0) === 0;
        }));

        return [$unused, $refs];
    }

    protected function scanControllers(string $root): array
    {
        $controllersDir = app_path('Http/Controllers');
        $phpFiles = collect($this->files->allFiles($controllersDir))
            ->filter(fn($f) => Str::endsWith($f->getFilename(), '.php'))
            ->values();

        $routesFiles = [base_path('routes/web.php'), base_path('routes/api.php'), base_path('routes/console.php')];
        $routesContent = '';
        foreach ($routesFiles as $rf) {
            if ($this->files->exists($rf)) {
                $routesContent .= "\n" . $this->files->get($rf);
            }
        }

        $unused = [];
        $refs = [];
        foreach ($phpFiles as $file) {
            $path = $file->getPathname();
            $content = $this->files->get($path);
            if (!preg_match('/class\s+(\\w+)/', $content, $m)) {
                continue;
            }
            $class = $m[1];
            $fq = 'App\\Http\\Controllers\\' . $class;
            // Controller referenced in routes?
            $usedInRoutes = Str::contains($routesContent, $class . '::class');
            $methodRefs = 0;
            // For simplicity, treat presence of Controller::class in routes as a reference
            $refs[$path] = $usedInRoutes ? 1 : 0;
            if (!$usedInRoutes) {
                $unused[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
            }
        }

        return [$unused, $refs];
    }

    protected function scanModels(string $root): array
    {
        $modelsDir = app_path('Models');
        $phpFiles = collect($this->files->allFiles($modelsDir))
            ->filter(fn($f) => Str::endsWith($f->getFilename(), '.php'))
            ->values();

        $projectFiles = collect($this->files->allFiles($root))
            ->filter(function ($f) {
                $path = $f->getPathname();
                if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) return false;
                if (str_contains($path, DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)) return false;
                return Str::endsWith($path, ['.php', '.blade.php']);
            })
            ->values();

        $unused = [];
        $refs = [];
        foreach ($phpFiles as $file) {
            $path = $file->getPathname();
            $content = $this->files->get($path);
            if (!preg_match('/class\s+(\\w+)/', $content, $m)) {
                continue;
            }
            $class = $m[1];
            $short = $class;
            $count = 0;
            foreach ($projectFiles as $f) {
                $c = $this->files->get($f->getPathname());
                if ($f->getPathname() === $path) continue; // skip definition file
                $count += substr_count($c, '\\App\\Models\\' . $short);
                $count += substr_count($c, 'use App\\Models\\' . $short);
                $count += substr_count($c, $short . '::');
            }
            $refs[str_replace($root . DIRECTORY_SEPARATOR, '', $path)] = $count;
            if ($count === 0) {
                $unused[] = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
            }
        }

        return [$unused, $refs];
    }

    protected function scanNamespace(string $root, string $dir, string $namespace): array
    {
        if (! $this->files->exists($dir)) {
            return [[], []];
        }

        $phpFiles = collect($this->files->allFiles($dir))
            ->filter(fn($f) => Str::endsWith($f->getFilename(), '.php'))
            ->values();

        $projectFiles = collect($this->files->allFiles($root))
            ->filter(function ($f) {
                $path = $f->getPathname();
                if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) return false;
                if (str_contains($path, DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)) return false;
                return Str::endsWith($path, ['.php', '.blade.php', '.js']);
            })
            ->values();

        $unused = [];
        $refs = [];
        foreach ($phpFiles as $file) {
            $path = $file->getPathname();
            $content = $this->files->get($path);
            if (!preg_match('/(?:class|trait|interface)\s+(\w+)/', $content, $m)) {
                continue;
            }
            $class = $m[1];
            $fqcn = $namespace . '\\' . $class;
            $count = 0;
            foreach ($projectFiles as $f) {
                $c = $this->files->get($f->getPathname());
                if ($f->getPathname() === $path) continue;
                $count += substr_count($c, $fqcn);
                $count += substr_count($c, 'use ' . $fqcn);
                $count += substr_count($c, $class . '::');
                // Job dispatch patterns
                $count += substr_count($c, 'dispatch(new ' . $class);
                $count += substr_count($c, $class . '::dispatch(');
                // Instantiation and trait use inside classes in same namespace
                $count += substr_count($c, 'new ' . $class . '(');
                $count += substr_count($c, 'use ' . $class . ';');
                // Type-hints and mentions
                $count += substr_count($c, $class . ' $');
                $count += substr_count($c, $class . ' $');
                $count += substr_count($c, $class . '\n');
                // Fallback: plain class name appearances
                $count += substr_count($c, $class);
            }
            $rel = str_replace($root . DIRECTORY_SEPARATOR, '', $path);
            $refs[$rel] = $count;
            if ($count === 0) {
                $unused[] = $rel;
            }
        }

        return [$unused, $refs];
    }

    protected function moveFile(string $src, string $dest): void
    {
        $this->files->ensureDirectoryExists(dirname($dest));
        $this->files->move($src, $dest);
        $this->line("Moved: {$src} -> {$dest}");
    }
}
