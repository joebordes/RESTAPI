<?php

/**
 * @see       https://github.com/laminas/laminas-log for the canonical source repository
 * @copyright https://github.com/laminas/laminas-log/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-log/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Log\Formatter;

use DateTime;

class ErrorHandler extends Simple
{
    const DEFAULT_FORMAT = '%timestamp% %priorityName% (%priority%) %message% (errno %extra[errno]%) in %extra[file]% on line %extra[line]%';

    /**
     * This method formats the event for the PHP Error Handler.
     *
     * @param  array $event
     * @return string
     */
    public function format($event)
    {
        $output = $this->format;

        if (isset($event['timestamp']) && $event['timestamp'] instanceof DateTime) {
            $event['timestamp'] = $event['timestamp']->format($this->getDateTimeFormat());
        }

        foreach ($this->buildReplacementsFromArray($event) as $name => $value) {
            $output = str_replace("%$name%", $value, $output);
        }

        return $output;
    }

    /**
     * Flatten the multi-dimensional $event array into a single dimensional
     * array
     *
     * @param array $event
     * @param string $key
     * @return array
     */
    protected function buildReplacementsFromArray($event, $key = null)
    {
        $result = [];
        foreach ($event as $index => $value) {
            $nextIndex = $key === null ? $index : $key . '[' . $index . ']';
            if ($value === null) {
                continue;
            }
            if (! is_array($value)) {
                if ($key === null) {
                    $result[$nextIndex] = $value;
                } else {
                    if (! is_object($value) || method_exists($value, "__toString")) {
                        $result[$nextIndex] = $value;
                    }
                }
            } else {
                $result = array_merge($result, $this->buildReplacementsFromArray($value, $nextIndex));
            }
        }
        return $result;
    }
}
