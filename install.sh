#! /bin/bash

# Script to install Phinx
# Requirements: php, curl, phar

requirements=(git php phar curl)

command_exists () {
	type "$1" &> /dev/null ;
}

need_message () {
	echo "You need $1 installed to run install.sh"
}

exists_message () {
	echo "$1 existed"
}


for i in ${requirements[@]}; do
	if command_exists "$i"; then
		exists_message "$i"
	else
		need_message "$i"
		exit 1
	fi
done

echo "Beginning installation"
# Create temporary install folder
mkdir /tmp/phinx_install
cd /tmp/phinx_install
# Clone phinx
git clone git://github.com/robmorgan/phinx.git
cd phinx
# Install Composer
curl -s https://getcomposer.org/installer | php
# Install Phinx dependencies
php composer.phar install --no-dev
# Install Box
curl -LSs http://box-project.org/installer.php | php
# Create Phar archive
if php box.phar build; then
	# Move to executable path
	# TODO needs to be changable
	if mv phinx*.phar /usr/bin/phinx; then
		echo "## Installation complete!"
	else
		echo "Trying moving with sudo"
		# TODO needs to be changable
		if sudo mv phinx*.phar /usr/bin/phinx; then
			echo "## Installation complete!"
		else
			echo "Failed with installing to selected directory..."
			exit
		fi
	fi
else
	echo "## Building the box failed, please read the error and retry installation once fixed"
fi
# Remove temporary install folder
rm -rf /tmp/phinx_install
