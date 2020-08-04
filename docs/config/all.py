# -*- coding: utf-8 -*-
#
# Phinx documentation build configuration file, created by
# sphinx-quickstart on Thu Jun 14 17:39:42 2012.
#

# Import the base theme configuration
from cakephpsphinx.config.all import *

# The full version, including alpha/beta/rc tags.
release = '0.12.x'

# The search index version.
search_version = 'phinx-0'

# The marketing display name for the book.
version_name = ''

# Project name shown in the black header bar
project = 'Phinx'

# Other versions that display in the version picker menu.
version_list = [
    {'name': '0.12', 'number': '/phinx/0', 'title': '0.12', 'current': True},
]

# Languages available.
languages = ['en', 'es', 'fr', 'ja']

# The GitHub branch name for this version of the docs
# for edit links to point at.
branch = 'master'

# Current version being built
version = '0.12'

show_root_link = True

repository = 'cakephp/phinx'

source_path = 'docs/'

hide_page_contents = ('search', '404', 'contents')
