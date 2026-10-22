<?php

namespace App\Support\Backups;

use RuntimeException;

// argv stages chained stdout → stdin with proc_open, never through a shell, so nothing
// is ever interpolated into a command string. Every stage's exit code is checked: a
// failure anywhere in the chain fails the whole run, which `a | b` in a shell would hide.
class Pipeline
{
    /**
     * @param  array<int, array{argv: list<string>, env?: array<string, string>}>  $stages
     */
    public static function run(array $stages, ?string $stdoutPath = null): void
    {
        $procs = [];
        $errs = [];
        $input = ['file', '/dev/null', 'r'];
        $last = count($stages) - 1;

        foreach ($stages as $i => $stage) {
            $errs[$i] = tmpfile();
            $output = $i === $last ? ['file', $stdoutPath ?? '/dev/null', 'w'] : ['pipe', 'w'];
            $env = isset($stage['env']) ? getenv() + $stage['env'] : null;

            $proc = proc_open($stage['argv'], [0 => $input, 1 => $output, 2 => $errs[$i]], $pipes, null, $env);

            if (is_resource($input)) {
                fclose($input);
            }
            if (! is_resource($proc)) {
                foreach ($procs as $started) {
                    proc_terminate($started);
                    proc_close($started);
                }
                throw new RuntimeException('Could not start '.$stage['argv'][0].'.');
            }

            $procs[$i] = $proc;
            $input = $i === $last ? null : $pipes[1];
        }

        $failures = [];
        foreach ($procs as $i => $proc) {
            $code = proc_close($proc);
            rewind($errs[$i]);
            $stderr = trim((string) stream_get_contents($errs[$i]));
            fclose($errs[$i]);

            if ($code !== 0) {
                $failures[] = $stages[$i]['argv'][0]." exited $code".($stderr === '' ? '' : ': '.mb_strimwidth($stderr, 0, 1000, '…'));
            }
        }

        if ($failures !== []) {
            throw new RuntimeException(implode('; ', $failures));
        }
    }
}
