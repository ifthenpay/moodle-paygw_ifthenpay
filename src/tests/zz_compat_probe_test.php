<?php
// TEMPORARY diagnostic for the Moodle 4.5 CI job; removed in the next commit.
namespace paygw_ifthenpay;

/**
 * Temporary probe.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class zz_compat_probe_test extends \advanced_testcase {
    /**
     * Emit diagnostics as annotations.
     *
     * @return void
     */
    public function test_probe(): void {
        global $CFG;
        $this->resetAfterTest();
        $out = fn($m) => fwrite(STDOUT, "\n::notice title=probe::" . str_replace(["\n", ','], [' | ', ';'], $m) . "\n");
        $out('moodle ' . $CFG->release . ' / phpunit ' . \PHPUnit\Runner\Version::id());
        $broken = new class implements \core\clock {
            public function now(): \DateTimeImmutable {
                throw new \RuntimeException('broken');
            }
            public function time(): int {
                throw new \RuntimeException('broken');
            }
        };
        \core\di::set(\core\clock::class, $broken);
        $out('di get is broken clock: ' . var_export(\core\di::get(\core\clock::class) === $broken, true)
            . ' class=' . get_class(\core\di::get(\core\clock::class)));
        try {
            $t = new \paygw_ifthenpay\task\send_notification();
            $t->set_custom_data(['txid' => 1]);
            $id = \core\task\manager::queue_adhoc_task($t);
            $out('queue_adhoc_task did NOT throw; returned ' . var_export($id, true));
        } catch (\Throwable $e) {
            $out('queue_adhoc_task threw ' . get_class($e) . ': ' . $e->getMessage());
        } finally {
            \core\di::set(\core\clock::class, new \core\system_clock());
        }
        $m = new \ReflectionMethod(\core\task\manager::class, 'queue_adhoc_task');
        $src = array_slice(file($m->getFileName()), $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1);
        $hits = array_filter($src, fn($l) => preg_match('/clock|time\(|transaction/i', $l));
        $out('queue_adhoc_task lines ' . $m->getStartLine() . '-' . $m->getEndLine() . ': ' . trim(implode(' || ', array_map('trim', $hits))));
        $this->assertTrue(true);
    }
}
