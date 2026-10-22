<?php

declare(strict_types=1);

use Mphp\Typegen\TypeScriptGenerator;

it('turns the app DTOs and named routes into TypeScript', function () {
    $app = testApp();
    $generator = new TypeScriptGenerator(dirname(__DIR__, 2) . '/app/Data', 'App\\Data');

    $types = $generator->types();
    $routes = $generator->routes($app->router);

    expect($types)->toContain("export interface NameData {\n  id: number;\n  firstname: string;\n  createdAt: string;\n}")
        ->and($types)->toContain('names: NameData[];')
        ->and($routes)->toContain("'names.update': { method: 'PUT', path: '/api/names/{id}' },")
        ->and($routes)->toContain("'names.update': { id: string | number };")
        ->and($routes)->toContain("'names.index': NameData[];")
        ->and($routes)->toContain("'names.update': NameData;")
        ->and($routes)->toContain("'names.destroy': void;")
        ->and($routes)->toContain("'names.store': { firstname: string };")
        ->and($routes)->toContain("'names.update': { firstname: string };")
        ->and(substr($routes, strpos($routes, 'interface RouteBodies')))->not->toContain('names.index');
});

it('maps nullable, union, enum, date and array types', function () {
    $dir = tempDir();
    file_put_contents($dir . '/Status.php', "<?php\nnamespace TypegenFixture;\nenum Status: string { case On = 'on'; case Off = 'off'; }\n");
    file_put_contents($dir . '/Sample.php', <<<'PHP'
        <?php
        namespace TypegenFixture;
        final class Sample {
            public function __construct(
                public ?int $a,
                public string|int $b,
                public bool $c,
                public ?\DateTimeImmutable $d,
                public array $e,
                public Status $f,
                public mixed $g,
            ) {}
        }
        PHP);
    require_once $dir . '/Status.php';
    require_once $dir . '/Sample.php';

    $types = (new TypeScriptGenerator($dir, 'TypegenFixture'))->types();

    expect($types)->toContain('a: number | null;')
        ->and($types)->toContain('b: string | number;')
        ->and($types)->toContain('c: boolean;')
        ->and($types)->toContain('d: string | null;')
        ->and($types)->toContain('e: unknown[];')
        ->and($types)->toContain('f: Status;')
        ->and($types)->toContain('g: unknown;')
        ->and($types)->toContain("export type Status = 'on' | 'off';");
});
