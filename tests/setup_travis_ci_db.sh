#!/bin/sh
rm TestConfiguration.php
cp -v TestConfiguration.php.dist TestConfiguration.php
sed -e 's/@db_adapter_mysql_enabled@/true/g' -e 's/@db_adapter_mysql_host@/127.0.0.1/g' -e 's/@db_adapter_mysql_username@/root/g' -e 's/@db_adapter_mysql_password@//g' -e 's/@db_adapter_mysql_database@/phinx_testing/g' -e 's/@db_adapter_mysql_port@/3306/g' TestConfiguration.php >> TestConfiguration.php
#sed  TestConfiguration.php >> TestConfiguration.php
#sed  TestConfiguration.php >> TestConfiguration.php
#sed  TestConfiguration.php >> TestConfiguration.php
#sed  TestConfiguration.php >> TestConfiguration.php
#sed  TestConfiguration.php >> TestConfiguration.php
cat TestConfiguration.php