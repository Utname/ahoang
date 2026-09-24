<?php


class WrapFilter
{
    /**
     * @param string $words
     * @return string
     * @throws Exception
     */
    public function process(string $words): string
    {
        if(strlen($words) > 0) {
            $hl = preg_split("/\s+/", $words);
            $hl[0] = "<span>" . $hl[0] . "</span>";
            $words = join(" ", $hl);
        }
        return $words;
    }
}