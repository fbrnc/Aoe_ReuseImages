<?php

 /**
 * @category   Aoe
 * @package    Aoe_ReuseImages
 * @author     Fabrizio Branca <fabrizio.branca@aoemedia.de>
 * @author     Chris Jones <chris@studiobanks.com>
 */
class Aoe_ReuseImages_Model_Media extends Mage_Catalog_Model_Product_Attribute_Backend_Media
{    
    public function beforeSave($object)
    {
        parent::beforeSave($object);

        // Clean up files that are possibly left, because they're not moved (added by Fabrizio Branca)
        $ioObject = new Varien_Io_File();
        foreach (array_keys($this->_renamedImages) as $imagePath) {
            $ioObject->rm($this->_getConfig()->getTmpMediaPath($imagePath));
        }

        return $this;
    }

    /**
     * @param String $fileName
     * @param String $dispretionPath
     * @return String
     */
    protected function _getNotDuplicatedFilename($fileName, $dispretionPath)
    {
        // Added by Fabrizio Branca to enable reusing images, if they have the same md5 checksum
        $shouldTargetPath = $this->_getConfig()->getMediaPath($fileName);
        if (!is_file($shouldTargetPath) || (md5_file($file) != md5_file($shouldTargetPath))) {
            return parent::_getNotDuplicatedFilename($fileName, $dispretionPath);
        }

        return $fileName;
    }

    /**
     * @param string $file
     * @return string
     */
    protected function _moveImageFromTmp($file)
    {
        $ioObject = new Varien_Io_File();

        if (strrpos($file, '.tmp') == strlen($file)-4) {
            $file = substr($file, 0, strlen($file)-4);
        }

        // Added by Fabrizio Branca to enable reusing images, if they have the same md5 checksum
        $shouldTargetPath = $this->_getConfig()->getMediaPath($file);
        if (is_file($shouldTargetPath) && (md5_file($this->_getConfig()->getTmpMediaPath($file)) == md5_file($shouldTargetPath))) {
            return str_replace($ioObject->dirsep(), '/', $file);
        }

        return parent::_moveImageFromTmp($file);
    }

    /**
     * Copy image and return new filename.
     *
     * @param string $file
     * @return string
     */
    protected function _copyImage($file)
    {
    	return $file;
    }
} 
