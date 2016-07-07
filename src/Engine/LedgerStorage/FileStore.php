<?php
declare(strict_types=1);
namespace Airship\Engine\LedgerStorage;

use Airship\Alerts\FileSystem\AccessDenied as FileAccessDenied;
use Airship\Engine\Contract\LedgerStorageInterface;

/**
 * Class FileStore
 * @package Airship\Engine\LedgerStorage
 */
class FileStore implements LedgerStorageInterface
{
    const FILE_FORMAT = 'Y-m-d.\l\o\g';
    const TIME_FORMAT = \AIRSHIP_DATE_FORMAT;

    /**
     * @var string
     */
    protected $basedir;

    /**
     * @var string
     */
    protected $fileFormat;

    /**
     * @var string
     */
    protected $timeFormat;

    /**
     * FileStore constructor.
     * @param string $baseDirectory
     * @param string $logfileFormat
     * @param string $timeFormat
     */
    public function __construct(
        string $baseDirectory = '',
        string $logfileFormat = self::FILE_FORMAT,
        string $timeFormat = self::TIME_FORMAT
    ) {
        if (\strlen($baseDirectory) < 2) {
            $this->basedir = ROOT . '/tmp/logs/';
        } else {
            $this->basedir = $baseDirectory;
        }
        if (!\is_dir($this->basedir)) {
            \mkdir($this->basedir, 0775);
        }
        $this->fileFormat = $logfileFormat;
        $this->timeFormat = $timeFormat;
    }
    
    /**
     * Store a log message -- used by Ledger
     * 
     * @param string $level
     * @param string $message
     * @param string $context (JSON encoded)
     * @return mixed
     * @throws FileAccessDenied
     */
    public function store(string $level, string $message, string $context)
    {
        $now = new \DateTime('now');
        $filename = $now->format($this->fileFormat);
        
        \touch($this->basedir . DIRECTORY_SEPARATOR . $filename);
        $file = \realpath($this->basedir . DIRECTORY_SEPARATOR . $filename);
        if ($file === false) {
            throw new FileAccessDenied(
                \trk('errors.file.lfi')
            );
        }
        if (\strpos($file, $this->basedir) === false) {
            throw new FileAccessDenied(
                \trk('errors.file.lfi')
            );
        }
        
        if (!\file_exists($file)) {
            \touch($file);
            \chmod($file, 0770);
        }
        
        $time = $now->format($this->timeFormat);
        
        return \file_put_contents(
            $file,
            $time . 
                "\t" . \preg_replace('#[^a-z]*#', '', $level) .
                "\t" . \json_encode($message) .
                "\t" . $context .
                "\n",
            FILE_APPEND
        );
    }
}
