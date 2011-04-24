<?php

class TagCommonReply extends Tag
{
    function TagCommonReply($iId = null, $oRow = null, $sTextId = '')
    {
        $this->Constructor($iId, $oRow, $sTextId);
    }

    protected function getTagClass()
    {
        return 'CommonReply';
    }
}

?>