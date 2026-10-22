<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Data\HomeProps;
use App\Data\NameData;
use App\Models\Name;
use Mphp\Http\Response;
use Mphp\Routing\Attributes\Get;
use Mphp\Support\Config;
use Mphp\View\View;

final class HomeController
{
    // Type-hint what you need: the container injects it.
    public function __construct(
        private readonly View $view,
        private readonly Config $config,
    ) {}

    #[Get('/', name: 'home')]
    public function index(): Response
    {
        $props = new HomeProps(
            appName: (string) $this->config->get('app.name'),
            phpVersion: PHP_VERSION,
            names: array_map(NameData::fromModel(...), Name::all()),
        );

        return $this->view->page('Home', $props, title: $props->appName);
    }
}
