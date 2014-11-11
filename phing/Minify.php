<?php

require_once 'phing/filters/BaseParamFilterReader.php';
require_once 'phing/filters/ChainableReader.php';

class Minify extends BaseFilterReader implements ChainableReader {
    private $buffer     = '';
    private $lastWasWS  = false;

    function read($len = null)
    {
        if ($this->buffer === null) {
            return -1;
        }

        $buffer = $this->in->read($len);
        if ($buffer === -1) {
            $res = $this->minifySource($this->buffer);
            $this->buffer = null;
            return $res;
        } else {
            $this->buffer .= $buffer;
        }
        return '';
    }

    protected function minifySource($source)
    {
        $res = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], array(T_COMMENT, T_WHITESPACE))) {
                    if (stripos($token[1], 'copyright') !== false &&
                        stripos($token[1], 'license') !== false &&
                        stripos($token[1], 'licence') !== false) {
                        $res .= $token[1];
                    } elseif (!$this->lastWasWS) {
                        $res .= ' ';
                    }
                    $this->lastWasWS = true;
                } else {
                    $res .= $token[1];
                    $this->lastWasWS = $token[0] === T_DOC_COMMENT;
                }
            } elseif (strpos("\r\n", $token) === false || !$this->lastWasWS) {
                $res .= $token;
                $this->lastWasWS = false;
            }
        }
        return $res;
    }

    function chain(Reader $reader) {
        $newFilter = new self($reader);
        $newFilter->setProject($this->getProject());
        return $newFilter;
    }
}
