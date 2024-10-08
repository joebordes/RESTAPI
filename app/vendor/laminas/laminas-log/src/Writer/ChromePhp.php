<?php

/**
 * @see       https://github.com/laminas/laminas-log for the canonical source repository
 * @copyright https://github.com/laminas/laminas-log/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-log/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Log\Writer;

use Laminas\Log\Exception;
use Laminas\Log\Formatter\ChromePhp as ChromePhpFormatter;
use Laminas\Log\Logger;
use Laminas\Log\Writer\ChromePhp\ChromePhpBridge;
use Laminas\Log\Writer\ChromePhp\ChromePhpInterface;
use Traversable;

class ChromePhp extends AbstractWriter
{
    /**
     * The instance of ChromePhpInterface that is used to log messages to.
     *
     * @var ChromePhpInterface
     */
    protected $chromephp;

    /**
     * Initializes a new instance of this class.
     *
     * @param null|ChromePhpInterface|array|Traversable $instance An instance of ChromePhpInterface
     *        that should be used for logging
     */
    public function __construct($instance = null)
    {
        if ($instance instanceof Traversable) {
            $instance = iterator_to_array($instance);
        }

        if (is_array($instance)) {
            parent::__construct($instance);
            $instance = isset($instance['instance']) ? $instance['instance'] : null;
        }

        if (!($instance instanceof ChromePhpInterface || $instance === null)) {
            throw new Exception\InvalidArgumentException('You must pass a valid Laminas\Log\Writer\ChromePhp\ChromePhpInterface');
        }

        $this->chromephp = $instance === null ? $this->getChromePhp() : $instance;
        $this->formatter = new ChromePhpFormatter();
    }

    /**
     * Write a message to the log.
     *
     * @param array $event event data
     * @return void
     */
    protected function doWrite(array $event)
    {
        $line = $this->formatter->format($event);

        switch ($event['priority']) {
            case Logger::EMERG:
            case Logger::ALERT:
            case Logger::CRIT:
            case Logger::ERR:
                $this->chromephp->error($line);
                break;
            case Logger::WARN:
                $this->chromephp->warn($line);
                break;
            case Logger::NOTICE:
            case Logger::INFO:
                $this->chromephp->info($line);
                break;
            case Logger::DEBUG:
                $this->chromephp->trace($line);
                break;
            default:
                $this->chromephp->log($line);
                break;
        }
    }

    /**
     * Gets the ChromePhpInterface instance that is used for logging.
     *
     * @return ChromePhpInterface
     */
    public function getChromePhp()
    {
        // Remember: class names in strings are absolute; thus the class_exists
        // here references the canonical name for the ChromePhp class
        if (!$this->chromephp instanceof ChromePhpInterface
            && class_exists('ChromePhp')
        ) {
            $this->setChromePhp(new ChromePhpBridge());
        }
        return $this->chromephp;
    }

    /**
     * Sets the ChromePhpInterface instance that is used for logging.
     *
     * @param  ChromePhpInterface $instance The instance to set.
     * @return ChromePhp
     */
    public function setChromePhp(ChromePhpInterface $instance)
    {
        $this->chromephp = $instance;
        return $this;
    }
}
