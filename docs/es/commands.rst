. index::
   single: Commands

Comandos
########

Phinx se corre usando un numero de comandos.

Migracion de Comandos
=====================

Comando de Inicio

El comanado de inicio (manera corta de inicialización) se usa para preparar el proyecto Phinix. Este comando genera el archivo phinx.yml en el directorio raiz del proyecto:

.. code-block:: bash

    $ cd yourapp
    $ phinx init .

Abre este archivo en tu editor de texto para configurar tu projecto. Por favot mira el :doc:`Configuration <configuration>` para mas información.

Comando de creación.
--------------------

El comando de creación se usa para crear un nuevo archivo de migración. Requiere un argumento: el nombre de la migración. El nombre de la migracion debera especificarse en el formato CamelCase:

.. code-block:: bash

    $ phinx create MyNewMigration

Abre el nuevo archivo de migración en tu editor de texto  para agregar las transformaciones de tu base de datos. Phinx crea archivos de migracion usando direcciones especificas en tu archivo phinx.yml . Por favor mire :doc:`Configuration <configuration>` para mas información. 

Usted puede sobreescribir el archivo modelo usado por Phinx suministrando una alternativa del modelo de archivo:

.. code-block:: bash

    $ phinx create MyNewMigration --template="<file>"

Tambien puedes suministrar un modelo de clase general. Esta clase deberá implementar la interfaz ``Phinx\Migration\CreationInterface``.

.. code-block:: bash

    $ phinx create MyNewMigration --class="<class>"

Además de proveer el modelo para la migracion, la clase tambien puede definir una "callback" que sera llamada una vez que el archivo de migración haya sido generado por el modelo. 

No puede usar ``--template`` y ``--class`` juntos.

El comando de migración
-----------------------

El comando de migración corre todas las migraciones que se encuentren disponibles, opcionalmente hasta una version especificada.

.. code-block:: bash

    $ phinx migrate -e development

Para migrar a una version especifica se utiliza el parametro ``--targe`` o ``-t`` resumido.

.. code-block:: bash

    $ phinx migrate -e development -t 20110103081132

Use ``--dry-run`` para imprimir las consultas a la salida sin ejecutarlas 

.. code-block:: bash

    $ phinx migrate --dry-run


El comando para revertir
------------------------

El comando para revertir se utiliza para deshacer las migraciones anteriores ejecutadas por Phinx. Es lo opuesto al comando Migrar.  

Puede revertir a la migración anterior usando el comando rollback sin argumentos.

.. code-block:: bash

    $ phinx rollback -e development

Para revertir todas las migraciones a una versión específica, use el parámetro ``--target`` o ``-t`` para abreviar

.. code-block:: bash

    $ phinx rollback -e development -t 20120103083322

Especificar 0 como la versión de destino revertirá todas las migraciones.

.. code-block:: bash

    $ phinx rollback -e development -t 0

Para revertir todas las migraciones a una fecha específica, use el parámetro ``--date`` o -d para abreviar.

.. code-block:: bash

    $ phinx rollback -e development -d 2012
    $ phinx rollback -e development -d 201201
    $ phinx rollback -e development -d 20120103
    $ phinx rollback -e development -d 2012010312
    $ phinx rollback -e development -d 201201031205
    $ phinx rollback -e development -d 20120103120530

Si se establece un punto de interrupción, bloqueando más reversiones, puede anular el punto de interrupción utilizando el parámetro ``--force`` o ``-f`` para abreviar

.. code-block:: bash

    $ phinx rollback -e development -t 0 -f

Utilice ``--dry-run`` para imprimir las consultas a la salida estándar sin ejecutarlas

.. code-block:: bash

    $ phinx rollback --dry-run

.. note::

    When rolling back, Phinx orders the executed migrations using the order specified in the version_order option of your phinx.yml file. Please see the :doc:`Configuration <configuration>` chapter for more information.

El comando de estado
--------------------

El comando Estado imprime una lista de todas las migraciones, junto con su estado actual. Puede usar este comando para determinar qué migraciones se han ejecutado.

.. code-block:: bash

    $ phinx status -e development

La salida de este comando es 0 si la base de datos está actualizada (es decir, todas las migraciones están activas) o uno de los siguientes códigos de lo contrario:

#. Queda por lo menos una migración por ejecutar.
#: se ejecutó una migración y se registró en la base de datos, pero ahora falta
el archivo de migración

El comando de interrupción 
---------------------------

El comando Punto de interrupción se usa para establecer puntos de interrupción, lo que le permite limitar los retrotracción. Puede alternar el punto de interrupción de la migración más reciente al no proporcionar ningún parámetro

.. code-block:: bash

    $ phinx breakpoint -e development

Para alternar un punto de interrupción en una versión específica, use el parámetro ``--target`` o ``-t`` para abreviar

.. code-block:: bash

    $ phinx breakpoint -e development -t 20120103083322

Puede eliminar todos los puntos de interrupción utilizando el parámetro ``--remove-all`` o ``-r`` para abreviar.

.. code-block:: bash

    $ phinx breakpoint -e development -r

Los puntos de interrupción son visibles cuando ejecuta el comando de estado.

"Database Seeding"
------------------

El comando Crear semilla se puede usar para crear nuevas clases de base de datos. Requiere un argumento, el nombre de la clase. El nombre de la clase debe especificarse en formato CamelCase.

.. code-block:: bash

    $ phinx seed:create MyNewSeeder

Abra el nuevo archivo semilla en su editor de texto para agregar los comandos semilla de su base de datos. Phinx crea archivos semilla utilizando la ruta especificada en su archivo phinx.yml. Consulte el capítulo: doc: `Configuration <configuración>` para obtener más información.

El comando de ejecución de semillas
-----------------------------------

El comando Seed Run ejecuta todas las clases semilla disponibles u opcionalmente solo una.

.. code-block:: bash

    $ phinx seed:run -e development

Para ejecutar solo una clase semilla, use el parámetro ``--seed`` o ``-s`` para abreviar.

.. code-block:: bash

    $ phinx seed:run -e development -s MyNewSeeder


Parámetro del archivo de configuración
--------------------------------------

Al ejecutar Phinx desde la línea de comandos, puede especificar un archivo de configuración usando el parámetro ``--configuration`` o ``-c``. Además de YAML, el archivo de configuración puede ser la salida calculada de un archivo PHP como una matriz de PHP::

    <?php
        return [
            "paths" => [
                "migrations" => "application/migrations"
            ],
            "environments" => [
                "default_migration_table" => "phinxlog",
                "default_database" => "dev",
                "dev" => [
                    "adapter" => "mysql",
                    "host" => $_ENV['DB_HOST'],
                    "name" => $_ENV['DB_NAME'],
                    "user" => $_ENV['DB_USER'],
                    "pass" => $_ENV['DB_PASS'],
                    "port" => $_ENV['DB_PORT'],
                ]
            ]
        ];

Phinx detecta automáticamente qué analizador de idioma usar para los archivos con las extensiones ``.yml`` y ``.php``. El analizador apropiado también se puede especificar a través de los parámetros ``--parser`` y ``-p``. Cualquier otra cosa que no sea "php" se trata como YAML.

Al usar una matriz de PHP, puede proporcionar una clave de conexión con una instancia de PDO existente. También es importante pasar el nombre de la base de datos, ya que Phinx lo requiere para ciertos métodos como ``hasTable()``::

    <?php
        return [
            "paths" => [
                "migrations" => "application/migrations"
            ],
            "environments" => [
                "default_migration_table" => "phinxlog",
                "default_database" => "dev",
                "dev" => [
                    "name" => "dev_db",
                    "connection" => $pdo_instance
                ]
            ]
        ];

Ejecutando Phinx en una aplicación web
--------------------------------------

Phinx también se puede ejecutar dentro de una aplicación web utilizando la clase ``Phinx\Wrapper\TextWrapper``. Un ejemplo de esto se proporciona en **app/web.php**, que se puede ejecutar como un servidor independiente:

.. code-block:: bash

    $ php -S localhost:8000 vendor/robmorgan/phinx/app/web.php

Esto creará un servidor web local en http://localhost:8000 que mostrará el estado actual de la migración de forma predeterminada. Para ejecutar las migraciones, use http://localhost:8000/migrate y para revertir use http: // localhost: 8000 / rollback.

La aplicación web incluida es solo un ejemplo y no debe utilizarse en producción


.. note::
    Para modificar las variables de configuración en tiempo de ejecución
    e invalidar ``%%PHINX_DBNAME%%`` u otra opción dinámica, establezca ``$
    _SERVER['PHINX_DBNAME']`` antes de ejecutar los comandos. Las opciones
    disponibles están documentadas en la página de Configuración.

Usando Phinx con PHPUnit
------------------------

Phinx puede usarse dentro de las pruebas de su unidad para preparar o sembrar la base de datos. Puedes usarlo programáticamente::

    public function setUp ()
    {
      $app = new PhinxApplication();
      $app->setAutoExit(false);
      $app->run(new StringInput('migrate'), new NullOutput());
    }

Si usa una base de datos de memoria, deberá darle a Phinx una instancia de PDO específica. Puedes interactuar con Phinx directamente usando la clase Manager::

    use PDO;
    use Phinx\Config\Config;
    use Phinx\Migration\Manager;
    use PHPUnit\Framework\TestCase;
    use Symfony\Component\Console\Input\StringInput;
    use Symfony\Component\Console\Output\NullOutput;

    class DatabaseTestCase extends TestCase {

        public function setUp ()
        {
            $pdo = new PDO('sqlite::memory:', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $configArray = require('phinx.php');
            $configArray['environments']['test'] = [
                'adapter'    => 'sqlite',
                'connection' => $pdo
            ];
            $config = new Config($configArray);
            $manager = new Manager($config, new StringInput(' '), new NullOutput());
            $manager->migrate('test');
            $manager->seed('test');
            // You can change default fetch mode after the seeding
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
            $this->pdo = $pdo;
        }

    }
