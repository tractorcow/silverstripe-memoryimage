<?php

/**
 * Description of MemoryGD
 *
 * @author Damo
 */
class MemoryGD extends GD
{

    public function getFormat()
    {
        return $this->format;
    }

    protected $format = null;
    
    public function __construct($data, $format)
    {
        parent::__construct(null);

        if (!$data)
            return;

        $this->format = $format;

        $gd = imagecreatefromstring($data);
        if ($gd !== false)
            $this->setGD($gd);
    }

    function getData()
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'mgd');

        switch ($this->format)
        {
            case 'gif':
                imagegif($this->gd, $tempFile);
                break;
            case 'png':
                imagepng($this->gd, $tempFile);
                break;
            case 'jpg':
            default:
                imagejpeg($this->gd, $tempFile, $this->quality);
                break;
        }

        return file_get_contents($tempFile);
    }

}
