<?php
/*************************************/
/* image and image_resource classes  */
/*************************************/

/**
 * Image class for handling screenshot and thumbnail image files.
 */
class Image {
    var $sFile; // absolute path from the docroot
    var $aDebugLog;
    var $oImage;
    var $iWidth;
    var $iHeight;
    var $iType;
    
    /**
     * Constructor:
     * $sFile is the full path to the image. $this->isLoaded()
     * should really be checked after making a new object.
     */
    function Image($sPath, $bAbsolutePath=false)
    {
        /* if $bAbsolutePath is true we should use the $sPath without modification */
        /* otherwise use appdb_fullpath() to convert the relative $sPath into a absolute path */
        if($bAbsolutePath)
            $this->sFile = $sPath;
        else /* relative path */
            $this->sFile = appdb_fullpath($sPath);

        $oInfo = @getimagesize($this->sFile);       
        
        if( empty($oInfo) )
        {
            $this->set_debuglog("Failed to load file ".$this->sFile);
            return;
        }
        
        switch( $oInfo[2] )
        {
            case 2:
                $oImage = imagecreatefromjpeg($this->sFile);
            break;
        
            case 3:
                $oImage = imagecreatefrompng($this->sFile);
            break;
        
            default;
                $this->set_debuglog("Image type ({$oInfo[2]}) unknown");
                return;
            return;
        }
        
        $this->oImage = $oImage;
        $this->iWidth = $oInfo[0];
        $this->iHeight = $oInfo[1];
        $this->iType = $oInfo[2];
        
        $this->set_debuglog("New image class created with as $this->sFile as"
                          ." file and {$oInfo[2]} as type. Dimensions"
                          ." {$oInfo[0]}x{$oInfo[1]}");
    }
    
    /**
     * isLoaded()
     * This function should always be checked after loading a file
     * with the constructor. Returns true if the image has been
     * successfully loaded.
     */ 
    function isLoaded()
    {
        if($this->iWidth > 0 AND $this->iHeight > 0)
            return true;
        else
            return false;
    }
    
    /**
     * Returns the latest debug log made for the last function. If $bFull is
     * set it will return the full log as array of the object.
     */
    function get_debuglog($bFull = 0)
    {
        if($bFull)
            return $this->aDebugLog;
        else
            return end($this->aDebugLog);
    }
    
    function get_width()
    {
        return $this->iWidth;
    }
    
    function get_height()
    {
        return $this->iHeight;
    }
    
    /**
     * Returns the image resource identifier.
     */
    function get_image_resource()
    {
        return $this->oImage;
    }
    
    /**
     * make_thumb()
     *
     * Calculates resize based on one parameter and calculates the other
     * with the right aspect ratio. If you want to use $iNewHeight set
     * $iNewWidth to 0.
     *
     * If none are set APPDB_THUMBNAIL_WIDTH is used. If both are set
     * $iNewWidth is used.
     *
     * If you want to make a border, look at resize_image_border() comment
     * and set $iBorderWidth and $sBorderColor as appropriate.
     *
     */
    function make_thumb($iNewWidth, $iNewHeight, $iBorderWidth = 0, $sBorderColor = '')
    {
        
        if($iNewWidth == 0 AND $iNewHeight == 0)
        {
            $iNewWidth = APPDB_THUMBNAIL_WIDTH;
            $iNewHeight = $this->calculate_proportions($this->iWidth, $this->iHeight,$iNewWidth);
        }
        else if($iNewWidth > 0)
        {
            $iNewHeight = $this->calculate_proportions($this->iWidth, $this->iHeight,$iNewWidth);
        }
        else if($iNewHeight > 0)
        {
            $iNewWidth = $this->calculate_proportions($this->iWidth, $this->iHeight, 0, $iNewHeight);
        }
                    
        $this->set_debuglog("Resizing image to $iNewWidth x $iNewHeight");
        
        if(!empty($sBorderColor) and $iBorderWidth > 0)
            $this->resize_image_border($sBorderColor,$iBorderWidth,$iNewHeight,$iNewWidth);
        else
            $this->resize_image($iNewWidth,$iNewHeight);
    }
    
    /**
     * make_full() 
     *
     * Function will make sure your image is as big or smaller than the sizes
     * set here with $max_width and $max_height. Aspect ratio will be mantained.
     *
     * If none are set APPDB_SCREENSHOT_MAXWIDTH and APPDB_SCREENSHOT_MAXHEIGHT
     * are used.
     */    
    function make_full($iMaxWidth = 0, $iMaxHeight = 0)
    {
        if(!$iMaxWidth > 0)
            $iMaxWidth = APPDB_SCREENSHOT_MAXWIDTH;
        
        if(!$iMaxHeight > 0)
            $iMaxHeight = APPDB_SCREENSHOT_MAXHEIGHT;
            
        if($this->iWidth > $iMaxWidth)
        {
            /* The width is too much */
            $iNewWidth = $iMaxWidth;
            $iNewHeight = $this->calculate_proportions($this->iWidth,$this->iHeight,$iNewWidth);
            
            /* Check if the height is also within the limits */
            if($iNewHeight > $iMaxHeight )
            {
                $iNewWidth = $this->calculate_proportions($iNewWidth,$iNewHeight,0,$iMaxHeight);
                $iNewHeight = $iMaxHeight;
            }
        }
        else if($this->iHeight > $iMaxHeight)
        {
            /* Width was ok, height not */
            $iNewWidth = $this->calculate_proportions($this->iWidth,$this->iHeight,0,$iMaxHeight);
            $iNewHeight = $iMaxHeight;
        }
        else
        {
            /* All ok */
            $iNewWidth = $this->iWidth;
            $iNewHeight = $this->iHeight;
        }
        
        $this->set_debuglog("Resizing image to $iNewWidth x $iNewHeight");
        
        $this->resize_image($iNewWidth, $iNewHeight);
    }
    
    /**
     * resize_image()
     *
     * Resizes the image with the width and height specified with
     * $iNewHeight and $iNewWidth.
     */
    function resize_image($iNewWidth, $iNewHeight)
    {
        // GD 2.x
        if(function_exists("imagecreatetruecolor"))
        {
            $oNewImage = imagecreatetruecolor($iNewWidth, $iNewHeight);
            imagecopyresampled($oNewImage,$this->oImage,0,0,0,0,
                               $iNewWidth,$iNewHeight,$this->iWidth,$this->iHeight);
        } else // GD 1.x
        {
            $oNewImage = imagecreate($iNewWidth, $iNewHeight);
            imagecopyresized($oNewImage,$this->oImage,0,0,0,0,
                             $iNewWidth,$iNewHeight,$this->iWidth,$this->iHeight);
        }
        
        $this->set_debuglog("imagecopyresized($oNewImage,$this->oImage,0,0,0,0,$iNewWidth,"
                            ."$iNewHeight,$this->iWidth,$this->iHeight);");
        imagedestroy($this->oImage);
        $this->oImage = $oNewImage;
        $this->iWidth = $iNewWidth;
        $this->iHeight= $iNewHeight;
    }
    
    /**
     * resize_image_border()
     *
     * Resizes the image. With the $iNewWidth + $iBorderWidth*2
     * and $iNewHeight + $iBorderWidth*2 as size. $sBorderColor is a
     * HTML hexadecimal color (like #0000FF)
     */
    function resize_image_border($sBorderColor, $iBorderWidth, $iNewHeight, $iNewWidth)
    {
        $r = hexdec(substr($sBorderColor, 1, 2));
        $g = hexdec(substr($sBorderColor, 3, 2));
        $b = hexdec(substr($sBorderColor, 5, 2));
        
        /* We multiply the border width by two because there are borders
         on both sides */
        // GD 2.x
        if(function_exists("imagecreatetruecolor"))
        {
            $new = imagecreatetruecolor($iNewWidth + ($iBorderWidth*2), $iNewHeight + ($iBorderWidth*2));
        } else // GD 1.x
        {
            $new = imagecreate($iNewWidth + ($iBorderWidth*2), $iNewHeight + ($iBorderWidth*2));
        }

        /* Make the border by filling it completely,
        later on we will overwrite everything except the border */
        $color = ImageColorAllocate( $new, $r, $g, $b );
        imagefill($new,0,0,$color);
        
        // GD 2.x
        if(function_exists("imagecopyresampled"))
        {
            imagecopyresampled($new,$this->oImage,$iBorderWidth,$iBorderWidth,
                               0,0, $iNewWidth,$iNewHeight,
                               $this->iWidth,$this->iHeight);
        } else // GD 1.x
        {
            imagecopyresized($new,$this->oImage,$iBorderWidth,$iBorderWidth,
                             0,0,$iNewWidth,$iNewHeight,
                             $this->iWidth,$this->iHeight);
        }
        
        $this->set_debuglog("imagecopyresized($new,$this->oImage,$iBorderWidth,$iBorderWidth,0,0,"
                           ." $iNewWidth,$iNewHeight,$this->iWidth,$this->iHeight); with a $iBorderWidth px border"
                           ." in $sBorderColor");
        imagedestroy($this->oImage);
        $this->oImage = $new;
        $this->iWidth = $iNewWidth;
        $this->iHeight= $iNewHeight;
    }
    
    /**
     * add_watermark()
     *
     * $watermark is an image resource identifier to any image resource.
     *
     * $min_mark_wwidth and $min_mark_height are the minimum sizes of the
     * destination image before the watermark is added. If none are set
     * both will be 0.
     *
     * A warning for transparency. If you resize an image down with make_thumb()
     * you loose the transparency on png images.
     */
    function add_watermark($oWatermark, $iMinMarkWidth = 0, $iMinMarkHeight = 0)
    {
        $iWatermarkWidth = imagesx($oWatermark);
        $iWatermarkHeight = imagesy($oWatermark);
          
        if($this->iWidth > $iMinMarkWidth AND
           $this->iHeight > $iMinMarkHeight)
        {
            $iWatermark_x = $this->iWidth - $iWatermarkWidth;
            $iWatermark_y = $this->iHeight - $iWatermarkHeight;
            
            imagecopy($this->oImage, $oWatermark, $iWatermark_x, $iWatermark_y,
                      0, 0, $iWatermarkWidth, $iWatermarkHeight); 
            
            $this->set_debuglog("imagecopy($this->oImage, $oWatermark,"
                                ."$iWatermark_x, $iWatermark_y, 0, 0,"
                                ."$iWatermarkWidth, $iWatermarkHeight);");
        }
    }
    
    /**
     * Save the image to a file set with $store.
     *
     * $iType is optional and is set like the second index of getimagesize().
     * If none (or 0) is set the original type of the file is used.
     * If $store is not give, the current file name will be used.
     * $quality is the jpeg output quality (100 best, 0 worst). Default is 75
     */
    function output_to_file($sOutputFilename=null, $iType = 0, $iQuality = 75)
    {
        if(!$sOutputFilename)
            $sOutputFilename = $this->sFile;
        if($iType == 0)
            $iType = $this->iType;
        
        switch($iType)
        {
            case 2:
            imagejpeg($this->oImage, $sOutputFilename, $iQuality);
            $this->set_debuglog("Saved file as jpeg to $sOutputFilename");
            break;
            
            case 3:
            imagepng($this->oImage, $sOutputFilename);
            $this->set_debuglog("Saved file as png to $sOutputFilename");
            break;
            
            default:
            $this->set_debuglog("Unknown output type");
            return;
        }
        
        return true;
    }
    
    /**
     * Output the files to the browser.
     *
     * If $bHeader is true a Content-type header with the correct type
     * is set.
     * $iType is optional and is set like the second index of getimagesize().
     * If none (or 0) is set the original type of the file is used.
     *
     * $iQuality is the jpeg output quality (100 best, 0 worst)
     */  
    function output_to_browser($bHeader, $iType = 0, $iQuality = 75)
    {
        if($iType == 0 )
            $iType = $this->iType;
        
        switch($iType)
        {
            case 2:
            if($bHeader)
                header('Content-type: image/jpeg');
            imagejpeg($this->oImage,null,$iQuality);
            $this->set_debuglog("Outputed file as jpeg to browser");
            break;
            
            case 3:
            if($bHeader)
                header('Content-type: image/png');
            imagepng($this->oImage);            
            $this->set_debuglog("Outputed file as png to browser");
            break;
            
            default:
            $this->set_debuglog("Unknown output type");
        }
    }
    
    /**
     * Destroys the image resource. Be sure to do this at the end of your
     * actions with this object.
     */
    function destroy()
    {
        if(is_resource($this->oImage))
            imagedestroy($this->oImage);
    }

    /**    
     * Deletes the screenshot from the file system.
     */
    function delete()
    {
        // if the file exists, delete it
        if(is_file($this->sFile))
            unlink($this->sFile);
    }

    
    /***********************
    * PRIVATE FUNCTIONS
    ************************/
    
    function set_debuglog( $sLog )
    {
        $this->aDebugLog[] = $sLog;
    }
    
    function calculate_proportions($iWidth, $iHeight,
                                   $iNewWidth, $iNewHeight = '0')
    {
        if($iNewWidth > 0)
            // we want to calculate the new height
            return ($iHeight * $iNewWidth) / $iWidth;
        else if( $iNewHeight > 0 )
            return ($iWidth * $iNewHeight) / $iHeight;
        else
            return 0;
    }

}


class ImageResource extends Image {
    
    function ImageResource($oImage,$iType)
    {
        
        $this->oImage = $oImage;
        $this->iWidth = imagesx($oImage);
        $this->iHeight = imagesy($oImage);
        $this->iType = $iType;
        
        $this->set_debuglog("New image class created with $oImage as"
                          ." image resource and $iType as type. Dimensions"
                          ." {$this->iWidth}x{$this->iHeight}");
    }
}
?>
