#!/bin/bash
set -e

VENDOR_DIR=/app/vendor/

function prepareConfig {
	cat phpunit.xml.dist |
	sed 's/localhost/mysql/' | sed 's/127.0.0.1/postgres/' > phpunit.xml.docker
	trap shutdown EXIT
}

function printHelp {
	echo "Available commands: {install, phpunit, bash}"
	echo
	echo " - install: install all dependencies using composer freshly"
	echo " - quick-install: copy all dependencies from the docker image"
	echo " - phpunit: run unit tests"
	echo " - bash: drop into shell"
	exit 1
}

function installCommand  {
	prepareConfig
	echo "Installing dependencies using composer:"
	composer update --no-interaction --prefer-source --prefer-stable \
		--prefer-lowest
	echo "done installing dependencies"
}

function quickInstallCommand {
	echo "Installing vendor dir from docker image"
	rm -rf $VENDOR_DIR 2> /dev/null || true
	cp -r /vendor_preinstalled/vendor/ $VENDOR_DIR
	echo "Done installing vendor dir"
}

function phpunitCommand  {
	prepareConfig
	if [ ! -d $VENDOR_DIR ]; then
		quickInstallCommand
	fi

	php vendor/bin/phpunit --config phpunit.xml.docker
}

function bashCommand {
	prepareConfig
	bash
}

function shutdown {
	rm phpunit.xml.docker 2> /dev/null || true
}

key="$1"

case $key in
	quick-install)
		quickInstallCommand
		;;

	install)
		installCommand
		;;

	phpunit)
		phpunitCommand
		;;

	bash)
		bashCommand
		;;
	*)
		printHelp
esac

