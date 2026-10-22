# M-PHP

Framework PHP léger et sécurisé, avec une interface **Svelte 5 + TypeScript**.
PHP reste la source de vérité : les types TypeScript et le client d'API sont **générés** depuis vos contrôleurs.

- PHP 8.4+ (testé sur 8.5), aucune dépendance Composer en production
- SQLite par défaut (rien à installer), MySQL / PostgreSQL en option
- CSRF, CSP stricte, requêtes préparées, chiffrement libsodium, sessions durcies
- Déploiement : un zip (`php bin/mphp package`) ou **un seul fichier PHAR** (`php bin/mphp compile`)
- Barre de debug (requêtes SQL, temps, détection N+1) en développement

Site de documentation : https://mandanyaina.github.io/mphp/ · Guide pour agents/IA : [AGENT.md](AGENT.md)

## Démarrage

```bash
composer install
npm install
cp .env.example .env
php bin/mphp key:generate
php bin/mphp migrate

php bin/mphp dev          # PHP http://127.0.0.1:8000 + Vite (rechargement à chaud), un seul terminal
```

Avec Docker : `docker compose up --build` (http://localhost:8000).

## Structure

| Dossier | Rôle |
|---|---|
| `app/Controllers` | Contrôleurs ; les routes sont des attributs PHP |
| `app/Models` | Modèles (`$fillable` obligatoire) |
| `app/Data` | Classes de données envoyées au navigateur → types TypeScript |
| `config/` | `app.php`, `database.php` (valeurs lues dans `.env`) |
| `database/migrations` | Migrations, exécutées par `php bin/mphp migrate` |
| `resources/pages` | Une page Svelte par fichier (`Home.svelte` ↔ `$view->page('Home', …)`) |
| `resources/views` | Gabarits PHP (page shell, erreurs, e-mails) |
| `public/` | **Seule** racine web. Le reste n'est jamais servi |
| `core/` | Le framework (namespace `Mphp\`) |
| `storage/` | Logs, cache, uploads, base SQLite (ignoré par git) |

## Un exemple complet

```php
// app/Requests/NameInput.php : validation côté serveur ET type du corps côté TypeScript
final readonly class NameInput extends FormRequest
{
    public function __construct(#[Rule('required|string|max:50')] public string $firstname) {}
}

// app/Controllers/NameController.php
#[Prefix('/api/names')]
final class NameController
{
    #[Put('/{id:\d+}', name: 'names.update')]
    public function update(NameInput $input, int $id): NameData   // 422 automatique si invalide
    {
        $name = Name::find($id) ?? throw new HttpException(404, 'Name not found.');

        return NameData::fromModel($name->update($input->toArray()));
    }
}
```

```bash
php bin/mphp types:generate
```

```ts
// resources/pages/Home.svelte (extrait)
const updated = await api('names.update', { params: { id: 3 }, body: { firstname: 'Ada' } });
//    ^? NameData : route, paramètres, corps et type de réponse viennent tous du PHP

const form = new Form('names.store', { firstname: '' });   // erreurs 422 par champ : form.errors.firstname
```

Une faute de frappe dans le nom de la route, un paramètre manquant, un champ de corps oublié ou un mauvais type de réponse sont des **erreurs de compilation**.

## Commandes

```
php bin/mphp dev                 PHP + Vite ensemble (développement)
php bin/mphp serve               Serveur PHP seul
php bin/mphp routes              Liste des routes
php bin/mphp make:controller X   make:model X   make:migration create_x_table
php bin/mphp migrate             migrate:rollback   migrate:status
php bin/mphp types:generate      Types TS + helper de routes depuis le PHP
php bin/mphp key:generate        Clé de chiffrement (APP_KEY)
php bin/mphp build               Front de production + cache des routes
php bin/mphp package             dist/release.zip prêt à envoyer
php bin/mphp compile             dist/mphp.phar : l'application dans un seul fichier
composer test                    Pest
npm run check                    Vérification TypeScript / Svelte
```

## Ce que le framework fournit

| Besoin | API |
|---|---|
| Requête / réponse | `Request` (`input()`, `all()`, `bearerToken()`), `Response::json/html/redirect/noContent` |
| Retour d'action | `Response`, tableau/objet → JSON, chaîne → HTML, `null` → 204 |
| Injection | Typez la dépendance dans le constructeur ou l'action : le conteneur la fournit |
| Middleware | `'csrf'`, `'throttle:5,60'`, ou une classe implémentant `Mphp\Http\Middleware` |
| Validation | `FormRequest` + `#[Rule('…')]` (typé, 422 automatique, type TS généré) ou `Validator::validate($data, [...])` |
| Base de données | `$db->table('t')->where('a', '>=', 1)->orderBy('id')->get()` ; `Model::create/find/update/delete` |
| Chiffrement | `Crypto::encrypt/decrypt/sign` (libsodium) ; `Passwords::hash/verify` |
| Session / CSRF | `Session`, `Csrf` (la balise `<meta name="csrf-token">` est ajoutée aux pages) |
| Upload | `Uploader::store($_FILES['f'], 'image')` : type détecté sur le contenu |
| Erreurs | `throw new HttpException(404, '…')` ; page de debug si `APP_DEBUG=true`, message générique sinon |

## Barre de debug

Avec `APP_DEBUG=true`, chaque page HTML reçoit une barre en bas d'écran (cliquez pour la déplier) : statut, route, temps, mémoire et **chaque requête SQL** avec sa durée et ses valeurs. Trois requêtes identiques ou plus sont signalées comme un possible N+1. Elle fonctionne sans JavaScript, donc sous la CSP stricte.
Les réponses d'API reçoivent un en-tête `Server-Timing` (visible dans l'onglet Réseau). En production (`APP_DEBUG=false`), rien n'est ajouté.

## Déploiement

### Option 1 : un PHAR (un seul fichier)

```bash
php bin/mphp compile                      # -> dist/mphp.phar (code + front compilé)
# sur le serveur, dans le dossier de votre choix :
php mphp.phar key:generate                # crée .env à côté du PHAR
php mphp.phar migrate
php mphp.phar serve --port=8000
```

Le code est en lecture seule dans l'archive ; `.env` et `storage/` (logs, uploads, SQLite) vivent dans le dossier courant (ou `MPHP_HOME`). Les commandes de développement (`make:*`, `build`…) sont refusées dans un PHAR.

### Option 2 : un zip

1. `php bin/mphp package` → `dist/release.zip` (contient le front compilé et le cache des routes, pas de `vendor/` ni `node_modules/`).
2. Envoyer et décompresser sur le serveur, copier `.env.example` en `.env` (`APP_ENV=production`, `APP_DEBUG=false`), `php bin/mphp key:generate`, `php bin/mphp migrate`.
3. Pointer la racine web sur `public/`. Si l'hébergeur ne le permet pas, le `.htaccess` à la racine redirige tout vers `public/` et rien d'autre n'est accessible.

Sous Nginx : `root /chemin/vers/public; location / { try_files $uri /index.php?$query_string; }` puis le bloc PHP-FPM habituel.

## Site de documentation (GitHub Pages)

Le site statique est dans `site/` et publié par `.github/workflows/pages.yml` à chaque changement sur `master`.
Activation (une seule fois) : **Settings → Pages → Source : GitHub Actions**. Aperçu local : `php -S 127.0.0.1:9000 -t site`.

## Licence

MIT
