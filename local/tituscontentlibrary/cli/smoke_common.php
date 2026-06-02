<?php
/**
 * Shared helpers for Titus Content Library smoke tests.
 *
 * Include this file from each smoke_test_*.php script AFTER the Moodle bootstrap.
 * Do NOT define CLI_SCRIPT here — each script owns its own preamble.
 */

defined('MOODLE_INTERNAL') || die();

class titus_smoke {

    /** @var int */
    private static int $passed = 0;

    /** @var int */
    private static int $failed = 0;

    /** @var string[] */
    private static array $failures = [];

    // -------------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------------

    public static function assert_true(bool $condition, string $label): void {
        if ($condition) {
            self::$passed++;
            echo "  [PASS] {$label}\n";
        } else {
            self::$failed++;
            self::$failures[] = $label;
            echo "  [FAIL] {$label}\n";
        }
    }

    public static function assert_false(bool $condition, string $label): void {
        self::assert_true(!$condition, $label);
    }

    public static function assert_equals(mixed $expected, mixed $actual, string $label): void {
        $ok = ($expected === $actual);
        if (!$ok) {
            echo "        expected: " . var_export($expected, true) . "\n";
            echo "        actual:   " . var_export($actual, true) . "\n";
        }
        self::assert_true($ok, $label);
    }

    public static function assert_not_empty(mixed $value, string $label): void {
        self::assert_true(!empty($value), $label);
    }

    public static function assert_greater_than(int $min, int $actual, string $label): void {
        self::assert_true($actual > $min, "{$label} (got {$actual}, expected > {$min})");
    }

    public static function assert_contains(string $needle, string $haystack, string $label): void {
        self::assert_true(str_contains($haystack, $needle), "{$label} ('{$needle}' not found in '{$haystack}')");
    }

    // -------------------------------------------------------------------------
    // Moodle helpers
    // -------------------------------------------------------------------------

    /**
     * Set the global $USER to the site admin so capability checks pass.
     */
    public static function become_admin(): void {
        global $USER, $DB;
        $admins = get_admins();
        $USER   = reset($admins);
        \core\session\manager::set_user($USER);
    }

    /**
     * Drain adhoc tasks matching $classname (FQCN with leading backslash stripped).
     *
     * Iterates up to $maxiterations times, executing only tasks whose class matches.
     * Throws RuntimeException if a matching task fails.
     *
     * @param string $classname  Full class name, e.g. '\local_tituscontentlibrary\task\add_content_task'
     * @param int    $maxiter    Safety cap on loop iterations.
     * @return int Number of tasks executed.
     */
    public static function drain_adhoc(string $classname, int $maxiter = 20): int {
        $classname = ltrim($classname, '\\');
        $executed  = 0;

        for ($i = 0; $i < $maxiter; $i++) {
            $task = \core\task\manager::get_next_adhoc_task(time());
            if ($task === null) {
                break;
            }

            $taskclass = ltrim(get_class($task), '\\');
            if ($taskclass !== $classname) {
                // Not our task — release it so it isn't lost, then stop.
                \core\task\manager::adhoc_task_failed($task);
                echo "  [WARN] Unexpected adhoc task in queue: {$taskclass}\n";
                break;
            }

            try {
                $task->execute();
                \core\task\manager::adhoc_task_complete($task);
                $executed++;
            } catch (\Throwable $e) {
                \core\task\manager::adhoc_task_failed($task);
                throw new \RuntimeException(
                    "Adhoc task {$classname} threw: " . $e->getMessage(), 0, $e
                );
            }
        }

        return $executed;
    }

    /**
     * Assert no adhoc tasks remain in the queue.
     */
    public static function assert_adhoc_queue_empty(string $label = 'Adhoc queue is empty'): void {
        $task = \core\task\manager::get_next_adhoc_task(time());
        if ($task !== null) {
            // Release it so we don't corrupt queue state.
            \core\task\manager::adhoc_task_failed($task);
            self::assert_true(false, $label . ' (found: ' . get_class($task) . ')');
        } else {
            self::assert_true(true, $label);
        }
    }

    // -------------------------------------------------------------------------
    // Summary
    // -------------------------------------------------------------------------

    /**
     * Print totals and return exit code (0 = all passed, 1 = some failed).
     */
    public static function summary_and_exit_code(): int {
        $total = self::$passed + self::$failed;
        echo "\n";
        echo "Results: " . self::$passed . "/{$total} passed";
        if (self::$failed > 0) {
            echo "  (" . self::$failed . " FAILED)";
            echo "\n";
            foreach (self::$failures as $f) {
                echo "  - {$f}\n";
            }
        }
        echo "\n";
        return self::$failed > 0 ? 1 : 0;
    }

    // -------------------------------------------------------------------------
    // State persistence between scripts (via plugin config)
    // -------------------------------------------------------------------------

    public static function set_state(string $key, string $value): void {
        set_config('smoke_' . $key, $value, 'local_tituscontentlibrary');
    }

    public static function get_state(string $key): string {
        return (string) get_config('local_tituscontentlibrary', 'smoke_' . $key);
    }
}
