## Sommaire

<!--ts-->
* [Introduction](#introduction)
* [Installation](#installation)
  * [Pre-requis](#pre_requis)
  * [Procedure d'installation](#proced)
* [Documentation](#docs)
    * [Structure des dossiers](#structure)
    * [Utilisation du model](#model_usage)
    * [Gestion des vues](#gestion_vue)
    * [Mise en place du controller](#controller)
    * [Routing](#routing)
    * [Base de données](#bdd)
<!--te-->
    
<a name="introduction"></a>
## Introduction

MPHP : Aime - PHP. C'est un Framework open source qui permet au développeur web de développer une application web rapidement.

<a name="installation"></a>
## Installation

<a name="pre_requis"></a>
### I - Pre-requis :

1 - Docker (Installation sur : [Documentation Docker](https://docs.docker.com/engine/install/)) <br/>
2 - Docker compose (Installation sur [Documentation Docker compose](https://docs.docker.com/compose/install/))

<a name="proced"></a>
### II - Procedure d'installation

1 - Cloner le projet

`$ git clone https://github.com/MandaNyAina/mphp`

2 - Accéder dans le dossier mphp

`$ cd mphp/`

3 - Puis lancer tout simplement Docker compose par la commande suivante

`$ docker-compose up`

<a name="docs"></a>
## Documentation

<a name="structure"></a>
### I - Structure des dossiers

![](https://raw.githubusercontent.com/MandaNyAina/mphp/images/structure_dossier.png)

Specification pour chaque dossier :

| Dossier 	| Rôle                                                                                                                            	| Pour le développeur 	|
|---------	|---------------------------------------------------------------------------------------------------------------------------------	|---------------------	|
| assets  	| Dossier pour les fichiers partagés, comme les fichiers de style (css),<br>script (js/ts), icons, images, ...                    	|         Oui         	|
| config  	| Dossier de configuration pour l'application                                                                                     	|         Oui         	|
| core    	| Dossier qui contient les codes du Framework et Plugins, comme<br>gestionnaire du controller, model, ...                         	|         Non         	|
| helpers 	| Dossier des helpers                                                                                                             	|         Oui         	|
| routes  	| Dossier qui contient le fichier du route                                                                                        	|         Oui         	|
| src     	| Dossier qui contient les codes sources de l'application pour les 3<br>couches :<br><br>- Controllers<br>- Models<br>- Templates 	|         Oui         	|
| vendor  	| Dossier qui contient les modules installés et l'autoload du fichier.                                                            	|         Non         	|

<a name="model_usage"></a>
### II - Utilisation du model

Pour le fichier de modèle, il faut mettre les fichiers de modèle dans le dossier `src/Models` comme dans l'exemple suivant :

![](https://raw.githubusercontent.com/MandaNyAina/mphp/images/model_dossier.png)

Puis le code du modèle doit être :

![](https://raw.githubusercontent.com/MandaNyAina/mphp/images/model_file.png)

**Comment récupérer des informations dans la base de données ?** <br>

On peut connecter avec la base de données par l'attribut `db`. Information sur la base de donnees [ici](#bdd)

<a name="gestion_vue"></a>
### III - Gestion des vues

MPHP utilise [AngularJS](https://docs.angularjs.org/tutorial) comme librairie Front. Le fichier de la vue se trouve dans `src/Templates` :

![](https://raw.githubusercontent.com/MandaNyAina/mphp/images/view_dossier.png)

Puis la gestion des variables se fait comme :

![](https://raw.githubusercontent.com/MandaNyAina/mphp/images/view_file.png)

La vue peut être un fichier`.html` ou`.php`, et doit imperativement dans le dossier template.

**Comment se passe avec l'interpolation ?** <br>

Pour l'interpolation de la vue avec MPHP, on utilise #[], par exemple, pour afficher dans #[ user], on appelle load view dans contrôleur : `$this->render(view path, $data)` avec `$data = ['user' => "valeur"]

**Les vues pour afficher une erreur** <br>

| Dossier 	| Rôle                                                                                                                          	|
|---------	|-------------------------------------------------------------------------------------------------------------------------------	|
| 403/    	| Contient le fichier `forbidden.html`, pour afficher une erreur quand l'utilisateur <br>accède à un service restreint.           	|
| 404/    	| Contient le fichier `notfound.html`, pour afficher une erreur quand l'utilisateur <br>tente d’accéder à une route non existante 	|
| 500/    	| Contient le fichier `errorserver.html`, pour afficher une erreur quand le serveur <br>rencontre un problème                     	|

Ces fichiers peuvent être modifiés par rapport au design de l'application.

**Comment utiliser la vue ?** <br>

La vue est appelée dans le controller. Cette question sera vite répondue dans la section suivante.

<a name="controller"></a>
### IV - Mise en place du controller

Il faut mettre les fichiers de contrôleur dans le dossier `src/Controllers` comme dans l'exemple suivant :

![](https://raw.githubusercontent.com/MandaNyAina/mphp/images/controller_dossier.png) 

Puis le code du contrôleur doit être ressemblé à :

![](https://raw.githubusercontent.com/MandaNyAina/mphp/images/controller_file.png)

Le nom du fichier devra identique au nom du controller et le namespace doit refléter les répertoires pour accéder dans le fichier par exemple si le nom du controller est `TestController`, donc :

- le fichier doit être `TestController.php`
- le fichier se trouve dans `src/Controllers/TestController/TestController.php`
- le namespace doit être `namespace App\Controllers\TestController;`

**Comment loader un model ?** <br>

On peut instancier le modèle dans le constructeur du controller, par exemple pour inclure le modèle `TestModel`: 
> On doit créer un attribut privé de la class du controller `private TestModel $test model;` puis initier la valeur par `$this->test_model = $this-get_model('TestModel');` dans __construct du controller

**Comment afficher une vue ?** <br>

On peut rendre directement la vue en utilisant `$this->render(view_path, $data)` dans le controller, par exemple si le template se trouve dans le `Templates/teste/testeViews.php` donc : 

>`view_path => teste/testeViews` et `$data = [ 'user' => 'valeur' ]`<br>

**Comment envoyer une réponse au client (API Rest) ?** <br>

Pour renvoyer une réponse pour une solution RestAPI avec MPHP : 

> On peut faire directement `$this-send_response(message, status_code, $data);`, avec :

- `message` : le message qu'on souhaite mettre dans la réponse
- `status_code` : code de réponse HTTP, on utilise directement les variables constantes dans le dossier `config/Constant.php`. Par exemple `HTTP_OK` pour une réponse 200
- `$data` : contient les données de retour.

Un exemple concret :

Code :

> `$this->send_response("Get name with data in db", HTTP_OK, array_merge($data, [[ 'name' => $req->GET->name ]] ));`

Puis le résultat :

`{
    "message": "Get name with data in db",
    "status": 200,
    "data": [
        {
            "id": "1",
            "created_at": "2022-01-14 11:48:23"
        },
        {
            "id": "2",
            "created_at": "2022-01-16 12:14:22"
        },
        {
            "name": "koto"
        }
    ]
}`

<a name="routing"></a>
### V - Routing

Le fichier de route se trouve dans le répertoire `routes/Routes.php`.

![](https://raw.githubusercontent.com/MandaNyAina/mphp/images/route_dossier.png)

Et le fichier comporte l'instanciation du module MainRoutes

![](https://raw.githubusercontent.com/MandaNyAina/mphp/images/route_file.png)

> On peut appeler la methode par `$routes->method(path, "controller#method");`

- paramètre 1 : permet de specifier le route puis la valeur du controller dans
- paramètre 2 : Le controller est définie par le nom de la class du controller et la methode du class, par exemple si on a `"TestController#get_name"`,

> Cela signifie que le controller est `TestController` et la méthode appelée sera `get_name`.

On a les 4 méthodes principales des routes :

- `$routes->get("/get_name", "TestController#get_name");` : methode GET
- `$routes->post("/set_name", "TestController#insert_name");` : methode POST
- `$routes->put("/update_name", "TestController#update_name");` : methode PUT
- `$routes->delete("/delete_name", "TestController#delete_name");` : methode DELETE

<a name="bdd"></a>
### VI - Base de données

On peut utiliser la base de donnees dans le model en appelant la methode `db`.

#### 1 - Methode select

Permet de faire une requête `select` dans la base de donnees, par exemple pour faire une requête select dans la table `test_db` :

> $this->db->select('test_table');

Puis pour specifier les colonnes, on peut faire :

> `$this->db->select('test_table', 'id');` qui retourne uniquement la colonne `id`

Et pour filtrer une requête, ou qu'on souhaite renforcer la requête donc :

> `$this->db->select('test_table', 'id', 'id = 1');` qui retourne uniquement la colonne `id` dont la valeur est 1

#### 2 - Methode insert

Une requête insert afin de faire une insertion de valeur dans la base de donnees, le code est simple :

> `$this->db->insert(nom_table, data);` avec `data` est de type array.

Par exemple, pour insérer une valeur dans 'test_table' :

> `$this->db->insert('test_table', [ 'id' => 3, 'created_at' => date('Y-m-d H:i:s') ]);`

#### 3 - Methode update

Une requête update afin de faire mettre à jour des valeurs dans la base de donnees, on peut le faire comme suit :

> `$this->db->update(nom_table, data, selecteur);` dont `data` est de type array, `selecteur` le filtre par exemple `id = 1`.

Par exemple, pour mettre à jour la date du `id=3` dans la 'test_table' :

> `$this->db->update('test_table', [ 'created_at' => date('Y-m-d H:i:s') ], 'id = 3');`

Le sélecteur n'est pas obligatoire, mais le fait de ne pas le mettre, mettre à jour toutes les données par la valeur envoyée

#### 4 - Methode delete

C'est la méthode utilisée pour supprimer une valeur dans la base de données.

> `$this->db->delete(nom_table, selecteur);` dont le `selecteur` est le filtre par exemple `id = 1`.

Par exemple, pour supprimer l'`id=3` dans la 'test_table' :

> `$this->db->delete('test_table', 'id = 3');`

#### 5 - Requête getLastRow

Une requête qui permet de récupérer la dernière ligne insérer dans la base de données. C'est simple :

>  `$this->db->getLastRow(nom_table, colonne);`, avec colonne n'est pas obligatoire.

Par exemple, pour récupérer the last row pour la table 'test_table' :

> `$this->db->delete('test_table');`

#### 6 - Any requête

Quand on souhaite exécuter un script SQL, on peut lancer :

> `$this->db->execute(query);`

Par exemple, pour récupérer les valeurs dans la table 'test_table' :

> `$this->db->execute("SELECT * FROM 'test_table'");`


