#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
python3 -c 'import sys; assert (3,12) <= sys.version_info[:2] <= (3,14), "Use Python 3.12, 3.13 or 3.14"'
python3 -m venv .venv-training
.venv-training/bin/python -m pip install --only-binary=:all: --requirement requirements-training.txt
.venv-training/bin/python -m pip check
.venv-training/bin/python -c 'import catboost,sys; print("Training runtime:",sys.version.split()[0],"CatBoost",catboost.__version__)'
.venv-training/bin/python -m collector prepare-training --help
