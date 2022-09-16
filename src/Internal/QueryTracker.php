<?php

namespace CodeDistortion\Stepwise\Internal;

use DB;

/**
 * Keep track of the given queries - used by testing code
 */
class QueryTracker
{
    /**
     * Keeps track of the queries that have been passed
     *
     * @var boolean
     */
    private $trackRealQueries = true;

    /**
     * Keeps track of the queries that have been passed
     *
     * @var array
     */
    private $queries = [];





    /**
     * Constructor
     *
     * @param boolean $trackRealQueries Should real queries be tracked?.
     */
    public function __construct(bool $trackRealQueries = true)
    {
        $this->trackRealQueries = $trackRealQueries;

        DB::listen(function ($sql) {
            if ($this->trackRealQueries) {
                $this->trackQuery($sql->sql, $sql->bindings, $sql->time, true);
            }
        });
    }

    /**
     * Turn query-tracking on or off
     *
     * @param boolean $trackRealQueries Turn it on or off?.
     * @return self
     */
    public function trackQueries(bool $trackRealQueries = true): self
    {
        $this->trackRealQueries = $trackRealQueries;

        return $this; // chainable
    }



    /**
     * Add a query to the list
     *
     * @param string  $query     The query to add.
     * @param array   $values    The values used in the query.
     * @param float   $time      The time the query took to run in ms.
     * @param boolean $realQuery Whether this is a real query that was run in the database or not.
     * @return void
     */
//    public function trackQuery(string $query, array $values = [], float $time = 0.0, bool $realQuery = false): void // @TODO PHP 7.1
    public function trackQuery(string $query, array $values = [], float $time = 0.0, bool $realQuery = false)
    {
        $this->queries[] = [
            'query' => $query,
            'values' => $values,
            'time' => $time,
            'realQuery' => $realQuery,
        ];
    }

    /**
     * Return the queries
     *
     * @return array
     */
    public function queries(): array
    {
        return $this->queries;
    }

    /**
     * Return the queries - in a rendered format with bindings replaced back into their placeholders
     *
     * Used for string comparison during unit testing.
     * @return array
     */
    public function renderedQueries(): array
    {
        $queries = [];
        foreach (array_keys($this->queries) as $index) {
            $queries[] = $this->placeQueryData($this->queries[$index]['query'], $this->queries[$index]['values']);
        }
        return $queries;
    }

    /**
     * Take the given queries and neatly format them ready for copying and pasting into tests
     *
     * @return string
     */
    public function copyableQueries(): string
    {
        $renderedQueries = array_map(function ($str) {
            return str_replace('"', '\\"', $str);
        }, $this->renderedQueries());

        return '"'.implode(
            '",'.PHP_EOL.PHP_EOL.'"',
            str_replace(PHP_EOL, '\n"'.PHP_EOL.'."', $renderedQueries)
        ).'"'.PHP_EOL;
    }



    /**
     * Take the given query and place it's values into their ? placeholders
     *
     * @param string $query  The query to place the values in.
     * @param array  $values The values to place in the query.
     * @return string
     */
    protected function placeQueryData(string $query, array $values = []): string
    {

        // replace the values
        foreach ($values as $index => $value) {
            if (!is_int($index)) {

                unset($values[$index]);

                $index = ':'.$index;
                $pos = mb_strpos($query, $index, 0);
                if ($pos !== false) {
                    $query = mb_substr($query, 0, $pos)
                        .$this->escVal($value)
                        .mb_substr($query, $pos + mb_strlen($index));
                }
            }
        }

        // find the position of each "?"
        $positions = [];
        $lastPos = 0;
        $needle = '?';
        while (($lastPos = mb_strpos($query, $needle, $lastPos)) !== false) {
            $positions[] = $lastPos;
            $lastPos = $lastPos + mb_strlen($needle);
        }

        // replace the ?'s with the query values
        foreach (array_reverse($positions) as $pos) {
            if (count($values)) {
                $value = array_pop($values);
                $query = mb_substr($query, 0, $pos).$this->escVal($value).mb_substr($query, $pos + 1);
            }
        }
        return $query;
    }

    /**
     * Escape the given value, ready for placing into the query
     *
     * This is used when testing which queries are produced
     * @param boolean|integer|string|null $value The value to escape.
     * @return string
     */
    protected function escVal($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return ($value ? '1' : '0');
        }
        return (is_string($value) ? '"'.addslashes($value).'"' : (string) $value);
    }
}
