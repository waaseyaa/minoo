<?php

declare(strict_types=1);

/**
 * scripts/check-implicit-array-params.php
 *
 * Regression guard: detect controller methods that accept `array $params` or
 * `array $query` parameters WITHOUT the explicit `#[MapRoute]` / `#[MapQuery]`
 * attributes. After mission #753 migration, this script must exit 0 with no
 * offenders. Re-introducing an implicit-array parameter in any controller will
 * cause the script to exit 1.
 *
 * Pure PHP CLI: no Composer autoload, no framework boot. Uses token_get_all
 * for accurate detection (no false matches in docblocks / strings / closures).
 *
 * See kitty-specs/migrate-controllers-explicit-route-attributes-01KQYNX7/contracts/check-cli.md
 */

const EXIT_OK = 0;
const EXIT_OFFENDERS = 1;
const EXIT_ARGUMENT = 2;
const EXIT_PARSE = 3;

function usage(): void
{
    $script = basename((string) ($_SERVER['argv'][0] ?? 'check-implicit-array-params.php'));
    fwrite(STDOUT, <<<USAGE
        Usage: php {$script} [--path <dir>] [--format text|json] [--quiet] [--help]

        Detect controller methods using implicit `array \$params` or `array \$query`
        parameters (without the explicit #[MapRoute] / #[MapQuery] attributes).

        Options:
          --path <dir>          Root directory to scan (default: src/Controller).
          --format <text|json>  Output format (default: text).
          --quiet               Suppress per-offender lines; emit only the summary.
          --help                Print this message and exit 0.

        Exit codes:
          0  No offenders detected.
          1  At least one offender found.
          2  Argument error.
          3  Parse error in a scanned file.
        USAGE . "\n");
}

/**
 * Parse argv. Returns ['path' => string, 'format' => 'text'|'json', 'quiet' => bool] or null on error.
 *
 * @param list<string> $argv
 * @return array{path: string, format: string, quiet: bool}|null
 */
function parseArgs(array $argv): ?array
{
    $path = 'src/Controller';
    $format = 'text';
    $quiet = false;

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        switch ($arg) {
            case '--help':
            case '-h':
                usage();
                exit(EXIT_OK);
            case '--quiet':
                $quiet = true;
                break;
            case '--path':
                if (!isset($argv[$i + 1])) {
                    fwrite(STDERR, "error: --path requires a value\n");
                    return null;
                }
                $path = $argv[++$i];
                break;
            case '--format':
                if (!isset($argv[$i + 1])) {
                    fwrite(STDERR, "error: --format requires a value\n");
                    return null;
                }
                $format = $argv[++$i];
                if ($format !== 'text' && $format !== 'json') {
                    fwrite(STDERR, "error: --format must be 'text' or 'json'\n");
                    return null;
                }
                break;
            default:
                fwrite(STDERR, "error: unknown argument {$arg}\n");
                return null;
        }
    }

    return ['path' => $path, 'format' => $format, 'quiet' => $quiet];
}

/**
 * @return list<string>
 */
function discoverFiles(string $rootPath): array
{
    if (!is_dir($rootPath)) {
        return [];
    }
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS));
    $files = [];
    foreach ($iter as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

/**
 * Scan one file for offenders.
 *
 * @return list<array{fqcn: string, method: string, parameter: string, recommended: string}>
 */
function scanFile(string $path): array
{
    $source = file_get_contents($path);
    if ($source === false) {
        throw new RuntimeException("could not read file: {$path}");
    }

    try {
        $tokens = token_get_all($source, TOKEN_PARSE);
    } catch (Throwable $e) {
        throw new RuntimeException("parse error in {$path}: {$e->getMessage()}");
    }

    $namespace = '';
    $class = '';
    $offenders = [];

    $count = count($tokens);
    $braceDepth = 0;
    $parenDepth = 0;
    $inClass = false;

    for ($i = 0; $i < $count; $i++) {
        $tok = $tokens[$i];

        if (is_array($tok)) {
            $id = $tok[0];

            // Capture namespace (sequence of T_STRING + T_NAME_QUALIFIED + T_NS_SEPARATOR up to ;)
            if ($id === T_NAMESPACE) {
                $namespace = '';
                $j = $i + 1;
                while ($j < $count) {
                    $t = $tokens[$j];
                    if (!is_array($t)) {
                        if ($t === ';' || $t === '{') {
                            break;
                        }
                    } else {
                        $tid = $t[0];
                        if ($tid === T_STRING || $tid === T_NAME_QUALIFIED || $tid === T_NS_SEPARATOR) {
                            $namespace .= $t[1];
                        }
                    }
                    $j++;
                }
                continue;
            }

            if ($id === T_CLASS) {
                $j = $i + 1;
                while ($j < $count) {
                    $t = $tokens[$j];
                    if (is_array($t) && $t[0] === T_STRING) {
                        $class = $t[1];
                        break;
                    }
                    if (!is_array($t) && $t === '{') {
                        break;
                    }
                    $j++;
                }
                $inClass = true;
                continue;
            }

            if ($id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $braceDepth++;
                continue;
            }

            if ($id === T_FUNCTION) {
                if (!$inClass || $braceDepth !== 1 || $parenDepth !== 0) {
                    continue;
                }

                // Look ahead for method name + opening (
                $methodName = '';
                $j = $i + 1;
                while ($j < $count) {
                    $t = $tokens[$j];
                    if (is_array($t) && $t[0] === T_STRING) {
                        $methodName = $t[1];
                    }
                    if (!is_array($t) && $t === '(') {
                        break;
                    }
                    $j++;
                }
                if ($j >= $count) {
                    continue;
                }

                // Walk param list. Track each "slot" between commas at depth 1.
                $depth = 1;
                $slotStart = $j + 1;
                $k = $j + 1;
                while ($k < $count) {
                    $t = $tokens[$k];
                    if (is_array($t)) {
                        $k++;
                        continue;
                    }
                    if ($t === '(' || $t === '[' || $t === '{') {
                        $depth++;
                        $k++;
                        continue;
                    }
                    if ($t === ')' || $t === ']' || $t === '}') {
                        $depth--;
                        if ($depth === 0) {
                            $offender = inspectSlot($tokens, $slotStart, $k);
                            if ($offender !== null) {
                                $offenders[] = [
                                    'fqcn' => ($namespace !== '' ? $namespace . '\\' : '') . $class,
                                    'method' => $methodName,
                                    'parameter' => $offender['name'],
                                    'recommended' => $offender['attribute'],
                                ];
                            }
                            $i = $k;
                            break;
                        }
                        $k++;
                        continue;
                    }
                    if ($t === ',' && $depth === 1) {
                        $offender = inspectSlot($tokens, $slotStart, $k);
                        if ($offender !== null) {
                            $offenders[] = [
                                'fqcn' => ($namespace !== '' ? $namespace . '\\' : '') . $class,
                                'method' => $methodName,
                                'parameter' => $offender['name'],
                                'recommended' => $offender['attribute'],
                            ];
                        }
                        $slotStart = $k + 1;
                        $k++;
                        continue;
                    }
                    $k++;
                }
                continue;
            }
        } else {
            if ($tok === '{') {
                $braceDepth++;
            } elseif ($tok === '}') {
                $braceDepth--;
                if ($braceDepth === 0) {
                    $inClass = false;
                }
            } elseif ($tok === '(') {
                $parenDepth++;
            } elseif ($tok === ')') {
                $parenDepth--;
            }
        }
    }

    return $offenders;
}

/**
 * Inspect one parameter slot. Return offender info if this slot has bare
 * `array $params` or `array $query` without the matching attribute, else null.
 *
 * @return array{name: string, attribute: string}|null
 */
function inspectSlot(array $tokens, int $start, int $end): ?array
{
    // Walk slot tokens; track attribute presence, type token, variable name, disqualifiers.
    $sawMapRoute = false;
    $sawMapQuery = false;
    $arrayType = false;
    $variableName = null;
    $disqualified = false;

    for ($i = $start; $i < $end; $i++) {
        $tok = $tokens[$i];

        if (is_array($tok)) {
            $id = $tok[0];
            $text = $tok[1];

            if ($id === T_ATTRIBUTE) {
                // Attribute block opens with #[ — find matching ] and inspect the contents.
                $depth = 1;
                $j = $i + 1;
                $attrText = '';
                while ($j < $end && $depth > 0) {
                    $tt = $tokens[$j];
                    if (is_array($tt)) {
                        $attrText .= $tt[1];
                    } else {
                        if ($tt === '[') {
                            $depth++;
                        } elseif ($tt === ']') {
                            $depth--;
                            if ($depth === 0) {
                                break;
                            }
                        }
                        $attrText .= $tt;
                    }
                    $j++;
                }
                if (preg_match('/\bMapRoute\b/', $attrText)) {
                    $sawMapRoute = true;
                }
                if (preg_match('/\bMapQuery\b/', $attrText)) {
                    $sawMapQuery = true;
                }
                $i = $j;
                continue;
            }

            if ($id === T_ARRAY) {
                $arrayType = true;
                continue;
            }

            if ($id === T_VARIABLE) {
                $variableName = ltrim($text, '$');
                continue;
            }

            if ($id === T_ELLIPSIS) {
                $disqualified = true;
                continue;
            }

            if ($id === T_NAME_QUALIFIED || $id === T_NAME_FULLY_QUALIFIED || $id === T_STRING) {
                if ($arrayType) {
                    // T_ARRAY followed by another type token (e.g. union) = disqualify.
                    // Only true if we've already set arrayType. Otherwise this is a non-array type.
                    $disqualified = true;
                } else {
                    // A non-array type token before T_ARRAY → unrelated param type.
                    $disqualified = true;
                }
            }
        } else {
            if ($tok === '?' || $tok === '|' || $tok === '&') {
                $disqualified = true;
            }
            if ($tok === '=') {
                // Default value follows — fine, doesn't disqualify but the type is already determined.
                break;
            }
        }
    }

    if ($disqualified || !$arrayType || $variableName === null) {
        return null;
    }

    if ($variableName === 'params' && !$sawMapRoute) {
        return ['name' => 'params', 'attribute' => 'MapRoute'];
    }
    if ($variableName === 'query' && !$sawMapQuery) {
        return ['name' => 'query', 'attribute' => 'MapQuery'];
    }

    return null;
}

function main(array $argv): int
{
    $args = parseArgs($argv);
    if ($args === null) {
        return EXIT_ARGUMENT;
    }

    $files = discoverFiles($args['path']);
    if ($files === []) {
        fwrite(STDERR, "warning: no .php files found under {$args['path']}\n");
    }

    $allOffenders = [];
    $controllersWithOffenders = [];

    foreach ($files as $file) {
        try {
            $offenders = scanFile($file);
        } catch (RuntimeException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return EXIT_PARSE;
        }
        if ($offenders !== []) {
            $allOffenders = array_merge($allOffenders, $offenders);
            $controllersWithOffenders[$offenders[0]['fqcn']] = true;
        }
    }

    $totalOffenders = count($allOffenders);
    $totalControllers = count($controllersWithOffenders);

    if ($args['format'] === 'json') {
        $payload = [
            'schema' => 'implicit-array-params/v1',
            'scanned_path' => $args['path'],
            'offenders' => $allOffenders,
            'total_offenders' => $totalOffenders,
            'total_files' => $totalControllers,
        ];
        fwrite(STDOUT, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    } else {
        if (!$args['quiet']) {
            foreach ($allOffenders as $o) {
                fwrite(STDOUT, sprintf(
                    "%s::%s \$%s -> #[%s]\n",
                    $o['fqcn'],
                    $o['method'],
                    $o['parameter'],
                    $o['recommended']
                ));
            }
        }
        fwrite(STDERR, sprintf(
            "TOTAL: %d unannotated array params across %d controllers\n",
            $totalOffenders,
            $totalControllers
        ));
    }

    return $totalOffenders === 0 ? EXIT_OK : EXIT_OFFENDERS;
}

exit(main($_SERVER['argv']));
