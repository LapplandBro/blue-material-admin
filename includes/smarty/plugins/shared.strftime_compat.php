<?php
/**
 * Shared Smarty helper: strftime-compatible formatting without strftime().
 *
 * @param string $format strftime-style format
 * @param int $timestamp
 * @return string
 */
function smarty_strftime_compat($format, $timestamp)
{
    if (DIRECTORY_SEPARATOR == '\\') {
        $_win_from = array('%D',       '%h', '%n', '%r',          '%R',    '%t', '%T');
        $_win_to   = array('%m/%d/%y', '%b', "\n", '%I:%M:%S %p', '%H:%M', "\t", '%H:%M:%S');
        if (strpos($format, '%e') !== false) {
            $_win_from[] = '%e';
            $_win_to[]   = sprintf('%\' 2d', date('j', $timestamp));
        }
        if (strpos($format, '%l') !== false) {
            $_win_from[] = '%l';
            $_win_to[]   = sprintf('%\' 2d', date('h', $timestamp));
        }
        $format = str_replace($_win_from, $_win_to, $format);
    }

    $map = array(
        '%a' => 'D',
        '%A' => 'l',
        '%d' => 'd',
        '%e' => 'j',
        '%j' => 'z',
        '%u' => 'N',
        '%w' => 'w',
        '%U' => 'W',
        '%V' => 'W',
        '%W' => 'W',
        '%b' => 'M',
        '%B' => 'F',
        '%h' => 'M',
        '%m' => 'm',
        '%C' => '',
        '%g' => 'y',
        '%G' => 'Y',
        '%y' => 'y',
        '%Y' => 'Y',
        '%H' => 'H',
        '%k' => 'G',
        '%I' => 'h',
        '%l' => 'g',
        '%M' => 'i',
        '%p' => 'A',
        '%P' => 'a',
        '%r' => 'h:i:s A',
        '%R' => 'H:i',
        '%S' => 's',
        '%T' => 'H:i:s',
        '%X' => 'H:i:s',
        '%z' => 'O',
        '%Z' => 'T',
        '%c' => 'D M j H:i:s Y',
        '%D' => 'm/d/y',
        '%F' => 'Y-m-d',
        '%s' => 'U',
        '%n' => "\n",
        '%t' => "\t",
        '%%' => '%',
    );

    $out = '';
    $len = strlen($format);
    for ($i = 0; $i < $len; $i++) {
        if ($format[$i] === '%' && $i + 1 < $len) {
            $spec = '%' . $format[$i + 1];
            if (isset($map[$spec])) {
                $mapped = $map[$spec];
                if ($mapped === '' || $mapped === "\n" || $mapped === "\t" || $mapped === '%') {
                    $out .= $mapped;
                } else {
                    $out .= date($mapped, $timestamp);
                }
            } else {
                $out .= $spec;
            }
            $i++;
            continue;
        }
        $out .= $format[$i];
    }
    return $out;
}
