<?php


namespace Modules\RatingSystemPro\Traits;


trait OperationTrait
{
    /**
     * @param $file
     * @param $pattern
     * @param $replace
     * @return bool
     */
    private static function replaceContent($file, $pattern, $replace)
    {
        $content = file_get_contents($file);
        $return  = preg_replace($pattern, $replace, $content);
        file_put_contents($file, $return);

        return true;
    }

    /**
     * @param $file
     * @param $text
     * @param $change
     * @param bool $append
     */
    private static function replaceContentByText($file, $text, $change, $append = false)
    {
        if($append) {
            $newText = $text;
        }else{
            $newText = $text ."\n".$change;
        }

        $str = file_get_contents($file);
        $str = str_replace($change, $newText, $str);
        file_put_contents($file, $str);
    }

    /**
     * @param $replacement
     * @param $line
     * @param $file
     */
    private static function replaceContentByLine($replacement, $line, $file)
    {
        $specific_line = $line; // sample value squeeze it on this line
        $contents = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if($specific_line > sizeof($contents)) {
            $specific_line = sizeof($contents) + 1;
        }

        array_splice($contents, $specific_line-1, 0, array($replacement)); // arrays start at zero index
        $contents = implode("\n", $contents);
        file_put_contents($file, $contents);
        return true;
    }
}
