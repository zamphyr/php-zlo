<?php

namespace Zamphyr\Zlo;

/**
 * Naïf PHP implementation of Zamphyr Localization
 *
 * @package Zlo
 * @version 0.0.4
 * @author Марко Кажић <marko.kazic@zamphyr.com>
 * @link http://zlo.zamphyr.com
 * @copyright Zamphyr
 * @license Unlicense
 */
class Zlo
{
    // the configuration
    private $config;    
    // ZLID code for currently loaded translation.
    public $language;
    // Path to the translation folder.
    public $path;

    /**
     * Row count for currently loaded translation file,
     * subtracted for the number of empty lines in PHP implementation.
     */
    private $rowCount;

    /**
     * Keep initialized path of the file to use inside all methods.
     */
    private $filePath;

    /**
     * Keeps loaded file available for all methods.
     */
    private $fileContents;

    function __construct($path)
    {
        $this->config = parse_ini_file("config.ini", true);
        $this->path = $path;
    }

    /**
     * Checks platform and returns proper line ending character.
     */

    private function zl2Br()
    {
        return (stristr(PHP_OS, 'WIN') || stristr(PHP_OS, 'DAR')) ? "\r\n" : "\n";
    }

    /**
     * Plural check function. Takes the natural number and calculates the form
     * which needs to be printed out based on the artificial level number.
     */

    private function zloPlural( &$n )
    {
        $languageCode = substr($this->language, 0, 3);

        // at least, this way, it's easier to see what's going on.
        switch($languageCode) {
            case 'ach':
            case 'aka':
            case 'amh':
            case 'arn':
            case 'bre':
            case 'fil':
            case 'fra':
            case 'gun':
            case 'lin':
            case 'mfe':
            case 'mlg':
            case 'mri':
            case 'oci':
            case 'tgk':
            case 'tir':
            case 'tur':
            case 'uzb':
            case 'wln':
                return ($n > 1) ? 2 : 1;
            case 'aym':
            case 'bod':
            case 'cgg':
            case 'dzo':
            case 'fas':
            case 'ind':
            case 'jpn':
            case 'jbo':
            case 'kat':
            case 'kaz':
            case 'kor':
            case 'kir':
            case 'lao':
            case 'msa':
            case 'mya':
            case 'sah':
            case 'sun':
            case 'tha':
            case 'tat':
            case 'uig':
            case 'vie':
            case 'wol':
            case 'zho':
                return 1;
            case 'srp':
            case 'bos':
            case 'hrv':
            case 'rus':
            case 'ukr':
            case 'bel':
                if ($n === 1 && $n % 100 !== 11 || $n % 10 == 1 && $n > 20)
                    return 1;
                elseif ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20))
                    return 2;
                else
                    return 3; 
            case 'ces':
            case 'slk':
                return ($n == 1) ? 1 : ($n >= 2 && $n <= 4) ? 2 : 3;
            case 'ara':
                if ($n === 0)
                    return 6;
                elseif ($n === 1)
                    return 1;
                elseif ($n === 2)
                    return 2;
                elseif ($n % 100 >= 3 && $n % 100 <= 10)
                    return 3;
                elseif ($n % 100 >= 11)
                    return 4;
                else
                    return 5;
            case 'csb':
                return $n == 1 ? 1 : $n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 2 : 3;
            case 'cym':
                return ($n == 1) ? 1 : ($n==2) ? 2 : ($n != 8 && $n != 11) ? 3 : 4;
            case 'gle':
                return $n == 1 ? 1 : $n==2 ? 2 : $n < 7 ? 3 : $n < 11 ? 4 : 5;
            case 'gla':
                return ($n == 1 || $n == 11) ? 1 : ($n == 2 || $n == 12) ? 2 : ($n > 2 && $n < 20) ? 3 : 4;
            case 'isl':
                return ($n % 10 != 1 || $n % 100 == 11) ? 1: 2;
            case 'cor':
                return ($n == 1) ? 1 : ($n==2) ? 2 : ($n == 3) ? 3 : 4;
            case 'lit':
                return ($n % 10==1 && $n % 100 != 11 ? 1 : $n % 10 >= 2 && ($n % 100 < 10 || $n % 100 >= 20) ? 2 : 3);
            case 'lav':
                return ($n % 10==1 && $n % 100!=11 ? 1 : $n != 0 ? 2 : 3);
            case 'mnk':
                return ($n == 0 ? 1 : $n == 1 ? 2 : 3);
            case 'mlt':
                return ($n == 1 ? 1 : $n == 0 || ($n % 100 > 1 && $n % 100 < 11) ? 2 : ($n % 100 > 10 && $n % 100 < 20 ) ? 3 : 4);
            case 'pol':
                return ($n == 1 ? 1 : $n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 2 : 3);
            case 'ron':
                return ($n == 1 ? 1 : ($n == 0 || ($n % 100 > 0 && $n % 100 < 20)) ? 2 : 3);
            case 'slv':
                return ($n % 100 == 1 ? 1 : $n % 100 == 2 ? 2 : $n % 100 == 3 || $n % 100 == 4 ? 3 : 0);
            default:
                return ($n != 1) ? 2 : 1;
        }
    }

    /**
     * Returns information for a translation file from header.
     * When file is not loaded returns fallback header info.
     */
    public function zloHeader($ZL_HEADER_LANG, $ZL_DM = NULL)
    {
        /**
         * Header fallback. Anti-apocalyptic measure. UTF-8 is enforced.
         * "Enforced"... such a nice word.
         */

        $ZL_HEADER_FALLBACK = array
        (
            'VAR' => 'zlo',
            'VER' => NULL,
            'REV' => NULL,
            'PRV' => NULL,
            'PRE' => NULL,
            'PRU' => NULL,
            'CHR' => 'utf-8',
            'BDO' => NULL,
            'JEZ' => $this->language
            );

        $trans_file = $this->path . $ZL_HEADER_LANG . ((is_null($ZL_DM)) ? '' : '-' . $ZL_DM) . self::config["general"]["extension"];

        if ( !( $trans_file === $this->filePath ) )
        {
            if (file_exists($trans_file) && filesize($trans_file) !== 0)
            {

                $this->fileContents = file($trans_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

                $this->rowCount = count($this->fileContents);

                $this->filePath = $trans_file;

            }
            else
            {
                return $ZL_HEADER_FALLBACK;
            }
        }

        for ($i=0; $i < 16; $i++) {
            $hvalue[$i]= substr($this->fileContents[$i+1], 4);
            $hname[$i] = substr($this->fileContents[$i+1], 0, 3);
        }

        return $ZL_HEADER = array
        (
            $hname[0] => $hvalue[0],
            $hname[1] => $hvalue[1],
            $hname[2] => $hvalue[2],
            $hname[3] => $hvalue[3],
            $hname[4] => $hvalue[4],
            $hname[5] => $hvalue[5],
            $hname[6] => $hvalue[6],
            $hname[7] => $hvalue[7],
            'JEZ' => substr($hvalue[0],0,3)
            );
    }

        /**
     * Lists all available translation files
     * in the initialized folder for libzlo.
     */

        public function zloLangList()
        {
            for ($i=0; $i < count(array_slice(scandir($this->path),2)); $i++)
            {
                if (stripos(array_slice(scandir($this->path),2)[$i], self::config["general"]["extension"]))
                {
                    $ZL_LIST_TRANSLATIONS[$i] = array_slice(scandir($this->path),2)[$i];
                }
            }

            return array_values($ZL_LIST_TRANSLATIONS);
        }

    /**
     * Returns stats for a specific translation. Uses state pattern in the object
     * even though it probably doesn't need it. Loves ice cream and long walks on the beach.
     */


    public function zloStat( $language, $ZL_DM = NULL )
    {

        $stat_oznaka = $stat_sourcea = $stat_prevoda = 0;

        $trans_file = $this->path . $language . ((is_null($ZL_DM)) ? '' : '-' . $ZL_DM) . self::config["general"]["extension"];

        if ( !( $trans_file === $this->filePath ) )
        {
            if (file_exists($trans_file) && filesize($trans_file) !== 0)
            {

                $this->fileContents = file($trans_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

                $this->rowCount = count($this->fileContents);

                $this->filePath = $trans_file;

            }
        }

        for ($i = 0; $i < $this->rowCount; $i++)
        {
            if ($this->fileContents[$i][0] === '!' && $this->fileContents[$i][1] === 'i')
                $stat_sourcea++;

            elseif ($this->fileContents[$i][0] === '!' && $this->fileContents[$i][1] === 'm' && isset($this->fileContents[$i][3]))
                $stat_prevoda++;

            elseif ($this->fileContents[$i][0] === '#' && $this->fileContents[$i][1] == ',' && strpos($this->fileContents[$i], 'f'))
                $stat_oznaka++;

        }

        /**
         * Calculates % of translated strings
         */

        $proc_prev = ($stat_prevoda !== 0) ? round(1 / ($stat_sourcea / $stat_prevoda) * 100, 2) : 0;

        /**
         * Calculates % of fuzzy strings
         */

        $proc_sumnjivih = ($stat_oznaka !== 0) ? round(1 / ($stat_sourcea / $stat_oznaka) * 100, 2) : 0;

        return array(
            'ZL_STAT_IZV' => $stat_sourcea,
            'ZL_STAT_PRV' => $stat_prevoda,
            'ZL_STAT_OZN' => $stat_oznaka,
            'ZL_STAT_PCP' => $proc_prev,
            'ZL_STAT_PCS' => $proc_sumnjivih,
            'ZL_STAT_SIZE' => filesize($trans_file));
    }

    /**
     * Evil in the flesh
     */

    public function zlo( $source, $ZL_DM = NULL, $n = 'i' )
    {

        /**
         * Faux function overloading or something like that.
         * If second parameter is integer, sets domain as empty.
         */

        if( is_int( $ZL_DM ) ){
            $n = $ZL_DM;
            $ZL_DM = '';
        }

        /**
         * Open file
         */

        $trans_file = $this->path . $this->language . ((is_null($ZL_DM)) ? '' : '-' . $ZL_DM) . self::config["general"]["extension"];

        if (!($trans_file === $this->filePath)) {
            if (file_exists($trans_file) && filesize($trans_file) !== 0)
            {
                $this->fileContents = file($trans_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
                $this->rowCount = count($this->fileContents);

                $this->filePath = $trans_file;
            }
        }

        $nivo = $this->zloPlural( $n );

        /**
         * Evil deeds
         */

        // If singular or first level
        if ( ( $n === 'i' || $n === 1 ) || ( $nivo === 1 && $n > 1 ) ){
            // Start after the header
            for ($i=16; $i < $this->rowCount; $i++) {
                // Check if the current line is the source for translation
                if($this->fileContents[$i] === '!i ' . $source ) {
                    // Check that translation is there
                    if ($this->fileContents[$i+1] !== '!m ' && $this->fileContents[$i+1][0] === '!') {
                        // Check if there is a new line character
                        if (strpos($this->fileContents[$i+1], "\\n")) {
                            // Return the translation with valid new line character
                            return str_replace("\\n", nl2br($this->zl2Br()), htmlspecialchars(substr($this->fileContents[$i+1],3)));

                        }
                        else
                        {
                            // Return the translation
                            return htmlspecialchars(substr($this->fileContents[$i+1],3));
                        }

                    }
                    else
                    {
                        // Translation is not found, returning the source
                        return $source;
                    }
                }

            }

            /**
             * No strings attached! If translation is not found return the source value.
             */

            return $source;

        }
        // Houston, we have a non-singular form
        elseif ($n !== 'i' || $n === 0) {
            // Start after the header
            for ($i=16; $i < $this->rowCount; $i++) {
                // FInd the source
                if($this->fileContents[$i] === '!i ' . $source) {
                    /**
                     * Checks to see if translation for the required level exists
                     */
                    if ($this->fileContents[$i+$nivo+1] !== "!$nivo " && $this->fileContents[$i+$nivo+1][0] === '!') {
                        // Is there a new lie character in the translation?
                        if (strpos($this->fileContents[$i+$nivo+1], "\\n")){
                            // Return the translation with valid new line character
                            return str_replace("\\n", nl2br($this->zl2Br()), htmlspecialchars(substr($this->fileContents[$i+$nivo+1], 3)));

                        }
                        else
                            // Just return the translation
                            return htmlspecialchars(substr($this->fileContents[$i+$nivo+1], 3));
                    }
                    else
                        // No translation found, falling back
                        return $source;
                }

            }

            /**
             * No strings attached! If translation is not found return the source value
             */

            return $source;
        }

    }
}

?>
