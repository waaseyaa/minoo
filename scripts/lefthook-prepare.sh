#!/bin/sh
# Runs from npm "prepare". Migrates repos that used Husky (core.hooksPath=.husky/_)
# so Lefthook can install into .git/hooks.
set -e
if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  exit 0
fi
hooks_path=$(git config --get core.hooksPath 2>/dev/null || true)
case "$hooks_path" in
  *husky*) git config --unset core.hooksPath ;;
esac
exec lefthook install -f
