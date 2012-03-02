<?php

/**
 * Stores files as blobs
 *
 * @author Damo
 */
class MemoryImage extends Image
{

    static $db = array(
        'ImageData' => 'Blob'
    );

    // image header tables
    static $header_types = array(
        // from http://www.garykessler.net/library/file_sigs.html
        'jpg' => "\xFF\xD8\xFF",
        'png' => "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A",
        'gif' => "\x47\x49\x46"
    );

    protected function matchesPattern($data, $pattern)
    {
        return strpos($data, $pattern) === 0;
    }


    protected function determineFormat($data)
    {
        foreach (self::$header_types as $type => $pattern)
            if ($this->matchesPattern($data, $pattern))
                return $type;
        return 'jpg';
    }

    public function exists()
    {
        return !!$this->ImageData;
    }

    public function setData($data)
    {
        $this->ImageData = $data;
        
        $format = $this->determineFormat($data);
        $this->ParentID = -1;
        $this->Filename = "image.$format";
    }
    
    protected function extractDataFromImage(Image $image)
    {
        // We'll need to extract content from the file itself
        if (!$image || !$image->exists())
            return null;
        
        if(is_a($image, 'MemoryImage'))
            return $image->ImageData;
        
        $path = $image->getFullPath();
        return $this->extractDataFromFile($path);
    }
    
    protected function extractDataFromFile($path)
    {
        if (!file_exists($path))
            return null;
        
        return file_get_contents($path);
    }

    public function CopyFromImage(Image $image)
    {
        $data = $this->extractDataFromImage($image);
        $this->setData($data);
    }
    
    public function CopyFromFile($path)
    {
        $data = $this->extractDataFromFile($path);
        $this->setData($data);
    }

    public function setDataBase64($data)
    {
        $this->setData(base64_decode($data));
    }    
    
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        
        // if we have been assigned a file in the filesystem, before we save this record we should load this file
        if($this->ParentID >= 0 && !empty($this->Name))
            $this->CopyFromFile($this->getFullPath());
        
        // Required to prevent deletion of this during filesystem synctasks
        $this->ParentID = -1;
    }

    /**
     * Return an image object representing the image in the given format.
     * This image will be generated using generateFormattedImage().
     * The generated image is cached, to flush the cache append ?flush=1 to your URL.
     * @param string $format The name of the format.
     * @param string $arg1 An argument to pass to the generate function.
     * @param string $arg2 A second argument to pass to the generate function.
     * @return Image_Cached
     */
    function getFormattedImage($format, $arg1 = null, $arg2 = null)
    {
        if (!$this->exists())
            return null;

        $data = $this->generateFormattedImage($format, $arg1, $arg2);
        
        if(!$data)
            return null;
        
        $sizedImage = new MemoryImage();
        $sizedImage->setData($data);

        // Pass through the title so the templates can use it
        $sizedImage->Title = $sizedImage->Title;
        return $sizedImage;
    }

    /**
     * Generate an image on the specified format.
     * @param string $format Name of the format to generate.
     * @param string $arg1 Argument to pass to the generate method.
     * @param string $arg2 A second argument to pass to the generate method.
     * @return binary Contents of the formatted image
     */
    function generateFormattedImage($format, $arg1 = null, $arg2 = null)
    {
        $gd = new MemoryGD($this->ImageData, $this->determineFormat($this->ImageData));
        if (!$gd->hasGD())
            return null;

        $generateFunc = "generate$format";
        if ($this->hasMethod($generateFunc))
        {
            $gd = $this->$generateFunc($gd, $arg1, $arg2);
            return $gd
                    ? $gd->getData()
                    : null;
        }

        USER_ERROR("MemoryImage::generateFormattedImage - Image $format function not found.", E_USER_WARNING);
    }
    
    /**
     * Retrieve image data in a format suitable for putting into the src tag of an image element 
     */
	function getURL() {
		if (!$this->ImageData)
            return null;

        $data = chunk_split(base64_encode($this->ImageData));
        $format = $this->determineFormat($this->ImageData);
        
        return "data:image/$format;base64,$data";
	}

    /**
     * Return an XHTML img tag for this Image,
     * or NULL if the image file doesn't exist on the filesystem.
     * 
     * @return string
     */
    function getTag()
    {
        $data = $this->getURL();
        if(!$data)
            return null;

        if ($this->Title)
            $title = Convert::raw2att($this->Title);
        elseif (preg_match("/([^\/]*)\.[a-zA-Z0-9]{1,6}$/", $this->Filename, $matches))
            $title = Convert::raw2att($matches[1]);
        else
            $title = $this->Filename;
        
        return "<img src=\"$data\" alt=\"$title\" />";
    }

}