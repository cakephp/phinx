# -*- coding: utf-8 -*-
#
# Phinx documentation build configuration file, created by
# sphinx-quickstart on Thu Jun 14 17:39:42 2012.
#

# Import the base theme configuration
from cakephpsphinx.config.all import *

# The full version, including alpha/beta/rc tags.
release = '0.13.x'

# The search index version.
search_version = 'phinx-0.13'

# The marketing display name for the book.
version_name = ''

# Project name shown in the black header bar
project = 'Phinx'

# Other versions that display in the version picker menu.
version_list = [
    {'name': '0.13', 'number': '/phinx/0.13', 'title': '0.13', 'current': True},
    {'name': '0.14', 'number': '/phinx/0.14', 'title': '0.14'},
    {'name': '0.15', 'number': '/phinx/0.15', 'title': '0.15'},
    {'name': '0.16', 'number': '/phinx/0.16', 'title': '0.16'},
]

# Languages available.
languages = ['en']

# The GitHub branch name for this version of the docs
# for edit links to point at.
branch = '0.13.x'

# Current version being built
version = '0.13'

show_root_link = True

repository = 'cakephp/phinx'

source_path = 'docs/'

hide_page_contents = ('search', '404', 'contents')
