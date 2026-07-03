# -*- coding: utf-8 -*-
#
# Standalone Sphinx configuration for the GitHub Pages build.
#
# Unlike docs/en/conf.py (which pulls in the CakePHP docs theme via
# cakephpsphinx for the apps.cakephp.org deployment), this config produces a
# self-contained, vanilla Sphinx site using the Furo theme. It is published to
# the `docs/` subpath of the `gh-pages` branch by
# .github/workflows/deploy_docs_ghpages.yml.
#
# Build with:
#   sphinx-build -b html -c docs/gh-pages docs/en <output-dir>

project = 'Phinx'
copyright = '2012, Rob Morgan'
author = 'Rob Morgan'

# Keep in sync with docs/config/all.py
release = '0.16'
version = '0.16'

extensions = []

# index.rst is the landing page and the root document, so the "Phinx
# Documentation" brand link in the sidebar points at index.html. (contents.rst
# is the root toctree used by the CakePHP build and is excluded here.)
root_doc = 'index'

source_suffix = '.rst'
language = 'en'

exclude_patterns = ['_build', 'Thumbs.db', '.DS_Store', 'contents.rst']

# -- HTML output -------------------------------------------------------------

html_theme = 'furo'
html_title = 'Phinx Documentation'

html_theme_options = {
    'source_repository': 'https://github.com/cakephp/phinx',
    'source_branch': '0.x',
    'source_directory': 'docs/en/',
}
