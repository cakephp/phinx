Phinx Migrations
################

Phinx est un utilitaire autonome en ligne de commande pour gérer les Migrations de bases de données. Le plugin officiel de Migrations de CakePHP est basé sur cet outil.

Phinx rend ridiculement simple la gestion des migrations de bases de données pour vos applications PHP. En moins de 5 minutes,
vous pouvez installer Composer et créer votre première migration de base de données. Phinx ne s'occupe que de la migration des
bases de données, il laisse de côté les aspects ORM de la base de données et le cadre applicatif.

Introduction
============

Les bons développeurs gèrent toujours leurs codes sources avec un outil de gestion de versions,
alors pourquoi ne feraient-ils pas la même chose avec leurs schémas de bases de données.

Phinx permet aux développeurs de modifier et de manipuler leurs bases de données d'une façon claire et concise. Il évite
d'avoir à écrire du SQL à la main et offre à la place une API puissante pour écrire des scripts de migration en utilisant PHP.
Les développeurs peuvent alors versionner ces fichiers de migration en utilisant leur outil de versionnement préféré. Cela rend
les migrations Phinx indépendantes des moteurs de base de données. Phinx conserve la trace des migrations précédentes, cela
permet de se concentrer un peu plus sur l'amélioration de votre application et un peu moins sur l'état de votre base de
données.

Objectifs
=========

Phinx a été développé avec les objectifs suivants en tête :

* Être portable entre les principaux moteurs de base de données.
* Être indépendant de tout framework PHP.
* Être aisement installable.
* Être utilisable facilement en ligne de commande.
* Être intégrable avec d'autres outils PHP (Phing, PHPUnit) et des frameworks web.

Installation
============

Phinx devrait être installé en utilisant Composer, qui est un outil pour la gestion des dépendances en PHP. Visiter le site
internet de `Composer <https://getcomposer.org/>`_ pour avoir plus d’informations.

.. note::

    Phinx a besoin au minimum de PHP 5.4 (ou supérieur)

Pour installer Phinx, il suffit simplement de l'appeler via Composer

.. code-block:: bash

    php composer.phar require robmorgan/phinx

Créez les dossiers ``db/migrations`` dans votre projet en vous assurant que les droits sont bien configurés.
C’est à cet endroit que vos fichiers de migrations devraient être créés et laissés.

Phinx peut maintenant être exécuté depuis la racine de votre projet.

.. code-block:: bash

    vendor/bin/phinx init
