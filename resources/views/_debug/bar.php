<?php
/**
 * Debug toolbar (APP_DEBUG=true only). Pure HTML + the stylesheet served by DebugBar: works under the strict CSP.
 *
 * @var int $status @var string $method @var string $path @var float $ms @var float $dbMs @var int $memory
 * @var list<array{sql: string, bindings: list<mixed>, ms: float}> $queries
 * @var array<string, int> $repeated @var string $route
 */
?>
<link rel="stylesheet" href="/_mphp/bar.css">
<div id="mphp-debug">
    <details>
        <summary class="s<?= e(intdiv($status, 100)) ?>">
            <span class="dot"></span>
            <b><?= e($status) ?></b>
            <span><?= e($method) ?> <?= e($path) ?></span>
            <span><?= e(number_format($ms, 1)) ?> ms</span>
            <span><?= e(number_format($memory / 1048576, 1)) ?> MB</span>
            <span><?= e(count($queries)) ?> queries · <?= e(number_format($dbMs, 1)) ?> ms</span>
            <?php if ($repeated): ?><span class="warn">⚠ repeated queries (N+1?)</span><?php endif ?>
        </summary>
        <div class="panel">
            <h4>Request</h4>
            <table>
                <tr><td>Route</td><td><code><?= e($route) ?></code></td></tr>
                <tr><td>PHP</td><td><code><?= e(PHP_VERSION) ?></code></td></tr>
            </table>

            <h4>SQL (<?= e(count($queries)) ?>)</h4>
            <table>
                <?php foreach ($queries as $query): ?>
                    <tr>
                        <td class="n"><?= e(number_format($query['ms'], 2)) ?> ms</td>
                        <td>
                            <code><?= e($query['sql']) ?></code>
                            <?php if ($query['bindings']): ?><br><code>← <?= e(json_encode($query['bindings'], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)) ?></code><?php endif ?>
                            <?php if (isset($repeated[$query['sql']])): ?><br><span class="warn">⚠ ran <?= e($repeated[$query['sql']]) ?>×</span><?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
                <?php if (!$queries): ?><tr><td>No query.</td></tr><?php endif ?>
            </table>
        </div>
    </details>
</div>
