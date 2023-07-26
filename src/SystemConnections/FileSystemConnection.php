<?php

namespace Lkt\Factory\Instantiator\SystemConnections;

use chillerlan\Filereader\Drivers\DiskDriver;

final class FileSystemConnection
{
    private static DiskDriver|null $diskDriver = null;

    public static function getDiskDriver(): ?DiskDriver
    {
        if (!is_object(FileSystemConnection::$diskDriver)) {
            FileSystemConnection::$diskDriver = new DiskDriver();
        }
        return FileSystemConnection::$diskDriver;
    }
}