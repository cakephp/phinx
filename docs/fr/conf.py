import sys, os

# Append the top level directory of the docs, so we can import from the config dir.
sys.path.insert(0, os.path.abspath('..'))

# Pull in all the configuration options defined in the global config file..
from config.all import *

language = 'fr'
