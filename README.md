# Memory Image module for Silverstripe

This module provides a simple way of storing and working with image data stored as BLOBs (Binary Large OBjects)
stored in the database.

The new MemoryImage class should pick up all Image class extensions that you may have defined, and can be used with all
custom and built in image resizing mechanisms the same way as you would use a normal image.

Images will not be deleted when doing a FilesystemSyncTask.

## Credits and Authors

 * Damian Mooyman - <https://github.com/tractorcow/silverstripe-memoryimage>

## Requirements

 * SilverStripe 2.4.5, may work on lower versions
 * PHP 5.2

## Installation Instructions

 * Extract all files into the 'memoryimage' folder under your Silverstripe root.
 * Use the "MemoryImage" class in place of the "Image" class whenever you wish for an image to be stored in
   memory instead of the filesystem.

## API

In addition to the MemoryImage class, this module also adds the Blob class for database storage of BLOB objects,
and a MemoryGD class for providing image data manipulation of image data.

The new MemoryImage includes these functions and properties

### MemoryImage Properties

 * Data - Allows image data string to be get/set
 * DataBase64 - Same as above, but with base64 encoded data
 
### MemoryImage Functions
 * setData($data, $filename) - Allows the data property to be set, but with an explicit 'name' for storage in the database
 * CopyFromImage(Image $image, $filename) - Copies the supplied image into this image
 * CopyFromFile($path, $filename) - Copies the supplied file path into this image
 * CopyFromURL($url, $filename) - Load content from the provided image URL into memory
 * SaveToFile($path) - Writes the image data to the specified filesystem path

