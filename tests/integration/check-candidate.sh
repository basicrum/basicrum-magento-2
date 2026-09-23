#!/usr/bin/env sh
set -eu

module_root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd -P)
repository_root=$(git -C "$module_root" rev-parse --show-toplevel)
if [ "$repository_root" != "$module_root" ]; then
    echo "Run the release gate from the module's own Git checkout." >&2
    exit 1
fi
changes=$(git -C "$module_root" status --porcelain --untracked-files=all)
if [ -n "$changes" ]; then
    echo "The release candidate must be clean: commit or remove pending changes before certification." >&2
    exit 1
fi
git -C "$module_root" rev-parse --verify HEAD
