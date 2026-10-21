#!/bin/sh
set -eu

# Correctifs du 21 octobre 2026 : PHP 8.4.27 et Node.js 22.23.4.
for runtime in "$@"; do
    case "$runtime" in
        php)
            php -r '
                if (PHP_VERSION_ID < 80427 || PHP_VERSION_ID >= 80500) {
                    fwrite(STDERR, "PHP 8.4.27 ou plus recent dans la branche 8.4 est requis; version actuelle: " . PHP_VERSION . PHP_EOL);
                    exit(1);
                }
            '
            ;;
        node)
            node -e '
                const [major, minor, patch] = process.versions.node.split(".").map(Number);
                if (major !== 22 || minor < 23 || (minor === 23 && patch < 4)) {
                    console.error(`Node.js 22.23.4 ou plus recent dans la branche 22 est requis; version actuelle: ${process.version}`);
                    process.exit(1);
                }
            '
            ;;
        *)
            echo "Runtime inconnu: $runtime" >&2
            exit 2
            ;;
    esac
done
