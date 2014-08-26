#! /bin/bash
# Script to install Phinx
# Requirements: php, curl, phar

command_exists () {
	type "$1" &> /dev/null ;
}

need_message () {
	echo "You need $1 installed to run install.sh"
}

exists_message () {
	echo "$1 existed"
}

# TODO needs to be nicer
if command_exists git; then
	exists_message "Git"
	if command_exists php; then
		exists_message "PHP"
		if command_exists phar; then
			exists_message "phar"
			if command_exists curl; then
				exists_message "Curl"
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
				php composer.phar install
				# Install Box
				curl -s http://box-project.org/installer.php | php
				# Create Phar archive
				php box.phar build
				# Move to executable path
				if mv phinx*.phar /usr/bin/phinx; then
					echo "## Installation complete!"
				else
					echo "Trying moving with sudo"
					if sudo mv phinx*.phar /usr/bin/phinx; then
						echo "## Installation complete!"
					else
						echo "Failed with installing to selected directory..."
						exit
					fi
				fi
				# Remove temporary install folder
				#rm -rf /tmp/phinx_install
			else
				need_message "curl"
			fi
		else
			need_message "phar"
		fi
	else
		need_message "PHP"
	fi
else
	need_message "Git"
fi
