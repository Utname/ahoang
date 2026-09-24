<?php


class FileFilter
{
    /**
     * @param string $filename
     * @return string
     * @throws Exception
     */
    public function process(string $filename): string
    {

        $fileDir = FilesService::getFileDir($filename);

        return '/files/files/'.$fileDir.'/'.$filename;
    }
}