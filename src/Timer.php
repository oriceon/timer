<?php

namespace Oriceon\Timer;

use Illuminate\Support\Facades\File;
use JsonException;

class Timer
{
    /**
     * Holds all timers.
     */
    protected static array $timers = [];

    protected static array $timeCapsule = [
        'day'  => [0 => 'o zi',         1 => 'zile'],
        'hour' => [0 => 'o oră',        1 => 'ore'],
        'min'  => [0 => 'un minut',     1 => 'minute'],
        'sec'  => [0 => 'o secundă',    1 => 'secunde'],
    ];

    /**
     * Handles the creation of a new timer. Simply supply a name and an
     * optional description.
     *
     * @param null $description
     */
    public static function start(string $name, $description = null): void
    {
        if (env('APP_ACTIVITY_LOG_ACTIVE') == true) {
            self::$timers[$name] = [
                'description' => $description,
                'time'        => [
                    'start' => microtime(true),
                    'end'   => null,
                    'diff'  => null,
                    'human' => null,
                ],
                'memory' => [
                    'start' => memory_get_usage(),
                    'end'   => null,
                    'diff'  => null,
                    'human' => null,
                ],
                'checkpoints' => [],
            ];
        }
    }

    /**
     * Handles the stopping an existing timer. Simply supply a name and an
     * optional number of decimal places. Will return the finalized timer values.
     */
    public static function stop(string $name, int $decimals = 5): array
    {
        if (env('APP_ACTIVITY_LOG_ACTIVE') == true) {
            // early calculation of stop time
            $time_end = microtime(true);

            // early calculation of stop time memory usage
            $memory_end = memory_get_usage();

            // calculate elapsed time
            self::$timers[$name] = self::get($name);

            self::$timers[$name]['time']['end']     = $time_end;
            self::$timers[$name]['time']['diff']    = $time_end - self::$timers[$name]['time']['start'];
            self::$timers[$name]['time']['human']   = self::_secondsToTime(self::$timers[$name]['time']['diff']);

            self::$timers[$name]['memory']['end']   = $memory_end;
            self::$timers[$name]['memory']['diff']  = $memory_end - self::$timers[$name]['memory']['start'];
            self::$timers[$name]['memory']['human'] = formatBytes(self::$timers[$name]['memory']['diff']);

            return self::$timers[$name];
        }

        return [];
    }

    /**
     * A special timer endpoint which will allow for the creation of several
     * checkpoints based on a singular start timer. To use, specify a start
     * timer name, a unique checkpoint name, an optional checkpoint description
     * describing the timer purpose, and an optional number of decimal places
     * to use for calculating the number of seconds since start time.
     *
     * @param string     $name        The start timer name
     * @param null|mixed $description An optional description of the checkpoint
     * @param int        $decimals    The number of decimal places to include
     */
    public static function checkpoint(string $name, mixed $description = null, int $decimals = 5): void
    {
        if (env('APP_ACTIVITY_LOG_ACTIVE') == true) {
            // early calculation of stop time
            $time_end = microtime(true);

            // early calculation of stop time memory usage
            $memory_end = memory_get_usage();

            self::$timers[$name] = self::get($name);

            $count = count(self::$timers[$name]['checkpoints']);

            self::$timers[$name]['checkpoints'][$count] = [
                'description' => $description,
                'time'        => ['end' => $time_end],
                'memory'      => ['end' => $memory_end],
            ];

            // calculate elapsed time
            self::$timers[$name]['checkpoints'][$count]['time']['diff_from_start']   = $time_end - self::$timers[$name]['time']['start'];
            self::$timers[$name]['checkpoints'][$count]['time']['human']             = self::_secondsToTime(self::$timers[$name]['checkpoints'][$count]['time']['diff_from_start']);

            self::$timers[$name]['checkpoints'][$count]['memory']['diff_from_start'] = $memory_end - self::$timers[$name]['memory']['start'];
            self::$timers[$name]['checkpoints'][$count]['memory']['human']           = formatBytes(self::$timers[$name]['checkpoints'][$count]['memory']['diff_from_start']);

            if ($count > 0) {
                $cnt = $count - 1;

                self::$timers[$name]['checkpoints'][$count]['time']['diff_from_last_checkpoint']  = $time_end - self::$timers[$name]['checkpoints'][$cnt]['time']['end'];
                self::$timers[$name]['checkpoints'][$count]['time']['human']                      = self::_secondsToTime(self::$timers[$name]['checkpoints'][$count]['time']['diff_from_last_checkpoint']);

                self::$timers[$name]['checkpoints'][$count]['memory']['diff_from_last_checkpoint'] = $memory_end - self::$timers[$name]['checkpoints'][$cnt]['memory']['end'];
                self::$timers[$name]['checkpoints'][$count]['memory']['human']                     = formatBytes(self::$timers[$name]['checkpoints'][$count]['memory']['diff_from_last_checkpoint']);
            }
        }
    }

    /**
     * Helper to retrieve a timer. If none exists, we assume that the timer start
     * time is equivalent to LARAVEL_START.
     */
    public static function get(string $name): array
    {
        if (array_key_exists($name, self::$timers)) {
            return self::$timers[$name];
        }

        return [
            'description' => 'Timer since LARAVEL_START.',
            'time'        => [
                'start' => defined('LARAVEL_START') ? LARAVEL_START : microtime(true),
                'end'   => null,
                'diff'  => null,
                'human' => null,
            ],
            'memory' => [
                'start' => null,
                'end'   => null,
                'diff'  => null,
                'human' => null,
            ],
            'checkpoints' => [],
        ];
    }

    /**
     * A quick method for returning all existing timers in the event you have
     * a problem/error/exception and need to do something with your timers.
     *
     * @throws JsonException
     */
    public static function dump(bool $toFile = false): array
    {
        if (env('APP_ACTIVITY_LOG_ACTIVE') == true) {
            // early calculation of stop time
            $time_end = microtime(true);

            // early calculation of stop time memory usage
            $memory_end = memory_get_usage();

            // ensure we end all timers
            foreach (self::$timers as $name => $timer) {
                if (self::$timers[$name]['time']['end'] == null) {
                    self::$timers[$name]['time']['end']   = $time_end;
                    self::$timers[$name]['time']['diff']  = $time_end - self::$timers[$name]['time']['start'];
                    self::$timers[$name]['time']['human'] = self::_secondsToTime(self::$timers[$name]['time']['diff']);
                }

                if (self::$timers[$name]['memory']['end'] == null) {
                    self::$timers[$name]['memory']['end']   = $memory_end;
                    self::$timers[$name]['memory']['diff']  = $memory_end - self::$timers[$name]['memory']['start'];
                    self::$timers[$name]['memory']['human'] = formatBytes(self::$timers[$name]['memory']['diff']);
                }
            }

            // if logging to file
            if ($toFile) {
                self::_write();
            }

            return self::$timers;
        }

        return [];
    }

    /**
     * A quick method for returning all existing timers in the event you have
     * a problem/error/exception and need to do something with your timers.
     *
     * @throws JsonException
     */
    public static function log(): void
    {
        if (env('APP_ACTIVITY_LOG_ACTIVE') == true) {
            $result = '';

            $dump = self::dump();

            foreach ($dump as $name => $time) {
                $result .= '[' . date('Y-m-d H:i:s', $time['time']['start']) . '] ' . $name . ': ' . $time['description'] . "\r\n";
                $result .= '[' . date('Y-m-d H:i:s', $time['time']['end']) . '] ' . $name . ': took ' . $time['time']['human'] . ' (' . $time['time']['diff'] . '), memory usage: ' . $time['memory']['human'] . "  \r\n";

                if (isset($time['checkpoints'])) {
                    foreach ($time['checkpoints'] as $no => $checkpoint) {
                        $result .= '[' . date('Y-m-d H:i:s', $checkpoint['time']['end']) . '] ' . $checkpoint['description'] . ' : took ' . $checkpoint['time']['human'] . ' (' . $checkpoint['time'][$no == 0 ? 'diff_from_start' : 'diff_from_last_checkpoint'] . '), memory usage: ' . $checkpoint['memory']['human'] . " \r\n";
                    }
                }

                $result .= "\r\n";
            }

            if ( ! empty($result)) {
                File::append(storage_path('logs/timer-' . date('Y-m-d') . '.log'), $result);
            }
        }
    }

    /**
     * Clears out all existing timers. Consider this a reset.
     */
    public static function clear(): void
    {
        self::$timers = [];
    }

    /**
     * Attempts to pretty print the timer data to a file for later parsing.
     *
     * @throws JsonException|JsonException
     */
    private static function _write(): void
    {
        $json           = self::dump();
        $json           = json_encode($json, JSON_THROW_ON_ERROR);
        $result         = '';
        $pos            = 0;
        $strLen         = strlen($json);
        $indentStr      = "\t";
        $newLine        = "\n";
        $prevChar       = '';
        $outOfQuotes    = true;

        for ($i = 0; $i <= $strLen; ++$i) {
            // grab the next character in the string
            $char = $json[$i];

            // are we inside a quoted string?
            if ($char == '"' && $prevChar != '\\') {
                $outOfQuotes = ! $outOfQuotes;

            // if this character is the end of an element,
                // output a new line and indent the next line.
            } elseif (($char == '}' || $char == ']') && $outOfQuotes) {
                $result .= $newLine;

                --$pos;

                $result .= str_repeat($indentStr, $pos);
            }

            // add the character to the result string.
            $result .= $char;

            // if the last character was the beginning of an element,
            // output a new line and indent the next line.
            if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
                $result .= $newLine;

                if ($char == '{' || $char == '[') {
                    ++$pos;
                }

                $result .= str_repeat($indentStr, $pos);
            }

            $prevChar = $char;
        }

        File::append(storage_path('logs/timer-' . date('Y-m-d') . '.log'), $result);
    }

    /**
     * @param $inputSeconds
     * @param $takeOnly
     * @return string
     */
    private static function _secondsToTime($inputSeconds, $takeOnly = null): string
    {
        $secondsInAMinute = 60;
        $secondsInAnHour  = 60 * $secondsInAMinute;
        $secondsInADay    = 24 * $secondsInAnHour;

        // extract days
        $days = floor($inputSeconds / $secondsInADay);

        // extract hours
        $hourSeconds = $inputSeconds % $secondsInADay;
        $hours       = floor($hourSeconds / $secondsInAnHour);

        // extract minutes
        $minuteSeconds = $hourSeconds % $secondsInAnHour;
        $minutes       = floor($minuteSeconds / $secondsInAMinute);

        // extract the remaining seconds
        $remainingSeconds = $minuteSeconds % $secondsInAMinute;
        $seconds          = ceil($remainingSeconds);

        // return the final array
        $obj = [
            'd' => (int) $days,
            'h' => (int) $hours,
            'm' => (int) $minutes,
            's' => (int) $seconds,
        ];

        $x  = ($obj['d'] > 0 ? ($obj['d'] == 1 ? self::$timeCapsule['day'][0] : $obj['d'] . ' ' . self::$timeCapsule['day'][1]) . ', ' : '');
        $x .= ($obj['h'] > 0 ? ($obj['h'] == 1 ? self::$timeCapsule['hour'][0] : $obj['h'] . ' ' . self::$timeCapsule['hour'][1]) . ', ' : '');
        $x .= ($obj['m'] > 0 ? ($obj['m'] == 1 ? self::$timeCapsule['min'][0] : $obj['m'] . ' ' . self::$timeCapsule['min'][1]) . ', ' : '');
        $x .= ($obj['s'] > 0 ? ($obj['s'] == 1 ? self::$timeCapsule['sec'][0] : $obj['s'] . ' ' . self::$timeCapsule['sec'][1]) . ', ' : '');

        $ret = rtrim($x, ', ');

        if ( ! is_null($takeOnly)) {
            // ca sa nu ne lungim mult cu textul
            // luam doar primele 2 raportari de timp : 12 zile, 10 ore
            $split_time = array_chunk(explode(', ', $ret), $takeOnly);

            if (isset($split_time[0])) {
                $ret = implode(', ', $split_time[0]);
            }
        }

        return $ret;
    }
}
