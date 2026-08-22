import sys
import os

# Make app/ modules importable in tests
sys.path.insert(0, os.path.abspath(os.path.dirname(__file__)))
