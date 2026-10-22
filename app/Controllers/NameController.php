<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Data\NameData;
use App\Models\Name;
use App\Requests\NameInput;
use Mphp\Http\HttpException;
use Mphp\Http\Response;
use Mphp\Routing\Attributes\Delete;
use Mphp\Routing\Attributes\Get;
use Mphp\Routing\Attributes\Post;
use Mphp\Routing\Attributes\Prefix;
use Mphp\Routing\Attributes\Put;
use Mphp\Typegen\Ts;

/**
 * Routes live next to the code that handles them. `name:` feeds the generated
 * TypeScript helper, so the front end calls api('names.update', { id }) instead of hand-written URLs.
 */
#[Prefix('/api/names')]
final class NameController
{
    /** @return list<NameData> */
    #[Get(name: 'names.index')]
    #[Ts('NameData[]')]
    public function index(): array
    {
        return array_map(NameData::fromModel(...), Name::all());
    }

    // NameInput is validated before this runs (422 otherwise) and typed on the front end.
    #[Post(name: 'names.store', middleware: ['throttle:30,60'])]
    #[Ts('NameData')]
    public function store(NameInput $input): Response
    {
        return Response::json(NameData::fromModel(Name::create($input->toArray())), 201);
    }

    #[Put('/{id:\d+}', name: 'names.update')]
    public function update(NameInput $input, int $id): NameData
    {
        $name = Name::find($id) ?? throw new HttpException(404, 'Name not found.');

        return NameData::fromModel($name->update($input->toArray()));
    }

    #[Delete('/{id:\d+}', name: 'names.destroy')]
    #[Ts('void')]
    public function destroy(int $id): Response
    {
        (Name::find($id) ?? throw new HttpException(404, 'Name not found.'))->delete();

        return Response::noContent();
    }
}
