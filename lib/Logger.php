<?php

namespace Andygrond\Hugonette;

/* PSR-3 compatible logger for Hugonette
 * Extra level 'view' collects messages for user awareness
 * @author Andygrond 2020
 * Dependency: https://github.com/donatj/PhpUserAgent
**/

use Tracy\Debugger;
use Tracy\OutputDebugger;

class Logger
{
  // message level hierachy
  private $levels = [
    'debug'     => 10, // Detailed debug information
    'info'      => 20, // Interesting events
    'notice'    => 30, // Normal but significant events
    'warning'   => 40, // Exceptional occurrences that are not errors
    'error'     => 50, // Runtime errors that do not require immediate action but should typically be logged and monitored
    'critical'  => 60, // Critical conditions
    'alert'     => 70, // Action must be taken immediately - this should trigger the SMS alerts
    'emergency' => 80, // System is unusable
  ];

  private $collection = []; // messages waiting for output
  private $minLevel = 0;    // lowest level of logged messages
  private $channel;         // active output channel
  private $logFile = '';    // path to log filename

  public $debugMode = false; // Logger is in Tracy development mode


  /* log initialization
  @ $path = /path/to/log/filename.log or /path/to/log/folder/
  file extension .log is obligatory - Formatter instance will be applied
  when directory is given, native tracy log will be used
  @ $channel - set main log channel ['plain'|'tracy'|'ajax']
  Tracy debugger will not be used in 'plain' channel
  @ $debugMode = switch Tracy to development mode
  */
  public function __construct(string $path, string $channel = 'plain', bool $debugMode = false)
  {
    // set log dir and file
    if (strrchr($path, '.') == '.log') {
      $this->logFile = $path;
      $logPath = dirname($path) .'/';
      ini_set('error_log', $path);
    } else {
      $logPath = rtrim($path, '/') .'/';
    }

    // initialize Tracy debugger
    if ($channel != 'plain') {
      $this->debugMode = $debugMode;  // for use in app
      $mode = $debugMode? Debugger::DEVELOPMENT : Debugger::PRODUCTION;
      Debugger::enable($mode, $logPath);
    }

    $this->channel = $channel;
  }

  // destruction of Logger instance will write the $collection to log file
  public function __destruct()
  {
    $this->flush();
  }

  // all log level messages goes here
  // @level - PSR-3 level
  // @args = [$message, $context]
  public function __call(string $level, array $args)
  {
    [$message, $context] = array_pad($args, 2, []);
    $this->log($level, $message, $context);
  }

  // logs with an arbitrary level
  public function log(string $level, $message, $context = [])
  {
    if (!$levelNo = @$this->levels[$level]) {
      throw new \BadMethodCallException('Log method not found: ' .$level);
    }

    if ($levelNo >= $this->minLevel) {  // message filtering
      $this->collection[] = [
        'level' => strtoupper($level),
        'message' => $message,
        'context' => isset($context)? json_encode($context, JSON_UNESCAPED_UNICODE) : '',
      ];
    }
  }

  // set lowest level of registered messages
  public function minLevel(string $level)
  {
    if (isset($this->levels[$level])) {
      $this->minLevel = $this->levels[$level];
    } else {
      throw new \UnexpectedValueException("Log level: $level is not valid. Use PSR-3 level");
    }
  }

  // enable fireLog - log to console also
  public function enable($mode)
  {
    switch($mode) {
      case 'fireLog':
        $this->channel = 'ajax';
        break;
      case 'OutputDebugger':
        OutputDebugger::enable();
        break;
    }
  }

  // write $collection to file
  private function flush()
  {
    $formatter = new LogFormatter;
    $message = $formatter->message($this->collection);

    if ($this->logFile) {
      file_put_contents($this->logFile, $formatter->date() .$message ."\n", FILE_APPEND | LOCK_EX);
    } else {
      Debugger::log($message);
    }

    if ($this->channel == 'ajax') {
      Debugger::fireLog($message);
    }
  }

}
