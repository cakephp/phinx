# Upgrading Phinx to 0.9

* Template Creation is now supported for use by Migrations, Repeatable Migrations and Seeds.

  Some renaming of the interface, abstract base class and methods has
  been undertaken so that the names are not tied specifically to
  Migrations.

  The following elements have been renamed:

  * Interface

    Phinx 0.8

        `Phinx\Migration\CreationInterface`

    Phinx 0.9

        `Phinx\Templates\TemplateCreationInterface`.

  * Class

    Phinx 0.8

        `Phinx\Migration\AbstractTemplateCreation`

    Phinx 0.9

        `Phinx\Templates\AbstractTemplateCreation`.

  * Methods

    Phinx 0.8:

        `Phinx\Migration\CreationInterface\getMigrationTemplate()`

        `Phinx\Migration\CreationInterface\postMigrationCreation($migrationFilename, $className, $baseClassName)`

    Phinx 0.9

        `Phinx\Templates\TemplateCreationInterface\getTemplate()`

        `Phinx\Templates\TemplateCreationInterface\postTemplateCreation($filename, $className, $baseClassName)`
