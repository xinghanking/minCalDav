<?php

namespace Caldav\Utils;

class Calendar
{
    public static function arrToIscText(array $arr) {
        $comp = [];
        if (isset($arr['RECURRENCE-ID']) && $arr['RECURRENCE-ID'] == '') {
            unset($arr['RECURRENCE-ID']);
        }
        foreach ($arr as $k => $value) {
            if (is_array($value)) {
                if(isset($value['p'])) {
                    foreach ($value['p'] as $p => $v) {
                        $value['p'][$p] = $p . '=' . $v;
                    }
                    $comp[] = $k . ';' . implode(';', $value['p']) . ':' . $value['v'];
                } elseif (isset($value[0])) {
                    if (is_array($value[0])) {
                        if(isset($value[0]['p'])) {
                            foreach($value as $item) {
                                foreach ($item['p'] as $p => $v) {
                                    $item['p'][$p] = $p . '=' . $v;
                                }
                                $comp[] = $k . ';' . implode(';', $item['p']) . ':' . $item['v'];
                            }
                        } else {
                            foreach ($value as $v) {
                                $comp[] = 'BEGIN:'."$k\n".self::arrToIscText($v) . "\nEND:"."$k";
                            }
                        }
                    } else {
                        foreach ($value as $v) {
                            $comp[] = $k.':'.$v;
                        }
                    }
                }
            } else {
                $comp[] = $k.':'.$value;
            }
        }
        return implode("\n", $comp);
    }
    public static function icsToArr(string $ics)
    {
        $info            = [];
        $stack           = [];
        $currentCompName = '';
        $currentNode     = &$info;
        if(!str_starts_with($ics, 'BEGIN:VCALENDAR') || substr($ics, -13) != 'END:VCALENDAR') {
            return null;
        }
        $ics = trim(substr($ics,15, -13));
        $ics = preg_split("/\r?\n/", $ics);
        if (empty($ics)){
            return null;
        }
        $currentKey = '';
        $currentValue = '';
        foreach ($ics as $c) {
            if(empty($c)) {
                continue;
            }
            if (in_array(substr($c, 0, 1),[' ', "\t"], true)) {
               if(!empty($currentKey) && is_string($currentValue)) {
                   $c = substr($c, 1);
                   $currentValue .= $c == '' ? "\n" : $c;
                   continue;
               }
            }
            if(!empty($currentKey)) {
                $key = explode(';', $currentKey);
                if (count($key) > 1) {
                   $currentKey = array_shift($key);
                   $currentValue = ['p' => [], 'v' => $currentValue];
                   foreach ($key as $p) {
                       [$k, $v] = explode('=', $p);
                       $currentValue['p'][$k] = $v;
                   }
                }
                if (empty($currentCompName)) {
                    $info['VCALENDAR'][$currentKey] = $currentValue;
                } else {
                    if (isset($currentNode[$currentKey])) {
                        if (is_array($currentNode[$currentKey]) && !isset($currentNode[$currentKey]['v'])) {
                            $currentNode[$currentKey][] = $currentValue;
                        } else {
                            $currentNode[$currentKey] = [$currentNode[$currentKey], $currentValue];
                        }
                    } else {
                        $currentNode[$currentKey] = $currentValue;
                    }
                }
                $currentKey = '';
                $currentValue = null;
            }
            if (empty(!$c) && str_contains($c, ':')) {
                [$key, $value] = explode(':', $c, 2);
                if ($key == 'BEGIN') {
                    $stack[]            = [$currentCompName, &$currentNode];
                    $currentCompName    = $value;
                    if(isset($currentNode[$value])) {
                        $currentNode[$value][] = [];
                    } else {
                        $currentNode[$value] = [[]];
                    }
                    $currentNode        = &$currentNode[$value][count($currentNode[$value]) - 1];
                } elseif ($key == 'END') {
                    if ($currentCompName != $value) {
                        return null;
                    }
                    $a               = array_pop($stack);
                    $currentNode     = &$a[1];
                    $currentCompName = $a[0];
                } else {
                    $currentKey = $key;
                    $currentValue = $value;
                }
            }
        }
        return $info;
    }
}