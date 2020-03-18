<?php

class Localizer
{
    private $locales;
    private $defaultLang;

    function __construct($path, $lang) {
        $languages = json_decode(file_get_contents($path . "/languages.json"));
        foreach ($languages as $curLang) {
            $this->locales[$curLang] = json_decode(file_get_contents($path . "/$curLang.json"), true);
        }
        $this->defaultLang = $lang;
    }

    function get($str) {
        return $this->locales[$this->defaultLang][$str]['text'] ?? $this->locales['ru']['str']['text'];
    }

    public function setDefaultLang($defaultLang): void {
        $this->defaultLang = $defaultLang;
    }

}
