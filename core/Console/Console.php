<?php

declare(strict_types=1);

namespace Mphp\Console;

use Mphp\App;
use Mphp\Database\Connection;
use Mphp\Database\Migrator;
use Mphp\Security\Crypto;
use Mphp\Typegen\TypeScriptGenerator;
use ZipArchive;

/** `php bin/mphp <command>`: the whole developer workflow in one tiny CLI. */
final class Console
{
    /** @var array<string, array{0: string, 1: callable(list<string>, array<string, string|true>): int}> */
    private array $commands;

    public function __construct(private readonly App $app)
    {
        $this->commands = [
            'serve' => ['Start the dev server (--host=127.0.0.1 --port=8000)', $this->serve(...)],
            'dev' => ['PHP server + Vite (hot reload) in one terminal (--host --port)', $this->dev(...)],
            'routes' => ['List every route', $this->routes(...)],
            'routes:cache' => ['Cache routes for production (attribute routes only)', $this->routesCache(...)],
            'routes:clear' => ['Delete the route cache', $this->routesClear(...)],
            'key:generate' => ['Write a fresh APP_KEY into .env (--force to replace, --show to print)', $this->keyGenerate(...)],
            'make:controller' => ['Create app/Controllers/<Name>Controller.php', $this->makeController(...)],
            'make:model' => ['Create app/Models/<Name>.php', $this->makeModel(...)],
            'make:migration' => ['Create database/migrations/<date>_<name>.php', $this->makeMigration(...)],
            'migrate' => ['Run pending migrations', $this->migrate(...)],
            'migrate:rollback' => ['Roll back the last batch', $this->rollback(...)],
            'migrate:status' => ['Show migration status', $this->status(...)],
            'types:generate' => ['Generate TypeScript types + route helper from PHP', $this->typesGenerate(...)],
            'build' => ['Build the front end and cache the routes', $this->build(...)],
            'package' => ['Build, then zip a ready-to-upload release (dist/release.zip)', $this->package(...)],
            'compile' => ['Build, then pack everything into ONE file: dist/mphp.phar', $this->compile(...)],
        ];
    }

    /** Commands that make sense inside a PHAR (the code there is read-only). */
    private const PHAR_COMMANDS = ['help', 'serve', 'routes', 'key:generate', 'migrate', 'migrate:rollback', 'migrate:status'];

    /** @param list<string> $argv */
    public function run(array $argv): int
    {
        $name = $argv[1] ?? 'help';
        [$arguments, $options] = $this->parse(array_slice($argv, 2));

        if (!isset($this->commands[$name])) {
            $this->help();

            return $name === 'help' ? 0 : 1;
        }

        if ($this->app->isPhar() && !in_array($name, self::PHAR_COMMANDS, true)) {
            $this->line("✗ [$name] is a development command: run it from the source project, not from the PHAR.");

            return 1;
        }

        try {
            return ($this->commands[$name][1])($arguments, $options);
        } catch (\Throwable $e) {
            $this->line("✗ {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * @param list<string> $args
     * @return array{0: list<string>, 1: array<string, string|true>}
     */
    private function parse(array $args): array
    {
        $positional = [];
        $options = [];
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--')) {
                [$key, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, true);
                $options[$key] = $value;
            } else {
                $positional[] = $arg;
            }
        }

        return [$positional, $options];
    }

    private function help(): void
    {
        $this->line("M-PHP\n\nUsage: php bin/mphp <command>\n");
        foreach ($this->commands as $name => [$description]) {
            $this->line(sprintf('  %-17s %s', $name, $description));
        }
    }

    private function serve(array $args, array $options): int
    {
        $host = (string) ($options['host'] ?? '127.0.0.1');
        $port = (int) ($options['port'] ?? 8000);
        $this->line("Serving http://$host:$port  (Ctrl+C to stop; run `npm run dev` in another terminal for the front end)");

        putenv('PHP_CLI_SERVER_WORKERS=4');

        if ($this->app->isPhar()) {
            // The PHAR is its own router script; .env and storage/ are read from the current directory.
            putenv('MPHP_HOME=' . getcwd());
            passthru(sprintf('%s -S %s -t %s %s', escapeshellarg(PHP_BINARY), escapeshellarg("$host:$port"), escapeshellarg((string) getcwd()), escapeshellarg(\Phar::running(false))), $code);

            return $code;
        }

        passthru(sprintf('%s -S %s -t %s %s', escapeshellarg(PHP_BINARY), escapeshellarg("$host:$port"), escapeshellarg($this->path('public')), escapeshellarg($this->path('public/index.php'))), $code);

        return $code;
    }

    private function compile(array $args, array $options): int
    {
        if (ini_get('phar.readonly')) {
            // Creating a PHAR is disabled by default: re-run ourselves once with it enabled.
            passthru(sprintf('%s -d phar.readonly=0 %s compile', escapeshellarg(PHP_BINARY), escapeshellarg($this->path('bin/mphp'))), $code);

            return $code;
        }
        if (($code = $this->build([], [])) !== 0) {
            return $code;
        }

        $count = PharBuilder::build($this->app->basePath, $this->path('dist/mphp.phar'));
        $size = number_format(filesize($this->path('dist/mphp.phar')) / 1024, 0);
        $this->line("✓ dist/mphp.phar ($count files, $size KB)\n  Copy it anywhere, then:  php mphp.phar key:generate && php mphp.phar migrate && php mphp.phar serve");

        return 0;
    }

    private function dev(array $args, array $options): int
    {
        $host = (string) ($options['host'] ?? '127.0.0.1');
        $port = (int) ($options['port'] ?? 8000);
        $this->line("Dev: PHP on http://$host:$port + Vite on :5173 (Ctrl+C stops both)");

        // One shell, two background jobs; `kill 0` takes the whole process group down on exit.
        $php = sprintf('PHP_CLI_SERVER_WORKERS=4 %s -S %s -t %s %s', escapeshellarg(PHP_BINARY), escapeshellarg("$host:$port"), escapeshellarg($this->path('public')), escapeshellarg($this->path('public/index.php')));
        passthru(sprintf("cd %s && trap 'kill 0' INT TERM EXIT; %s & npm run dev & wait", escapeshellarg($this->app->basePath), $php), $code);

        return $code;
    }

    private function routes(array $args, array $options): int
    {
        foreach ($this->app->router->routes() as $r) {
            $handler = is_array($r->handler) ? substr(strrchr($r->handler[0], '\\') ?: $r->handler[0], 1) . '@' . $r->handler[1] : 'Closure';
            $this->line(sprintf('%-7s %-28s %-16s %s %s', $r->method, $r->path, $r->name ?? '-', $handler, $r->middleware ? '[' . implode(',', $r->middleware) . ']' : ''));
        }

        return 0;
    }

    private function routesCache(array $args, array $options): int
    {
        if (!$this->app->router->isCacheable()) {
            $this->line('✗ Some routes use closures and cannot be cached. Move them to controller attributes.');

            return 1;
        }
        $this->write('storage/cache/routes.php', "<?php\n\nreturn " . var_export($this->app->router->export(), true) . ";\n", true);
        $this->line('✓ Routes cached (production reads storage/cache/routes.php; delete it or run routes:clear after changing routes).');

        return 0;
    }

    private function routesClear(array $args, array $options): int
    {
        @unlink($this->path('storage/cache/routes.php'));
        $this->line('✓ Route cache cleared.');

        return 0;
    }

    private function keyGenerate(array $args, array $options): int
    {
        $key = Crypto::generateKey();
        if (isset($options['show'])) {
            $this->line($key);

            return 0;
        }

        $env = $this->app->dataPath . '/.env';
        if (!is_file($env)) {
            copy($this->path('.env.example'), $env); // source may be inside the PHAR, target is next to it
        }
        $content = (string) file_get_contents($env);

        if (preg_match('/^APP_KEY=(.+)$/m', $content) && !isset($options['force'])) {
            $this->line('✗ APP_KEY already set. Re-run with --force to replace it (this invalidates anything encrypted with the old key).');

            return 1;
        }

        $content = preg_match('/^APP_KEY=.*$/m', $content)
            ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $content)
            : rtrim($content) . "\nAPP_KEY=$key\n";
        file_put_contents($env, $content);
        $this->line('✓ APP_KEY written to .env');

        return 0;
    }

    private function makeController(array $args, array $options): int
    {
        $name = $this->className($args[0] ?? null, 'Controller');
        $route = '/' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', substr($name, 0, -10)));
        $this->write("app/Controllers/$name.php", <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Controllers;

            use Mphp\\Routing\\Attributes\\Get;

            final class $name
            {
                #[Get('$route')]
                public function index(): array
                {
                    return ['ok' => true];
                }
            }

            PHP);
        $this->line("✓ app/Controllers/$name.php");

        return 0;
    }

    private function makeModel(array $args, array $options): int
    {
        $name = $this->className($args[0] ?? null);
        $table = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';
        $this->write("app/Models/$name.php", <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Models;

            use Mphp\\Database\\Model;

            final class $name extends Model
            {
                protected static string \$table = '$table';

                /** Mass-assignment allow-list. */
                protected static array \$fillable = [];
            }

            PHP);
        $this->line("✓ app/Models/$name.php");

        return 0;
    }

    private function makeMigration(array $args, array $options): int
    {
        $name = strtolower((string) ($args[0] ?? ''));
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Usage: make:migration create_posts_table (snake_case)');
        }
        $file = 'database/migrations/' . date('Y_m_d_His') . "_$name.php";
        $this->write($file, <<<'PHP'
            <?php

            declare(strict_types=1);

            use Mphp\Database\Connection;
            use Mphp\Database\Migration;

            return new class extends Migration {
                public function up(Connection $db): void
                {
                    // $db->statement(sprintf('CREATE TABLE things (%s, name VARCHAR(100) NOT NULL)', $db->idColumn()));
                }

                public function down(Connection $db): void
                {
                    // $db->statement('DROP TABLE things');
                }
            };

            PHP);
        $this->line("✓ $file");

        return 0;
    }

    private function migrator(): Migrator
    {
        $path = $this->app->dataPath . '/storage';
        if (!is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return new Migrator($this->app->container->get(Connection::class), $this->path('database/migrations'));
    }

    private function migrate(array $args, array $options): int
    {
        $done = $this->migrator()->migrate();
        $this->line($done === [] ? 'Nothing to migrate.' : implode("\n", array_map(static fn(string $n): string => "✓ migrated $n", $done)));

        return 0;
    }

    private function rollback(array $args, array $options): int
    {
        $undone = $this->migrator()->rollback();
        $this->line($undone === [] ? 'Nothing to roll back.' : implode("\n", array_map(static fn(string $n): string => "✓ rolled back $n", $undone)));

        return 0;
    }

    private function status(array $args, array $options): int
    {
        foreach ($this->migrator()->status() as $row) {
            $this->line(($row['ran'] ? '[x] ' : '[ ] ') . $row['name']);
        }

        return 0;
    }

    private function typesGenerate(array $args, array $options): int
    {
        $generator = new TypeScriptGenerator($this->path('app/Data'), 'App\\Data');
        $this->write('resources/js/generated/types.ts', $generator->types(), true);
        $this->write('resources/js/generated/routes.ts', $generator->routes($this->app->router), true);
        $this->line('✓ resources/js/generated/types.ts + routes.ts');

        return 0;
    }

    private function build(array $args, array $options): int
    {
        if ($this->typesGenerate([], []) !== 0) {
            return 1;
        }
        passthru('cd ' . escapeshellarg($this->app->basePath) . ' && npm run build', $code);
        if ($code !== 0) {
            return $code;
        }

        return $this->routesCache([], []);
    }

    private function package(array $args, array $options): int
    {
        if (($code = $this->build([], [])) !== 0) {
            return $code;
        }
        if (!is_file($this->path('public/build/.vite/manifest.json'))) {
            throw new \RuntimeException('public/build is missing: the front-end build did not produce a manifest.');
        }

        $target = $this->path('dist/release.zip');
        @mkdir(dirname($target), 0775, true);
        @unlink($target);

        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Cannot create the zip file.');
        }

        // Allow-list: only what production needs. No vendor/, node_modules/, tests, sources or .env.
        foreach (['app', 'bin', 'config', 'core', 'database', 'public', 'resources/views'] as $dir) {
            $this->addDirectory($zip, $dir);
        }
        foreach (['.htaccess', '.env.example', 'composer.json', 'storage/cache/routes.php'] as $file) {
            if (is_file($this->path($file))) {
                $zip->addFile($this->path($file), $file);
            }
        }
        foreach (['storage/logs', 'storage/cache', 'storage/uploads'] as $dir) {
            $zip->addEmptyDir($dir);
        }
        $zip->close();

        $this->line('✓ dist/release.zip  (upload + unzip; point the web root at public/, or keep the root .htaccess; copy .env.example to .env)');

        return 0;
    }

    private function addDirectory(ZipArchive $zip, string $dir): void
    {
        $root = $this->path($dir);
        if (!is_dir($root)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = $dir . substr($file->getPathname(), strlen($root));
            if ($file->isFile() && $relative !== 'public/hot') {
                $zip->addFile($file->getPathname(), $relative);
            }
        }
    }

    private function className(?string $name, string $suffix = ''): string
    {
        if ($name === null || !preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            throw new \InvalidArgumentException('Give a StudlyCase name, e.g. Post');
        }

        return str_ends_with($name, $suffix) ? $name : $name . $suffix;
    }

    private function write(string $relative, string $content, bool $overwrite = false): void
    {
        $path = $this->path($relative);
        if (is_file($path) && !$overwrite) {
            throw new \RuntimeException("$relative already exists.");
        }
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, $content);
    }

    private function path(string $relative): string
    {
        return $this->app->basePath . '/' . $relative;
    }

    private function line(string $text): void
    {
        fwrite(STDOUT, $text . "\n");
    }
}
