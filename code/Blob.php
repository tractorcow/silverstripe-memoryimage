<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Blob
 *
 * @author Damo
 */
class Blob extends DBField
{
    function requireField()
    {
        DB::requireField($this->tableName, $this->name, "blob");
    }

    public function BASE64()
    {
        return $this->getBase64();
    }
    
    public function getBase64()
    {
        return base64_encode($this->value);
    }
    
    public function setBase64($value)
    {
        $this->value = base64_decode($value);
    }
    
    public function forTemplate()
    {
        return $this->getBase64();
    }
}