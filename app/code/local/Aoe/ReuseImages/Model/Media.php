<?php
/**
 * Catalog product media gallery attribute backend model
 *
 * @author Fabrizio Branca <fabrizio.branca@aoemedia.de>
 */
class Aoe_ReuseImages_Model_Media extends Mage_Catalog_Model_Product_Attribute_Backend_Media
{    

	public function beforeSave($object)
    {
		$return = parent::beforeSave($object);
    		
        // clean up files that are possibly left, because they're not moved (added by Fabrizio Branca)
        $ioObject = new Varien_Io_File();
        foreach (array_keys($this->_renamedImages) as $imagePath) {
        	$ioObject->rm($this->_getConfig()->getTmpMediaPath($imagePath));
        }

        return $return;
    }

    /**
     * Add image to media gallery and return new filename
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string                     $file              file path of image in file system
     * @param string|array               $mediaAttribute    code of attribute with type 'media_image',
     *                                                      leave blank if image should be only in gallery
     * @param boolean                    $move              if true, it will move source file
     * @param boolean                    $exclude           mark image as disabled in product page view
     * @return string
     */
    public function addImage(Mage_Catalog_Model_Product $product, $file, $mediaAttribute=null, $move=false, $exclude=true)
    {
        $file = realpath($file);

        if (!$file || !file_exists($file)) {
            Mage::throwException(Mage::helper('catalog')->__('Image does not exist.'));
        }
        $pathinfo = pathinfo($file);
        if (!isset($pathinfo['extension']) || !in_array(strtolower($pathinfo['extension']), array('jpg','jpeg','gif','png'))) {
            Mage::throwException(Mage::helper('catalog')->__('Invalid image file type.'));
        }

        $fileName       = Varien_File_Uploader::getCorrectFileName($pathinfo['basename']);
        $dispretionPath = Varien_File_Uploader::getDispretionPath($fileName);
        $fileName       = $dispretionPath . DS . $fileName;
        
        // Added by Fabrizio Branca to enable reusing images, if they have the same md5 checksum
        $shouldTargetPath = $this->_getConfig()->getMediaPath($fileName);  
        if (!is_file($shouldTargetPath) || (md5_file($file) != md5_file($shouldTargetPath))) {
	        $fileName = $this->_getNotDuplicatedFilename($fileName, $dispretionPath);
        }
        
        $ioAdapter = new Varien_Io_File();
        $ioAdapter->setAllowCreateFolders(true);
        $distanationDirectory = dirname($this->_getConfig()->getTmpMediaPath($fileName));

        try {
            $ioAdapter->open(array(
                'path'=>$distanationDirectory
            ));

            if ($move) {
                $ioAdapter->mv($file, $this->_getConfig()->getTmpMediaPath($fileName));
            } else {
                $ioAdapter->cp($file, $this->_getConfig()->getTmpMediaPath($fileName));
                $ioAdapter->chmod($this->_getConfig()->getTmpMediaPath($fileName), 0777);
            }
        }
        catch (Exception $e) {
            Mage::throwException(Mage::helper('catalog')->__('Failed to move file: %s', $e->getMessage()));
        }

        $fileName = str_replace(DS, '/', $fileName);

        $attrCode = $this->getAttribute()->getAttributeCode();
        $mediaGalleryData = $product->getData($attrCode);
        $position = 0;
        if (!is_array($mediaGalleryData)) {
            $mediaGalleryData = array(
                'images' => array()
            );
        }

        foreach ($mediaGalleryData['images'] as &$image) {
            if (isset($image['position']) && $image['position'] > $position) {
                $position = $image['position'];
            }
        }

        $position++;
        $mediaGalleryData['images'][] = array(
            'file'     => $fileName,
            'position' => $position,
            'label'    => '',
            'disabled' => (int) $exclude
        );

        $product->setData($attrCode, $mediaGalleryData);

        if (!is_null($mediaAttribute)) {
            $this->setMediaAttribute($product, $mediaAttribute, $fileName);
        }

        return $fileName;
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

    /**
     * Move image from temporary directory to normal
     *
     * @param string $file
     * @return string
     */
    protected function _moveImageFromTmp($file)
    {
        $ioObject = new Varien_Io_File();
        $destDirectory = dirname($this->_getConfig()->getMediaPath($file));
        try {
            $ioObject->open(array('path'=>$destDirectory));
        } catch (Exception $e) {
            $ioObject->mkdir($destDirectory, 0777, true);
            $ioObject->open(array('path'=>$destDirectory));
        }

        if (strrpos($file, '.tmp') == strlen($file)-4) {
            $file = substr($file, 0, strlen($file)-4);
        }
        
        // Added by Fabrizio Branca to enable reusing images, if they have the same md5 checksum
        $shouldTargetPath = $this->_getConfig()->getMediaPath($file);
        if (is_file($shouldTargetPath) && (md5_file($this->_getConfig()->getTmpMediaPath($file)) == md5_file($shouldTargetPath))) {
        	return str_replace($ioObject->dirsep(), '/', $file);
        }
        
       	$destFile = dirname($file) . $ioObject->dirsep()
                  . Varien_File_Uploader::getNewFileName($this->_getConfig()->getMediaPath($file));

        $ioObject->mv(
            $this->_getConfig()->getTmpMediaPath($file),
            $this->_getConfig()->getMediaPath($destFile)
        );

        return str_replace($ioObject->dirsep(), '/', $destFile);

    }

} 
