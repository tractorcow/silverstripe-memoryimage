<?php

/**
 * Stores files as blobs and provides methods for importing or exporting images
 * @property string $Data The image data as a binary string
 * @property string $DataBase64 The image data as base 64 encoded string
 * @author Damian Mooyman
 */
class MemoryImage extends Image
{

    /**
     * Determines whether uploaded images should be deleted from the file system after they have been stored in memory
     * @var boolean
     */
    public static $remove_uploaded_files = true;
    static $db = array(
        'ImageData' => 'Blob'
    );
    static $casting = array(
        'Data' => 'HTMLText',
        'DtaaBase64' => 'HTMLText'
    );

    /**
     * Table mapping image types to the file data header
     * @link http://www.garykessler.net/library/file_sigs.html
     * @var array
     */
    static $header_types = array(
        'jpg' => "\xFF\xD8\xFF",
        'png' => "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A",
        'gif' => "\x47\x49\x46"
    );

    /**
     * Default file type for this image if the header type could not be parsed
     * @var string
     */
    public static $default_type = 'jpg';

    /**
     * Determines whether or not the current data segment contains the specified header string
     * @param string $header Binary header string to check against
     * @return boolean Flag indicating if this header is present
     */
    protected function hasDataHeader($header)
    {
        $data = $this->ImageData;
        return strpos($data, $header) === 0;
    }

    /**
     * Determines the file format of the currently given 
     * @return string 
     */
    protected function determineFormat()
    {
        foreach (self::$header_types as $type => $pattern)
            if ($this->hasDataHeader($pattern))
                return $type;
        return self::$default_type;
    }

    /**
     * Determines if this image has valid content
     * @return boolean A flag indicating the present of image data
     */
    public function exists()
    {
        return !!$this->ImageData;
    }

    /**
     * Generate an appropriate random image filename for this file with appropriate extension
     * @return string A random filename
     */
    protected function generateFilename()
    {
        $format = $this->determineFormat();
        $name = '';
        for ($i = 0; $i < 7; $i++)
            $name .= chr(rand(97, 122));
        return "image-data-$name-" . time() . ".$format";
    }

    function getFilename()
    {
        // Restores the Filename property to the default dataobject behaviour
        return $this->getField('Filename');
    }

    function setParentID($parentID)
    {
        // Restores the ParentID property to the default dataobject behaviour
        $this->setField('ParentID', $parentID);
        return $this->getField('ParentID');
    }

    /**
     * Safely assigns a binary string of data to this image with an optional filename
     * @param string $data Binary string of image data
     * @param string $filename Filename to use when storing this image internally
     */
    public function setData($data, $filename = null)
    {
        $this->ImageData = $data;
        $this->ParentID = -1;

        // Set filename if requested, or there is currently no filename set
        if ($filename || empty($this->Filename))
            $this->Filename = $filename
                    ? $filename
                    : $this->generateFilename();
    }

    /**
     * Retrieves image data
     * @return type 
     */
    public function getData()
    {
        return $this->ImageData;
    }

    /**
     * Extracts the image data from a given image as a binary string
     * @param Image $image The image to import data from
     * @return string|null A binary string containing the contained image if given
     */
    protected function extractDataFromImage(Image $image)
    {
        // We'll need to extract content from the file itself
        if (!$image || !$image->exists())
            return null;

        if (is_a($image, 'MemoryImage'))
            return $image->ImageData;

        $path = $image->getFullPath();
        return $this->extractDataFromResource($path);
    }

    /**
     * Extracts data from a file or url resource path
     * @param string $resource The full system path or url to the image
     * @return string|null A binary string containing the file contents, or null if failure
     */
    protected function extractDataFromResource($resource)
    {
        $result = file_get_contents($resource);
        if ($result === false)
            return null;
        return $result;
    }

    /**
     * Copies the supplied image into this image
     * @param Image $image the image object to copy from
     * @param string $filename Optional filename to use when storing this image internally
     * @return MemoryImage Reference to the resulting image object
     */
    public function CopyFromImage(Image $image, $filename = null)
    {
        $data = $this->extractDataFromImage($image);
        $this->setData($data, $filename);
        return $this;
    }

    /**
     * Writes the image data to the specified filesystem path
     * @param string $path Output path to save this image data into
     */
    public function SaveToFile($path)
    {
        file_put_contents($path, $this->ImageData);
    }

    /**
     * Copies the supplied file path into this image
     * @param string $path The full system path to the image
     * @param string $filename Optional filename to use when storing this image internally
     * @return MemoryImage Reference to the resulting image object
     */
    public function CopyFromFile($path, $filename = null)
    {
        $data = $this->extractDataFromResource($path);
        $this->setData($data, $filename);
        return $this;
    }

    /**
     * Load content from the provided image URL into memory
     * @param string $url The URL of the image
     * @param string $filename Optional filename to use when storing this image internally
     * @return MemoryImage Reference to the resulting image object
     */
    public function CopyFromURL($url, $filename = null)
    {
        // This function really only exists as a convenience to those using it
        return $this->CopyFromFile($url, $filename);
    }

    /**
     * Assigns a base64 encoded string of data to this image with an optional filename
     * @param string $data base64 encoded string of image data
     * @param string $filename Filename to use when storing this image internally
     */
    public function setDataBase64($data, $filename = null)
    {
        $this->setData(base64_decode($data), $filename = null);
    }

    /**
     * Retrieves the contents of this image as base64 encoded string
     * @return string Base 64 encoded string containing this image data
     */
    public function getDataBase64()
    {
        return base64_encode($this->ImageData);
    }

    /**
     * Ensures that any physically uploaded image assigned to this object is correctly loaded into the database
     * before saving it 
     */
    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();

        // if we have been assigned a file in the filesystem, before we save this record we should load this file
        if ($this->ParentID >= 0 && !empty($this->Name))
        {
            // Locate uploaded image
            $path = $this->getFullPath();
            $this->Filename = null; // Forces this image to be renamed
            $this->CopyFromFile($path);

            // remove physical file
            if (self::$remove_uploaded_files)
                unlink($path);
        }

        // Required to prevent deletion of this during filesystem synctasks, as well as a simple way of distinguishing
        // this image from filesystem based images
        $this->ParentID = -1;
    }

    /**
     * Return an image object representing the image in the given format.
     * This image will be generated using generateFormattedImage().
     * The generated image is cached, to flush the cache append ?flush=1 to your URL.
     * @param string $format The name of the format.
     * @param string $arg1 An argument to pass to the generate function.
     * @param string $arg2 A second argument to pass to the generate function.
     * @return MemoryImage The resulting memory image
     */
    function getFormattedImage($format, $arg1 = null, $arg2 = null)
    {
        if (!$this->exists())
            return null;

        $data = $this->generateFormattedImage($format, $arg1, $arg2);

        if (!$data)
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
     * @return string String of binary contents of the formatted image
     */
    function generateFormattedImage($format, $arg1 = null, $arg2 = null)
    {
        $gd = new MemoryGD($this->ImageData, $this->determineFormat());
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
     * @return string Embedded base64 encoded image in a format suitable for putting inside a html image src tag
     */
    function getURL()
    {
        if (!$this->exists())
            return null;

        $data = chunk_split(base64_encode($this->ImageData));
        $format = $this->determineFormat();

        return "data:image/$format;base64,$data";
    }
    
    function getAbsoluteURL()
    {
        return $this->getURL();
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
        if (!$data)
            return null;

        if ($this->Title)
            $title = Convert::raw2att($this->Title);
        elseif (preg_match("/([^\/]*)\.[a-zA-Z0-9]{1,6}$/", $this->Filename, $matches))
            $title = Convert::raw2att($matches[1]);
        else
            $title = $this->Filename;

        return "<img src=\"$data\" alt=\"$title\" />";
    }

    public function deleteFormattedImages()
    {
        return 0;
    }

    public function updateFilesystem()
    {
        // Since there's no longer a filesystem we should no longer do this dance
    }

    /**
     * Get the dimensions of this Image.
     * @param string $dim If this is equal to "string", return the dimensions in string form,
     * if it is 0 return the height, if it is 1 return the width.
     * @return string|int
     */
    function getDimensions($dim = "string")
    {
        $gd = new MemoryGD($this->ImageData, $this->determineFormat());
        if (!$gd->hasGD())
            return ($dim === "string")
                    ? "MemoryImage not initialised"
                    : null;

        if($dim === "string")
            return sprintf("%sx%s", $gd->getWidth(), $gd->getHeight());
        
        if($dim == 0)
            return $gd->getWidth();
        
        if($dim == 1)
            return $gd->getHeight();
    }

}