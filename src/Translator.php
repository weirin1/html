<?php

namespace Weirin\Html;

use DOMElement;
use DOMDocument;
use InvalidArgumentException;

/**
 * 小程序rich-text组件的nodes格式
 * Class Translator
 * @package Html
 */
class Translator
{
    /**
     * @var string
     */
    public $charset = 'UTF-8';
    /**
     * @var array
     */
    public $defaultAttrs = [
        'img' => [
            'width' => '100%'
        ]
    ];
    /**
     * @var DOMElement
     */
    protected $_root;
    /**
     * @var []
     */
    protected $_array = null;
    /**
     * @var bool
     */
    protected $_removeEmptyStrings = true;
    /**
     * @var bool
     */
    protected $_parseCss = true;

    /**
     * Translator constructor.
     * @param $html
     */
    public function __construct($html) {

        if (false === mb_strpos(mb_strtolower(mb_substr($html, 0, 80)), '<!doctype') ) {
            $html = "<!DOCTYPE html><html><head><meta charset='{$this->charset}' /></head><body>$html</body></html>";
        }

        $dom = new DOMDocument('1.0', $this->charset);

        @libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        @libxml_clear_errors();


        $body = $dom->getElementsByTagName('body');
        if (0 == $body->length) {
            throw new InvalidArgumentException('Invalid HTML.');
        }

        $this->_root = $body[0];
    }

    /**
     * @return array|mixed|null
     */
    public function toArray() {

        if (null === $this->_array) {

            $array = $this->_domElementToArray($this->_root);

            if(empty($array) || ! isset($array['children'])) {
                $this->_array = [];
            }
            else {
                $this->_array = $array['children'];
            }
        }

        return $this->_array;
    }

    /**
     * @return string
     */
    public function toJson() {
        return json_encode($this->toArray());
    }

    /**
     * @param bool $value
     */
    public function parseCss($value = true) {
        $this->_parseCss = boolval($value);
    }

    /**
     * @param bool $value
     */
    public function removeEmptyStrings($value = true) {
        $this->_removeEmptyStrings = boolval($value);
    }

    /**
     * @param $css
     * @return array
     */
    protected function _parseInlineCss($css) {

        $urls = [];

        // fix issue with ";" symbol in url()
        $css = preg_replace_callback('/url(\s+)?\(.*\)/i', function ($match) use (&$urls) {
            $index = count($urls) + 1;
            $index = "%%$index%%";
            $urls[$index] = $match[0];
            return $index;
        }, $css);


        $arr = array_filter(array_map('trim', explode(';', $css)));
        $result = [];

        foreach ($arr as $item) {

            list ($attribute, $value) = array_map('trim', explode(':', $item));

            // restore original url()
            if (preg_match('/%%\d+%%/', $value)) {
                $value = preg_replace_callback('/%%\d+%%/', function ($match) use ($urls) {

                    if (isset($urls[$match[0]])) {
                        return $urls[$match[0]];
                    }
                    else {
                        return $match[0];
                    }
                }, $value);
            }

            $result[$attribute] = $value;
        }

        return $result;
    }

    /**
     * @param DOMElement $element
     * @return array
     */
    protected function _domElementToArray(DOMElement $element) {
        $node = mb_strtolower($element->tagName);

        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attr = mb_strtolower($attribute->name);
            $value = $attribute->value;

            /**
             * 小程序不支持style的数组化
             */
//            if ('style' == $attr && $this->_parseCss) {
//                $value = $this->_parseInlineCss($value);
//            }

            $attributes[$attr] = $value;
        }

        $children = [];
        if ($element->hasChildNodes()) {
            foreach ($element->childNodes as $childNode) {

                if (XML_ELEMENT_NODE === $childNode->nodeType) {

                    $children[] = $this->_domElementToArray($childNode);
                }
                elseif (XML_TEXT_NODE === $childNode->nodeType ) {

                    $text = $childNode->nodeValue;

                    if (!$this->_removeEmptyStrings || "" != trim($text)) {
                        $children[] = [
                            'type' => 'text',
                            'text' => $text
                        ];
                    }
                }
            }
        }

        // node => name
        $result = [
            'name' => $node,
        ];

        foreach ($this->defaultAttrs as $name => $attrs){
            switch ($name){
                case 'img':
                    $attributes = array_merge($attributes, $attrs);
                    break;
            }
        }

        if (count($attributes) > 0) {
            //attributes  => attrs
            $result['attrs'] = $attributes;
        }

        if (count($children) > 0) {
            $result['children'] = $children;
        }

        return $result;
    }

}