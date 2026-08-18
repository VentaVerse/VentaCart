<?php

namespace Tests\Feature\AdminV2;

use App\Services\ExtensionManager;
use Tests\TestCase;

/**
 * Every bare class reference in extension code must resolve.
 *
 * The conversion waves moved files out of App\ namespaces, where
 * same-namespace siblings (models, services) resolved without imports.
 * After the move, a reference the rewrite missed compiles fine and
 * fatals only when its code path runs — the free-shipping page's Brand
 * lookup and the weight-based provider's GeoZoneResolver (a checkout
 * path) both shipped that way. This scan is the guard: it tokenizes
 * every extension source file, resolves each X:: / new X reference
 * against the file's imports and namespace, and fails on anything that
 * would fatal at runtime.
 */
class ExtensionClassReferencesTest extends TestCase
{
    public function test_every_bare_class_reference_in_extension_code_resolves(): void
    {
        // Boot every extension's autoloader so resolution checks can see
        // extension classes (registerAutoloader is keyed off manifests).
        $manager = app(ExtensionManager::class);
        foreach ($manager->discovered() as $manifest) {
            $ns = preg_replace('/\\\\[^\\\\]+$/', '', $manifest['provider']) . '\\';
            $src = $manifest['path'] . '/src/';
            spl_autoload_register(function ($class) use ($ns, $src) {
                if (str_starts_with($class, $ns)) {
                    $file = $src . str_replace('\\', '/', substr($class, strlen($ns))) . '.php';
                    if (is_file($file)) {
                        require_once $file;
                    }
                }
            });
        }

        $files = glob(base_path('extensions/*/src/{,*/,*/*/,*/*/*/}*.php'), GLOB_BRACE);
        $this->assertNotEmpty($files, 'No extension sources found — glob broken?');

        $bad = [];
        foreach ($files as $file) {
            foreach ($this->unresolvedReferences($file) as $ref) {
                $bad[] = str_replace(base_path() . '/', '', $file) . ' -> ' . $ref;
            }
        }

        $this->assertSame([], $bad, "Unresolvable class references in extension code:\n" . implode("\n", $bad));
    }

    /** @return string[] short names that resolve to nothing */
    private function unresolvedReferences(string $file): array
    {
        $tokens = token_get_all(file_get_contents($file));
        $n = count($tokens);
        $namespace = '';
        $uses = [];
        $refs = [];

        for ($i = 0; $i < $n; $i++) {
            $t = $tokens[$i];
            if (! is_array($t)) {
                continue;
            }

            if ($t[0] === T_NAMESPACE) {
                for ($j = $i + 1; $j < $n; $j++) {
                    if (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED], true)) {
                        $namespace = $tokens[$j][1];
                        break;
                    }
                    if ($tokens[$j] === ';') {
                        break;
                    }
                }
            }

            if ($t[0] === T_USE) {
                $name = '';
                $alias = null;
                $sawAs = false;
                $j = $i + 1;
                for (; $j < $n; $j++) {
                    $tj = $tokens[$j];
                    if ($tj === ';' || $tj === '(' || $tj === '{') {
                        break;
                    }
                    if (! is_array($tj)) {
                        continue;
                    }
                    if ($tj[0] === T_AS) {
                        $sawAs = true;
                    } elseif (in_array($tj[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                        if ($sawAs) {
                            $alias = $tj[1];
                        } else {
                            $name = $tj[1];
                        }
                    }
                }
                // Only import statements (end in ;) count; `use (` is a
                // closure capture and `use X {` inside a class is a trait
                // adaptation block (still an import of X, but conservative
                // is fine: plain trait `use X;` is covered).
                if ($name !== '' && ($tokens[$j] ?? null) === ';') {
                    $short = $alias ?? (str_contains($name, '\\') ? substr($name, strrpos($name, '\\') + 1) : $name);
                    $uses[$short] = ltrim($name, '\\');
                }
            }

            if ($t[0] === T_STRING && isset($tokens[$i + 1]) && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] === T_DOUBLE_COLON) {
                $refs[] = $t[1];
            }

            if ($t[0] === T_NEW) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $tj = $tokens[$j];
                    if (is_array($tj) && $tj[0] === T_WHITESPACE) {
                        continue;
                    }
                    if (is_array($tj) && $tj[0] === T_STRING) {
                        $refs[] = $tj[1];
                    }
                    break;
                }
            }
        }

        $builtins = ['self', 'static', 'parent', 'class'];
        $bad = [];
        foreach (array_unique($refs) as $ref) {
            if (in_array(strtolower($ref), $builtins, true)) {
                continue;
            }
            $viaImport = $uses[$ref] ?? null;
            $viaNamespace = $namespace !== '' ? $namespace . '\\' . $ref : $ref;
            foreach ([$viaImport, $viaNamespace, $ref] as $candidate) {
                if ($candidate !== null && (class_exists($candidate) || interface_exists($candidate) || trait_exists($candidate) || enum_exists($candidate))) {
                    continue 2;
                }
            }
            $bad[] = "{$ref} (would resolve as " . ($viaImport ?? $viaNamespace) . ')';
        }

        return $bad;
    }
}
